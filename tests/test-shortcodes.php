<?php

class Mostaager_Shortcodes_Test extends WP_UnitTestCase
{
    public function test_ms_dashboard_shortcode_exists()
    {
        $this->assertTrue(shortcode_exists('ms_dashboard'), 'The ms_dashboard shortcode should be registered.');
    }

    public function test_ms_dashboard_block_is_registered()
    {
        if (!function_exists('register_block_type')) {
            $this->markTestSkipped('Block API is not available.');
        }

        $this->assertTrue(has_block('mostaager-facility-pro/ms-dashboard') || has_block('mostaager-facility-pro/ms-dashboard'), 'The Mostaager dashboard block should be registered.');
    }
}
