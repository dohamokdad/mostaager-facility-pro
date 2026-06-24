<?php
/**
 * Telr payment gateway integration for Mostaager.
 * This file registers a WooCommerce payment gateway and provides callback handling.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', function () {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    class WC_Gateway_Mostaager_Telr extends WC_Payment_Gateway {
        public function __construct() {
            $this->id = 'mostaager_telr';
            $this->method_title = 'Mostaager Telr';
            $this->method_description = 'بوابة Telr لدفع فواتير Mostaager.';
            $this->has_fields = false;
            $this->supports = array('products');

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title', 'Telr Payment');
            $this->description = $this->get_option('description', 'Pay securely using Telr.');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_api_mostaager_telr_callback', array($this, 'handle_callback'));
        }

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'تمكين / تعطيل',
                    'type' => 'checkbox',
                    'label' => 'تمكين بوابة Telr',
                    'default' => 'no',
                ),
                'title' => array(
                    'title' => 'عنوان الدفع',
                    'type' => 'text',
                    'default' => 'Telr Payment',
                ),
                'description' => array(
                    'title' => 'الوصف',
                    'type' => 'textarea',
                    'default' => 'Pay securely using Telr.',
                ),
                'merchant_id' => array(
                    'title' => 'Merchant ID',
                    'type' => 'text',
                ),
                'auth_key' => array(
                    'title' => 'Auth Key',
                    'type' => 'text',
                ),
                'test_mode' => array(
                    'title' => 'وضع الاختبار',
                    'type' => 'checkbox',
                    'label' => 'تمكين وضع الاختبار',
                    'default' => 'yes',
                ),
            );
        }

        public function payment_fields() {
            if ($this->description) {
                echo wpautop(wp_kses_post($this->description));
            }
        }

        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
            if (!$order) {
                return array('result' => 'fail');
            }

            $redirect = ms_request_telr_payment_url($order);
            if (!$redirect) {
                wc_add_notice(__('Payment gateway is not available. Please contact support.', 'mostaager'), 'error');
                return array('result' => 'fail');
            }

            return array(
                'result' => 'success',
                'redirect' => $redirect,
            );
        }

        public function handle_callback() {
            $cart = isset($_REQUEST['cart']) ? sanitize_text_field($_REQUEST['cart']) : '';
            $tran_ref = isset($_REQUEST['tran_ref']) ? sanitize_text_field($_REQUEST['tran_ref']) : '';
            if ($cart && preg_match('/^order-(\d+)$/', $cart, $matches)) {
                $order_id = intval($matches[1]);
                $order = wc_get_order($order_id);
                if ($order && $order->get_status() !== 'completed') {
                    $payload = array(
                        'ivp_method' => 'check',
                        'ivp_store' => $this->get_option('merchant_id'),
                        'ivp_authkey' => $this->get_option('auth_key'),
                        'ivp_cart' => $cart,
                        'tran_ref' => $tran_ref,
                    );

                    $verify = wp_remote_post('https://secure.telr.com/gateway/order.json', array(
                        'method' => 'POST',
                        'body' => wp_json_encode($payload),
                        'headers' => array(
                            'Content-Type' => 'application/json',
                        ),
                        'timeout' => 30,
                    ));

                    if (is_wp_error($verify)) {
                        error_log('[Mostaager Telr] callback verify request failed: ' . $verify->get_error_message());
                    } else {
                        $body = json_decode(wp_remote_retrieve_body($verify), true);
                        $status_code = isset($body['order']['status']['code']) ? $body['order']['status']['code'] : '';
                        if ($status_code === 'A') {
                            $order->payment_complete($tran_ref ?: 'telr-callback');
                        } else {
                            $http_code = wp_remote_retrieve_response_code($verify);
                            error_log('[Mostaager Telr] callback verify failed: HTTP ' . $http_code . ' response: ' . wp_json_encode($body));
                        }
                    }
                }
            }

            status_header(200);
            echo 'ok';
            exit;
        }
    }

    add_filter('woocommerce_payment_gateways', function ($gateways) {
        $gateways[] = 'WC_Gateway_Mostaager_Telr';
        return $gateways;
    });
});
