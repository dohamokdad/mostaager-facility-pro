# Mostaager Facilities Pro Plugin: Code Enhancements and Feature Suggestions

This document outlines proposed code enhancements and additional feature suggestions for the `Mostaager Facilities Pro` WordPress plugin, building upon the initial analysis. The goal is to improve code quality, maintainability, security, and extend functionality.

## 1. Architectural Refinement: Moving Towards OOP

To improve code organization and maintainability, a shift from a purely procedural approach to an Object-Oriented Programming (OOP) structure is highly recommended. This involves encapsulating related functionalities within classes.

### Proposed Structure for `bootstrap/app.php`

Instead of a minimal `app.php`, it should act as a central loader for various plugin components, including an autoloader for classes.

```php
<?php
// /mostaager-facility-pro/bootstrap/app.php

if (!defined('ABSPATH')) exit;

// Define core plugin constants if not already defined
if (!defined('MFP_PATH')) define('MFP_PATH', plugin_dir_path(dirname(__FILE__, 2)));
if (!defined('MFP_URL')) define('MFP_URL', plugin_dir_url(dirname(__FILE__, 2)));
if (!defined('MFP_VERSION')) define('MFP_VERSION', '1.0.1'); // Increment version
if (!defined('MFP_TEXT_DOMAIN')) define('MFP_TEXT_DOMAIN', 'mostaager-facility-pro');

// Autoload classes (example using a simple autoloader)
spl_autoload_register(function ($class_name) {
    // Define a base namespace for your plugin classes
    $namespace = 'MostaagerFacilitiesPro\\';

    // Check if the class is part of our namespace
    if (strpos($class_name, $namespace) === 0) {
        // Remove the namespace prefix
        $relative_class = substr($class_name, strlen($namespace));

        // Replace namespace separators with directory separators
        $file = MFP_PATH . 'includes/' . str_replace('\\', '/', $relative_class) . '.php';

        // If the file exists, require it
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

// Load text domain for internationalization
add_action('plugins_loaded', function() {
    load_plugin_textdomain(MFP_TEXT_DOMAIN, false, basename(MFP_PATH) . '/languages/');
});

// Initialize core plugin classes
MostaagerFacilitiesPro\Core\Activator::init();
MostaagerFacilitiesPro\Admin\AdminPages::init();
MostaagerFacilitiesPro\API\RestApi::init();
// ... other initializations

// Example of a core activator class
// This would replace the procedural mfp_activate_plugin function
```

### Example: Core Activator Class (`includes/Core/Activator.php`)

```php
<?php
// /mostaager-facility-pro/includes/Core/Activator.php

namespace MostaagerFacilitiesPro\Core;

class Activator {

    public static function init() {
        register_activation_hook(MFP_PATH . 'mostaager-facility-pro.php', [__CLASS__, 'activate']);
    }

    public static function activate() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $tables = [];

        $tables[] = "CREATE TABLE {$wpdb->prefix}mfp_buildings (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            city VARCHAR(100),
            country VARCHAR(100),
            status VARCHAR(50) DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(id)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}mfp_invoices (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            invoice_number VARCHAR(100) NOT NULL UNIQUE,
            building_id BIGINT UNSIGNED NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            paid_amount DECIMAL(10,2) DEFAULT 0.00,
            currency VARCHAR(10) DEFAULT 'EGP',
            status VARCHAR(50) DEFAULT 'pending',
            invoice_type VARCHAR(100),
            due_date DATE,
            PRIMARY KEY(id),
            FOREIGN KEY (building_id) REFERENCES {$wpdb->prefix}mfp_buildings(id) ON DELETE CASCADE
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}mfp_maintenance (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            building_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            description LONGTEXT,
            priority VARCHAR(50) DEFAULT 'medium',
            status VARCHAR(50) DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(id),
            FOREIGN KEY (building_id) REFERENCES {$wpdb->prefix}mfp_buildings(id) ON DELETE CASCADE
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}mfp_transactions (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            invoice_id BIGINT UNSIGNED NOT NULL,
            gateway VARCHAR(100),
            amount DECIMAL(10,2) NOT NULL,
            currency VARCHAR(10) DEFAULT 'EGP',
            payment_status VARCHAR(50) DEFAULT 'pending',
            payment_method VARCHAR(100),
            transaction_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(id),
            FOREIGN KEY (invoice_id) REFERENCES {$wpdb->prefix}mfp_invoices(id) ON DELETE CASCADE
        ) $charset_collate;";

        foreach($tables as $sql){
            dbDelta($sql);
        }

        // Optionally, add default data or settings here
    }
}
```

