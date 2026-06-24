<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('elementor/elements/categories_registered', 'ms_register_mostaager_elementor_category');
add_action('elementor/widgets/register', 'ms_register_mostaager_elementor_widget');

function ms_register_mostaager_elementor_category($elements_manager)
{
    if (!method_exists($elements_manager, 'add_category')) {
        return;
    }

    $elements_manager->add_category(
        'mostaager',
        array(
            'title' => 'Mostaager',
            'icon' => 'fa fa-building',
        )
    );
}

function ms_register_mostaager_elementor_widget($widgets_manager)
{
    if (!class_exists('\Elementor\Widget_Base')) {
        return;
    }

    if (class_exists('Mostaager_Agent_Dashboard_Widget')) {
        $widgets_manager->register(new Mostaager_Agent_Dashboard_Widget());
    }
    if (class_exists('Mostaager_Owner_Dashboard_Widget')) {
        $widgets_manager->register(new Mostaager_Owner_Dashboard_Widget());
    }
    if (class_exists('Mostaager_Tenant_Dashboard_Widget')) {
        $widgets_manager->register(new Mostaager_Tenant_Dashboard_Widget());
    }
    if (class_exists('Mostaager_Building_Dashboard_Widget')) {
        $widgets_manager->register(new Mostaager_Building_Dashboard_Widget());
    }
}

if (!class_exists('Mostaager_Agent_Dashboard_Widget')) {
    class Mostaager_Agent_Dashboard_Widget extends \Elementor\Widget_Base
    {
        public function get_name()
        {
            return 'ms_agent_dashboard';
        }

        public function get_title()
        {
            return 'لوحة الوكيل';
        }

        public function get_icon()
        {
            return 'eicon-dashboard';
        }

        public function get_categories()
        {
            return array('mostaager');
        }

        public function get_keywords()
        {
            return array('dashboard', 'agent', 'agent board', 'mostaager');
        }

        protected function _register_controls()
        {
            $this->start_controls_section(
                'section_tabs',
                array(
                    'label' => 'التبويبات',
                )
            );

            $this->add_control(
                'show_properties',
                array(
                    'label' => 'العقارات',
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => 'عرض',
                    'label_off' => 'إخفاء',
                    'return_value' => 'yes',
                    'default' => 'yes',
                )
            );

            $this->add_control(
                'show_maintenance',
                array(
                    'label' => 'الصيانة',
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => 'عرض',
                    'label_off' => 'إخفاء',
                    'return_value' => 'yes',
                    'default' => 'yes',
                )
            );

            $this->add_control(
                'show_invoices',
                array(
                    'label' => 'الفواتير',
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => 'عرض',
                    'label_off' => 'إخفاء',
                    'return_value' => 'yes',
                    'default' => 'yes',
                )
            );

            $this->add_control(
                'show_notifications',
                array(
                    'label' => 'الإشعارات',
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => 'عرض',
                    'label_off' => 'إخفاء',
                    'return_value' => 'yes',
                    'default' => 'yes',
                )
            );

            $this->add_control(
                'show_analytics',
                array(
                    'label' => 'الإحصائيات',
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => 'عرض',
                    'label_off' => 'إخفاء',
                    'return_value' => 'yes',
                    'default' => 'yes',
                )
            );

            $this->end_controls_section();

            $this->start_controls_section(
                'section_visibility',
                array(
                    'label' => 'إظهار التبويبات',
                )
            );

            $this->add_control(
                'show_add_property',
                array(
                    'label' => 'إضافة عقار',
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => 'عرض',
                    'label_off' => 'إخفاء',
                    'return_value' => 'yes',
                    'default' => 'yes',
                )
            );

            $this->add_control(
                'show_subscriptions',
                array(
                    'label' => 'الاشتراكات',
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => 'عرض',
                    'label_off' => 'إخفاء',
                    'return_value' => 'yes',
                    'default' => 'yes',
                )
            );

            $this->add_control(
                'show_discussions',
                array(
                    'label' => 'المناقشات',
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => 'عرض',
                    'label_off' => 'إخفاء',
                    'return_value' => 'yes',
                    'default' => 'yes',
                )
            );

            $this->end_controls_section();

            $this->start_controls_section(
                'section_style',
                array(
                    'label' => 'الألوان',
                )
            );

            $this->add_control(
                'sidebar_background',
                array(
                    'label' => 'لون خلفية الشريط الجانبي',
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'default' => '#f8fafc',
                )
            );

            $this->end_controls_section();

            $this->start_controls_section(
                'section_language',
                array(
                    'label' => 'اللغة',
                )
            );

            $this->add_control(
                'language',
                array(
                    'label' => 'اختر اللغة',
                    'type' => \Elementor\Controls_Manager::SELECT,
                    'options' => array(
                        'ar' => 'عربي',
                        'en' => 'English',
                    ),
                    'default' => 'ar',
                )
            );

            $this->end_controls_section();
        }

        protected function render()
        {
            $settings = $this->get_settings_for_display();
            $dashboard_settings = array(
                'tabs' => array(
                    'listings' => !empty($settings['show_properties']) ? 'show' : 'hide',
                    'maintenance' => !empty($settings['show_maintenance']) ? 'show' : 'hide',
                    'invoices' => !empty($settings['show_invoices']) ? 'show' : 'hide',
                    'notifications' => !empty($settings['show_notifications']) ? 'show' : 'hide',
                    'analytics' => !empty($settings['show_analytics']) ? 'show' : 'hide',
                ),
            );

            $dashboard_settings['tabs']['add-property'] = !empty($settings['show_add_property']) ? 'show' : 'hide';
            $dashboard_settings['tabs']['subscriptions'] = !empty($settings['show_subscriptions']) ? 'show' : 'hide';
            $dashboard_settings['tabs']['discussions'] = !empty($settings['show_discussions']) ? 'show' : 'hide';

            add_filter('ms_agent_dashboard_settings', function ($existing) use ($dashboard_settings) {
                return array_merge((array) $existing, $dashboard_settings);
            });

            echo '<div class="ms-elementor-agent-dashboard" style="background:' . esc_attr($settings['sidebar_background'] ?? '#f8fafc') . ';">';
            echo do_shortcode('[agent_dashboard_v4]');
            echo '</div>';
        }
    }
}

