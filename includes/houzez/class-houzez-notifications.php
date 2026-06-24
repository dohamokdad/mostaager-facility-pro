<?php
if (!defined('ABSPATH')) {
    exit;
}

class MFP_Houzez_Notifications {

    public static function init() {
        add_action('wp_footer', array(__CLASS__, 'inject_notification_script'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_assets'), 20);
    }

    public static function enqueue_assets() {
        if (!is_user_logged_in()) {
            return;
        }

        if (!wp_script_is('mostaager-dashboard-js', 'registered')) {
            return;
        }

        $dashboard_url = self::get_dashboard_url();

        wp_localize_script('mostaager-dashboard-js', 'mfpNotif', array(
            'rest_url' => rest_url('mfp/v1/notifications'),
            'nonce' => wp_create_nonce('wp_rest'),
            'dashboard_url' => esc_url_raw($dashboard_url),
            'user_id' => get_current_user_id(),
        ));
    }

    public static function inject_notification_script() {
        if (!is_user_logged_in()) {
            return;
        }
        ?>
        <style>
            .mfp-bell-badge {
                position: absolute;
                top: -4px;
                right: -4px;
                min-width: 18px;
                height: 18px;
                padding: 0 6px;
                border-radius: 999px;
                background: #ef4444;
                color: #fff;
                font-size: 0.72rem;
                line-height: 18px;
                text-align: center;
                z-index: 1001;
                box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.8);
            }
            .mfp-notifications-dropdown {
                position: absolute;
                top: calc(100% + 10px);
                right: 0;
                width: 320px;
                max-width: calc(100vw - 24px);
                background: #fff;
                border: 1px solid rgba(15, 23, 42, 0.12);
                border-radius: 14px;
                box-shadow: 0 20px 48px rgba(15, 23, 42, 0.16);
                z-index: 9999;
                direction: rtl;
                overflow: hidden;
            }
            .mfp-notif-item {
                padding: 12px 14px;
                border-bottom: 1px solid #f1f5f9;
                cursor: pointer;
                transition: background 0.2s ease;
            }
            .mfp-notif-item:hover {
                background: #f8fafc;
            }
            .mfp-tag {
                display: inline-block;
                margin-bottom: 8px;
                padding: 3px 8px;
                border-radius: 999px;
                background: #2563eb;
                color: #fff;
                font-size: 0.72rem;
                letter-spacing: 0.02em;
                text-transform: uppercase;
            }
            .mfp-notif-text {
                display: block;
                color: #0f172a;
                font-size: 0.92rem;
                margin-bottom: 6px;
            }
            .mfp-notif-meta {
                color: #64748b;
                font-size: 0.78rem;
            }
            .mfp-standalone-badge {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 8px 12px;
                border-radius: 999px;
                background: #2563eb;
                color: #fff;
                font-size: 0.85rem;
                cursor: pointer;
                text-decoration: none;
            }
        </style>
        <script type="text/javascript">
            (function () {
                var config = window.mfpNotif || {};
                if (!config.rest_url || !config.nonce) {
                    return;
                }

                var bellSelectors = [
                    '.fave_header_notification',
                    '.houzez-notification-bell',
                    '[class*="notification"][class*="bell"]',
                    '[class*="bell"][class*="notification"]',
                ];

                var notificationBell = null;
                for (var i = 0; i < bellSelectors.length; i++) {
                    notificationBell = document.querySelector(bellSelectors[i]);
                    if (notificationBell) {
                        break;
                    }
                }

                var badge = null;
                var dropdown = null;
                var notifications = [];

                var createBadge = function (count) {
                    if (!notificationBell) {
                        return;
                    }
                    if (!badge) {
                        badge = document.createElement('span');
                        badge.className = 'mfp-bell-badge';
                        badge.setAttribute('aria-label', 'Mostaager notifications');
                        notificationBell.style.position = notificationBell.style.position || 'relative';
                        notificationBell.appendChild(badge);
                    }
                    badge.textContent = count > 9 ? '9+' : String(count);
                    badge.style.display = count > 0 ? 'inline-flex' : 'none';
                };

                var formatRelativeTime = function (dateString) {
                    if (!dateString) {
                        return '';
                    }
                    var date = new Date(dateString);
                    if (isNaN(date.getTime())) {
                        return dateString;
                    }
                    var diff = Math.floor((Date.now() - date.getTime()) / 1000);
                    if (diff < 60) {
                        return 'الآن';
                    }
                    if (diff < 3600) {
                        return Math.floor(diff / 60) + ' دقيقة';
                    }
                    if (diff < 86400) {
                        return Math.floor(diff / 3600) + ' ساعة';
                    }
                    return Math.floor(diff / 86400) + ' يوم';
                };

                var getTabForType = function (type) {
                    if (!type) {
                        return 'notifications';
                    }
                    if (type.indexOf('invoice') !== -1) {
                        return 'invoices';
                    }
                    if (type.indexOf('maintenance') !== -1) {
                        return 'maintenance';
                    }
                    return 'notifications';
                };

                var closeDropdown = function () {
                    if (dropdown && dropdown.parentNode) {
                        dropdown.parentNode.removeChild(dropdown);
                    }
                    dropdown = null;
                };

                var patchNotificationRead = function (notificationId, type) {
                    fetch(config.rest_url + '/' + notificationId + '/read', {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': config.nonce,
                        },
                        credentials: 'same-origin',
                    }).then(function () {
                        var tab = getTabForType(type);
                        var redirectUrl = config.dashboard_url || window.location.href;
                        redirectUrl += (redirectUrl.indexOf('?') === -1 ? '?' : '&') + 'tab=' + tab;
                        window.location.href = redirectUrl;
                    }).catch(function () {
                        var tab = getTabForType(type);
                        var redirectUrl = config.dashboard_url || window.location.href;
                        redirectUrl += (redirectUrl.indexOf('?') === -1 ? '?' : '&') + 'tab=' + tab;
                        window.location.href = redirectUrl;
                    });
                };

                var buildDropdown = function (items) {
                    if (dropdown) {
                        closeDropdown();
                    }

                    dropdown = document.createElement('div');
                    dropdown.className = 'mfp-notifications-dropdown';
                    dropdown.setAttribute('role', 'menu');

                    if (!items || !items.length) {
                        var empty = document.createElement('div');
                        empty.className = 'mfp-notif-item';
                        empty.textContent = 'لا توجد إشعارات جديدة.';
                        dropdown.appendChild(empty);
                    } else {
                        items.slice(0, 5).forEach(function (item) {
                            var row = document.createElement('div');
                            row.className = 'mfp-notif-item';
                            row.addEventListener('click', function () {
                                patchNotificationRead(item.id, item.type);
                            });

                            var tag = document.createElement('span');
                            tag.className = 'mfp-tag';
                            tag.textContent = 'Mostaager';
                            row.appendChild(tag);

                            var message = document.createElement('span');
                            message.className = 'mfp-notif-text';
                            message.textContent = item.message || 'إشعار من Mostaager';
                            row.appendChild(message);

                            var meta = document.createElement('span');
                            meta.className = 'mfp-notif-meta';
                            meta.textContent = formatRelativeTime(item.created_at || '');
                            row.appendChild(meta);

                            dropdown.appendChild(row);
                        });
                    }

                    if (notificationBell && notificationBell.parentNode) {
                        notificationBell.parentNode.appendChild(dropdown);
                    } else {
                        document.body.appendChild(dropdown);
                    }
                };

                var fetchNotifications = function () {
                    fetch(config.rest_url, {
                        method: 'GET',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': config.nonce,
                        },
                        credentials: 'same-origin',
                    }).then(function (response) {
                        return response.json();
                    }).then(function (data) {
                        if (!data || !data.success) {
                            return;
                        }
                        notifications = data.data || [];
                        var unreadCount = notifications.filter(function (item) {
                            return !item.read && item.is_read !== 1;
                        }).length;
                        createBadge(unreadCount);
                    }).catch(function () {
                        createBadge(0);
                    });
                };

                var ensureStandaloneBadge = function () {
                    var userHeader = document.querySelector('header, .header, .dashboard-header, .topbar');
                    if (!userHeader) {
                        return;
                    }
                    var container = document.createElement('div');
                    container.className = 'mfp-standalone-badge';
                    container.textContent = 'Mostaager';
                    container.addEventListener('click', function (event) {
                        event.preventDefault();
                        if (dropdown) {
                            closeDropdown();
                            return;
                        }
                        buildDropdown(notifications);
                    });
                    userHeader.appendChild(container);
                };

                var bindBellClick = function () {
                    if (!notificationBell) {
                        return;
                    }
                    notificationBell.style.position = notificationBell.style.position || 'relative';
                    notificationBell.addEventListener('click', function (event) {
                        event.preventDefault();
                        event.stopPropagation();
                        if (dropdown) {
                            closeDropdown();
                            return;
                        }
                        buildDropdown(notifications);
                    });
                };

                document.addEventListener('click', function (event) {
                    if (dropdown && !dropdown.contains(event.target) && notificationBell && !notificationBell.contains(event.target)) {
                        closeDropdown();
                    }
                });

                window.addEventListener('DOMContentLoaded', function () {
                    fetchNotifications();
                    if (notificationBell) {
                        bindBellClick();
                    } else {
                        ensureStandaloneBadge();
                    }
                });
            })();
        </script>
        <?php
    }

    private static function get_dashboard_url() {
        $page_id = intval(get_option('mfp_dashboard_page_id'));
        if ($page_id) {
            $permalink = get_permalink($page_id);
            if ($permalink) {
                return untrailingslashit($permalink);
            }
        }

        $shortcodes = array('agent_dashboard_v4', 'rent_dashboard_v4', 'building_dashboard');
        foreach ($shortcodes as $shortcode) {
            $pages = get_posts(array(
                'post_type' => 'page',
                'post_status' => 'publish',
                's' => $shortcode,
                'posts_per_page' => 1,
            ));
            if (!empty($pages)) {
                return untrailingslashit(get_permalink($pages[0]->ID));
            }
        }

        return untrailingslashit(home_url('/dashboard/'));
    }
}