**Key Improvements in Activator:**
*   Encapsulated activation logic within a class.
*   Added `NOT NULL`, `UNIQUE`, `DEFAULT` constraints to database fields for better data integrity.
*   Introduced `FOREIGN KEY` constraints to enforce referential integrity between tables, improving data consistency.
*   Added `created_at` to `mfp_maintenance` and `transaction_date` to `mfp_transactions` for better tracking.

## 2. Comprehensive Admin Interface with Custom Post Types (CPTs)

Leveraging WordPress Custom Post Types (CPTs) is the most WordPress-native way to manage structured data like Buildings, Invoices, and Maintenance requests. This provides a familiar UI for users and integrates well with existing WordPress features.

### Proposed Structure for CPT Registration (`includes/Admin/CustomPostTypes.php`)

```php
<?php
// /mostaager-facility-pro/includes/Admin/CustomPostTypes.php

namespace MostaagerFacilitiesPro\Admin;

class CustomPostTypes {

    public static function init() {
        add_action('init', [__CLASS__, 'register_buildings_cpt']);
        add_action('init', [__CLASS__, 'register_invoices_cpt']);
        add_action('init', [__CLASS__, 'register_maintenance_cpt']);
        // ... register other CPTs as needed
    }

    public static function register_buildings_cpt() {
        $labels = [
            'name'                  => _x('Buildings', 'Post Type General Name', MFP_TEXT_DOMAIN),
            'singular_name'         => _x('Building', 'Post Type Singular Name', MFP_TEXT_DOMAIN),
            'menu_name'             => __('Buildings', MFP_TEXT_DOMAIN),
            'name_admin_bar'        => __('Building', MFP_TEXT_DOMAIN),
            'archives'              => __('Building Archives', MFP_TEXT_DOMAIN),
            'attributes'            => __('Building Attributes', MFP_TEXT_DOMAIN),
            'parent_item_colon'     => __('Parent Building:', MFP_TEXT_DOMAIN),
            'all_items'             => __('All Buildings', MFP_TEXT_DOMAIN),
            'add_new_item'          => __('Add New Building', MFP_TEXT_DOMAIN),
            'add_new'               => __('Add New', MFP_TEXT_DOMAIN),
            'new_item'              => __('New Building', MFP_TEXT_DOMAIN),
            'edit_item'             => __('Edit Building', MFP_TEXT_DOMAIN),
            'update_item'           => __('Update Building', MFP_TEXT_DOMAIN),
            'view_item'             => __('View Building', MFP_TEXT_DOMAIN),
            'view_items'            => __('View Buildings', MFP_TEXT_DOMAIN),
            'search_items'          => __('Search Building', MFP_TEXT_DOMAIN),
            'not_found'             => __('Not Found', MFP_TEXT_DOMAIN),
            'not_found_in_trash'    => __('Not Found in Trash', MFP_TEXT_DOMAIN),
            'featured_image'        => __('Featured Image', MFP_TEXT_DOMAIN),
            'set_featured_image'    => __('Set featured image', MFP_TEXT_DOMAIN),
            'remove_featured_image' => __('Remove featured image', MFP_TEXT_DOMAIN),
            'use_featured_image'    => __('Use as featured image', MFP_TEXT_DOMAIN),
            'insert_into_item'      => __('Insert into building', MFP_TEXT_DOMAIN),
            'uploaded_to_this_item' => __('Uploaded to this building', MFP_TEXT_DOMAIN),
            'items_list'            => __('Buildings list', MFP_TEXT_DOMAIN),
            'items_list_navigation' => __('Buildings list navigation', MFP_TEXT_DOMAIN),
            'filter_items_list'     => __('Filter buildings list', MFP_TEXT_DOMAIN),
        ];
        $args = [
            'label'                 => __('Building', MFP_TEXT_DOMAIN),
            'description'           => __('Property Buildings', MFP_TEXT_DOMAIN),
            'labels'                => $labels,
            'supports'              => ['title', 'editor', 'custom-fields'], // Add custom-fields for meta boxes
            'hierarchical'          => false,
            'public'                => true,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'menu_position'         => 5,
            'menu_icon'             => 'dashicons-admin-multisite',
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => true,
            'can_export'            => true,
            'has_archive'           => true,
            'exclude_from_search'   => false,
            'publicly_queryable'    => true,
            'capability_type'       => 'post',
            'show_in_rest'          => true, // Enable for Gutenberg and REST API
        ];
        register_post_type('mfp_building', $args);
    }

    public static function register_invoices_cpt() {
        // Similar registration for 'mfp_invoice' CPT
        // Supports: ['title', 'custom-fields']
        // menu_icon: 'dashicons-media-text'
        // show_in_rest: true
    }

    public static function register_maintenance_cpt() {
        // Similar registration for 'mfp_maintenance' CPT
        // Supports: ['title', 'editor', 'custom-fields']
        // menu_icon: 'dashicons-hammer'
        // show_in_rest: true
    }
}
```