if (!class_exists('Mostaager_Owner_Dashboard_Widget')) {
    class Mostaager_Owner_Dashboard_Widget extends \Elementor\Widget_Base
    {
        public function get_name()
        {
            return 'ms_owner_dashboard';
        }

        public function get_title()
        {
            return 'لوحة المالك';
        }

        public function get_icon()
        {
            return 'eicon-building';
        }

        public function get_categories()
        {
            return array('mostaager');
        }

        public function get_keywords()
        {
            return array('dashboard', 'owner', 'property owner', 'mostaager');
        }

        protected function _register_controls()
        {
            $this->start_controls_section(
                'section_tabs',
                array(
                    'label' => 'التبويبات',
                )
            );

            $this->add_control(
                'show_overview',
                array(
                    'label' => 'نظرة عامة',
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => 'عرض',
                    'label_off' => 'إخفاء',
                    'return_value' => 'yes',
                    'default' => 'yes',
                )
            );

            $this->add_control(
                'show_properties',
                array(
                    'label' => 'العقارات',
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => 'عرض',
                    'label_off' => 'إخفاء',
                    'return_value' => 'yes',
                    'default' => 'yes',
                )
            );

            $this->add_control(
                'show_invoices',
                array(
                    'label' => 'الفواتير',
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => 'عرض',
                    'label_off' => 'إخفاء',
                    'return_value' => 'yes',
                    'default' => 'yes',
                )
            );

            $this->add_control(
                'show_maintenance',
                array(
                    'label' => 'الصيانة',
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => 'عرض',
                    'label_off' => 'إخفاء',
                    'return_value' => 'yes',
                    'default' => 'yes',
                )
            );

            $this->add_control(
                'show_wallet',
                array(
                    'label' => 'المحفظة',
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => 'عرض',
                    'label_off' => 'إخفاء',
                    'return_value' => 'yes',
                    'default' => 'yes',
                )
            );

            $this->add_control(
                'show_notifications',
                array(
                    'label' => 'الإشعارات',
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => 'عرض',
                    'label_off' => 'إخفاء',
                    'return_value' => 'yes',
                    'default' => 'yes',
                )
            );

            $this->end_controls_section();

            $this->start_controls_section(
                'section_style',
                array(
                    'label' => 'الألوان',
                )
            );

            $this->add_control(
                'sidebar_background',
                array(
                    'label' => 'لون خلفية الشريط الجانبي',
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'default' => '#f8fafc',
                )
            );

            $this->end_controls_section();

            $this->start_controls_section(
                'section_language',
                array(
                    'label' => 'اللغة',
                )
            );

            $this->add_control(
                'language',
                array(
                    'label' => 'اختر اللغة',
                    'type' => \Elementor\Controls_Manager::SELECT,
                    'options' => array(
                        'ar' => 'عربي',
                        'en' => 'English',
                    ),
                    'default' => 'ar',
                )
            );

            $this->end_controls_section();
        }

        protected function render()
        {
            $settings = $this->get_settings_for_display();
            $dashboard_settings = array(
                'tabs' => array(
                    'overview' => !empty($settings['show_overview']) ? 'show' : 'hide',
                    'properties' => !empty($settings['show_properties']) ? 'show' : 'hide',
                    'invoices' => !empty($settings['show_invoices']) ? 'show' : 'hide',
                    'maintenance' => !empty($settings['show_maintenance']) ? 'show' : 'hide',
                    'wallet' => !empty($settings['show_wallet']) ? 'show' : 'hide',
                    'notifications' => !empty($settings['show_notifications']) ? 'show' : 'hide',
                ),
            );

            add_filter('ms_owner_dashboard_settings', function ($existing) use ($dashboard_settings) {
                return array_merge((array) $existing, $dashboard_settings);
            });

            echo '<div class="ms-elementor-owner-dashboard" style="background:' . esc_attr($settings['sidebar_background'] ?? '#f8fafc') . ';">';
            echo do_shortcode('[owner_dashboard_v4]');
            echo '</div>';
        }
    }
}

