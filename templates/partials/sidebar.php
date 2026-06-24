<?php
if (!defined('ABSPATH')) {
    exit;
}

$menu_items = isset($ms_dashboard_menu_items) && is_array($ms_dashboard_menu_items) ? $ms_dashboard_menu_items : array();
?>
<aside class="ms-sidebar">
    <div class="ms-sidebar-title">القائمة</div>
    <ul class="ms-sidebar-menu">
        <?php foreach ($menu_items as $item) : ?>
            <?php
            $label = isset($item['label']) ? $item['label'] : '';
            $href = isset($item['href']) ? $item['href'] : '#';
            $tab = isset($item['data_tab']) ? $item['data_tab'] : '';
            $external = !empty($item['external']);
            $icon = isset($item['icon']) ? $item['icon'] : '';
            $badge = isset($item['badge']) ? $item['badge'] : '';
            $active = !empty($item['active']);
            $attributes = '';
            if ($tab) {
                $attributes .= ' data-tab="' . esc_attr($tab) . '"';
                if (!$external) {
                    $href = '#' . esc_attr($tab);
                }
            }
            if ($external) {
                $attributes .= ' data-external="true"';
            }
            $link_classes = 'ms-tab-link' . ($active ? ' active' : '');
            ?>
            <li class="<?php echo esc_attr($active ? 'active' : ''); ?>">
                <a href="<?php echo esc_url($href); ?>" class="<?php echo esc_attr($link_classes); ?>"<?php echo $attributes; ?>>
                    <span class="ms-menu-item-main">
                        <?php if ($icon) : ?>
                            <span class="ms-menu-icon"><?php echo esc_html($icon); ?></span>
                        <?php endif; ?>
                        <span class="ms-menu-label"><?php echo esc_html($label); ?></span>
                    </span>
                    <?php if ($badge) : ?>
                        <span class="ms-menu-badge"><?php echo esc_html($badge); ?></span>
                    <?php endif; ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</aside>