**Key Improvements with CPTs:**
*   **Native WordPress UI:** Provides a familiar interface for managing data.
*   **Internationalization:** Labels are wrapped in `_x()` and `__()` for translation.
*   **REST API Ready:** `show_in_rest => true` makes these CPTs accessible via the WordPress REST API, which is crucial for mobile app integration.
*   **Custom Fields:** Allows for easy attachment of meta data (city, country, status for buildings; amount, due date for invoices, etc.) using custom meta boxes.

## 3. Robust Data Handling: Validation, Sanitization, and Escaping

Proper data handling is paramount for security and data integrity. This involves validating input, sanitizing it before saving to the database, and escaping output before displaying it.

### Example: Saving Building Meta Data (using a Meta Box)

This example demonstrates how to add a meta box to the `mfp_building` CPT and handle saving its data securely.

```php
<?php
// /mostaager-facility-pro/includes/Admin/BuildingMetaBox.php

namespace MostaagerFacilitiesPro\Admin;

class BuildingMetaBox {

    public static function init() {
        add_action('add_meta_boxes', [__CLASS__, 'add_building_meta_box']);
        add_action('save_post_mfp_building', [__CLASS__, 'save_building_meta_data']);
    }

    public static function add_building_meta_box() {
        add_meta_box(
            'mfp_building_details',
            __('Building Details', MFP_TEXT_DOMAIN),
            [__CLASS__, 'render_building_meta_box'],
            'mfp_building',
            'normal',
            'high'
        );
    }

    public static function render_building_meta_box($post) {
        // Add a nonce field so we can check it later.
        wp_nonce_field('mfp_save_building_meta', 'mfp_building_meta_nonce');

        $city = get_post_meta($post->ID, '_mfp_building_city', true);
        $country = get_post_meta($post->ID, '_mfp_building_country', true);
        $status = get_post_meta($post->ID, '_mfp_building_status', true);
        ?>
        <p>
            <label for="mfp_building_city"><?php _e('City', MFP_TEXT_DOMAIN); ?>:</label>
            <input type="text" id="mfp_building_city" name="mfp_building_city" value="<?php echo esc_attr($city); ?>" class="widefat" />
        </p>
        <p>
            <label for="mfp_building_country"><?php _e('Country', MFP_TEXT_DOMAIN); ?>:</label>
            <input type="text" id="mfp_building_country" name="mfp_building_country" value="<?php echo esc_attr($country); ?>" class="widefat" />
        </p>
        <p>
            <label for="mfp_building_status"><?php _e('Status', MFP_TEXT_DOMAIN); ?>:</label>
            <select id="mfp_building_status" name="mfp_building_status" class="widefat">
                <option value="active" <?php selected($status, 'active'); ?>><?php _e('Active', MFP_TEXT_DOMAIN); ?></option>
                <option value="inactive" <?php selected($status, 'inactive'); ?>><?php _e('Inactive', MFP_TEXT_DOMAIN); ?></option>
                <option value="under_construction" <?php selected($status, 'under_construction'); ?>><?php _e('Under Construction', MFP_TEXT_DOMAIN); ?></option>
            </select>
        </p>
        <?php
    }

    public static function save_building_meta_data($post_id) {
        // Check if our nonce is set.
        if (!isset($_POST['mfp_building_meta_nonce'])) {
            return;
        }

        // Verify that the nonce is valid.
        if (!wp_verify_nonce($_POST['mfp_building_meta_nonce'], 'mfp_save_building_meta')) {
            return;
        }

        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check the user's permissions.
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Sanitize and save data.
        if (isset($_POST['mfp_building_city'])) {
            $city = sanitize_text_field($_POST['mfp_building_city']);
            update_post_meta($post_id, '_mfp_building_city', $city);
        }

        if (isset($_POST['mfp_building_country'])) {
            $country = sanitize_text_field($_POST['mfp_building_country']);
            update_post_meta($post_id, '_mfp_building_country', $country);
        }

        if (isset($_POST['mfp_building_status'])) {
            $status = sanitize_text_field($_POST['mfp_building_status']);
            // Further validation for allowed status values if necessary
            $allowed_statuses = ['active', 'inactive', 'under_construction'];
            if (in_array($status, $allowed_statuses)) {
                update_post_meta($post_id, '_mfp_building_status', $status);
            }
        }
    }
}
```