if (!class_exists('Mostaager_Tenant_Dashboard_Widget')) {
    class Mostaager_Tenant_Dashboard_Widget extends \Elementor\Widget_Base
    {
        public function get_name()
        {
            return 'ms_tenant_dashboard';
        }

        public function get_title()
        {
            return 'لوحة المستأجر';
        }

        public function get_icon()
        {
            return 'eicon-person';
        }

        public function get_categories()
        {
            return array('mostaager');
        }

        public function get_keywords()
        {
            return array('dashboard', 'tenant', 'renter', 'mostaager');
        }

        protected function _register_controls()
        {
            $this->start_controls_section(
                'section_tabs',
                array(
                    'label' => 'التبويبات',
                )
            );

            $this->add_control(
                'show_overview',
                array(
                    'label' => 'نظرة عامة',
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => 'عرض',
                    'label_off' => 'إخفاء',
                    'return_value' => 'yes',
                    'default' => 'yes',
                )
            );

            $this->add_control(
                'show_invoices',
                array(
                    'label' => 'الفواتير',
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => 'عرض',
                    'label_off' => 'إخفاء',
                    'return_value' => 'yes',
                    'default' => 'yes',
                )
            );

            $this->add_control(
                'show_meters',
                array(
                    'label' => 'العدادات',
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => 'عرض',
                    'label_off' => 'إخفاء',
                    'return_value' => 'yes',
                    'default' => 'yes',
                )
            );

            $this->add_control(
                'show_bookings',
                array(
                    'label' => 'الحجوزات',
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => 'عرض',
                    'label_off' => 'إخفاء',
                    'return_value' => 'yes',
                    'default' => 'yes',
                )
            );

            $this->add_control(
                'show_maintenance',
                array(
                    'label' => 'الصيانة',
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => 'عرض',
                    'label_off' => 'إخفاء',
                    'return_value' => 'yes',
                    'default' => 'yes',
                )
            );

            $this->add_control(
                'show_wallet',
                array(
                    'label' => 'المحفظة',
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => 'عرض',
                    'label_off' => 'إخفاء',
                    'return_value' => 'yes',
                    'default' => 'yes',
                )
            );

            $this->add_control(
                'show_notifications',
                array(
                    'label' => 'الإشعارات',
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => 'عرض',
                    'label_off' => 'إخفاء',
                    'return_value' => 'yes',
                    'default' => 'yes',
                )
            );

            $this->end_controls_section();

            $this->start_controls_section(
                'section_style',
                array(
                    'label' => 'الألوان',
                )
            );

            $this->add_control(
                'sidebar_background',
                array(
                    'label' => 'لون خلفية الشريط الجانبي',
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'default' => '#f8fafc',
                )
            );

            $this->end_controls_section();

            $this->start_controls_section(
                'section_language',
                array(
                    'label' => 'اللغة',
                )
            );

            $this->add_control(
                'language',
                array(
                    'label' => 'اختر اللغة',
                    'type' => \Elementor\Controls_Manager::SELECT,
                    'options' => array(
                        'ar' => 'عربي',
                        'en' => 'English',
                    ),
                    'default' => 'ar',
                )
            );

            $this->end_controls_section();
        }

        protected function render()
        {
            $settings = $this->get_settings_for_display();
            $dashboard_settings = array(
                'tabs' => array(
                    'overview' => !empty($settings['show_overview']) ? 'show' : 'hide',
                    'invoices' => !empty($settings['show_invoices']) ? 'show' : 'hide',
                    'meters' => !empty($settings['show_meters']) ? 'show' : 'hide',
                    'bookings' => !empty($settings['show_bookings']) ? 'show' : 'hide',
                    'maintenance' => !empty($settings['show_maintenance']) ? 'show' : 'hide',
                    'wallet' => !empty($settings['show_wallet']) ? 'show' : 'hide',
                    'notifications' => !empty($settings['show_notifications']) ? 'show' : 'hide',
                ),
            );

            add_filter('ms_tenant_dashboard_settings', function ($existing) use ($dashboard_settings) {
                return array_merge((array) $existing, $dashboard_settings);
            });

            echo '<div class="ms-elementor-tenant-dashboard" style="background:' . esc_attr($settings['sidebar_background'] ?? '#f8fafc') . ';">';
            echo do_shortcode('[rent_dashboard_v4]');
            echo '</div>';
        }
    }
}

