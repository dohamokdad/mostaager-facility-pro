<?php

if (!defined('ABSPATH')) exit;

function ms_stat_card($title, $value, $icon = '') {

    ob_start();
    ?>

    <div class="ms-card">

        <div class="ms-card-top">

            <div class="ms-card-icon">
                <?php echo esc_html($icon); ?>
            </div>

            <h3><?php echo esc_html($title); ?></h3>

        </div>

        <div class="ms-card-value">
            <?php echo esc_html($value); ?>
        </div>

    </div>

    <?php
    return ob_get_clean();

}
