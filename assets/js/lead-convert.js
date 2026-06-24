(function () {
    if (typeof window.msLeadConvert === 'undefined') {
        return;
    }

    var config = window.msLeadConvert;
    var ajaxUrl = config.ajax_url;
    var messages = config.messages || {};

    function showConfirmation() {
        return window.confirm(messages.confirm || 'هل أنت متأكد من تحويل هذا الـ Lead إلى مستأجر؟');
    }

    function updateLeadCard(button, data) {
        var container = button.closest('.inside') || button.parentNode;
        if (!container) {
            return;
        }
        container.innerHTML = '<div style="padding:14px;border:1px solid #d1d5db;border-radius:10px;background:#f8fafc;">' +
            '<p style="margin:0 0 10px;font-weight:700;color:#111;">تم تحويل الـ Lead إلى مستأجر بنجاح.</p>' +
            '<p style="margin:0;">معرف المستأجر: ' + parseInt(data.tenant_id, 10) + '</p>' +
            '</div>';
    }

    function sendConvertRequest(button) {
        var leadId = button.dataset.leadId;
        var propertyId = button.dataset.propertyId;
        var security = button.dataset.security;

        if (!leadId || !security) {
            return;
        }

        if (!showConfirmation()) {
            return;
        }

        button.disabled = true;
        button.textContent = messages.sending || 'جاري التحويل...';

        var formData = new FormData();
        formData.append('action', 'ms_convert_lead_to_tenant');
        formData.append('lead_id', leadId);
        formData.append('property_id', propertyId || '');
        formData.append('security', security);

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
                    updateLeadCard(button, payload.data);
                } else {
                    button.disabled = false;
                    button.textContent = messages.error || 'حدث خطأ أثناء التحويل.';
                    setTimeout(function () {
                        button.textContent = 'تحويل إلى مستأجر في Mostaager';
                    }, 3000);
                }
            })
            .catch(function () {
                button.disabled = false;
                button.textContent = messages.error || 'حدث خطأ أثناء التحويل.';
                setTimeout(function () {
                    button.textContent = 'تحويل إلى مستأجر في Mostaager';
                }, 3000);
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.body.addEventListener('click', function (event) {
            var button = event.target.closest('.ms-convert-lead-button');
            if (!button) {
                return;
            }
            event.preventDefault();
            sendConvertRequest(button);
        });
    });
})();