if (!class_exists('Mostaager_Building_Dashboard_Widget')) {
    class Mostaager_Building_Dashboard_Widget extends \Elementor\Widget_Base
    {
        public function get_name()
        {
            return 'ms_building_dashboard';
        }

        public function get_title()
        {
            return 'لوحة مدير المبنى';
        }

        public function get_icon()
        {
            return 'eicon-building';
        }

        public function get_categories()
        {
            return array('mostaager');
        }

        public function get_keywords()
        {
            return array('dashboard', 'building manager', 'facilities', 'mostaager');
        }

        protected function _register_controls()
        {
            $this->start_controls_section(
                'section_tabs',
                array(
                    'label' => 'التبويبات',
                )
            );

            $this->add_control(
                'show_overview',
                array(
                    'label' => 'نظرة عامة',
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => 'عرض',
                    'label_off' => 'إخفاء',
                    'return_value' => 'yes',
                    'default' => 'yes',
                )
            );

            $this->add_control(
                'show_maintenance',
                array(
                    'label' => 'الصيانة',
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => 'عرض',
                    'label_off' => 'إخفاء',
                    'return_value' => 'yes',
                    'default' => 'yes',
                )
            );

            $this->add_control(
                'show_wallet',
                array(
                    'label' => 'المحفظة',
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => 'عرض',
                    'label_off' => 'إخفاء',
                    'return_value' => 'yes',
                    'default' => 'yes',
                )
            );

            $this->add_control(
                'show_expenses',
                array(
                    'label' => 'المصروفات',
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => 'عرض',
                    'label_off' => 'إخفاء',
                    'return_value' => 'yes',
                    'default' => 'yes',
                )
            );

            $this->add_control(
                'show_bookings',
                array(
                    'label' => 'الحجوزات',
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => 'عرض',
                    'label_off' => 'إخفاء',
                    'return_value' => 'yes',
                    'default' => 'yes',
                )
            );

            $this->add_control(
                'show_discussions',
                array(
                    'label' => 'المناقشات',
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => 'عرض',
                    'label_off' => 'إخفاء',
                    'return_value' => 'yes',
                    'default' => 'yes',
                )
            );

            $this->add_control(
                'show_invoices',
                array(
                    'label' => 'الفواتير',
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => 'عرض',
                    'label_off' => 'إخفاء',
                    'return_value' => 'yes',
                    'default' => 'yes',
                )
            );

            $this->add_control(
                'show_collection',
                array(
                    'label' => 'التحصيل',
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => 'عرض',
                    'label_off' => 'إخفاء',
                    'return_value' => 'yes',
                    'default' => 'yes',
                )
            );

            $this->add_control(
                'show_notifications',
                array(
                    'label' => 'الإشعارات',
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => 'عرض',
                    'label_off' => 'إخفاء',
                    'return_value' => 'yes',
                    'default' => 'yes',
                )
            );

            $this->end_controls_section();

            $this->start_controls_section(
                'section_style',
                array(
                    'label' => 'الألوان',
                )
            );

            $this->add_control(
                'sidebar_background',
                array(
                    'label' => 'لون خلفية الشريط الجانبي',
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'default' => '#f8fafc',
                )
            );

            $this->end_controls_section();

            $this->start_controls_section(
                'section_language',
                array(
                    'label' => 'اللغة',
                )
            );

            $this->add_control(
                'language',
                array(
                    'label' => 'اختر اللغة',
                    'type' => \Elementor\Controls_Manager::SELECT,
                    'options' => array(
                        'ar' => 'عربي',
                        'en' => 'English',
                    ),
                    'default' => 'ar',
                )
            );

            $this->end_controls_section();
        }

        protected function render()
        {
            $settings = $this->get_settings_for_display();
            $dashboard_settings = array(
                'tabs' => array(
                    'overview' => !empty($settings['show_overview']) ? 'show' : 'hide',
                    'maintenance' => !empty($settings['show_maintenance']) ? 'show' : 'hide',
                    'wallet' => !empty($settings['show_wallet']) ? 'show' : 'hide',
                    'expenses' => !empty($settings['show_expenses']) ? 'show' : 'hide',
                    'bookings' => !empty($settings['show_bookings']) ? 'show' : 'hide',
                    'discussions' => !empty($settings['show_discussions']) ? 'show' : 'hide',
                    'invoices' => !empty($settings['show_invoices']) ? 'show' : 'hide',
                    'collection' => !empty($settings['show_collection']) ? 'show' : 'hide',
                    'notifications' => !empty($settings['show_notifications']) ? 'show' : 'hide',
                ),
            );

            add_filter('ms_building_dashboard_settings', function ($existing) use ($dashboard_settings) {
                return array_merge((array) $existing, $dashboard_settings);
            });

            echo '<div class="ms-elementor-building-dashboard" style="background:' . esc_attr($settings['sidebar_background'] ?? '#f8fafc') . ';">';
            echo do_shortcode('[manager_dashboard_v4]');
            echo '</div>';
        }
    }
}
