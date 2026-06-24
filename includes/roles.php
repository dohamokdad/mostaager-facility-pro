<?php
if (!defined('ABSPATH')) exit;

/**
 * Register Mostaager custom roles and capabilities
 */
function ms_register_facility_roles()
{
    $roles = array(
        'owner' => array(
            'label' => __('مالك', 'mostaager-facility-pro'),
            'capabilities' => array(
                'read' => true,
                'edit_posts' => false,
                'ms_view_dashboard' => true,
            ),
        ),
        'tenant' => array(
            'label' => __('مستأجر', 'mostaager-facility-pro'),
            'capabilities' => array(
                'read' => true,
                'edit_posts' => false,
                'ms_view_dashboard' => true,
            ),
        ),
        'agent' => array(
            'label' => __('وسيط', 'mostaager-facility-pro'),
            'capabilities' => array(
                'read' => true,
                'edit_posts' => false,
                'ms_view_dashboard' => true,
            ),
        ),
        'building_manager' => array(
            'label' => __('مدير مبنى', 'mostaager-facility-pro'),
            'capabilities' => array(
                'read' => true,
                'edit_posts' => false,
                'ms_view_dashboard' => true,
            ),
        ),
        'mostaager_manager' => array(
            'label' => __('Mostaager Manager', 'mostaager-facility-pro'),
            'capabilities' => array(
                'read' => true,
                'ms_view_dashboard' => true,
            ),
        ),
        'mostaager_supervisor' => array(
            'label' => __('Mostaager Supervisor', 'mostaager-facility-pro'),
            'capabilities' => array(
                'read' => true,
                'ms_view_dashboard' => true,
            ),
        ),
    );

    foreach ($roles as $role_key => $role_data) {
        if (!get_role($role_key)) {
            add_role($role_key, $role_data['label'], $role_data['capabilities']);
        }
    }

    $admin = get_role('administrator');
    if ($admin && ! $admin->has_cap('ms_view_dashboard')) {
        $admin->add_cap('ms_view_dashboard');
    }
}
add_action('init', 'ms_register_facility_roles');

/**
 * Remove Mostaager roles and capabilities on cleanup
 */
function ms_remove_facility_roles()
{
    $remove_roles = array(
        'owner',
        'tenant',
        'agent',
        'building_manager',
        'mostaager_manager',
        'mostaager_supervisor',
    );

    foreach ($remove_roles as $role_key) {
        if (get_role($role_key)) {
            remove_role($role_key);
        }
    }
}
