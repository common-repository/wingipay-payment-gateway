<?php
/*
Plugin Name: Wingipay Payment Gateway
Plugin URI: https://wingipay.com
Description: Wingipay Payment gateway for WooCommerce
Version: 1.0
Author: Wingi Pay, Michael, Patrick, Teddy
Author URI: https://wingipay.com/about/
*/
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
add_action('plugins_loaded', 'wppp_woocomme_wingipay_init', 0);
add_action('init','wppp_add_cors_http_header');

function wppp_add_cors_http_header(){
    header("Access-Control-Allow-Origin: https://prod.wingipay.com");
}
function wppp_woocomme_wingipay_init()
{
    if (!class_exists('WC_Payment_Gateway'))
        return;
    
    class WPPP_Cbabl_Wingipay extends WC_Payment_Gateway
    {
        
        public function __construct()
        {
            $this->id            = 'wingipay';
            $this->medthod_title = 'Wingi Pay';
            $this->method_description = 'Recieve mobile money and card payments in your shop. ';
            $this->has_fields    = false;
            $this->icon          = plugins_url('wingipay-pay.png' ,__FILE__ );
            
            $this->init_form_fields();
            $this->init_settings();
            $this->title            = sanitize_text_field($this->get_option('title'), 'Wingipay' );
            $this->description      = sanitize_text_field($this->get_option('description'), 'Wingipay Card, Mobile Money Payment gateway for WooCommerce' );
            $this->api_key          = sanitize_text_field($this->get_option('api_key') );
            $this->redirect_page_id = sanitize_text_field($this->get_option('redirect_page_id') );
            $this->liveURL          = 'https://prod.wingipay.com/web/checkout/add/';
            $this->payURL           = '';
			// $this->merchant_id		= 'Wordpress 404';
            
            $this->msg['message'] = "";
            $this->msg['class']   = "";

            /*$this->method_description = "Pay with moble money using Wingipay";*/
            add_action('init', array(
                &$this,
                'check_wingipay_response'
            ));
            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
                    &$this,
                    'process_admin_options'
                ));
            } else {
                add_action('woocommerce_update_options_payment_gateways', array(
                    &$this,
                    'process_admin_options'
                ));
            }
            add_action('woocommerce_receipt_wingipay', array(
                &$this,
                'receipt_page'
            ));
        }
        
        
        // Fields that would be shown inside the user dashboard.
        function init_form_fields()
        {
            
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'cbabl'),
                    'type' => 'checkbox',
                    'label' => __('Enable Wingipay Payment Module.', 'cbabl'),
                    'default' => 'no'
                ),
                'title' => array(
                    'title' => __('Title:', 'cbabl'),
                    'type' => 'text',
                    'description' => __('This controls the title of the payment option the user sees during checkout.', 'cbabl'),
                    'default' => __('Wingipay', 'cbabl')
                ),
                'description' => array(
                    'title' => __('Description:', 'cbabl'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'cbabl'),
                    'default' => __('Online payment with your card or mobile money.', 'cbabl')
                ),
                'api_key' => array(
                    'title' => __('API Key', 'cbabl'),
                    'type' => 'text',
                    'description' => __('This is the API Key generated at the Wingipay Dashboard. Visit <a target="_blank" href="https://dashboard.wingipay.com">the Wingipay dashboard</a> to view your API Key.')
                ),

                'redirect_page_id' => array(
                    'title' => __('Return Page'),
                    'type' => 'select',
                    'options' => $this->get_pages('Select Page'),
                    'description' => "Select the page your customers would be returned to after the payment is made."
                )
            );
        }
        
        //  Shown in the dash above the form fields
        public function admin_options()
        {
            echo wp_kses_post('<h2>' . __('Wingipay Payment Gateway', 'cbabl') . '</h2>');
            echo wp_kses_post('<p>' . __('Receive payments from card, mobile money payments online using Wingipay.') . '</p>');
            echo wp_kses_post('<h3>' . __('To start using the plugin:', 'cbabl') . '</h3>');
            echo wp_kses_post('<p>' . __('<span>&#8226;</span> Head over to our <a href="https://web.wingipay.com/signup">dashboard and create a Wingipay account.</a>') . '</p>');
            echo wp_kses_post('<p>' . __('<span>&#8226;</span> Login to your Wingipay dashboard <a href="https://web.wingipay.com/login"> Click to login </a>when you are done.') . '</p>');
            echo wp_kses_post('<p>' . __('<span>&#8226;</span> Get your API Keys from your Wingipay dashboard settings.') . '</p>');

            // Generate the HTML For the settings form.
            echo wp_kses_post('<table class="form-table">');
            // Generate the HTML For the settings form.
            $this->generate_settings_html();
            echo wp_kses_post('</table>');

            echo wp_kses_post('<span><a href="https://wingipay.com/">Wingipay. </a> | <a href="https://wingipay.com">Wingipay Website</a> | <a href="https://wingipay.com/developer">Developer Documentation</a></span>');                                    
        }
        
        
        function payment_fields()
        {
            if ($this->description)
                echo wp_kses_post(wptexturize($this->description));
        }
        
        function receipt_page($order)
        {
            echo wp_kses_post('<p>' . __('Thank you for your order, please click the button below to pay with Wingipay.', 'cbabl') . '</p>');
            echo wp_kses_post($this->generate_wingipay_form($order));
        }
        
        
        //  Get the payment URL from the server
        function genPayURL($orderParams){
            // echo "get url test one ".$orderParams;

            $body = array(
                "success_url" => ($this->redirect_page_id == "" || $this->redirect_page_id == 0) ? get_site_url() . "/" : get_permalink($this->redirect_page_id),
                "price" => $orderParams->get_total(),
                "orderID" => $orderParams->get_id(),
                "apikey" => $this->api_key,
                "currency" => "GHS",
                "note" => "",
                "amount" => $orderParams->get_total(),
                "full_name" =>  $orderParams->get_billing_first_name()." ".$orderParams->get_billing_last_name(),
                "phone" =>  $orderParams->get_billing_phone(),
                "email" =>  $orderParams->get_billing_email(),
                "external_transaction_ref" => $orderParams->get_id(),
                "country" => "GH",
                "vendorId" => "",
                "transaction_ref" => "",
                "callbackurl" => get_site_url()."/wp-json/callback/v1/wingipay",
                "redirect_url" => get_site_url(),
                "setAmountByCustomer" => false
            );
            
            // echo "body get url test one ".$body;

            $args = array(
                'body' => $body,
                'timeout' => '5',
                'redirection' => '5',
                'httpversion' => '1.0',
                'blocking' => true,
                'headers' => array(
                    'Content-Type: application/json'
                ),
                'cookies' => array()
            );

            $response = wp_remote_post( $this->liveURL, $args );

            $response_code = wp_remote_retrieve_response_code( $response ); // HTTP response code (e.g., 200 for success)
            $response_message = wp_remote_retrieve_response_message( $response ); // HTTP response message (e.g., 'OK' for success)
            $body = wp_remote_retrieve_body( $response );
            // echo "response_code get url test one ".$response_code;
            // echo "response_message get url test one ".$response_message;
            // echo "body get url test one ".$body;

            if ( is_wp_error( $response ) ) {
                $error_message = $response->get_error_message();
                echo wp_kses_post("error message ".$error_message);
                return false;
            } else {
                $response_code = wp_remote_retrieve_response_code( $response ); // HTTP response code (e.g., 200 for success)
                $response_message = wp_remote_retrieve_response_message( $response ); // HTTP response message (e.g., 'OK' for success)
                $body = wp_remote_retrieve_body( $response ); // Response body
                $obj = json_decode($body, true);
                // echo "body ".$obj;
                // echo "redirect_url ".$obj['redirect_url'];
                
                // retrieved headers
                // $headers = wp_remote_retrieve_headers( $response );
                if( $obj['status'] === false){
                    return false;
                }
                else{
                    $payURL = $obj['redirect_url'];
                    return $obj['redirect_url'];
                }
            }
        }
        
        //  Process payment
        function process_payment($order_id)
        {
            // echo 'process pay'.$order_id;

            global $woocommerce;
            $order = new WC_Order($order_id);
            $result = $this->genPayURL($order);
            // echo " result p -> ".$order_id." <-> ".$woocommerce->cart->get_checkout_url()." <-> ". get_site_url();

            if($result === false){
                return array(
                    'result' => 'failure',
                    'redirect' =>  $woocommerce->cart->get_checkout_url()
                );

            }else{
                //clear cart items
                $woocommerce->cart->empty_cart();
                // $order->payment_complete();
                $order->update_status('pending');
                $order->reduce_order_stock();
                // $hook_url = get_site_url().'/wc-api/wingi_orderhook';
                return array(
                    'result' => 'success',
                    'redirect' => $result
                );  
            }
        }
        
        // Check for valid wingipay server callback
        function check_wingipay_response()
        {
            global $woocommerce;
            
            return array(
                'success' => true,
                'message' => 'Payment processed successfully.',
            );
            
        }
        
        function showMessage($content)
        {
            return '<div class="box ' . $this->msg['class'] . '-box">' . $this->msg['message'] . '</div>' . $content;
        }
        
        // get all pages
        function get_pages($title = false, $indent = true)
        {
            $wp_pages  = get_pages('sort_column=menu_order');
            $page_list = array();
            if ($title)
                $page_list[] = $title;
            foreach ($wp_pages as $page) {
                $prefix = '';
                // show indented child pages?
                if ($indent) {
                    $has_parent = $page->post_parent;
                    while ($has_parent) {
                        $prefix .= ' - ';
                        $next_page  = get_page($has_parent);
                        $has_parent = $next_page->post_parent;
                    }
                }
                // add to page list array array
                $page_list[$page->ID] = $prefix . $page->post_title;
            }
            return $page_list;
        }

    }
    
    // Add the Gateway to WooCommerce
    function wppp_woocommerce_add_wingipay_gateway($methods)
    {
        $methods[] = 'WPPP_Cbabl_Wingipay';
        return $methods;
    }

    
    add_filter('woocommerce_payment_gateways', 'wppp_woocommerce_add_wingipay_gateway');
}


