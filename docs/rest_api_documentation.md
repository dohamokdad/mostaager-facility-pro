# Mostaager Facilities Pro Plugin: REST API Documentation for Mobile App

This document outlines the proposed REST API endpoints for integrating the `Mostaager Facilities Pro` WordPress plugin with a mobile application. The API will leverage the WordPress REST API infrastructure, ensuring consistency and security.

## 1. API Base URL

The base URL for the custom API endpoints will be:

`https://your-wordpress-site.com/wp-json/mfp/v1/`

## 2. Authentication

For secure communication between the mobile app and the WordPress backend, it is recommended to use **Application Passwords** or **JWT Authentication**.

### 2.1 Application Passwords (Recommended for simplicity and WordPress-native approach)

*   **How it works:** WordPress generates unique, non-reversible passwords for specific applications (like your mobile app). These passwords can be revoked at any time without affecting the user's main password.
*   **Usage:** The mobile app will send the username and the generated application password in the `Authorization` header using Basic Authentication.
    `Authorization: Basic <base64_encoded(username:application_password)>`
*   **Setup:** Users can generate Application Passwords from their WordPress profile page (`/wp-admin/profile.php`).

### 2.2 JWT Authentication (For more advanced scenarios)

*   **Plugin Required:** A plugin like [JWT Authentication for WP-API](https://wordpress.org/plugins/jwt-authentication-for-wp-rest-api/) would be needed.
*   **Flow:** The mobile app sends user credentials to an authentication endpoint to receive a JWT token. This token is then included in the `Authorization` header for subsequent requests.
    `Authorization: Bearer <your_jwt_token>`

## 3. Endpoints

All endpoints will return data in JSON format.

### 3.1 Buildings

#### 3.1.1 Get All Buildings

*   **Endpoint:** `GET /buildings`
*   **Description:** Retrieves a list of all buildings.
*   **Permissions:** Authenticated users with `read` capability for `mfp_building` CPT.
*   **Query Parameters:**
    *   `status`: Filter by building status (e.g., `active`, `inactive`, `under_construction`).
    *   `city`: Filter by city.
    *   `per_page`: Number of items per page (default: 10).
    *   `page`: Current page number (default: 1).
*   **Example Request:**
    ```
    GET /wp-json/mfp/v1/buildings?status=active&city=Cairo
    ```
*   **Example Response (200 OK):**
    ```json
    [
        {
            "id": 1,
            "name": "برج النيل",
            "city": "القاهرة",
            "country": "مصر",
            "status": "active",
            "created_at": "2026-05-28 10:00:00"
        },
        {
            "id": 2,
            "name": "مجمع الياسمين",
            "city": "الجيزة",
            "country": "مصر",
            "status": "active",
            "created_at": "2026-05-28 10:05:00"
        }
    ]
    ```

#### 3.1.2 Get Single Building

*   **Endpoint:** `GET /buildings/{id}`
*   **Description:** Retrieves details for a specific building.
*   **Permissions:** Authenticated users with `read` capability for `mfp_building` CPT.
*   **Example Request:**
    ```
    GET /wp-json/mfp/v1/buildings/1
    ```
*   **Example Response (200 OK):**
    ```json
    {
        "id": 1,
        "name": "برج النيل",
        "city": "القاهرة",
        "country": "مصر",
        "status": "active",
        "created_at": "2026-05-28 10:00:00"
    }
    ```

### 3.2 Invoices

#### 3.2.1 Get All Invoices

*   **Endpoint:** `GET /invoices`
*   **Description:** Retrieves a list of all invoices.
*   **Permissions:** Authenticated users with `read` capability for `mfp_invoice` CPT.
*   **Query Parameters:**
    *   `building_id`: Filter by associated building.
    *   `status`: Filter by invoice status (e.g., `pending`, `paid`, `overdue`).
    *   `due_date_before`: Filter invoices due before a specific date (YYYY-MM-DD).
    *   `per_page`, `page`.
*   **Example Request:**
    ```
    GET /wp-json/mfp/v1/invoices?building_id=1&status=pending
    ```
*   **Example Response (200 OK):**
    ```json
    [
        {
            "id": 101,
            "invoice_number": "INV-1001",
            "building_id": 1,
            "amount": "3500.00",
            "paid_amount": "0.00",
            "currency": "EGP",
            "status": "pending",
            "invoice_type": "rent",
            "due_date": "2026-06-30"
        }
    ]
    ```

#### 3.2.2 Get Single Invoice

*   **Endpoint:** `GET /invoices/{id}`
*   **Description:** Retrieves details for a specific invoice.
*   **Permissions:** Authenticated users with `read` capability for `mfp_invoice` CPT.

### 3.3 Maintenance Requests

#### 3.3.1 Get All Maintenance Requests

*   **Endpoint:** `GET /maintenance`
*   **Description:** Retrieves a list of all maintenance requests.
*   **Permissions:** Authenticated users with `read` capability for `mfp_maintenance` CPT.
*   **Query Parameters:**
    *   `building_id`: Filter by associated building.
    *   `status`: Filter by status (e.g., `pending`, `completed`).
    *   `priority`: Filter by priority (e.g., `high`, `medium`).
    *   `per_page`, `page`.

#### 3.3.2 Get Single Maintenance Request

*   **Endpoint:** `GET /maintenance/{id}`
*   **Description:** Retrieves details for a specific maintenance request.
*   **Permissions:** Authenticated users with `read` capability for `mfp_maintenance` CPT.

### 3.4 Transactions

#### 3.4.1 Get All Transactions

*   **Endpoint:** `GET /transactions`
*   **Description:** Retrieves a list of all transactions.
*   **Permissions:** Authenticated users with `read` capability for `mfp_transaction` CPT.
*   **Query Parameters:**
    *   `invoice_id`: Filter by associated invoice.
    *   `payment_status`: Filter by payment status (e.g., `completed`, `failed`).
    *   `gateway`: Filter by payment gateway.
    *   `per_page`, `page`.

#### 3.4.2 Get Single Transaction

*   **Endpoint:** `GET /transactions/{id}`
*   **Description:** Retrieves details for a specific transaction.
*   **Permissions:** Authenticated users with `read` capability for `mfp_transaction` CPT.

## 4. Implementing Custom REST API Endpoints

To implement these custom endpoints, you would typically use the `register_rest_route` function within your plugin. This would be handled by a dedicated class, for example, `MostaagerFacilitiesPro\API\RestApi` as suggested in the architectural refinement.

### Example: Registering a Building Endpoint (`includes/API/RestApi.php`)

```php
<?php
// /mostaager-facility-pro/includes/API/RestApi.php

namespace MostaagerFacilitiesPro\API;

class RestApi {

    public static function init() {
        add_action(
            
            'rest_api_init',
            [__CLASS__, 'register_routes']
        );
    }

    public static function register_routes() {
        register_rest_route('mfp/v1', '/buildings', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_buildings'],
            'permission_callback' => [__CLASS__, 'get_buildings_permissions_check'],
            'args' => [
                'status' => [
                    'validate_callback' => function($param, $request, $key) {
                        return in_array($param, ['active', 'inactive', 'under_construction']);
                    },
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'city' => [
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'per_page' => [
                    'validate_callback' => 'is_numeric',
                    'sanitize_callback' => 'absint',
                    'default' => 10,
                ],
                'page' => [
                    'validate_callback' => 'is_numeric',
                    'sanitize_callback' => 'absint',
                    'default' => 1,
                ],
            ],
        ]);

        register_rest_route('mfp/v1', '/buildings/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_building'],
            'permission_callback' => [__CLASS__, 'get_building_permissions_check'],
            'args' => [
                'id' => [
                    'validate_callback' => 'is_numeric',
                    'required' => true,
                ],
            ],
        ]);

        // Register routes for invoices, maintenance, and transactions similarly
    }

    public static function get_buildings($request) {
        // Implement logic to fetch buildings from the database
        // Use $request->get_param('status'), $request->get_param('city'), etc.
        // Example: $buildings = get_posts([ 'post_type' => 'mfp_building', 'meta_query' => ... ]);
        // Or direct database query using $wpdb for custom tables

        $args = [
            'post_type' => 'mfp_building',
            'posts_per_page' => $request->get_param('per_page'),
            'paged' => $request->get_param('page'),
            'meta_query' => [],
        ];

        if ($status = $request->get_param('status')) {
            $args['meta_query'][] = [
                'key' => '_mfp_building_status',
                'value' => $status,
                'compare' => '='
            ];
        }
        if ($city = $request->get_param('city')) {
            $args['meta_query'][] = [
                'key' => '_mfp_building_city',
                'value' => $city,
                'compare' => '='
            ];
        }

        $buildings_query = new \WP_Query($args);
        $buildings_data = [];

        if ($buildings_query->have_posts()) {
            while ($buildings_query->have_posts()) {
                $buildings_query->the_post();
                $buildings_data[] = [
                    'id' => get_the_ID(),
                    'name' => get_the_title(),
                    'city' => get_post_meta(get_the_ID(), '_mfp_building_city', true),
                    'country' => get_post_meta(get_the_ID(), '_mfp_building_country', true),
                    'status' => get_post_meta(get_the_ID(), '_mfp_building_status', true),
                    'created_at' => get_the_date('Y-m-d H:i:s'),
                ];
            }
            wp_reset_postdata();
        }

        return new \WP_REST_Response($buildings_data, 200);
    }

    public static function get_building($request) {
        $building_id = (int) $request['id'];
        $building = get_post($building_id);

        if (empty($building) || $building->post_type !== 'mfp_building') {
            return new \WP_Error('rest_building_not_found', __('Building not found', MFP_TEXT_DOMAIN), ['status' => 404]);
        }

        $building_data = [
            'id' => $building->ID,
            'name' => $building->post_title,
            'city' => get_post_meta($building->ID, '_mfp_building_city', true),
            'country' => get_post_meta($building->ID, '_mfp_building_country', true),
            'status' => get_post_meta($building->ID, '_mfp_building_status', true),
            'created_at' => get_the_date('Y-m-d H:i:s', $building->ID),
        ];

        return new \WP_REST_Response($building_data, 200);
    }

    public static function get_buildings_permissions_check($request) {
        // Check if user is logged in and has 'read' capability for mfp_building
        return current_user_can('read_mfp_building');
    }

    public static function get_building_permissions_check($request) {
        // Check if user is logged in and has 'read' capability for mfp_building
        return current_user_can('read_mfp_building');
    }

    // Similar methods for Invoices, Maintenance, and Transactions
}
```

## 5. Error Handling

Standard WordPress REST API error handling will be used. For example, if a building is not found, a `WP_Error` object will be returned, which translates to a JSON response with an appropriate HTTP status code (e.g., 404 Not Found).

## 6. Future Considerations

*   **Write Endpoints:** Implement `POST`, `PUT`, `DELETE` methods for creating, updating, and deleting resources.
*   **Advanced Filtering & Sorting:** Add more sophisticated query parameters for filtering and sorting data.
*   **Field Limiting:** Allow clients to specify which fields they want to receive in the response to reduce payload size.
*   **Rate Limiting:** Implement rate limiting to prevent abuse of the API.
*   **Webhooks:** Provide webhook functionality to notify the mobile app of changes in real-time (e.g., new invoice created, maintenance status updated).
