/* dashboard-tabs.js - lightweight companion to dashboard.js
 * Only provides MostaagerQA test helpers.
 * All tab initialization and data fetching is handled by dashboard.js.
 */

// Minimal MostaagerQA helpers (avoid redefining when dashboard.js provides them)
if (!window.MostaagerQA) {
    window.MostaagerQA = {
        ajaxUrl: (typeof MostaagerAjax !== 'undefined' && MostaagerAjax.ajax_url) ? MostaagerAjax.ajax_url : null,
        nonce: (typeof MostaagerAjax !== 'undefined' && MostaagerAjax.nonce) ? MostaagerAjax.nonce : null,
        markNotificationsRead: async function() {
            if (!this.ajaxUrl || !this.nonce) throw new Error('MostaagerAjax is not loaded.');
            var params = new URLSearchParams({ action: 'ms_mark_notifications_read', security: this.nonce });
            var response = await fetch(this.ajaxUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, credentials: 'same-origin', body: params });
            return response.json();
        }
    };
    console.info('MostaagerQA helpers (minimal) available.');
} else {
    console.info('MostaagerQA already defined by dashboard.js; keeping existing definition.');
}