**Key Data Handling Practices:**
*   **Nonce Verification:** `wp_nonce_field` and `wp_verify_nonce` protect against Cross-Site Request Forgery (CSRF).
*   **Capability Checks:** `current_user_can` ensures only authorized users can save data.
*   **Sanitization:** `sanitize_text_field()` is used to clean input before saving to the database, preventing XSS and other injection attacks.
*   **Escaping:** `esc_attr()` is used when outputting data into HTML attributes, and `__()` for translatable strings.
*   **Validation:** Basic validation for `status` field is shown, ensuring only allowed values are saved.

## 4. Internationalization (i18n)

Making the plugin translatable is crucial for a global audience. This involves defining a text domain and wrapping all translatable strings.

### Implementation

1.  **Define Text Domain:** As shown in the `bootstrap/app.php` example, define `MFP_TEXT_DOMAIN`.
2.  **Load Text Domain:** Use `load_plugin_textdomain()` during the `plugins_loaded` action.
3.  **Wrap Strings:** All user-facing strings in the plugin (labels, messages, descriptions) should be wrapped in `__()` (for returning a translated string) or `_e()` (for echoing a translated string).

Example:
`__('Building Details', MFP_TEXT_DOMAIN)`

## 5. Additional Feature Suggestions

Beyond code quality and architectural improvements, here are some feature suggestions to enhance the plugin's utility and user experience:

### 5.1 Dashboard and Reporting
*   **Overview Dashboard:** A dedicated admin dashboard page displaying key metrics: total buildings, active invoices, overdue invoices, pending maintenance requests, recent transactions.
*   **Building-Specific Dashboards:** For each building, a summary view showing its associated invoices, maintenance, and transactions.
*   **Financial Reports:** Generate reports on income from invoices, expenses from maintenance, and overall financial health.
*   **Interactive Charts:** Utilize a library like Chart.js (can be enqueued in WordPress admin) to visualize data (e.g., invoice status distribution, monthly income trends).

### 5.2 Invoice Management Enhancements
*   **Invoice Generation:** Automated generation of recurring invoices (e.g., monthly rent).
*   **Payment Gateway Integration:** Integrate with popular payment gateways (e.g., Stripe, PayPal) to allow tenants to pay invoices directly.
*   **Invoice Templates:** Customizable PDF invoice templates.
*   **Email Notifications:** Send automated email notifications for new invoices, due date reminders, and payment confirmations.

### 5.3 Maintenance Management Workflow
*   **Maintenance Request Submission:** Allow tenants (or specific user roles) to submit maintenance requests via a frontend form.
*   **Status Tracking:** A clear workflow for maintenance requests (e.g., New, Assigned, In Progress, Completed, On Hold).
*   **Technician Assignment:** Assign maintenance tasks to specific users/technicians.
*   **Communication Log:** A log of all communications related to a maintenance request.