// Add a custom API endpoint
function wppp_callback_register_api_endpoint() {
    register_rest_route( 'callback/v1', '/wingipay', array(
        'methods'  => 'POST',
        'callback' => 'wppp_callback_process_payment',
    ) );
}
add_action( 'rest_api_init', 'wppp_callback_register_api_endpoint' );

// Process the payment request on callback
function wppp_callback_process_payment( $request ) {
    // Retrieve the payment data from the request
    $methods[] = 'WPPP_Cbabl_Wingipay';
    global $woocommerce;
    $payment_data = $request->get_params();
    $order = null;
    try{
        $order = new WC_Order($payment_data["external_transaction_ref"]);
    }
    catch(Exception $e){
        $order = false;
    }

    // Return a response
    if($order){
        if($payment_data["transaction_status"] === "SUCCESSFUL"){
            $order->update_status('processing');
            $order->payment_complete();
            return array(
                'success' => true,
                'message' => 'Payment processed successfully.',
                "payment_data" => $payment_data["external_transaction_ref"],
                "url" => get_site_url()
            );
        }
        else{
            // 
            $order->update_status('failed');
            return array(
                'success' => false,
                'message' => 'Payment unsuccessfully'
            );
        }
    }
    else{
        // 
        return array(
            'success' => false,
            'message' => 'Invalid external transaction ref'
        );
    }
}