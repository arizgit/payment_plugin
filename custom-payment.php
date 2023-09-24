<?php
/*
Plugin Name: Super Cool Payment Plugin
Plugin URI: http://wordpress.org/plugins
Description: Custom checkout payment method for Woo
Author: Ariz Borcelis
Version: 0.0.1
Author URI: https://www.linkedin.com/in/ariz-borcelis/
*/

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter( 'woocommerce_payment_gateways', 'supercool_add_gateway_class' );
function supercool_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_Supercool_Gateway'; // your class name is here
	return $gateways;
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'supercool_init_gateway_class' );
function supercool_init_gateway_class() {

	class WC_Supercool_Gateway extends WC_Payment_Gateway {

 		/**
 		 * Class constructor, more about it in Step 3
 		 */
    public function __construct() {
      $this->id = 'supercool'; // payment gateway plugin ID
      $this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
      $this->has_fields = true; // in case you need a custom credit card form
      $this->method_title = 'Super Cool Payment Gateway';
      $this->method_description = 'The super cool payment gateway'; // will be displayed on the options page
    
      // gateways can support subscriptions, refunds, saved payment methods,
      // but in this tutorial we begin with simple payments
      $this->supports = array(
        'products'
      );
    
      // Method with all the options fields
      $this->init_form_fields();
    
      // Load the settings.
      $this->init_settings();
      $this->title = $this->get_option( 'title' );
      $this->description = $this->get_option( 'description' );
      $this->enabled = $this->get_option( 'enabled' );
      $this->testmode = 'yes' === $this->get_option( 'testmode' );
      $this->private_key = $this->testmode ? $this->get_option( 'test_private_key' ) : $this->get_option( 'private_key' );
      $this->publishable_key = $this->testmode ? $this->get_option( 'test_publishable_key' ) : $this->get_option( 'publishable_key' );
      $this->api_domain_url = $this->get_option( 'api_domain_url' );
      $this->api_http_client_user_id = $this->get_option( 'http_client_user_id' );
      $this->api_http_client_password = $this->get_option( 'http_client_password' );
    
      // This action hook saves the settings
      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    
      // We need custom JavaScript to obtain a token
      add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
      
      // You can also register a webhook here
      // add_action( 'woocommerce_api_{webhook name}', array( $this, 'webhook' ) );

    }

		/**
 		 * Plugin options, we deal with it in Step 3 too
 		 */
    public function init_form_fields(){

      $this->form_fields = array(
        'enabled' => array(
          'title'       => 'Enable/Disable',
          'label'       => 'Enable Super Cool Payment Gateway',
          'type'        => 'checkbox',
          'description' => '',
          'default'     => 'no'
        ),
        'title' => array(
          'title'       => 'Title',
          'type'        => 'text',
          'description' => 'This controls the title which the user sees during checkout.',
          'default'     => 'Credit Card',
          'desc_tip'    => true,
        ),
        'description' => array(
          'title'       => 'Description',
          'type'        => 'textarea',
          'description' => 'This controls the description which the user sees during checkout.',
          'default'     => 'Pay with your credit card via our super-cool payment gateway.',
          'desc_tip'    => true,
        ),
        'testmode' => array(
          'title'       => 'Test mode',
          'label'       => 'Enable Test Mode',
          'type'        => 'checkbox',
          'description' => 'Place the payment gateway in test mode using test API keys.',
          'default'     => 'yes',
          'desc_tip'    => true,
        ),
        'api_domain_url' => array(
          'title'       => 'API domain URL',
          'type'        => 'text',
        ),
        'http_client_user_id' => array(
          'title'       => 'Client User ID',
          'type'        => 'text',
          'description' => 'For HTTP basic authentication',
          'desc_tip'    => true,
        ),
        'http_client_password' => array(
          'title'       => 'Client Password',
          'type'        => 'password',
          'description' => 'For HTTP basic authentication',
          'desc_tip'    => true,
        )
      );
    }

		/**
		 * You will need it if you want your custom credit card form, Step 4 is about it
		 */
		public function payment_fields() {

      // ok, let's display some description before the payment form
      if ( $this->description ) {
        // you can instructions for test mode, I mean test card numbers etc.
        if ( $this->testmode ) {
          $this->description .= ' TEST MODE ENABLED. In test mode, you can use the card numbers listed in <a href="#">documentation</a>.';
          $this->description  = trim( $this->description );
        }
        // display the description with <p> tags etc.
        echo wpautop( wp_kses_post( $this->description ) );
      }
    
      // I will echo() the form, but you can close PHP tags and print it directly in HTML
      echo '<fieldset id="wc-' . esc_attr( $this->id ) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
    
      // Add this action hook if you want your custom payment gateway to support it
      do_action( 'woocommerce_credit_card_form_start', $this->id );
    
      // I recommend to use inique IDs, because other gateways could already use #ccNo, #expdate, #cvv
      echo '<div class="form-row form-row-wide"><label>Card Number <span class="required">*</span></label>
        <input id="a_ccno" name="a_ccno" type="text" autocomplete="off" required="required">
        </div>
        <div class="form-row form-row-first">
          <label>Expiry Date <span class="required">*</span></label>
          <input id="a_expdate" name="a_expdate" type="text" autocomplete="off" placeholder="MM / YY" required="required">
        </div>
        <div class="form-row form-row-last">
          <label>Card Code (CVV) <span class="required">*</span></label>
          <input id="a_cvv" name="a_cvv" type="password" autocomplete="off" placeholder="CVV" required="required">
        </div>
        <div class="clear"></div>';
    
      do_action( 'woocommerce_credit_card_form_end', $this->id );
    
      echo '<div class="clear"></div></fieldset>';
				 
		}

		/*
		 * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
		 */
	 	public function payment_scripts() {

      // we need JavaScript to process a token only on cart/checkout pages, right?
      if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
        return;
      }

      // if our payment gateway is disabled, we do not have to enqueue JS too
      if ( 'no' === $this->enabled ) {
        return;
      }

      // no reason to enqueue JavaScript if API keys are not set
      if ( empty( $this->private_key ) || empty( $this->publishable_key ) ) {
        return;
      }

      // do not work with card detailes without SSL unless your website is in a test mode
      if ( ! $this->testmode && ! is_ssl() ) {
        return;
      }

      // let's suppose it is our payment processor JavaScript that allows to obtain a token
      wp_enqueue_script( 'custom_js', '/api/token.js' );

      // and this is our custom JS in your plugin directory that works with token.js
      wp_register_script( 'woocommerce_supercool', plugins_url( 'custom.js', __FILE__ ), array( 'jquery', 'custom_js' ) );

      // in most payment processors you have to use PUBLIC KEY to obtain a token
      wp_localize_script( 'woocommerce_supercool', 'supercool_params', array(
        'publishableKey' => $this->publishable_key
      ) );

      wp_enqueue_script( 'woocommerce_supercool' );
		
	 	}

		/*
 		 * Fields validation, more in Step 5
		 */
		public function validate_fields() {

		}

		/*
		 * We're processing the payments here, everything about it is in Step 5
		 */
		public function process_payment( $order_id ) {

      global $woocommerce;
    
      // we need it to get any order detailes
      $order = wc_get_order( $order_id );

      $body = [
        'trans_id'  => $order_id,
        'source_request'  => 'XX',
        'customer_info' => [
          "first_name" => $order->get_billing_first_name(),
          "last_name" => $order->get_billing_last_name(),
          "email" => $order->get_billing_email(),
          "mobile_number" => $order->get_billing_phone(),
          "dob" => '1980-01-01',
          "country" => $order->get_billing_country(),
          "state" => $order->get_billing_state(),
          "address" => $order->get_billing_address_1(),
          "city" => $order->get_billing_city(),
          "zipcode" => $order->get_billing_postcode(),
        ],
        'amount' => $order->get_total(),
        'currency' => $order->get_currency(),
        'card_num' => $_POST['a_ccno'],
        'expiry' => $_POST['a_expdate'],
        'cvv' => $_POST['a_cvv'],
        'name_on_card' => $order->get_billing_first_name().' '.$order->get_billing_last_name(),
        'wallet_address' => '',
        'customer_ip' => WC_Geolocation::get_ip_address(),
        'profile' => 0
      ];
      $body = wp_json_encode( $body );

      wc_add_notice( $body, 'error' );

      return;



      /*
      * Array with parameters for API interaction
      */
      $args = array(
        'body'    => $body,
        'method'  => 'POST',
        'headers' => array(
          'Authorization' => 'Basic ' . base64_encode( $this->api_http_client_user_id . ':' . $this->api_http_client_password ),
          'Content-Type' => 'application/json',
          'Accept' => '*/*',
          'Accept-Encoding' => 'gzip, deflate, br',
          'Connection' => 'keep-alive',
          'X-CBT-Client-Id' => '0tuZNMCHR9rn7LXhq6DP',
          'X-CBT-Client-Secret' => 'OiIez/z+jSgoD1lk1h/pf/QnB/q4PytKfRyh8RU16qc='
        )
      );
    
      /*
      * Your API interaction could be built with wp_remote_post()
      */
      $response = wp_remote_post( $this->api_domain_url, $args );
    
    
      if( !is_wp_error( $response ) ) {
    
        $body = json_decode( $response['body'], true );
    
        // it could be different depending on your payment processor
        if ( $body['response']['responseCode'] == 'APPROVED' ) {
    
          // we received the payment
          $order->payment_complete();
          $order->reduce_order_stock();
    
          // some notes to customer (replace true with false to make it private)
          $order->add_order_note( 'Hey, your order is paid! Thank you!', true );
    
          // Empty cart
          $woocommerce->cart->empty_cart();
    
          // Redirect to the thank you page
          return array(
            'result' => 'success',
            'redirect' => $this->get_return_url( $order )
          );
    
        } else {
          wc_add_notice( $response, 'error' );
          wc_add_notice( 'Please try again.', 'error' );
          return;
        }
    
      } else {
        wc_add_notice( 'Connection error.', 'error' );
        $error_string = $response->get_error_message();
        wc_add_notice( $error_string, 'error' );
        return;
      }
	 	}

		/*
		 * In case you need a webhook, like PayPal IPN etc
		 */
		public function webhook() {

      $order = wc_get_order( $_GET['id'] );
      $order->payment_complete();
      $order->reduce_order_stock();
    
      update_option('webhook_debug', $_GET);
					
	 	}
 	}
}