### 5.4 User Roles and Permissions
*   **Custom Roles:** Define specific roles like 
    // Further validation for allowed status values if necessary
            $allowed_statuses = ["active", "inactive", "under_construction"];
            if (in_array($status, $allowed_statuses)) {
                update_post_meta($post_id, "_mfp_building_status", $status);
            }
        }
    }
}
```

**Key Data Handling Practices:**
*   **Nonce Verification:** `wp_nonce_field` and `wp_verify_nonce` protect against Cross-Site Request Forgery (CSRF).
*   **Capability Checks:** `current_user_can` ensures only authorized users can save data.
*   **Sanitization:** `sanitize_text_field()` is used to clean input before saving to the database, preventing XSS and other injection attacks.
*   **Escaping:** `esc_attr()` is used when outputting data into HTML attributes, and `__()` for translatable strings.
*   **Validation:** Basic validation for `status` field is shown, ensuring only allowed values are saved.

## 4. Internationalization (i18n)

Making the plugin translatable is crucial for a global audience. This involves defining a text domain and wrapping all translatable strings.

### Implementation

1.  **Define Text Domain:** As shown in the `bootstrap/app.php` example, define `MFP_TEXT_DOMAIN`.
2.  **Load Text Domain:** Use `load_plugin_textdomain()` during the `plugins_loaded` action.
3.  **Wrap Strings:** All user-facing strings in the plugin (labels, messages, descriptions) should be wrapped in `__()` (for returning a translated string) or `_e()` (for echoing a translated string).

Example:
`__("Building Details", MFP_TEXT_DOMAIN)`

## 5. Additional Feature Suggestions

Beyond code quality and architectural improvements, here are some feature suggestions to enhance the plugin's utility and user experience:

### 5.1 Dashboard and Reporting
*   **Overview Dashboard:** A dedicated admin dashboard page displaying key metrics: total buildings, active invoices, overdue invoices, pending maintenance requests, recent transactions.
*   **Building-Specific Dashboards:** For each building, a summary view showing its associated invoices, maintenance, and transactions.
*   **Financial Reports:** Generate reports on income from invoices, expenses from maintenance, and overall financial health.
*   **Interactive Charts:** Utilize a library like Chart.js (can be enqueued in WordPress admin) to visualize data (e.g., invoice status distribution, monthly income trends).

### 5.2 Invoice Management Enhancements
*   **Invoice Generation:** Automated generation of recurring invoices (e.g., monthly rent).
*   **Payment Gateway Integration:** Integrate with popular payment gateways (e.g., Stripe, PayPal) to allow tenants to pay invoices directly.
*   **Invoice Templates:** Customizable PDF invoice templates.
*   **Email Notifications:** Send automated email notifications for new invoices, due date reminders, and payment confirmations.

### 5.3 Maintenance Management Workflow
*   **Maintenance Request Submission:** Allow tenants (or specific user roles) to submit maintenance requests via a frontend form.
*   **Status Tracking:** A clear workflow for maintenance requests (e.g., New, Assigned, In Progress, Completed, On Hold).
*   **Technician Assignment:** Assign maintenance tasks to specific users/technicians.
*   **Communication Log:** A log of all communications related to a maintenance request.

### 5.4 User Roles and Permissions
*   **Custom Roles:** Define specific roles like "Property Manager," "Tenant," and "Maintenance Staff" with granular capabilities to access and manage different aspects of the plugin.
*   **Tenant Portal:** A frontend portal where tenants can view their invoices, payment history, submit maintenance requests, and view building-specific information.

### 5.5 Integration with Houzez Theme
*   **Property Sync:** Automatically link buildings created in the plugin with properties managed by the Houzez theme.
*   **Custom Fields Mapping:** Map relevant building details from the plugin to Houzez property custom fields for consistent data display.
*   **Search and Filter:** Enhance Houzez search and filter options to include building-specific criteria managed by the plugin.

These enhancements aim to transform the `Mostaager Facilities Pro` plugin into a more robust, secure, and user-friendly property management solution within the WordPress ecosystem.
