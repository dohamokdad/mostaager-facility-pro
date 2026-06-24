<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_footer', 'ms_inject_houzez_bell_badge');

function ms_inject_houzez_bell_badge()
{
    if (!is_user_logged_in()) {
        return;
    }

    $user_id = get_current_user_id();
    $count = function_exists('ms_get_unread_notifications_count') ? ms_get_unread_notifications_count($user_id) : 0;
    ?>
    <script type="text/javascript">
        window.MostaagerHouzezBell = {
            unreadCount: <?php echo intval($count); ?>
        };
    </script>
    <?php
}
