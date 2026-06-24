function syncMostaagerDashboardTabs() {
    document.querySelectorAll('.ms-tab-content').forEach(panel => {
        const isActive = panel.classList.contains('active');
        panel.style.display = isActive ? 'block' : 'none';
    });
}

function initMostaagerDashboardTabs() {
    function normalizeTabName(tabName) {
        if (!tabName) return '';
        const normalized = tabName.trim();
        if (normalized === 'utility-bills') return 'invoices';
        if (normalized === 'collections') return 'financial';
        return normalized;
    }

    function getInitialTabFromUrl() {
        const hash = normalizeTabName(window.location.hash.substring(1));
        if (hash) {
            return hash;
        }
        const params = new URLSearchParams(window.location.search);
        const queryTab = normalizeTabName(params.get('tab'));
        return queryTab ? queryTab.trim() : '';
    }

    function clearTabState(dashboard) {
        dashboard.querySelectorAll('.ms-tab-link').forEach(l => l.classList.remove('active'));
        dashboard.querySelectorAll('.ms-tab-content').forEach(c => c.classList.remove('active'));
        dashboard.querySelectorAll('.ms-sidebar-menu li').forEach(li => li.classList.remove('active'));
    }

    function getTabName(link) {
        if (!link) return '';
        let tab = link.dataset.tab;
        if (!tab) {
            const href = link.getAttribute('href');
            if (href && href.startsWith('#')) {
                tab = href.slice(1).trim();
                if (tab) {
                    link.dataset.tab = tab;
                    link.setAttribute('data-tab', tab);
                }
            }
        }
        return tab ? tab.trim() : '';
    }

    function updateTabDisplay(dashboard) {
        dashboard.querySelectorAll('.ms-tab-content').forEach(panel => {
            const isActive = panel.classList.contains('active');
            panel.style.display = isActive ? 'block' : 'none';
        });
    }

    function activateTabByHash(hash) {
        if (!hash) {
            return false;
        }
        const tabName = normalizeTabName(String(hash || '').replace(/^#/, '').trim());
        if (!tabName) {
            return false;
        }
        let activated = false;
        document.querySelectorAll('.ms-dashboard, .ms-owner-dashboard').forEach(dashboard => {
            const urlLink = dashboard.querySelector('.ms-tab-link[data-tab="' + tabName + '"]') || dashboard.querySelector('.ms-tab-link[href="#' + tabName + '"]');
            if (urlLink) {
                activateTab(dashboard, urlLink);
                activated = true;
            }
        });
        return activated;
    }

    document.addEventListener('click', function (e) {
        const button = e.target.closest && e.target.closest('.property-nav-tabs button.property-tab-button');
        if (button) {
            const url = button.dataset.url;
            if (url) {
                e.preventDefault();
                e.stopImmediatePropagation();
                window.location.assign(url);
            }
            return;
        }
        const link = e.target.closest && e.target.closest('.property-nav-tabs a[href]');
        if (!link) {
            return;
        }
        if (link.getAttribute('href') === '#' || link.getAttribute('href') === '') {
            return;
        }
        if (link.target === '_blank') {
            return;
        }
        e.preventDefault();
        e.stopImmediatePropagation();
        window.location.assign(link.href);
    }, true);

    function activateTab(dashboard, link) {
        if (!link) return;
        dashboard = dashboard || link.closest('.ms-dashboard, .ms-owner-dashboard') || document.querySelector('.ms-dashboard, .ms-owner-dashboard');
        if (!dashboard) return;

        clearTabState(dashboard);

        link.classList.add('active');
        const parentLi = link.closest('li');
        if (parentLi) {
            parentLi.classList.add('active');
        }

        const tab = getTabName(link);
        if (tab) {
            const panel = dashboard.querySelector('#' + tab);
            if (panel) panel.classList.add('active');
        }

        updateTabDisplay(dashboard);
    }

    // Preserve server-defined active tabs; only fall back to first tab if none are marked
    document.querySelectorAll('.ms-dashboard, .ms-owner-dashboard').forEach(dashboard => {
        const links = dashboard.querySelectorAll('.ms-tab-link');
        if (!links || !links.length) return;

        const initialTab = getInitialTabFromUrl();
        if (initialTab) {
            const urlLink = dashboard.querySelector('.ms-tab-link[data-tab="' + initialTab + '"]');
            if (urlLink) {
                activateTab(dashboard, urlLink);
                return;
            }
        }

        const activeLink = dashboard.querySelector('.ms-tab-link.active');
        if (activeLink) {
            activateTab(dashboard, activeLink);
            return;
        }

        const activePanel = dashboard.querySelector('.ms-tab-content.active');
        if (activePanel && activePanel.id) {
            let correspondingLink = dashboard.querySelector('.ms-tab-link[data-tab="' + activePanel.id + '"]');
            if (!correspondingLink) {
                correspondingLink = dashboard.querySelector('.ms-tab-link[href="#' + activePanel.id + '"]');
            }
            if (correspondingLink) {
                activateTab(dashboard, correspondingLink);
                return;
            }
        }

        activateTab(dashboard, links[0]);
    });

    // Use delegated click handling so handlers attach even if DOM changes
    if (!window.MS_TAB_EVENTS_BOUND) {
        window.MS_TAB_EVENTS_BOUND = true;

        document.addEventListener('click', function (e) {
            let target = e.target;
            if (target.nodeType !== 1) {
                target = target.parentElement;
            }
            if (!target) return;

            const link = target.closest('.ms-tab-link');
            if (!link) return;
            if (link.dataset.external === 'true') {
                return;
            }
            if (target.closest('.property-nav-tabs') || target.closest('.houzez-properties-tabs-js')) {
                return;
            }
            e.preventDefault();

            activateTab(null, link);

            const tab = getTabName(link);
            if (tab && typeof window !== 'undefined') {
                try {
                    window.location.hash = tab;
                } catch (e) {
                    /* ignore */
                }
            }
        }, false); // use bubble phase to avoid interfering with other handlers

        window.addEventListener('hashchange', function () {
            activateTabByHash(window.location.hash);
        });
    }
}

// Initialize tab behavior once the DOM is ready
const initMostaagerTabsOnce = function () {
    if (typeof initMostaagerDashboardTabs === 'function') {
        initMostaagerDashboardTabs();
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMostaagerTabsOnce);
} else {
    initMostaagerTabsOnce();
}

// NOTE: All code below remains responsible only for data fetches and delete actions.

/* Utility Bills handlers */
function msShowToast(message, type = 'success') {
    try {
        var wrapper = document.getElementById('ms-toast-wrapper');
        if (!wrapper) {
            wrapper = document.createElement('div');
            wrapper.id = 'ms-toast-wrapper';
            wrapper.style.position = 'fixed';
            wrapper.style.right = '20px';
            wrapper.style.top = '20px';
            wrapper.style.zIndex = 99999;
            document.body.appendChild(wrapper);
        }
        var el = document.createElement('div');
        el.className = 'ms-toast ms-toast--' + (type === 'error' ? 'error' : 'success');
        el.textContent = message;
        wrapper.appendChild(el);
        setTimeout(function(){ el.remove(); }, 3000);
    } catch (e) { console.warn('msShowToast failed', e); }
}

async function msLoadUtilityBills(buildingId) {
    var tbody = document.getElementById('ms-utility-bills-tbody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="5">جارٍ التحميل...</td></tr>';
    try {
        var resp = await fetch('/wp-json/mostager/v1/utility-bills?building_id=' + encodeURIComponent(buildingId));
        var json = await resp.json();
        if (!json.success) throw new Error('Failed');
        var rows = json.data || [];
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="5">لا توجد فواتير</td></tr>';
            return;
        }
        tbody.innerHTML = '';
        rows.forEach(function(r){
            var tr = document.createElement('tr');
            tr.innerHTML = '<td>' + r.id + '</td><td>' + (r.title || '') + '</td><td>ج.م ' + (parseFloat(r.total_amount)||0).toFixed(2) + '</td><td>' + (r.status || '') + '</td><td>' + (r.status !== 'distributed' ? '<button class="ms-distribute-btn" data-id="'+r.id+'">توزيع</button>' : '<button class="ms-view-btn" data-id="'+r.id+'">عرض</button>') + '</td>';
            tbody.appendChild(tr);
        });
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="5">فشل في جلب الفواتير</td></tr>';
    }
}

document.addEventListener('DOMContentLoaded', function(){
    // Utility bills form
    var form = document.getElementById('ms-utility-bill-form');
    if (form) {
        form.addEventListener('submit', async function(e){
            e.preventDefault();
            var fd = new FormData(form);
            var payload = {};
            fd.forEach(function(v,k){ payload[k]=v; });
            try {
                var r = await fetch('/wp-json/mostager/v1/utility-bills', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
                var j = await r.json();
                if (j && j.success) {
                    msShowToast('تم إنشاء الفاتورة');
                    msLoadUtilityBills(payload.building_id);
                    form.reset();
                } else {
                    msShowToast('فشل إنشاء الفاتورة', 'error');
                }
            } catch (err) {
                msShowToast('خطأ في الاتصال', 'error');
            }
        });
    }

    document.body.addEventListener('click', async function(e){
        var btn = e.target.closest && e.target.closest('.ms-distribute-btn');
        if (btn) {
            var id = btn.dataset.id;
            if (!confirm('تأكيد توزيع الفاتورة #' + id + '?')) return;
            try {
                var resp = await fetch('/wp-json/mostager/v1/utility-bills/' + encodeURIComponent(id) + '/distribute', { method: 'POST', credentials: 'same-origin' });
                var j = await resp.json();
                if (j && j.success) {
                    msShowToast('تم توزيع الفاتورة');
                    var buildingId = document.querySelector('#ms-utility-bill-form input[name="building_id"]').value;
                    msLoadUtilityBills(buildingId);
                } else {
                    msShowToast('فشل التوزيع', 'error');
                }
            } catch (err) {
                msShowToast('خطأ في الاتصال', 'error');
            }
        }
        var topupBtn = e.target.closest && e.target.closest('#ms-owner-wallet-topup-btn');
        if (topupBtn) {
            e.preventDefault();
            var checkoutUrl = topupBtn.dataset.checkoutUrl || '';
            if (checkoutUrl) {
                window.location.href = checkoutUrl;
                return;
            }

            var amountInput = document.getElementById('ms-owner-wallet-topup-amount');
            var amount = amountInput ? parseFloat(amountInput.value) : 0;
            if (!amount || isNaN(amount) || amount <= 0) {
                msShowToast('يرجى إدخال مبلغ صالح لشحن المحفظة.', 'error');
                return;
            }

            topupBtn.disabled = true;
            var originalText = topupBtn.textContent;
            topupBtn.textContent = 'جاري إنشاء طلب الشحن...';

            fetch(MostaagerAjax.ajax_url, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                credentials: 'same-origin',
                body: new URLSearchParams({
                    action: 'ms_create_wallet_recharge',
                    security: MostaagerAjax.nonce,
                    amount: amount
                })
            }).then(function (response) {
                return response.json();
            }).then(function (json) {
                if (json && json.success && json.data && json.data.payment_url) {
                    window.location.href = json.data.payment_url;
                    return;
                }

                var message = (json && json.data && json.data.message) ? json.data.message : (json && json.data ? json.data : 'فشل إنشاء طلب شحن المحفظة.');
                msShowToast(message, 'error');
                topupBtn.disabled = false;
                topupBtn.textContent = originalText;
            }).catch(function (err) {
                console.error('Create wallet recharge AJAX error', err);
                msShowToast('حدث خطأ في الاتصال أثناء إنشاء طلب الشحن.', 'error');
                topupBtn.disabled = false;
                topupBtn.textContent = originalText;
            });
            return;
        }

        var viewBtn = e.target.closest && e.target.closest('.ms-view-btn');
        if (viewBtn) {
            var id = viewBtn.dataset.id;
            window.location.hash = 'invoices';
            // fetch details and show (basic)
            try {
                var resp = await fetch('/wp-json/mostager/v1/utility-bills/' + encodeURIComponent(id));
                var j = await resp.json();
                if (j && j.success) {
                    alert(JSON.stringify(j.data, null, 2));
                }
            } catch (e) { msShowToast('خطأ في جلب التفاصيل', 'error'); }
        }
    });

    // Initial load when on invoices tab with utility bills display block
    var activeUtility = document.querySelector('.ms-tab-content#invoices');
    if (activeUtility) {
        var binput = document.querySelector('#ms-utility-bill-form input[name="building_id"]');
        var bId = binput ? binput.value : null;
        if (bId) msLoadUtilityBills(bId);
    }
});

document.addEventListener('DOMContentLoaded', function(){
    // fetch owner dashboard data and update elements if present
    if (typeof MostaagerAjax === 'undefined') return;

    const propsEl = document.getElementById('ms-props-count');
    const invEl = document.getElementById('ms-invoices-count');
    const walletEl = document.getElementById('ms-wallet-balance');

    if (!propsEl && !invEl && !walletEl) return;

    fetch(MostaagerAjax.ajax_url + '?action=ms_get_owner_dashboard', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
    }).then(r => r.json()).then(json => {
        if (json.success && json.data) {
            const d = json.data;
            if (propsEl) propsEl.textContent = d.properties_count;
            if (invEl) invEl.textContent = d.invoices_count;
            if (walletEl) walletEl.textContent = 'ج.م ' + Number(d.wallet_balance).toFixed(2);
            // breakdown
            const paidEl = document.getElementById('ms-invoices-paid');
            const pendingEl = document.getElementById('ms-invoices-pending');
            if (paidEl && typeof d.invoices_paid !== 'undefined') paidEl.textContent = d.invoices_paid;
            if (pendingEl && typeof d.invoices_pending !== 'undefined') pendingEl.textContent = d.invoices_pending;
            // owner revenue & overdue
            const ownerPaidEl = document.getElementById('ms-owner-total-paid');
            const ownerDueEl = document.getElementById('ms-owner-total-due');
            const ownerNextEl = document.getElementById('ms-owner-next-due');
            const ownerOverdueEl = document.getElementById('ms-owner-overdue');
            if (ownerPaidEl && typeof d.owner_total_paid !== 'undefined') ownerPaidEl.textContent = Number(d.owner_total_paid).toFixed(2);
            if (ownerDueEl && typeof d.owner_total_due !== 'undefined') ownerDueEl.textContent = Number(d.owner_total_due).toFixed(2);
            if (ownerNextEl && typeof d.owner_next_due !== 'undefined') ownerNextEl.textContent = d.owner_next_due ? d.owner_next_due : '—';
            if (ownerOverdueEl && typeof d.owner_overdue_count !== 'undefined') ownerOverdueEl.textContent = d.owner_overdue_count;
        }
    }).catch(err => {
        // silent
        console.error('Dashboard AJAX error', err);
    });

});

// Delete property from agent dashboard table
if (typeof MostaagerAjax !== 'undefined') {
    document.addEventListener('click', function (e) {
        const deleteLink = e.target.closest('a.delete-property');
        if (!deleteLink) {
            return;
        }

        e.preventDefault();
        const propId = deleteLink.dataset.propId;
        const security = deleteLink.dataset.security;
        if (!propId || !security) {
            alert('لا يمكن حذف العقار. رقم العقار أو الرمز الأمني مفقود.');
            return;
        }

        if (!confirm('هل أنت متأكد من حذف هذا العقار؟')) {
            return;
        }

        fetch(MostaagerAjax.ajax_url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            credentials: 'same-origin',
            body: new URLSearchParams({
                action: 'ms_delete_agent_property',
                prop_id: propId,
                security: security
            })
        }).then(response => response.json()).then(json => {
            if (json && json.success) {
                const row = deleteLink.closest('tr');
                if (row) {
                    row.remove();
                }
                if (json.data && json.data.message) {
                    alert(json.data.message);
                }
                if (json.data && json.data.redirect) {
                    window.location.href = json.data.redirect;
                }
            } else {
                const message = json && json.data && json.data.message ? json.data.message : (json && json.data ? json.data : 'فشل حذف العقار.');
                alert(message);
            }
        }).catch(err => {
            console.error('Delete property AJAX error', err);
            alert('حدث خطأ في الاتصال أثناء حذف العقار.');
        });
    });

    // Pay invoice button handler
    document.addEventListener('click', function (e) {
        const payBtn = e.target.closest('.ms-pay-now-btn');
        if (!payBtn) {
            return;
        }

        e.preventDefault();
        const invoiceId = payBtn.dataset.invoiceId;
        const nonce = payBtn.dataset.nonce;
        if (!invoiceId || !nonce) {
            alert('لا يمكن معالجة الدفع. رقم الفاتورة أو الرمز الأمني مفقود.');
            return;
        }

        payBtn.disabled = true;
        payBtn.textContent = 'جاري المعالجة...';

        fetch(MostaagerAjax.ajax_url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            credentials: 'same-origin',
            body: new URLSearchParams({
                action: 'ms_pay_invoice',
                invoice_id: invoiceId,
                security: nonce
            })
        }).then(response => response.json()).then(json => {
            if (json && json.success && json.data && json.data.payment_url) {
                window.location.href = json.data.payment_url;
            } else {
                const message = json && json.data && json.data.message ? json.data.message : (json && json.data ? json.data : 'فشل معالجة الدفع.');
                alert(message);
                payBtn.disabled = false;
                payBtn.textContent = 'ادفع الآن';
            }
        }).catch(err => {
            console.error('Pay invoice AJAX error', err);
            alert('حدث خطأ في الاتصال أثناء معالجة الدفع.');
            payBtn.disabled = false;
            payBtn.textContent = 'ادفع الآن';
        });
    });


    // Manager invoice actions: mark pending invoices as paid or canceled.
    document.addEventListener('click', function (e) {
        const actionBtn = e.target.closest('.ms-mark-paid-btn, .ms-cancel-invoice-btn');
        if (!actionBtn || !MostaagerAjax || !MostaagerAjax.ajax_url) {
            return;
        }

        e.preventDefault();
        const invoiceId = actionBtn.dataset.invoiceId;
        const security = actionBtn.dataset.security || actionBtn.dataset.nonce || MostaagerAjax.nonce;
        const isCancel = actionBtn.classList.contains('ms-cancel-invoice-btn');
        const action = isCancel ? 'ms_manager_cancel_invoice' : 'ms_manager_mark_invoice_paid';
        const confirmMessage = isCancel ? 'هل أنت متأكد من إلغاء هذه الفاتورة؟' : 'هل تريد وضع هذه الفاتورة كمدفوعة؟';

        if (!invoiceId || !security) {
            alert('تعذر تنفيذ الإجراء. رقم الفاتورة أو الرمز الأمني مفقود.');
            return;
        }
        if (!confirm(confirmMessage)) {
            return;
        }

        const originalText = actionBtn.textContent;
        actionBtn.disabled = true;
        actionBtn.textContent = 'جاري التنفيذ...';

        fetch(MostaagerAjax.ajax_url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            credentials: 'same-origin',
            body: new URLSearchParams({
                action: action,
                invoice_id: invoiceId,
                security: security
            })
        }).then(response => response.json()).then(json => {
            if (json && json.success) {
                const row = actionBtn.closest('tr');
                if (row) {
                    const statusCell = row.querySelector('.ms-invoice-status');
                    const actionsCell = actionBtn.closest('td');
                    if (statusCell) {
                        statusCell.innerHTML = isCancel
                            ? '<span style="color:#ef4444;font-weight:600">ملغي</span>'
                            : '<span style="color:#10b981;font-weight:600">مدفوع ✓</span>';
                    }
                    if (actionsCell) {
                        actionsCell.innerHTML = isCancel
                            ? '<span style="color:#ef4444;font-weight:600">ملغي</span>'
                            : '<span style="color:#10b981;font-weight:600">مدفوع</span>';
                    }
                }
                alert(isCancel ? 'تم إلغاء الفاتورة بنجاح.' : 'تم وضع الفاتورة كمدفوعة بنجاح.');
            } else {
                const message = json && json.data
                    ? (typeof json.data === 'string' ? json.data : (json.data.message || json.data.error || 'فشل تنفيذ الإجراء.'))
                    : 'فشل تنفيذ الإجراء.';
                alert(message);
                actionBtn.disabled = false;
                actionBtn.textContent = originalText;
            }
        }).catch(err => {
            console.error('Manager invoice action error', err);
            alert('حدث خطأ في الاتصال أثناء تنفيذ الإجراء.');
            actionBtn.disabled = false;
            actionBtn.textContent = originalText;
        });
    });

    // Purchase subscription plan handler
    document.addEventListener('click', function (e) {
        const purchaseBtn = e.target.closest('.ms-subscription-purchase-btn');
        if (!purchaseBtn) {
            return;
        }

        e.preventDefault();
        const planKey = purchaseBtn.dataset.planKey;
        const planName = purchaseBtn.dataset.planName;
        const planPrice = purchaseBtn.dataset.planPrice;
        if (!planKey || !planName || !planPrice) {
            alert('تعذر تحديد تفاصيل الباقة. حاول مرة أخرى.');
            return;
        }

        purchaseBtn.disabled = true;
        purchaseBtn.textContent = 'جاري التحضير...';

        fetch(MostaagerAjax.ajax_url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            credentials: 'same-origin',
            body: new URLSearchParams({
                action: 'ms_purchase_subscription_plan',
                plan_key: planKey,
                security: MostaagerAjax.nonce
            })
        }).then(response => response.json()).then(json => {
            if (json && json.success && json.data && json.data.payment_url) {
                window.location.href = json.data.payment_url;
            } else {
                const message = json && json.data && json.data.message ? json.data.message : (json && json.data ? json.data : 'فشل إنشاء الطلب.');
                alert(message);
                purchaseBtn.disabled = false;
                purchaseBtn.textContent = 'اشترِ الآن';
            }
        }).catch(err => {
            console.error('Purchase subscription AJAX error', err);
            alert('حدث خطأ أثناء معالجة الطلب.');
            purchaseBtn.disabled = false;
            purchaseBtn.textContent = 'اشترِ الآن';
        });
    });

    // Mark notifications read when tenant opens the notifications tab
    document.addEventListener('click', function (e) {
        const notificationTab = e.target.closest('.ms-tab-link[data-tab="notifications"]');
        if (!notificationTab) {
            return;
        }

        const unreadBadge = notificationTab.querySelector('.ms-menu-badge');
        if (!MostaagerAjax || !MostaagerAjax.ajax_url) {
            return;
        }

        fetch(MostaagerAjax.ajax_url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            credentials: 'same-origin',
            body: new URLSearchParams({
                action: 'ms_mark_notifications_read',
                security: MostaagerAjax.nonce
            })
        }).then(response => response.json()).then(json => {
            if (json && json.success) {
                if (unreadBadge) {
                    unreadBadge.textContent = '';
                }
                document.querySelectorAll('.ms-notification-unread').forEach(function(el) {
                    el.classList.remove('ms-notification-unread');
                    el.classList.add('ms-notification-read');
                });
            }
        }).catch(err => {
            console.error('Mark notifications read error', err);
        });
    });

    // Agent property status update and contract upload actions
    document.addEventListener('change', function (e) {
        const select = e.target.closest('.agent-property-status');
        if (!select || !MostaagerAjax || !MostaagerAjax.ajax_url) {
            return;
        }

        const propId = parseInt(select.dataset.propId, 10);
        const status = select.value;
        if (!propId || !status) {
            return;
        }

        select.disabled = true;

        fetch(MostaagerAjax.ajax_url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            credentials: 'same-origin',
            body: new URLSearchParams({
                action: 'ms_agent_update_property_status',
                prop_id: propId,
                term_slug: status,
                security: MostaagerAjax.nonce
            })
        }).then(response => response.json()).then(json => {
            if (json && json.success) {
                alert('تم تحديث حالة العقار بنجاح.');
            } else {
                const message = json && json.data ? (typeof json.data === 'string' ? json.data : (json.data.message || json.data.error || 'فشل تحديث الحالة.')) : 'فشل تحديث الحالة.';
                alert(message);
            }
        }).catch(err => {
            console.error('Update property status error', err);
            alert('حدث خطأ أثناء تحديث حالة العقار.');
        }).finally(() => {
            select.disabled = false;
        });
    });

    document.addEventListener('click', function (e) {
        const uploadBtn = e.target.closest('.agent-upload-contract-button');
        if (!uploadBtn) {
            return;
        }

        const propId = uploadBtn.dataset.propId;
        const contractType = uploadBtn.dataset.contractType;
        if (!propId || !contractType) {
            alert('فشل تحديد العقار أو نوع العقد.');
            return;
        }

        const fileInput = document.querySelector('.agent-contract-file-input[data-prop-id="' + propId + '"][data-contract-type="' + contractType + '"]');
        if (fileInput) {
            fileInput.click();
        }
    });

    document.addEventListener('change', function (e) {
        const input = e.target.closest('.agent-contract-file-input');
        if (!input || !MostaagerAjax || !MostaagerAjax.ajax_url) {
            return;
        }

        const propId = parseInt(input.dataset.propId, 10);
        const contractType = input.dataset.contractType;
        if (!propId || !contractType || !input.files || input.files.length === 0) {
            return;
        }

        const file = input.files[0];
        const formData = new FormData();
        formData.append('action', 'ms_agent_upload_property_contract');
        formData.append('prop_id', propId);
        formData.append('contract_type', contractType);
        formData.append('security', MostaagerAjax.nonce);
        formData.append('contract_file', file);

        const button = document.querySelector('.agent-upload-contract-button[data-prop-id="' + propId + '"][data-contract-type="' + contractType + '"]');
        if (button) {
            button.disabled = true;
            button.textContent = 'جاري التحميل...';
        }

        fetch(MostaagerAjax.ajax_url, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        }).then(response => response.json()).then(json => {
            if (button) {
                button.disabled = false;
                button.textContent = contractType === 'sale' ? 'رفع عقد بيع' : 'رفع عقد إيجار';
            }
            if (json && json.success) {
                alert('تم رفع العقد بنجاح.');
                if (json.data && json.data.url) {
                    const linkWrapper = input.closest('td');
                    if (linkWrapper) {
                        const existingLink = linkWrapper.querySelector('.agent-contract-link');
                        const linkHtml = '<div class="agent-contract-link" style="margin-bottom:8px;"><a href="' + encodeURI(json.data.url) + '" target="_blank" style="color:#2563eb;text-decoration:underline;">' + (contractType === 'sale' ? 'عرض عقد البيع' : 'عرض عقد الإيجار') + '</a></div>';
                        if (existingLink) {
                            existingLink.outerHTML = linkHtml;
                        } else {
                            linkWrapper.insertAdjacentHTML('afterbegin', linkHtml);
                        }
                    }
                }
            } else {
                const message = json && json.data ? (typeof json.data === 'string' ? json.data : (json.data.message || json.data.error || 'فشل رفع العقد.')) : 'فشل رفع العقد.';
                alert(message);
            }
        }).catch(err => {
            console.error('Upload contract AJAX error', err);
            alert('حدث خطأ أثناء رفع العقد.');
            if (button) {
                button.disabled = false;
                button.textContent = contractType === 'sale' ? 'رفع عقد بيع' : 'رفع عقد إيجار';
            }
        }).finally(() => {
            input.value = '';
        });
    });
}

