(function () {
    if (typeof window.msHouzezBell === 'undefined') {
        return;
    }

    var config = window.msHouzezBell;
    var ajaxUrl = config.ajax_url;
    var csrf = config.nonce;
    var markReadCsrf = config.mark_read_nonce;
    var dashboardUrl = config.dashboard_url || window.location.origin;
    var bellElement = document.querySelector('.fave-notifications');

    if (!bellElement) {
        return;
    }

    var badge = document.createElement('span');
    badge.className = 'ms-houzez-bell-badge';
    badge.style.cssText = 'position:absolute;top:8px;right:8px;min-width:18px;height:18px;line-height:18px;font-size:12px;border-radius:9px;background:#ef4444;color:#fff;text-align:center;padding:0 6px;display:none;z-index:9999;';
    bellElement.style.position = 'relative';
    bellElement.appendChild(badge);

    var dropdown = document.createElement('div');
    dropdown.className = 'ms-houzez-bell-dropdown';
    dropdown.style.cssText = 'display:none;position:absolute;top:100%;right:0;min-width:320px;max-width:420px;background:#fff;color:#111;border:1px solid rgba(0,0,0,.12);box-shadow:0 14px 45px rgba(15,23,42,.15);border-radius:14px;padding:12px;z-index:99999;';
    bellElement.appendChild(dropdown);

    var notifications = [];
    var dropdownOpen = false;

    function formatDate(value) {
        try {
            var date = new Date(value);
            return isNaN(date.getTime()) ? value : date.toLocaleString('ar-EG');
        } catch (e) {
            return value;
        }
    }

    function renderNotifications() {
        dropdown.innerHTML = '';

        if (!notifications.length) {
            dropdown.innerHTML = '<div style="padding:14px;color:#334155;">لا توجد إشعارات جديدة من Mostaager.</div>';
            badge.style.display = 'none';
            return;
        }

        badge.textContent = notifications.length;
        badge.style.display = 'inline-flex';

        notifications.forEach(function (item) {
            var itemWrapper = document.createElement('div');
            itemWrapper.className = 'ms-houzez-notification-item';
            itemWrapper.style.cssText = 'margin-bottom:10px;padding:10px 10px 8px;border-bottom:1px solid #f1f5f9;cursor:pointer;';
            itemWrapper.dataset.notificationId = item.id;

            var title = document.createElement('div');
            title.style.cssText = 'font-size:13px;color:#0f172a;margin-bottom:6px;';
            title.textContent = item.message || item.title || 'إشعار Mostaager';
            itemWrapper.appendChild(title);

            var meta = document.createElement('div');
            meta.style.cssText = 'display:flex;justify-content:space-between;align-items:center;font-size:12px;color:#64748b;';
            var typeLabel = document.createElement('span');
            typeLabel.textContent = item.type ? 'Mostaager / ' + item.type : 'Mostaager';
            meta.appendChild(typeLabel);

            var timeLabel = document.createElement('span');
            timeLabel.textContent = formatDate(item.created_at || item.createdAt || '');
            meta.appendChild(timeLabel);
            itemWrapper.appendChild(meta);

            var actions = document.createElement('div');
            actions.style.cssText = 'margin-top:8px;display:flex;justify-content:space-between;align-items:center;gap:8px;';

            var openLink = document.createElement('a');
            openLink.textContent = 'عرض';
            openLink.href = item.url || (dashboardUrl + '#tab-invoices');
            openLink.style.cssText = 'color:#2563eb;font-size:12px;text-decoration:none;';
            openLink.target = '_blank';
            actions.appendChild(openLink);

            var markRead = document.createElement('button');
            markRead.type = 'button';
            markRead.textContent = 'وضع كمقروء';
            markRead.className = 'ms-mark-houzez-notification-read';
            markRead.style.cssText = 'background:#e2e8f0;border:none;border-radius:8px;padding:6px 10px;color:#0f172a;cursor:pointer;font-size:12px;';
            markRead.dataset.notificationId = item.id;
            actions.appendChild(markRead);

            itemWrapper.appendChild(actions);
            dropdown.appendChild(itemWrapper);
        });
    }

    function toggleDropdown() {
        dropdownOpen = !dropdownOpen;
        dropdown.style.display = dropdownOpen ? 'block' : 'none';
    }

    function fetchNotifications() {
        var formData = new FormData();
        formData.append('action', 'ms_get_houzez_notifications');
        formData.append('security', csrf);

        fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData,
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (payload) {
                if (!payload || !payload.success || !Array.isArray(payload.data.notifications)) {
                    return;
                }
                notifications = payload.data.notifications;
                renderNotifications();
            })
            .catch(function () {
                // ignore polling errors
            });
    }

    bellElement.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        toggleDropdown();
    });

    document.body.addEventListener('click', function (event) {
        if (!bellElement.contains(event.target) && dropdownOpen) {
            toggleDropdown();
        }
    });

    document.body.addEventListener('click', function (event) {
        var target = event.target;
        if (target && target.matches('.ms-mark-houzez-notification-read')) {
            event.preventDefault();
            var notificationId = target.dataset.notificationId;
            if (!notificationId) {
                return;
            }

            var formData = new FormData();
            formData.append('action', 'ms_mark_houzez_notifications_read');
            formData.append('notification_id', notificationId);
            formData.append('security', markReadCsrf);

            fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData,
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (payload) {
                    if (payload && payload.success) {
                        notifications = notifications.filter(function (item) {
                            return String(item.id) !== String(notificationId);
                        });
                        renderNotifications();
                    }
                })
                .catch(function () {
                    // ignore
                });
        }
    });

    fetchNotifications();
    setInterval(fetchNotifications, 60000);
})();