window.MS_DASHBOARD_TABS_LOADED = true;

// building, agent and rent dashboard live fetch
document.addEventListener('DOMContentLoaded', function(){
    if (typeof MostaagerAjax === 'undefined') return;

    // buildings
    const bEl = document.getElementById('ms-buildings-count');
    if (bEl) {
        fetch(MostaagerAjax.ajax_url + '?action=ms_get_building_dashboard', { method: 'POST', credentials: 'same-origin' })
        .then(r=>r.json()).then(json=>{
            if(json.success && json.data){
                const d = json.data;
                bEl.textContent = d.buildings_count;
                const unitsEl = document.getElementById('ms-units-count');
                const paidInvEl = document.getElementById('ms-paid-invoices');
                const activeMaintEl = document.getElementById('ms-active-maintenance');
                const unitsPaidEl = document.getElementById('ms-units-paid');
                const unitsUnpaidEl = document.getElementById('ms-units-unpaid');
                const collectionPercent = document.getElementById('ms-collection-percent');
                const collectionTotal = document.getElementById('ms-collection-total');

                if(unitsEl && typeof d.units_count !== 'undefined') unitsEl.textContent = d.units_count;
                if(paidInvEl && typeof d.paid_invoices_count !== 'undefined') paidInvEl.textContent = d.paid_invoices_count;
                if(activeMaintEl && typeof d.active_maintenance_count !== 'undefined') activeMaintEl.textContent = d.active_maintenance_count;
                if(unitsPaidEl && d.units_paid_info) unitsPaidEl.textContent = d.units_paid_info.paid;
                if(unitsUnpaidEl && d.units_paid_info) unitsUnpaidEl.textContent = d.units_paid_info.unpaid;
                if(collectionPercent && d.collection) collectionPercent.textContent = (typeof d.collection.percent !== 'undefined') ? d.collection.percent + '%' : '0%';
                if(collectionTotal && d.collection) collectionTotal.textContent = 'ج.م ' + Number(d.collection.total_collected).toFixed(2);
            }
        }).catch(()=>{});
    }

    // agent
    const aEl = document.getElementById('ms-listings-count');
    if (aEl) {
        fetch(MostaagerAjax.ajax_url + '?action=ms_get_agent_dashboard', { method: 'POST', credentials: 'same-origin' })
        .then(r=>r.json()).then(json=>{ if(json.success && json.data) aEl.textContent = json.data.listings_count; }).catch(()=>{});
    }

    // rent wallet
    const rEl = document.getElementById('ms-rent-wallet');
    const rentInvoicesEl = document.getElementById('ms-rent-invoices');
    const overdueEl = document.getElementById('ms-rent-overdue');
    const nextDueEl = document.getElementById('ms-next-due');
    const nextDueDateEl = document.getElementById('ms-next-due-date');
    const nextRentEl = document.getElementById('ms-next-rent');
    const rentStreakCard = document.querySelector('.ms-rent-streak-badge');

    if (rEl || rentInvoicesEl || overdueEl || nextDueEl || nextDueDateEl || nextRentEl || rentStreakCard) {
        fetch(MostaagerAjax.ajax_url + '?action=ms_get_rent_dashboard', { method: 'POST', credentials: 'same-origin' })
        .then(r=>r.json()).then(json=>{
            if(json.success && json.data){
                const d = json.data;
                if(rEl) rEl.textContent = 'ج.م ' + Number(d.wallet_balance).toFixed(2);
                if(rentInvoicesEl && typeof d.invoices_count !== 'undefined') rentInvoicesEl.textContent = d.invoices_count;
                if(overdueEl && typeof d.overdue_count !== 'undefined') overdueEl.textContent = d.overdue_count;
                if(nextDueEl) nextDueEl.textContent = d.next_due ? d.next_due : '—';
                if(nextDueDateEl) nextDueDateEl.textContent = d.next_due ? d.next_due : '—';
                if(nextRentEl && typeof d.next_due_amount !== 'undefined') nextRentEl.textContent = d.next_due_amount ? 'ج.م ' + Number(d.next_due_amount).toFixed(2) : '—';
                if(rentStreakCard && d.rent_streak) {
                    rentStreakCard.style.borderLeftColor = d.rent_streak.color || '#64748b';
                    const msNumberEl = rentStreakCard.querySelector('.ms-number');
                    if (msNumberEl) msNumberEl.textContent = Number(d.rent_streak.streak).toFixed(0) + ' شهر';
                    const msLabelEl = rentStreakCard.querySelector('p');
                    if (msLabelEl) msLabelEl.textContent = d.rent_streak.label || '';
                }
            }
        }).catch(()=>{});
    }

    // Maintenance tab functionality
    const maintenanceTableWrap = document.getElementById('ms-maintenance-table-wrap');
    if (maintenanceTableWrap) {
        const form = document.getElementById('ms-maintenance-form');
        const buildingSelector = document.getElementById('ms-building-selector');

        function getCurrentBuildingId() {
            if (buildingSelector && buildingSelector.value) {
                return buildingSelector.value;
            }
            return form ? form.querySelector('input[name="building_id"]')?.value : 0;
        }

        function renderMaintenanceTable(rows) {
            const tbody = document.getElementById('ms-maintenance-tbody');
            if (!tbody) return;
            tbody.innerHTML = '';
            if (!rows || rows.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" style="padding:12px;text-align:center">لا توجد طلبات صيانة</td></tr>';
                return;
            }
            rows.forEach(row => {
                const tr = document.createElement('tr');
                tr.style.borderTop = '1px solid #e5e7eb';
                const statusLabels = {
                    open: 'مفتوح',
                    in_progress: 'قيد التقدم',
                    completed: 'مكتمل',
                    closed: 'مغلق'
                };
                const currentStatus = row.status || 'open';
                const statusLabel = statusLabels[currentStatus] || currentStatus;
                // For legacy rows, avoid rendering inline status controls (they map to a different storage backend)
                let statusControlHtml = '';
                if (row.source && row.source === 'legacy') {
                    // show a read-only label for legacy rows
                    statusControlHtml = `<div style="padding:6px 8px;border:1px solid #eee;border-radius:6px;background:#fafafa;color:#666;text-align:center;">${statusLabel}</div>`;
                } else {
                    const statusSelect = `<select class="ms-maintenance-status-select" data-id="${row.id}" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px">` +
                        ['open', 'in_progress', 'completed', 'closed'].map(status => `
                            <option value="${status}" ${status === currentStatus ? 'selected' : ''}>${statusLabels[status]}</option>
                        `).join('') +
                        `</select>`;
                    const actionBtn = `<button class="ms-update-maintenance-status" data-id="${row.id}" style="margin-top:8px;padding:8px 12px;background:#2563eb;color:#fff;border:none;border-radius:6px;cursor:pointer" ${currentStatus === 'closed' ? 'disabled' : ''}>تحديث</button>`;
                    statusControlHtml = statusSelect + actionBtn;
                }
                tr.innerHTML = `
                    <td style="padding:12px">${row.title || ''}</td>
                    <td style="padding:12px">${row.maintenance_type || ''}</td>
                    <td style="padding:12px">ج.م ${Number(row.cost).toFixed(2)}</td>
                    <td style="padding:12px">${statusLabel}</td>
                    <td style="padding:12px">${row.invoices_count || 0}</td>
                    <td style="padding:12px">${row.created_at || ''}</td>
                    <td style="padding:12px">${statusControlHtml}</td>
                `;
                tbody.appendChild(tr);
            });
        }

        function fetchMaintenanceRequests() {
            const buildingId = getCurrentBuildingId();
            if (!buildingId) return;
            const params = new URLSearchParams({
                action: 'ms_get_maintenance_requests',
                building_id: buildingId,
                security: MostaagerAjax.nonce
            });
            fetch(MostaagerAjax.ajax_url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                credentials: 'same-origin',
                body: params
            }).then(r => r.json()).then(json => {
                if (json.success && json.data) {
                    renderMaintenanceTable(json.data);
                }
            }).catch(err => console.error('Maintenance fetch error', err));
        }

        fetchMaintenanceRequests();

        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(form);
                const params = new URLSearchParams(formData);
                params.append('action', 'ms_create_maintenance_request');
                params.append('security', MostaagerAjax.nonce);
                fetch(MostaagerAjax.ajax_url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    credentials: 'same-origin',
                    body: params
                }).then(r => r.json()).then(json => {
                    if (json.success && json.data) {
                        const successDiv = document.getElementById('ms-maintenance-success');
                        if (successDiv) {
                            successDiv.textContent = json.data.message || 'تم إنشاء الطلب بنجاح';
                            successDiv.style.display = 'block';
                            setTimeout(() => successDiv.style.display = 'none', 3000);
                        }
                        form.reset();
                        fetchMaintenanceRequests();
                    } else {
                        alert(json.data || 'فشل إنشاء الطلب');
                    }
                }).catch(err => {
                    console.error('Maintenance create error', err);
                    alert('حدث خطأ في الاتصال');
                });
            });

            const isRecurringCheckbox = document.getElementById('ms-is-recurring');
            const recurrenceDayWrap = document.getElementById('ms-recurrence-day-wrap');
            if (isRecurringCheckbox && recurrenceDayWrap) {
                isRecurringCheckbox.addEventListener('change', function() {
                    recurrenceDayWrap.style.display = this.checked ? 'block' : 'none';
                });
            }
        }

        const transferForm = document.getElementById('ms-transfer-request-form');
        if (transferForm) {
            transferForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(transferForm);
                formData.append('action', 'ms_request_building_transfer');
                formData.append('security', MostaagerAjax.nonce);

                fetch(MostaagerAjax.ajax_url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                }).then(r => r.json()).then(json => {
                    const messageEl = document.getElementById('ms-transfer-request-message');
                    if (json.success) {
                        if (messageEl) {
                            messageEl.style.color = '#10b981';
                            messageEl.textContent = json.data.message || 'تم إرسال طلب التحويل بنجاح.';
                            messageEl.style.display = 'block';
                        }
                        transferForm.reset();
                    } else {
                        if (messageEl) {
                            messageEl.style.color = '#ef4444';
                            messageEl.textContent = (json.data && json.data.message) ? json.data.message : 'فشل إرسال طلب التحويل.';
                            messageEl.style.display = 'block';
                        } else {
                            alert((json.data && json.data.message) ? json.data.message : 'فشل إرسال طلب التحويل.');
                        }
                    }
                }).catch(err => {
                    console.error('Transfer request error', err);
                    alert('حدث خطأ في الاتصال');
                });
            });
        }

        if (buildingSelector) {
            buildingSelector.addEventListener('change', function() {
                if (form) {
                    const hidden = form.querySelector('input[name="building_id"]');
                    if (hidden) {
                        hidden.value = this.value;
                    }
                }
                const transferForm = document.getElementById('ms-transfer-request-form');
                if (transferForm) {
                    const hiddenTransfer = transferForm.querySelector('input[name="building_id"]');
                    if (hiddenTransfer) {
                        hiddenTransfer.value = this.value;
                    }
                }
                const discussionRoot = document.querySelector('#agent-discussions, #building-discussions');
                if (discussionRoot) {
                    discussionRoot.dataset.buildingId = this.value;
                }
                // Broadcast building change so all dashboard modules can react
                try {
                    document.dispatchEvent(new CustomEvent('mostaager:building_changed', { detail: { building_id: this.value } }));
                } catch (err) {
                    // fallback for older browsers
                    fetchMaintenanceRequests();
                }
            });
        }

        // Ensure other modules refresh when building changes
        document.addEventListener('mostaager:building_changed', function(e) {
            if (typeof fetchMaintenanceRequests === 'function') {
                fetchMaintenanceRequests();
            }
            if (typeof loadDiscussions === 'function' && e && e.detail && e.detail.building_id) {
                loadDiscussions(e.detail.building_id);
            }
        });

        document.addEventListener('click', function(e) {
                const updateBtn = e.target.closest('.ms-update-maintenance-status');
                if (updateBtn) {
                    const maintenanceId = updateBtn.dataset.id;
                    const select = document.querySelector(`.ms-maintenance-status-select[data-id="${maintenanceId}"]`);
                    const status = select ? select.value : 'open';
                    const params = new URLSearchParams({
                        action: 'ms_update_maintenance_status',
                        maintenance_id: maintenanceId,
                        status: status,
                        security: MostaagerAjax.nonce,
                    });
                    fetch(MostaagerAjax.ajax_url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        credentials: 'same-origin',
                        body: params
                    }).then(r => r.json()).then(json => {
                    if (json.success) {
                        fetchMaintenanceRequests();
                    } else {
                        alert('فشل تحديث الحالة');
                    }
                }).catch(err => {
                    console.error('Maintenance status update error', err);
                    alert('حدث خطأ في الاتصال');
                });
            }
        });

            // Discussions (agent) — load topics, open thread, submit replies
            (function(){
                function renderDiscussionList(container, topics) {
                    container.innerHTML = '';
                    if (!topics || topics.length === 0) {
                        container.innerHTML = '<li style="padding:12px;color:#666">لا توجد مواضيع.</li>';
                        return;
                    }
                    topics.forEach(t => {
                        const li = document.createElement('li');
                        li.style.padding = '10px 8px';
                        li.style.borderBottom = '1px solid #f3f4f6';
                        li.style.cursor = 'pointer';
                        li.dataset.discussionId = t.id || t.ID || t.post_id || '';
                        li.innerHTML = `<div style="font-weight:600">${t.title || t.post_title || 'بدون عنوان'}</div><div style="font-size:12px;color:#666;margin-top:6px">${t.excerpt || t.excerpt || (t.created_at||'')}</div>`;
                        container.appendChild(li);
                    });
                }

                function renderDiscussionMessages(wrapper, replies) {
                    wrapper.innerHTML = '';
                    if (!replies || replies.length === 0) {
                        wrapper.innerHTML = '<div style="color:#666">لا توجد ردود حتى الآن.</div>';
                        return;
                    }
                    replies.forEach(r => {
                        const div = document.createElement('div');
                        div.style.padding = '8px';
                        div.style.borderBottom = '1px solid #f3f4f6';
                        div.innerHTML = `<div style="font-size:13px;color:#111"><strong>${r.author_name || r.author || 'مستخدم'}</strong> — <span style="font-size:12px;color:#666">${r.created_at || ''}</span></div><div style="margin-top:6px">${r.content || r.comment || ''}</div>`;
                        wrapper.appendChild(div);
                    });
                    if (wrapper.dataset.autoScroll === 'true') {
                        wrapper.scrollTop = wrapper.scrollHeight;
                    }
                }

                async function getCurrentDiscussionBuildingId() {
                    const selector = document.getElementById('ms-building-selector');
                    if (selector && selector.value) {
                        return selector.value;
                    }
                    const container = document.querySelector('#agent-discussions, #building-discussions');
                    return container ? container.dataset.buildingId : 0;
                }

                async function loadDiscussions(buildingId) {
                    const container = document.querySelector('#agent-discussions, #building-discussions');
                    if (!container || !MostaagerAjax || !MostaagerAjax.ajax_url) return;
                    const listEl = container.querySelector('.ms-discussions-list-ul');
                    const messagesEl = container.querySelector('.ms-discussion-messages');
                    const replyForm = container.querySelector('.ms-discussion-reply-form');
                    if (!listEl) return;

                    listEl.innerHTML = '<li style="padding:12px;color:#666">جاري التحميل...</li>';

                    try {
                        const params = new URLSearchParams({ action: 'ms_get_discussions', building_id: buildingId });
                        const resp = await fetch(MostaagerAjax.ajax_url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: params });
                        const json = await resp.json();
                        if (json && json.success && json.data) {
                            renderDiscussionList(listEl, json.data);
                            if (messagesEl) messagesEl.innerHTML = '';
                            if (replyForm) replyForm.style.display = 'none';
                        } else {
                            listEl.innerHTML = '<li style="padding:12px;color:#666">فشل جلب المواضيع.</li>';
                        }
                    } catch (err) {
                        console.error('Discussions fetch error', err);
                        listEl.innerHTML = '<li style="padding:12px;color:#666">خطأ في الاتصال أثناء جلب المواضيع.</li>';
                    }
                }

                async function loadDiscussionReplies(discussionId) {
                    const container = document.querySelector('#agent-discussions, #building-discussions');
                    if (!container || !MostaagerAjax || !MostaagerAjax.ajax_url) return;
                    const messagesEl = container.querySelector('.ms-discussion-messages');
                    const replyForm = container.querySelector('.ms-discussion-reply-form');
                    if (!messagesEl) return;

                    messagesEl.innerHTML = '<div style="color:#666;padding:12px">جاري التحميل...</div>';

                    try {
                        const params = new URLSearchParams({ action: 'ms_get_discussion_replies', discussion_id: discussionId });
                        const resp = await fetch(MostaagerAjax.ajax_url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: params });
                        const json = await resp.json();
                        if (json && json.success && json.data) {
                            renderDiscussionMessages(messagesEl, json.data);
                            if (replyForm) {
                                replyForm.style.display = 'block';
                                replyForm.dataset.currentDiscussion = discussionId;
                            }
                        } else {
                            messagesEl.innerHTML = '<div style="color:#666;padding:12px">فشل جلب الردود.</div>';
                            if (replyForm) replyForm.style.display = 'none';
                        }
                    } catch (err) {
                        console.error('Discussion replies fetch error', err);
                        messagesEl.innerHTML = '<div style="color:#666;padding:12px">خطأ في الاتصال أثناء جلب الردود.</div>';
                        if (replyForm) replyForm.style.display = 'none';
                    }
                }

                document.addEventListener('click', function(e) {
                    const discTab = e.target.closest('.ms-tab-link[data-tab="discussions"]');
                    if (discTab) {
                        setTimeout(async () => {
                            const container = document.querySelector('#agent-discussions, #building-discussions');
                            if (!container) return;
                            const buildingId = await getCurrentDiscussionBuildingId();
                            if (buildingId) loadDiscussions(buildingId);
                        }, 50);
                    }

                    const li = e.target.closest('#agent-discussions .ms-discussions-list-ul li, #building-discussions .ms-discussions-list-ul li');
                    if (li) {
                        const discussionId = li.dataset.discussionId;
                        if (discussionId) {
                            const siblings = li.parentElement.querySelectorAll('li');
                            siblings.forEach(s => s.style.background = '');
                            li.style.background = '#f8fafc';
                            loadDiscussionReplies(discussionId);
                        }
                    }
                });

                // معالج إنشاء موضوع جديد
                document.addEventListener('submit', function(e){
                    const createForm = e.target.closest('.ms-discussion-create-form');
                    if (createForm) {
                        e.preventDefault();
                        const buildingId = createForm.querySelector('input[name="building_id"]').value;
                        const title = createForm.querySelector('input[name="title"]').value.trim();
                        const content = createForm.querySelector('textarea[name="content"]').value.trim();
                        
                        if (!buildingId || !title || !content) {
                            alert('الرجاء ملء جميع الحقول.');
                            return;
                        }
                        
                        const params = new URLSearchParams({
                            action: 'ms_create_discussion',
                            building_id: buildingId,
                            title: title,
                            content: content,
                            security: (MostaagerAjax && MostaagerAjax.nonce) ? MostaagerAjax.nonce : ''
                        });
                        
                        fetch(MostaagerAjax.ajax_url, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: params
                        })
                        .then(r => r.json())
                        .then(json => {
                            if (json && json.success) {
                                alert('تم إنشاء الموضوع بنجاح!');
                                createForm.reset();
                                // إعادة تحميل المواضيع
                                const container = document.querySelector('#agent-discussions, #building-discussions');
                                if (container) {
                                    const buildingId = container.dataset.buildingId;
                                    if (buildingId) loadDiscussions(buildingId);
                                }
                            } else {
                                const msg = json && json.data ? json.data : 'فشل إنشاء الموضوع.';
                                alert(msg);
                            }
                        })
                        .catch(err => {
                            console.error('Create discussion error', err);
                            alert('حدث خطأ أثناء إنشاء الموضوع.');
                        });
                        return;
                    }
                });

                document.addEventListener('submit', function(e){
                    const form = e.target.closest('#agent-discussions .ms-discussion-reply-form, #building-discussions .ms-discussion-reply-form');
                    if (!form) return;
                    e.preventDefault();
                    const discussionId = form.dataset.currentDiscussion;
                    const textarea = form.querySelector('textarea[name="reply"]');
                    if (!discussionId || !textarea || !textarea.value.trim()) {
                        alert('الرجاء كتابة الرد.');
                        return;
                    }
                    const content = textarea.value.trim();
                    const params = new URLSearchParams({ action: 'ms_add_discussion_reply', discussion_id: discussionId, content: content, security: (MostaagerAjax && MostaagerAjax.nonce) ? MostaagerAjax.nonce : '' });
                    fetch(MostaagerAjax.ajax_url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: params })
                    .then(r => r.json()).then(json => {
                        if (json && json.success) {
                            if (json.data && json.data.reply) {
                                const container = document.querySelector('#agent-discussions, #building-discussions');
                                const messagesEl = container ? container.querySelector('.ms-discussion-messages') : null;
                                if (messagesEl) {
                                    renderDiscussionMessages(messagesEl, json.data.replies || [json.data.reply]);
                                }
                            }
                            textarea.value = '';
                        } else {
                            const msg = json && json.data && json.data.message ? json.data.message : 'فشل إرسال الرد.';
                            alert(msg);
                        }
                    }).catch(err => {
                        console.error('Add discussion reply error', err);
                        alert('حدث خطأ أثناء إرسال الرد.');
                    });
                });

                document.addEventListener('DOMContentLoaded', async function(){
                    const container = document.querySelector('#agent-discussions, #building-discussions');
                    const activePanel = document.querySelector('.ms-tab-content.active#discussions');
                    if (container && activePanel) {
                        const buildingId = await getCurrentDiscussionBuildingId();
                        if (buildingId) loadDiscussions(buildingId);
                    }
                });
            })();
    }
});

window.MostaagerQA = {
    ajaxUrl: (typeof MostaagerAjax !== 'undefined' && MostaagerAjax.ajax_url) ? MostaagerAjax.ajax_url : null,
    nonce: (typeof MostaagerAjax !== 'undefined' && MostaagerAjax.nonce) ? MostaagerAjax.nonce : null,
    getNonce: function() {
        return this.nonce;
    },
    getInvoiceNonceFromButton: function(invoiceId) {
        var button = document.querySelector('.ms-pay-now-btn[data-invoice-id="' + invoiceId + '"]');
        return button ? button.dataset.nonce : null;
    },
    getInvoiceButtons: function() {
        return Array.from(document.querySelectorAll('.ms-pay-now-btn')).map(function(button) {
            return {
                invoiceId: button.dataset.invoiceId,
                nonce: button.dataset.nonce,
                label: button.textContent.trim(),
            };
        });
    },
    payInvoiceByButton: async function(invoiceId) {
        var nonce = this.getInvoiceNonceFromButton(invoiceId);
        if (!nonce) {
            throw new Error('Unable to find invoice button nonce for invoice ' + invoiceId);
        }
        return this.payInvoice(invoiceId, nonce);
    },
    payFirstInvoice: async function() {
        var buttons = this.getInvoiceButtons();
        if (!buttons.length) {
            throw new Error('No pay-now buttons found on the page.');
        }
        return this.payInvoice(buttons[0].invoiceId, buttons[0].nonce);
    },
    markNotificationsRead: async function() {
        if (!this.ajaxUrl || !this.nonce) {
            throw new Error('MostaagerAjax is not loaded.');
        }
        var params = new URLSearchParams({
            action: 'ms_mark_notifications_read',
            security: this.nonce,
        });
        var response = await fetch(this.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body: params,
        });
        return response.json();
    },
    payInvoice: async function(invoiceId, nonce) {
        if (!this.ajaxUrl) {
            throw new Error('MostaagerAjax is not loaded.');
        }
        if (!invoiceId || !nonce) {
            throw new Error('invoiceId and nonce are required.');
        }
        var params = new URLSearchParams({
            action: 'ms_pay_invoice',
            invoice_id: invoiceId,
            security: nonce,
        });
        var response = await fetch(this.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body: params,
        });
        return response.json();
    }
};

console.info('MostaagerQA helpers are available in the browser console. Use MostaagerQA.markNotificationsRead(), MostaagerQA.getInvoiceButtons(), MostaagerQA.payInvoiceByButton(invoiceId), or MostaagerQA.payFirstInvoice().');


// Standalone discussions bootstrap for dashboards that do not render the manager maintenance table (notably the agent dashboard).
(function initMostaagerStandaloneDiscussions(){
    if (window.MS_STANDALONE_DISCUSSIONS_BOUND) {
        return;
    }
    const rootSelector = '#agent-discussions';
    if (!document.querySelector(rootSelector) || document.getElementById('ms-maintenance-table-wrap')) {
        return;
    }
    window.MS_STANDALONE_DISCUSSIONS_BOUND = true;

    function getAjax() {
        return (typeof MostaagerAjax !== 'undefined' && MostaagerAjax.ajax_url) ? MostaagerAjax : null;
    }

    function renderDiscussionList(container, topics) {
        container.innerHTML = '';
        if (!topics || topics.length === 0) {
            container.innerHTML = '<li style="padding:12px;color:#666">لا توجد مواضيع.</li>';
            return;
        }
        topics.forEach(t => {
            const li = document.createElement('li');
            li.style.padding = '10px 8px';
            li.style.borderBottom = '1px solid #f3f4f6';
            li.style.cursor = 'pointer';
            li.dataset.discussionId = t.id || t.ID || t.post_id || '';
            li.innerHTML = `<div style="font-weight:600">${t.title || t.post_title || 'بدون عنوان'}</div><div style="font-size:12px;color:#666;margin-top:6px">${t.excerpt || t.created_at || ''}</div>`;
            container.appendChild(li);
        });
    }

    function renderDiscussionMessages(wrapper, replies) {
        wrapper.innerHTML = '';
        if (!replies || replies.length === 0) {
            wrapper.innerHTML = '<div style="color:#666">لا توجد ردود حتى الآن.</div>';
            return;
        }
        replies.forEach(r => {
            const div = document.createElement('div');
            div.style.padding = '8px';
            div.style.borderBottom = '1px solid #f3f4f6';
            div.innerHTML = `<div style="font-size:13px;color:#111"><strong>${r.author_name || r.author || 'مستخدم'}</strong> — <span style="font-size:12px;color:#666">${r.created_at || ''}</span></div><div style="margin-top:6px">${r.content || r.comment || ''}</div>`;
            wrapper.appendChild(div);
        });
        wrapper.scrollTop = wrapper.scrollHeight;
    }

    async function loadDiscussions(buildingId) {
        const ajax = getAjax();
        const container = document.querySelector(rootSelector);
        if (!ajax || !container || !buildingId) return;
        const listEl = container.querySelector('.ms-discussions-list-ul');
        const messagesEl = container.querySelector('.ms-discussion-messages');
        const replyForm = container.querySelector('.ms-discussion-reply-form');
        if (!listEl) return;
        listEl.innerHTML = '<li style="padding:12px;color:#666">جاري التحميل...</li>';
        try {
            const params = new URLSearchParams({ action: 'ms_get_discussions', building_id: buildingId });
            const resp = await fetch(ajax.ajax_url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: params });
            const json = await resp.json();
            if (json && json.success && json.data) {
                renderDiscussionList(listEl, json.data);
                if (messagesEl) messagesEl.innerHTML = '';
                if (replyForm) replyForm.style.display = 'none';
            } else {
                listEl.innerHTML = '<li style="padding:12px;color:#666">فشل جلب المواضيع.</li>';
            }
        } catch (err) {
            console.error('Standalone discussions fetch error', err);
            listEl.innerHTML = '<li style="padding:12px;color:#666">خطأ في الاتصال أثناء جلب المواضيع.</li>';
        }
    }

    async function loadDiscussionReplies(discussionId) {
        const ajax = getAjax();
        const container = document.querySelector(rootSelector);
        if (!ajax || !container || !discussionId) return;
        const messagesEl = container.querySelector('.ms-discussion-messages');
        const replyForm = container.querySelector('.ms-discussion-reply-form');
        if (!messagesEl) return;
        messagesEl.innerHTML = '<div style="color:#666;padding:12px">جاري التحميل...</div>';
        try {
            const params = new URLSearchParams({ action: 'ms_get_discussion_replies', discussion_id: discussionId });
            const resp = await fetch(ajax.ajax_url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: params });
            const json = await resp.json();
            if (json && json.success && json.data) {
                renderDiscussionMessages(messagesEl, json.data);
                if (replyForm) {
                    replyForm.style.display = 'block';
                    replyForm.dataset.currentDiscussion = discussionId;
                }
            } else {
                messagesEl.innerHTML = '<div style="color:#666;padding:12px">فشل جلب الردود.</div>';
                if (replyForm) replyForm.style.display = 'none';
            }
        } catch (err) {
            console.error('Standalone discussion replies error', err);
            messagesEl.innerHTML = '<div style="color:#666;padding:12px">خطأ في الاتصال أثناء جلب الردود.</div>';
        }
    }

    document.addEventListener('click', function(e) {
        const discTab = e.target.closest('.ms-tab-link[data-tab="discussions"]');
        if (discTab) {
            setTimeout(() => {
                const container = document.querySelector(rootSelector);
                if (container && container.dataset.buildingId) loadDiscussions(container.dataset.buildingId);
            }, 50);
        }
        const li = e.target.closest(rootSelector + ' .ms-discussions-list-ul li');
        if (li && li.dataset.discussionId) {
            li.parentElement.querySelectorAll('li').forEach(s => s.style.background = '');
            li.style.background = '#f8fafc';
            loadDiscussionReplies(li.dataset.discussionId);
        }
    });

    document.addEventListener('submit', function(e) {
        const form = e.target.closest(rootSelector + ' .ms-discussion-reply-form');
        if (!form) return;
        e.preventDefault();
        const ajax = getAjax();
        const discussionId = form.dataset.currentDiscussion;
        const textarea = form.querySelector('textarea[name="reply"]');
        if (!ajax || !discussionId || !textarea || !textarea.value.trim()) {
            alert('الرجاء كتابة الرد.');
            return;
        }
        const params = new URLSearchParams({
            action: 'ms_add_discussion_reply',
            discussion_id: discussionId,
            content: textarea.value.trim(),
            security: ajax.nonce || ''
        });
        fetch(ajax.ajax_url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: params })
            .then(r => r.json())
            .then(json => {
                if (json && json.success) {
                    textarea.value = '';
                    loadDiscussionReplies(discussionId);
                } else {
                    const msg = json && json.data && json.data.message ? json.data.message : 'فشل إرسال الرد.';
                    alert(msg);
                }
            })
            .catch(err => {
                console.error('Standalone add discussion reply error', err);
                alert('حدث خطأ أثناء إرسال الرد.');
            });
    });

    const start = function() {
        const container = document.querySelector(rootSelector);
        if (container && container.dataset.buildingId) {
            const activePanel = document.querySelector('.ms-tab-content.active#discussions');
            if (activePanel) loadDiscussions(container.dataset.buildingId);
        }
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
