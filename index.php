<?php

/*
Plugin Name: پرداخت
Plugin URI: http://pardakht.ir/
Description: سرویس خرید و فروش پستی پرداخت (pardakht.ir) برای ووکامرس (پیش از نصب افزونه، از نصب و فعال بودن پلاگین های ووکامرس و ووکامرس فارسی مطمئن شوید)
Version: 2.1.1
Author: میلاد کیانی
Author URI: http://miladworld.ir/
*/

defined( 'ABSPATH' ) || exit;

require_once('epay.php');

$pardakhtShippingMethods = array(
	'PR_Pardakht_Pishtaz_Online_Method',
	'PR_Pardakht_Sefareshi_Online_Method',
	'PR_Pardakht_Peyk_Online_Method',
	'PR_Pardakht_Pishtaz_COD_Method',
	'PR_Pardakht_Sefareshi_COD_Method',
	'PR_Pardakht_Peyk_COD_Method'
);

$_pardakhtOnlineMethods = array(
	'pr_pardakht_pishtaz_online',
	'pr_pardakht_sefareshi_online',
	'pr_pardakht_peyk_online'
);
$_pardakhtCodMethods = array(
	'pr_pardakht_pishtaz_cod',
	'pr_pardakht_sefareshi_cod',
	'pr_pardakht_peyk_cod'
);
$_pardakhtShippingMethods = array_merge($_pardakhtOnlineMethods, $_pardakhtCodMethods);


add_action('plugins_loaded', 'woocommerce_pardakht_init', 0);

add_filter('woocommerce_available_payment_gateways', 'checkPaymentGateways');
function checkPaymentGateways($_availableGateways) {
	$session = WC()->session;
	$differentAddress = $session->get('shipping_to_different_address') == '1' ? true : false;
	$payment = $differentAddress ? $session->get('shipping_pardakht_payment') : $session->get('billing_pardakht_payment');
	$availableGateways = array();
	if($payment == 'online') {
		foreach($_availableGateways as $k=>$v) {
			if($k != 'cod') {
				$availableGateways[$k] = $v;
			}
		}
	} elseif($payment == 'cod') {
		foreach($_availableGateways as $k=>$v) {
			if($k == 'cod') {
				$availableGateways[$k] = $v;
			}
		}
	} else {
		foreach($_availableGateways as $k=>$v) {
			if($k != 'pardakht') {
				$availableGateways[$k] = $v;
			}
		}
	}
	return $availableGateways;
}
/* Self Deactive */
function pr_wc_admin_notice() {
	echo '<div class="error" style="direction: rtl;font-family: tahoma;font-size: 13px;"><p>پلاگین <strong>خرید و فروش پستی پرداخت</strong> غیر فعال شد. <strong>برای فعالسازی، پلاگین ووکامرس (Woocommerce) و ووکامرس فارسی (Persian Woocommerce) را نصب و فعال نمایید</strong>.</p></div>';
	if ( isset( $_GET['activate'] ) )
		unset( $_GET['activate'] );
}
function pr_wc_deactivate() {
    deactivate_plugins( plugin_basename( __FILE__ ) );
	pr_wc_admin_notice();
}
function pr_wc_deactive_check() {
	if(is_plugin_active(plugin_basename( __FILE__ ))) {
		if(!is_plugin_active('woocommerce/woocommerce.php')||!is_plugin_active('persian-woocommerce/woocommerce-persian.php')) {
			pr_wc_deactivate();
		}
	}
}

add_action( 'admin_init' , 'pr_wc_deactive_check' );
register_activation_hook( __FILE__, 'pr_wc_deactive_check' );

/* End Self Deactive */


$status_options = get_option( 'woocommerce_status_options', array() );
$status_options['shipping_debug_mode'] = true;
update_option( 'woocommerce_status_options', $status_options );

add_action( 'woocommerce_checkout_update_order_review', 'addShippingToSession', 10, 2 );
function addShippingToSession($post_data) {
	parse_str($post_data, $post_data);
	WC()->session->set('shipping_to_different_address', $post_data['shipping_different_address']);
	WC()->session->set('billing_pardakht_shipping', $post_data['billing_pardakht_shipping']);
	WC()->session->set('billing_pardakht_payment', $post_data['billing_pardakht_payment']);
	WC()->session->set('shipping_pardakht_shipping', $post_data['shipping_pardakht_shipping']);
	WC()->session->set('shipping_pardakht_payment', $post_data['shipping_pardakht_payment']);
	return true;
}

$pardakht_menu = get_option( 'pr_pardakht_settings' );
if ( $pardakht_menu !== false )
{
	define( 'PR_wsdl', $pardakht_menu['wsdl'] );
	define( 'PR_online_wsdl', $pardakht_menu['online_wsdl'] );
	define( 'PR_username', $pardakht_menu['username'] );
	define( 'PR_password', $pardakht_menu['password'] );
	define( 'PR_freesend_online', $pardakht_menu['free_send_online'] );
	define( 'PR_freesend', $pardakht_menu['free_send'] );
	define( 'PR_freeminamount', $pardakht_menu['free_min_amount'] );
	define( 'PR_freeminnumber', $pardakht_menu['free_min_number'] );
	define( 'PR_free_text', $pardakht_menu['free_text'] );
	define( 'PR_schedule_time', $pardakht_menu['schedule_time'] );
	define( 'PR_email', $pardakht_menu['email'] );
	define( 'PR_email_subject', $pardakht_menu['email_subject'] );
	define( 'PR_email_body', $pardakht_menu['email_body'] );
	$w_unit = strtolower( get_option('woocommerce_weight_unit') );
	define( 'PR_unit', $w_unit );

	define( 'PR_cantconnect', 'درحال حاضر اتصال به سرور پرداخت امکان پذیر نیست. لطفا دقایقی دیگر امتحان کنید.' );
}

function activate_PR_Pardakht_plugin()
{
	wp_schedule_event( time(), 'pr_pardakht_schedule_time', 'pr_pardakht_update_status' );

	$woocommerce_cod_settings_array = array(
		'enabled' => 'yes',
		'title' => 'پرداخت هنگام دریافت شرکت پرداخت',
		'description' => '',
		'instructions' => '',
		'enable_for_methods' => '',
		'enable_for_virtual' => 'yes'
	);

	$woocommerce_cod_settings = get_option( 'woocommerce_cod_settings' );
	if ( $woocommerce_cod_settings === false )
		add_option( 'woocommerce_cod_settings', $woocommerce_cod_settings_array, null, 'yes' );
	else
		update_option( 'woocommerce_cod_settings', $woocommerce_cod_settings_array );
}
register_activation_hook( __FILE__, 'activate_PR_Pardakht_plugin' );

function deactivate_PR_Pardakht_plugin()
{
	wp_clear_scheduled_hook( 'pr_pardakht_update_status' );
}
register_deactivation_hook( __FILE__, 'deactivate_PR_Pardakht_plugin' );

add_filter( 'cron_schedules', 'pr_pardakht_add_new_intervals' );
function pr_pardakht_add_new_intervals( $schedules )
{
	$schedules['pr_pardakht_schedule_time'] = array(
		'interval' => PR_schedule_time * 60,
		'display' => 'زمان بندی افزونه پرداخت'
	);

	return $schedules;
}

function pr_pardakht_update_status_func()
{
	return;
	global $wpdb;

	$results = $wpdb->get_results($wpdb->prepare("
		SELECT meta.meta_value, posts.ID FROM {$wpdb->posts} AS posts
		LEFT JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id
		LEFT JOIN {$wpdb->term_relationships} AS rel ON posts.ID=rel.object_ID
		LEFT JOIN {$wpdb->term_taxonomy} AS tax USING( term_taxonomy_id )
		LEFT JOIN {$wpdb->terms} AS term USING( term_id )

		WHERE 	meta.meta_key 		= '_pr_pardakht_order_id'
		AND     meta.meta_value     != ''
		AND 	posts.post_type 	= 'shop_order'
		AND 	posts.post_status 	IN ('wc-processing', 'wc-pending')
	"));

	if ( $results )
	{
		foreach( $results as $result )
		{
			$before_status = get_post_meta( $result->ID, '_pr_pardakht_status_code', true );

			try {
				$client = @new soapclient( PR_wsdl , array("exceptions" => 1) );
			} catch (Exception $e) {
				//
			}

			if ( !$client ) continue;

			$response_code = $client->GetStatus( $result->meta_value );
			$response_desc = '';
			if( strpos($response_code, ';') )
			{
				$res = explode( ';', $response_code );
				$response_code = $res[0];
				$response_desc = $res[1];
			}

			update_post_meta( $result->ID, '_pr_pardakht_status_code', $response_code );
			update_post_meta( $result->ID, '_pr_pardakht_status_desc', $response_desc );

			$response_factor = $client->GetFactorNumber( $result->meta_value, PR_username );
			update_post_meta( $result->ID, '_pr_pardakht_factor_code', $response_factor );

			$status = false;
			switch ( $response_code )
			{
				case '80':
					$status = 'completed';
					break;

				case '35':
				case '37':
				case '60':
					$status = 'refunded';
					break;

				case '16':
					$status = 'cancelled';
					break;
			}

			$order = new WC_Order( $result->ID );
			if ( $status )
			{
				$order->update_status( $status, 'وضعیت سفارش در سیستم پرداخت: ' . $response_code );
			}

			if ( PR_email == 'yes' )
			{
				if ( $before_status != $response_code )
				{
					$mailer = WC()->mailer();
					$site_title = get_bloginfo('name');
					$status = pr_pardakht_get_status_html( $response_code );

					$PR_email_subject = str_replace( '{site_title}', $site_title, PR_email_subject );
					$PR_email_body = str_replace( '{pardakht_order_status}', $status, PR_email_body );

					$mailer->send( $order->billing_email, $PR_email_subject, $mailer->wrap_message( $PR_email_subject, $PR_email_body ) );
				}
			}
		}
	}
}


$pardakht_payment = array('none'=>'انتخاب کنید','online'=>'پرداخت آنلاین','cod'=>'پرداخت در محل');

$pardakht_shipping = array('none'=>'انتخاب کنید','pishtaz'=>'پست پیشتاز','sefareshi'=>'پست سفارشی','peyk'=>'پیک');

function getStates()
{
	$states = include 'states.php';
	return $states;

}
function getCities() {
	$cities = include 'cities.php';
	return $cities;
}

add_action( 'plugins_loaded', 'pr_pardakht_woocommerce_init', 0 );
function pr_pardakht_woocommerce_init()
{
	add_action( 'woocommerce_shipping_init', 'pr_pardakht_shipping_method_init' );
	add_action( 'woocommerce_checkout_order_processed', 'pr_pardakht_save_order', 10, 2 );
	add_action( 'woocommerce_thankyou', 'pr_pardakht_show_invoice', 5 );
	add_action( 'woocommerce_admin_order_data_after_order_details', 'pr_pardakht_add_field_display_admin_order_details' );
	add_action( 'woocommerce_admin_order_data_after_billing_address', 'pr_pardakht_checkout_field_display_admin_order_billing' );
	add_action( 'woocommerce_admin_order_data_after_shipping_address', 'pr_pardakht_checkout_field_display_admin_order_shipping' );
	add_action( 'manage_shop_order_posts_custom_column', 'pr_pardakht_custom_order_column_values', 2 );
	add_action( 'wp_ajax_pr_pardakht_set_status', 'pr_pardakht_set_status' );
	add_action( 'pr_pardakht_update_status', 'pr_pardakht_update_status_func' );
	add_action( 'woocommerce_before_checkout_form', 'pr_before_checkout_form' );
	add_action( 'woocommerce_payment_complete', 'pr_payment_complete' );
	function sendPardakhtMail($order, $response_code)
	{
		$mailer = WC()->mailer();
		$site_title = get_bloginfo('name');
		$status = pr_pardakht_get_status_html( $response_code );

		$PR_email_subject = str_replace( '{site_title}', $site_title, PR_email_subject );
		$PR_email_body = str_replace( '{pardakht_order_status}', $status, PR_email_body );

		$mailer->send( $order->billing_email, $PR_email_subject, $mailer->wrap_message( $PR_email_subject, $PR_email_body ) );
	}

	/**
	 * get status from response code when calling get status method from webservice
	 */
	function getStatusFromResponseCode($response_code)
	{
		switch ( $response_code )
		{
			case '80':
				return 'completed';
				break;
			case '35':
			case '37':
			case '60':
				return 'refunded';
				break;
			case '16':
				return 'cancelled';
				break;
			default:
				return false;
		}
	}
	function pr_payment_complete($orderId)
	{
		$order = new WC_Order($orderId);

		$shippingMethod = array_values($order->get_shipping_methods());
		$shippingMethod = $shippingMethod[0]['method_id'];

		$paymentMethod = get_post_meta($orderId, '_payment_method', true);
		if(strpos($shippingMethod, 'pardakht') === false || $paymentMethod == 'pardakht' || $paymentMethod == 'cod') {
			return;
		}
		try {
			$client = @new soapclient( PR_wsdl , array("exceptions" => 1) );
		} catch (Exception $e) {
			//
		}
		if ( !$client ) {
			return;
		}
		if($order->is_paid()) {
			$session          = WC()->session;
			$differentAddress = $session->get( 'shipping_to_different_address' );
			$differentAddress = $differentAddress == 1 ? true : false;
			$customer         = $session->get( 'customer' );
			$firstName        = get_post_meta($orderId, '_shipping_first_name', true);
			$lastName         = get_post_meta($orderId, '_shipping_last_name', true);
			$gender           = get_post_meta($orderId, '_shipping_gender', true);
			$address          = get_post_meta($orderId, '_shipping_address_1', true);
			$address2         = get_post_meta($orderId, '_shipping_address_2', true);
			if(!empty($address2)) {
				$address .= ' - ' . $address2;
			}
			$zip      = get_post_meta($orderId, '_shipping_postcode', true);
			$city     = $differentAddress ? $customer['shipping_city'] : $customer['city'];
			$province = $differentAddress ? $customer['shipping_state'] : $customer['state'];

			$shipping = get_post_meta($orderId, '_shipping_pardakht_shipping', true);
			switch ( $shipping ) {
				case 'pishtaz':
					$delivery = 1;
					break;
				case 'sefareshi':
					$delivery = 2;
					break;
				case 'peyk':
					$delivery = 8;
					break;
				default:
					throw new Exception( 'wrong delivery method' );
			}

			$details = array(
				'Name'         => $firstName,
				'Family'       => $lastName,
				'Gender'       => !empty($gender) ? $gender : 1,
				'Email'        => $order->billing_email,
				'Address'      => $address,
				'ZIP'          => $zip,
				'Tel'          => $order->billing_phone,
				'MobileNum'    => $order->billing_phone,
				'Message'      => $order->customer_note,
				'DeliveryTime' => 12,
				'ID'           => PR_username,
				'Pass'         => md5( PR_password ),
				'City'         => $city,
				'Province'     => $province,
				'Delivery'     => $delivery,
				'Products'     => pr_prepare_products_string( $client, $order, $delivery, $city ),
			);

			$result = $client->AddOrderEpayment($details['Name'],
											    $details['Family'],
												$details['Gender'],
												$details['Email'],
												$details['Address'],
												$details['ZIP'],
												$details['Tel'],
												$details['MobileNum'],
												$details['Message'],
												$details['DeliveryTime'],
												$details['ID'],
												$details['Pass'],
												$details['City'],
												$details['Province'],
												$details['Delivery'],
												$details['Products']);
		}
	}

	function pr_prepare_products_array($order)
	{
		$unit = ( PR_unit == 'g' ) ? 1 : 1000;

		$products = array();
		$pr_price = $pr_weight = 0;
		foreach ( $order->get_items() as $item )
		{
			if ( $item['product_id'] > 0 )
			{
				$_product = $order->get_product_from_item( $item );

				$quantity = (int) $item['qty'];

				$weight = intval( $_product->weight * $unit );

				$price  = $order->get_item_total( $item );
				$price = ( get_woocommerce_currency() == "IRT" ) ? (int) $price * 10 : (int) $price;

				$pr_price += $price * $quantity;
				$pr_weight += $weight;

				$products[] = array( $_product->get_title(), $quantity, $weight, $price, 'pWP'.$item['product_id'] );
			}
		}
		return array(
			'products' => $products,
			'price' => $pr_price,
			'weight' => $pr_weight,
		);
	}

	/**
	 * register a product and prepare its string
	 * @param $order
	 * @param $client
     * @return array
	 */
	function pr_prepare_products_string($client, $order, $delivery, $city)
	{
		global $woocommerce;
		$unit = ( PR_unit == 'g' ) ? 1 : 1000;

		$products = '';
		$totalWeight = 0;
		$totalPrice = 0;
		foreach ( $order->get_items() as $item )
		{
			if ( $item['product_id'] > 0 )
			{
				$_product = $order->get_product_from_item( $item );

				$quantity = (int) $item['qty'];

				$weight = intval( $_product->weight * $unit );
				$totalWeight += $weight;

				$price = $order->get_item_total( $item );
				$price = ( get_woocommerce_currency() == "IRT" ) ? (int) $price * 10 : (int) $price;
				$totalPrice += $price * $quantity;

				$client->AddProduct(PR_username, md5( PR_password ), $_product->get_title(), 1, $price, $weight , 'pWP'.$item['product_id']);

				$products .= 'pWP'.$item['product_id'] . '^' . $quantity . ';';
			}
		}
		if(pr_pardakht_is_free_send($woocommerce) || pr_pardakht_is_free_send_online($woocommerce)) {
			$new_data = array(
				'price' => $totalPrice,
				'weight' => $totalWeight,
				'deliveryID' => $delivery,
				'paymentID' => 2,
				'destinationCityID' => $city,
			);
			$response = inquiryDeliveryPriceByUsername( $new_data );
			if(intval($response) > 0) {
				$products .= 'discount=' . intval($response) . ';';
			}
		}
		return $products;
	}

	add_filter( 'woocommerce_cart_shipping_method_full_label', 'pr_pardakht_remove_free_text', 10, 2 );
	add_filter( 'woocommerce_states', 'pr_pardakht_woocommerce_states' );
	add_filter( 'woocommerce_default_address_fields' , 'pr_pardakht_override_default_address_fields' );
	add_filter( 'woocommerce_checkout_fields' , 'pr_pardakht_add_to_checkout_fields' );
	add_filter( 'manage_edit-shop_order_columns', 'pr_pardakht_custom_order_column' );

	function pr_before_checkout_form() {
		echo <<<HEREDOC
<style>
	.woocommerce form .form-row-last, .woocommerce-page form .form-row-last {
	    float: left;
	}
	.woocommerce form .form-row-first, .woocommerce-page form .form-row-first {
	    float: right;
	}
	.woocommerce form input[name="billing_country"], .woocommerce form input[name="shipping_country"] {
		display: none;
	}
</style>
HEREDOC;
	}

	function pr_pardakht_shipping_method_init()
	{

		if ( class_exists( 'WC_Shipping_Method' ) ) {
			require_once('PardakhtSefareshiOnlineMethod.php');
			require_once('PardakhtPishtazOnlineMethod.php');
			require_once('PardakhtSefareshiCODMethod.php');
			require_once('PardakhtPeykOnlineMethod.php');
			require_once('PardakhtPishtazCODMethod.php');
			require_once('PardakhtPeykCODMethod.php');
		}

		function pr_add_pardakht_shipping_method( $methods )
		{
			global $pardakhtShippingMethods;
			if(!is_null(WC()->session)) {
				$session = WC()->session;
				$differentAddress = $session->get('shipping_to_different_address') == '1' ? true : false;
				$payment = $differentAddress ? $session->get('shipping_pardakht_payment') : $session->get('billing_pardakht_payment');
				$shipping = $differentAddress ? $session->get('shipping_pardakht_shipping') : $session->get('billing_pardakht_shipping');
				switch($payment) {
					case 'online':
						switch($shipping) {
							case 'pishtaz':
								$methods[] = 'PR_Pardakht_Pishtaz_Online_Method';
							break;
							case 'sefareshi':
								$methods[] = 'PR_Pardakht_Sefareshi_Online_Method';
							break;
							case 'peyk':
								$methods[] = 'PR_Pardakht_Peyk_Online_Method';
							break;
						}
					break;
					case 'cod':
						switch($shipping) {
							case 'pishtaz':
								$methods[] = 'PR_Pardakht_Pishtaz_COD_Method';
							break;
							case 'sefareshi':
								$methods[] = 'PR_Pardakht_Sefareshi_COD_Method';
							break;
							case 'peyk':
								$methods[] = 'PR_Pardakht_Peyk_COD_Method';
							break;
						}

					break;
				}
			} else {
				$methods = array_merge($methods, $pardakhtShippingMethods);
			}
			return $methods;
		}
		add_filter( 'woocommerce_shipping_methods', 'pr_add_pardakht_shipping_method' );
	}

	function pr_pardakht_save_order( $id, $posted )
	{
		global $woocommerce, $pardakht_shipping, $pardakht_payment, $_pardakhtShippingMethods, $_pardakhtCodMethods, $_pardakhtOnlineMethods;

		$city_array =  getCities();
		$state_array =  getStates();
		$order = new WC_Order( $id );

		if( !is_object( $order ) )
			return;

		$is_pardakht = false; 
		if ( $order->shipping_method )
		{
			if( in_array( $order->shipping_method, $_pardakhtShippingMethods ) )
			{
				$is_pardakht = true;
				$shipping_methods = $order->shipping_method;
			}
		}
		else
		{
			$shipping_s = $order->get_shipping_methods();

			foreach ( $shipping_s as $shipping )
			{
				if( in_array( $shipping['method_id'], $_pardakhtShippingMethods ) )
				{
					$is_pardakht = true;
					$shipping_methods = $shipping['method_id'];
					break;
				}
			}
		}

		if( !$is_pardakht
//		    || $order->payment_method != 'cod'
		) /* @TODO pardakht */
			return;

		if( $shipping_methods == 'pr_pardakht_pishtaz_cod' || $shipping_methods == 'pr_pardakht_pishtaz_online' )
			$delivery = '1';
		elseif( $shipping_methods == 'pr_pardakht_sefareshi_cod' || $shipping_methods == 'pr_pardakht_sefareshi_online' )
			$delivery = '2';
		elseif( $shipping_methods == 'pr_pardakht_peyk_cod' || $shipping_methods == 'pr_pardakht_peyk_online' )
			$delivery = '8';

		if( in_array($shipping_methods, $_pardakhtCodMethods) )
			$payment = '1';
		elseif( in_array($shipping_methods, $_pardakhtOnlineMethods) )
			$payment = '2';

		$products_info = pr_prepare_products_array($order);

		$products = $products_info['products'];

		$customer_city = $order->shipping_city;
		if( !$customer_city || $customer_city <= 0 )
			return false;

		$customer_state = $order->shipping_state;
		if( !$customer_state || $customer_state <= 0 )
			return false;
		


		if ( $posted['ship_to_different_address'] )
		{
			$first_name = $order->shipping_first_name;
			$last_name = $order->shipping_last_name;
			$gender = $order->shipping_gender;
			$address = $order->shipping_address_1 . ' - ' . $order->shipping_address_2;
			$zip = $order->shipping_postcode;
			$mobile = $order->shipping_mobile;
		}
		else
		{
			$first_name = $order->billing_first_name;
			$last_name = $order->billing_last_name;
			$gender = $order->billing_gender;
			$address = $order->billing_address_1 . ' - ' . $order->billing_address_2;
			$zip = $order->billing_postcode;
			$mobile = $order->billing_mobile;
		}
		$data = array(
			'Name' => $first_name,
			'Family' => $last_name,
			'Gender' => $gender,
			'Email' => $order->billing_email,
			'Address' => $address,
			'ZIP' => $zip,
			'Tel' => $order->billing_phone,
			'MobileNum' => $mobile,
			'Message' => $order->customer_note,
			'DeliveryTime' => '12',
			'ID' => PR_username,
			'Pass' => md5( PR_password ),
			'City' => $customer_city,
			'Province' => $customer_state,
			'Delivery' => $delivery,
			'Products' => $products,
			'PaymentMethod' => $payment,
		);
		try {
			$client = @new soapclient( PR_wsdl , array("exceptions" => 1) );
		} catch (Exception $e) {
			throw new Exception(PR_cantconnect, 1);
		}

		$discount = 0;
		if( pr_pardakht_is_free_send($woocommerce) === true || ($payment == 2 && pr_pardakht_is_free_send_online($woocommerce) === true))
		{
			$new_data = array(
				'price' => $products_info['price'],
				'weight' => $products_info['weight'],
				'deliveryID' => $data['Delivery'],
				'paymentID' => $data['PaymentMethod'],
				'destinationCityID' => $data['City'],
			);
			$response = inquiryDeliveryPriceByUsername( $new_data );	

			$discount = ($response == false) ? 0 : $response;
		}
		$data['discount'] = $discount;
		if($order->payment_method == 'pardakht' || $order->payment_method == 'cod') {
			$response = $client->AddOrderByProduct( $data['Name'], $data['Family'], $data['Gender'], $data['Email'], $data['Address'], $data['ZIP'], $data['Tel'], $data['MobileNum'], $data['Message'], $data['DeliveryTime'], $data['ID'], $data['Pass'], $data['City'], $data['Province'], $data['Delivery'], $data['Products'], $data['discount'], $data['PaymentMethod'] );
			if ( !in_array( $response, array( '10', '54', '56', '13', '14', '15', '16', '17', '18', '62', '51', '52', '53', '60' ) ) && !$response->faultcode )
			{
				// response is Ok
				update_post_meta( $id, '_billing_city', $city_array[$customer_city] );
				update_post_meta( $id, '_billing_state', $state_array[$customer_state] );

				update_post_meta( $id, '_shipping_city', $city_array[$customer_city] );
				update_post_meta( $id, '_shipping_state', $state_array[$customer_state] );
				update_post_meta( $id, '_pardakht_shipping', $pardakht_shipping[$delivery] );
				update_post_meta( $id, '_pardakht_payment', $pardakht_payment[$payment] );

				update_post_meta( $id, '_billing_country', 'IR' );
				update_post_meta( $id, '_shipping_country', 'IR' );
				$pardakht_order_id = explode( '/', $response );
				update_post_meta( $id, '_pr_pardakht_order_id', $pardakht_order_id[1] );

				$response_code = $client->GetStatus( $pardakht_order_id[1] );
				$response_desc = '';
				if( strpos($response_code, ';') )
				{
					$res = explode( ';', $response_code );
					$response_code = $res[0];
					$response_desc = $res[1];
				}

				update_post_meta( $id, '_pr_pardakht_status_code', $response_code );
				update_post_meta( $id, '_pr_pardakht_status_desc', $response_desc );

				$response_factor = $client->GetFactorNumber( $pardakht_order_id[1], PR_username );
				update_post_meta( $id, '_pr_pardakht_factor_code', $response_factor );
			}
			else
			{
				switch($response)
				{
					case '10':
						$result='شما مجاز به استفاده از این متد نیستید.';
						break;
					case '54':
						$result='نام کاربری یا رمز عبور معتبر نمی باشد.';
						break;
					case '56':
						$result='فروشگاه فعال نمی باشد .';
						break;
					case '13':
						$result='تعداد محصول معتبر نمی باشد.';
						break;
					case '14':
						$result='مبلغ محصول معتبر نمی باشد .';
						break;
					case '15':
						$result='شهر مقصد معتبر نمی باشد.';
						break;
					case '16':
						$result=' نحوه ارسال معتبر نمی باشد.';
						break;
					case '17':
						$result='کدپستی معتبر نمی باشد.';
						break;
					case '18':
						$result='کد محصول معتبر نمی باشد.';
						break;
					case '62':
						$result='آرایه ورودی محصولات معتبر نمی باشد.';
						break;
					case '51':
						$result='عنوان محصول معتبر نمی باشد.';
						break;
					case '52':
						$result='دسته بندی محصول معتبر نمی باشد.';
						break;
					case '53':
						$result='وزن محصول معتبر نمی باشد.';
						break;
					case '60':
						$result='جهت افزودن محصولات بیشتر نیاز به ارتقای پنل دارید.';
						break;
					default:
						if( $response->faultcode )
							$result = $response->faultstring;
						break;
				}
				$order->update_status( 'failed', 'خطای ثبت در سیستم پرداخت : ' . $result );
				$order->add_order_note( 'خطای ثبت در سیستم پرداخت : ' . $result );
				throw new Exception('خطای ثبت در سیستم پرداخت : ' . $result, 1);
			}

		}

	}

	function pr_pardakht_show_invoice( $order_id )
	{
		$order = new WC_Order( $order_id );
		if($order->has_shipping_method('pr_pardakht_sefareshi_cod') || $order->has_shipping_method('pr_pardakht_pishtaz_cod') || $order->has_shipping_method('pr_pardakht_peyk_cod'))
		{
			$pardakht_order_id = get_post_meta( $order_id, '_pr_pardakht_order_id', true );

			if( empty( $pardakht_order_id ) )
				return;

			$html = '<p>شماره سفارش پرداخت:<br>' . $pardakht_order_id . '</p>';
			$html .= '<p>جهت پیگیری سفارش می توانید به سایت <a href="http://server1.pardakht.ir/?pg=productOrderTrack">pardakht.ir</a> مراجعه کنید.</p>';

			echo $html;
			return;
		}
	}

	function pr_pardakht_remove_free_text( $full_label, $method )
	{
		global $woocommerce, $_pardakhtShippingMethods, $_pardakhtOnlineMethods;
		
		$shipping_city = $woocommerce->customer->city;

		if( !in_array( $method->id, $_pardakhtShippingMethods ) )
			return $full_label;

		if( empty( $shipping_city ) )
			return $method->label;

		if( pr_pardakht_is_free_send($woocommerce) === true || (in_array($method->id, $_pardakhtOnlineMethods) && pr_pardakht_is_free_send_online($woocommerce) === true) )
		{
			$full_label =  str_replace( __( 'Free', 'woocommerce' ), PR_free_text, $full_label );
			$full_label =  str_replace( __( 'Free!', 'woocommerce' ), PR_free_text, $full_label );
		}
		
		return $full_label;
	}

	function pr_pardakht_woocommerce_states( $states ) 
	{
		return array('IR'=>getStates());
	}

	function pr_pardakht_override_default_address_fields( $address_fields )
	{
		global $pardakht_payment, $pardakht_shipping;

		$peyk_cod = get_option( 'woocommerce_pr_pardakht_peyk_cod_settings' );
		$peyk_online = get_option( 'woocommerce_pr_pardakht_peyk_online_settings' );
		
		$pishtaz_cod = get_option( 'woocommerce_pr_pardakht_pishtaz_cod_settings' );
		$pishtaz_online = get_option( 'woocommerce_pr_pardakht_pishtaz_online_settings' );
		
		$sefareshi_cod = get_option( 'woocommerce_pr_pardakht_sefareshi_cod_settings' );
		$sefareshi_online = get_option( 'woocommerce_pr_pardakht_sefareshi_online_settings' );
		
		if(is_array($peyk_cod)&&is_array($peyk_online)) {
			if($peyk_cod['enabled']=='no'&&$peyk_online['enabled']=='no') {
				unset($pardakht_shipping['peyk']);
			}
		}
		if(is_array($pishtaz_cod)&&is_array($pishtaz_online)) {
			if($pishtaz_cod['enabled']=='no'&&$pishtaz_online['enabled']=='no') {
				unset($pardakht_shipping['pishtaz']);
			}
		}
		if(is_array($sefareshi_cod)&&is_array($sefareshi_online)) {
			if($sefareshi_cod['enabled']=='no'&&$sefareshi_online['enabled']=='no') {
				unset($pardakht_shipping['sefareshi']);
			}
		}
		
		
		if(is_array($peyk_cod)&&is_array($pishtaz_cod)&&is_array($sefareshi_cod)) {
			if($peyk_cod['enabled']=='no'&&$pishtaz_cod['enabled']=='no'&&$sefareshi_cod['enabled']=='no') {
				unset($pardakht_payment['cod']);
			}
		}
		if(is_array($peyk_online)&&is_array($pishtaz_online)&&is_array($sefareshi_online)) {
			if($peyk_online['enabled']=='no'&&$pishtaz_online['enabled']=='no'&&$sefareshi_online['enabled']=='no') {
				unset($pardakht_payment['online']);
			}
		}
		$new_fields = array(
			'first_name' => $address_fields['first_name'],
			'last_name' => $address_fields['last_name'],
			'company' => $address_fields['company'],
			'address_1' => $address_fields['address_1'],
			'address_2' => $address_fields['address_2'],
			'state' => array(
				'type' => 'select',
				'label' => 'استان',
				'class' => array('form-row-first'),
				'options' => getStates(),
			),
			'city' => array(
				'type' => 'select',
				'label' => 'شهر',
				'class' => array('form-row-wide'),
				'options' => array('0'=>'لطفا استان خود را انتخاب کنيد'),
			),
			'postcode' => $address_fields['postcode'],
			'pardakht_shipping' => array(
				'type' => 'select',
				'label' => 'روش ارسال',
				'required' => true,
				'class' => array('form-row-first','update_totals_on_change', 'address-field'),
				'options' => $pardakht_shipping,
			),
			'pardakht_payment' => array(
				'type' => 'select',
				'label' => 'روش پرداخت',
				'required' => true,
				'class' => array('form-row-first','update_totals_on_change', 'address-field'),
				'options' => $pardakht_payment,
			),
			'country' => array(
				'type' => 'text',
				'value' => 'IR',
			),
			'gender' => array(
				'type' => 'select',
				'label' => 'جنسیت',
				'required' => true,
				'class' => array( 'form-row-first' ),
				'options' => array( '1' => 'مذکر', '2' => 'مونث' ),
			),
			'mobile' => array(
				'type' => 'text',
				'label' => 'شماره موبایل',
				'required' => true,
				'class' => array( 'form-row-last' ),
				'clear' => true,
			)
		);
		if(isset(WC()->session)) {
			$session = WC()->session;
			$customerData = $session->get('customer');
			$dif = $session->get('shipping_to_different_address');
			$city = $dif == 1 ? intval($customerData['shipping_city']) : intval($customerData['city']);
			if($city != 0) {
				$new_fields['last_city'] = array(
					'type' => 'text',
					'label' => $city,
					'class' =>array('hide'),
				);
			}

		}

		return $new_fields;
	}

	function pr_pardakht_add_to_checkout_fields( $fields )
	{
		$fields['billing']['billing_phone']['label'] = 'شماره تلفن';
		$fields['billing']['billing_city']['class'] = array( 'address-field', 'update_totals_on_change' );
		$fields['shipping']['shipping_city']['class'] = array( 'address-field', 'update_totals_on_change' );

		return $fields;
	}

	function pr_pardakht_add_field_display_admin_order_details( $order )
	{
		$pardakht_order_id = get_post_meta( $order->id, '_pr_pardakht_order_id', true );

		if( empty($pardakht_order_id) )
			return;

		echo '<p class="form-field form-field-wide"><strong>شماره سفارش پرداخت:</strong><br>' . $pardakht_order_id . '</p>';
	}

	function pr_pardakht_checkout_field_display_admin_order_billing( $order )
	{
		echo '<p><strong>شماره موبایل:</strong><br>' . get_post_meta( $order->id, '_billing_mobile', true ) . '</p>';
	}
	
	function pr_pardakht_checkout_field_display_admin_order_shipping( $order )
	{
		echo '<p><strong>شماره موبایل:</strong><br>' . get_post_meta( $order->id, '_shipping_mobile', true ) . '</p>';
	}

	wp_enqueue_script( 'hw-pardakht', untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/js/script.js', array('jquery') );

	function pr_pardakht_custom_order_column( $columns )
	{
		$new_columns = ( is_array($columns) ) ? $columns : array();
		unset( $new_columns['order_actions'] );

		$new_columns['pr_pardakht_status'] = 'وضعیت سفارش در پرداخت';
		$new_columns['pr_pardakht_order'] = 'شماره سفارش';
		$new_columns['pr_pardakht_factor'] = 'کد رهگیری پستی';

		$new_columns['order_actions'] = $columns['order_actions'];
		return $new_columns;
	}

	function pr_pardakht_custom_order_column_values( $column )
	{
		global $post;
		$data = get_post_meta( $post->ID );

		if ( $column == 'pr_pardakht_status' )
		{
			$pardakht_status_code = (isset($data['_pr_pardakht_status_code'][0]) ? $data['_pr_pardakht_status_code'][0] : '');
			$pardakht_status_desc = '<br>' . (isset($data['_pr_pardakht_status_desc'][0]) ? $data['_pr_pardakht_status_desc'][0] : '');

			echo pr_pardakht_get_status_html( $pardakht_status_code, $pardakht_status_desc, $post->ID );
		}

		if ( $column == 'pr_pardakht_order' )
		{
			$pardakht_order_code = (isset($data['_pr_pardakht_order_id'][0]) ? $data['_pr_pardakht_order_id'][0] : '');
			
			echo $pardakht_order_code;
		}

		if ( $column == 'pr_pardakht_factor' )
		{
			$pardakht_factor_code = (isset($data['_pr_pardakht_factor_code'][0]) ? $data['_pr_pardakht_factor_code'][0] : '');
			
			switch ( $pardakht_factor_code )
			{
				case '46':
					$pardakht_factor_code = 'شماره سفارش معتبر نمی باشد';
					break;

				case '59':
					$pardakht_factor_code = 'سفارش موردنظر دارای بارکد پستی نمی باشد';
					break;
			}

			echo $pardakht_factor_code;
		}
	}

	function pr_pardakht_set_status()
	{
		$postid = $_POST['postid'];
		$status = $_POST['status'];

		$message = $error = '';

		if ( !isset( $postid ) || !in_array( $status, array( '30', '40', '20', '16' ) ) )
			return;

		$orderid = get_post_meta( $postid, '_pr_pardakht_order_id', true );

		try {
			$client = @new soapclient( PR_wsdl , array("exceptions" => 1) );
		} catch (Exception $e) {
			return;
		}
		$response = $client->UpdateStatus( $orderid, $status, PR_username, PR_password );

		switch( $response )
		{
			case '1':
				$message = 'تغییر وضعیت سفارش با موفقیت انجام شد';
				break;
			case '54':
				$message = 'خطا! نام کاربری یا رمز عبور معتبر نمی باشد';
				break;
			case '56':
				$message = 'خطا! فروشگاه فعال نمی باشد';
				break;
			case '46':
				$message = 'خطا! شماره سفارش معتبر نمی باشد';
				break;
			case '58':
				$message = 'خطا! بارکد پستی مربوط به سفارش موردنظر توسط پست ایجاد نشده و در حال حاضر امکان تغییر وضعیت وجود ندارد';
				break;
			case '48':
				$message = 'خطا! وضعیت سفارش جهت تغییر وضعیت مجاز نمی باشد.(امکان تغییر وضعیت تنها برای سفارشات معلق به وضعیت آماده ارسال یا لغو شده وجود دارد)';
				break;
			case '70':
				$message = 'خطا! با توجه به اینکه برای فروشگاه های لجستیک تغییر وضعیت از سوی سامانه پرداخت انجام می شود ، تغییر وضعیت این سفارش مجاز نمی باشد';
				break;
			
			default:
                $message = 'خطا!';
				break;
		}

		echo json_encode( array('message' => $message) );
		die();
	}

	function inquiryDeliveryPriceByUsername( $data )
	{
		try {
			$client = @new soapclient( PR_wsdl , array("exceptions" => 1) );
		} catch (Exception $e) {
			throw new Exception(PR_cantconnect, 1);
		}
		if ( !$client ) return false;
		try {
			$response = $client->InquiryDeliveryPriceByUsername( PR_username, md5( PR_password ), intval($data['price']), intval($data['weight']), intval($data['deliveryID']), intval($data['paymentID']), intval($data['destinationCityID']) );
		} catch(Exception $e) {
			global $error;
			$error = "خطایی روی داد. لطفاً به مدیر سایت اطلاع دهید. کد خطا: -۲ <!--$e-->";
			add_filter("woocommerce_cart_no_shipping_available_html",function() { global $error; return $error;});
			add_filter("woocommerce_no_shipping_available_html",function() { global $error; return $error;});
			return false;
		}
		if(!empty($response)&&!isset($response->faultcode)&&@strstr($response,"^")!==false) {
			$response = explode( '^', $response );
			if(count($response)==4) {
				return $response[0] + $response[2] + $response[3];
			}
			else {
				$error = "خطایی روی داد. لطفاً به مدیر سایت اطلاع دهید. کد خطا: -1<!--تعداد پارامتر های ارسالی از سرور اشتباه است-->";
				add_filter("woocommerce_cart_no_shipping_available_html",function() { global $error; return $error;});
				add_filter("woocommerce_no_shipping_available_html",function() { global $error; return $error;});
				return false;
			}
		} else {

			add_filter("woocommerce_cart_no_shipping_available_html","returnError");
			add_filter("woocommerce_no_shipping_available_html","returnError");
			global $newresp;
			$newresp = $response;
			function returnError() {
				global $newresp;
				$str = 'خطای نامشخص: ' . $newresp;
				switch(intval($newresp)) {
					case 54:
						$str = 'خطایی روی داد. لطفاً به مدیر سایت اطلاع دهید. کد خطا: 54<!--نام کاربری یا رمز عبور معتبر نمی باشد-->';
						break;
					case 63:
						$str = 'خطایی روی داد. لطفاً به مدیر سایت اطلاع دهید. کد خطا: 63<!--پارامترهای ورودی معتبر نمی باشد-->';
						break;
					case 64:
						$str = 'خطایی روی داد. لطفاً به مدیر سایت اطلاع دهید. کد خطا: 64<!--امکان محاسبه هزینه ارسال وجود ندارد-->';
						break;
					case 15:
						$str = 'شهر مقصد معتبر نمی باشد.<!--کد خطا 15-->';
						break;
					case 16:
						$str = 'نحوه ارسال معتبر نمی باشد.<!--کد خطا: 16-->';
						break;
					case 65:
						$str = 'نحوه پرداخت معتبر نمی باشد.<!--کد خطا: 65-->';
						break;
					case 71:
						$str = 'نحوه ارسال پیک در شهر موردنظر فعال نمی باشد.<!--کد خطا: 71-->';
						break;
					case 72:
						$str = 'نحوه ارسال موردنظر برای فروشگاه فعال نمی باشد<!--کد خطا: 72-->';
						break;
					case 73:
						$str = 'در شهر مورد نظر فقط ارسال با پیک فعال می باشد.<!--کد خطا: 73-->';
						break;
				}
				return $str;
			}

			return false;
		}
	}

	function pr_pardakht_is_free_send( $wc )
	{
		$is_freesend = false;

		if( PR_freesend == 'yes' )
		{
			if( PR_freeminamount > 0 && PR_freeminnumber > 0 && $wc->cart->subtotal >= PR_freeminamount && $wc->cart->cart_contents_count >= PR_freeminnumber )
				$is_freesend = true;
			elseif( PR_freeminamount > 0 && PR_freeminnumber <= 0 && $wc->cart->subtotal >= PR_freeminamount )
				$is_freesend = true;
			elseif( PR_freeminamount <= 0 && PR_freeminnumber > 0 && $wc->cart->cart_contents_count >= PR_freeminnumber )
				$is_freesend = true;
		}

		return $is_freesend;
	}

	function pr_pardakht_is_free_send_online( $wc )
	{
		$is_freesend = false;

		if( PR_freesend_online == 'yes' )
		{
			if( PR_freeminamount > 0 && PR_freeminnumber > 0 && $wc->cart->subtotal >= PR_freeminamount && $wc->cart->cart_contents_count >= PR_freeminnumber )
				$is_freesend = true;
			elseif( PR_freeminamount > 0 && PR_freeminnumber <= 0 && $wc->cart->subtotal >= PR_freeminamount )
				$is_freesend = true;
			elseif( PR_freeminamount <= 0 && PR_freeminnumber > 0 && $wc->cart->cart_contents_count >= PR_freeminnumber )
				$is_freesend = true;
		}

		return $is_freesend;
	}

	function pr_pardakht_get_status_html( $pardakht_status_code, $pardakht_status_desc = '', $postID = false )
	{
		$text = '';
		switch( $pardakht_status_code )
		{
			case '46':
				$text = 'شماره سفارش معتبر نمی باشد' . $pardakht_status_desc;
				break;
			case '1':
				$text = 'در حال ثبت' . $pardakht_status_desc;
				break;
			case '16':
				$text = 'در حال بررسی رد شده' . $pardakht_status_desc;
				break;
			case '17':
				$text = 'در حال بررسی' . $pardakht_status_desc;
				if ($postID):
				$text .= '<br><a href="#" class="button pr_pardakht_set_status" pr_postid="'.$postID.'" pr_status="20">معلق</a>';
				$text .= '<br><a href="#" class="button pr_pardakht_set_status" pr_postid="'.$postID.'" pr_status="16">لغو</a>';
				endif;
				break;
			case '20':
				$text = 'معلق' . $pardakht_status_desc;
				if ($postID):
				$text .= '<br><a href="#" class="button pr_pardakht_set_status" pr_postid="'.$postID.'" pr_status="30">آماده به ارسال</a>';
				$text .= '<br><a href="#" class="button pr_pardakht_set_status" pr_postid="'.$postID.'" pr_status="40">لغو</a>';
				endif;
				break;
			case '30':
				$text = 'آماده به ارسال' . $pardakht_status_desc;
				break;
			case '50':
				$text = 'دریافتی' . $pardakht_status_desc;
				break;
			case '55':
				$text = 'پیش برگشتی' . $pardakht_status_desc;
				break;
			case '60':
				$text = 'برگشتی' . $pardakht_status_desc;
				break;
			case '70':
				$text = 'توزیع شده' . $pardakht_status_desc;
				break;
			case '80':
				$text = 'وصولی' . $pardakht_status_desc;
				break;
			case '40':
				$text = 'انصرافی' . $pardakht_status_desc;
				break;
			case '52':
				$text = 'معطله' . $pardakht_status_desc;
				break;
			case '25':
				$text = 'تایید شده' . $pardakht_status_desc;
				break;
			case '27':
				$text = 'اشتباه در آماده به ارسال' . $pardakht_status_desc;
				break;
			case '28':
				$text = 'عدم حضور فروشنده' . $pardakht_status_desc;
				break;
			case '35':
				$text = 'عدم قبول' . $pardakht_status_desc;
				break;
			case '37':
				$text = 'غیر قابل توزیع' . $pardakht_status_desc;
				break;
			default:
				$text = 'خطا';
				break;
		}

		return $text;
	}
}

function pr_pardakht_add_menu()
{
	add_menu_page( 'سامانه پرداخت', 'سامانه پرداخت', 'manage_options', 'pr_pardakht_menu', 'pr_pardakht_menu' );
}
add_action( 'admin_menu', 'pr_pardakht_add_menu' );

function pr_pardakht_menu()
{
	$message = '';
	$arr = array(
				'wsdl' => 'http://server1.pardakht.ir/ws/pardakhtws.php?wsdl',
				'online_wsdl' => 'http://server1.pardakht.ir/ws/pardakhtpayment.php?wsdl',
				'username' => '',
				'password' => '',
				'sms' => 'no',
				'email' => 'no',
				'email_subject' => 'وضعیت سفارش شما از سایت {site_title}',
				'email_body' => 'وضعیت فعلی سفارش شما : {pardakht_order_status}',
				'schedule_time' => '60',
				'free_send_online' => 'no',
				'free_send' => 'no',
				'free_min_amount' => '0',
				'free_min_number' => '0',
				'free_text' => 'ارسال رایگان',
			);

	if ( wp_verify_nonce( $_REQUEST['nonce'], basename( __FILE__ ) ) )
	{
		$pardakht = shortcode_atts( $arr, $_POST );

		if ( $pardakht['sms'] && strlen(PR_wsdl) > 10 )
		{
			$value = ( $pardakht['sms'] == 'yes' ) ? '1' : '0';

			$settingArray = array( array( 'OrderSms' , $value ) );

			try {
				$client = @new soapclient( PR_wsdl , array("exceptions" => 1) );
			} catch (Exception $e) {
				throw new Exception(PR_cantconnect, 1);
			}

			$response = $client->setOrganizationSettings( PR_username, PR_password, $settingArray );

			if ( in_array( $response, array('54', '62') ) || $response->faultcode )
				unset($pardakht['sms']);
		}

		if ( get_option( 'pr_pardakht_settings' ) === false )
			add_option( 'pr_pardakht_settings', $pardakht, null, 'no' );
		else
			update_option( 'pr_pardakht_settings', $pardakht );

		$message = 'تنظیمات ذخیره شد.';
	}

	if ( get_option( 'pr_pardakht_settings' ) )
		$pardakht = shortcode_atts( $arr, get_option( 'pr_pardakht_settings' ) );
	else
		$pardakht = $arr;
?>
	<div class="wrap">
		<h2>تنظیمات</h2>

		<?php if( !empty( $message ) ) : ?>
		<div id="message" class="updated"><p><?php echo $message; ?></p></div>
		<?php endif; ?>

		<form method="POST">
			<input type="hidden" name="nonce" value="<?php echo wp_create_nonce( basename( __FILE__ ) ); ?>">
			<table class="form-table">
				<tbody>
					<tr valign="top">
						<th scope="row"><label for="wsdl">آدرس وب سرویس</label></th>
						<td><input name="wsdl" type="text" id="wsdl" dir="ltr" value="<?php esc_attr_e( $pardakht['wsdl'] ); ?>" class="regular-text"></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="online_wsdl">آدرس وب سرویس پرداخت آنلاین</label></th>
						<td><input name="online_wsdl" type="text" id="online_wsdl" dir="ltr" value="<?php esc_attr_e( $pardakht['online_wsdl'] ); ?>" class="regular-text"></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="username">نام کاربری</label></th>
						<td><input name="username" type="text" id="username" value="<?php esc_attr_e( $pardakht['username'] ); ?>" class="regular-text"></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="password">کلمه عبور</label></th>
						<td><input name="password" type="text" id="password" value="<?php esc_attr_e( $pardakht['password'] ); ?>" class="regular-text"></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="sms">ارسال پیامک اطلاع رسانی تغییر وضعیت سفارش</label></th>
						<td><input name="sms" type="checkbox" id="sms" value="yes" <?php checked( $pardakht['sms'], 'yes', true ); ?>></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="email">ارسال ایمیل اطلاع رسانی تغییر وضعیت سفارش</label></th>
						<td><input name="email" type="checkbox" id="email" value="yes" <?php checked( $pardakht['email'], 'yes', true ); ?>></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="email_subject">موضوع ایمیل</label></th>
						<td><input name="email_subject" type="text" id="email_subject" value="<?php esc_attr_e( $pardakht['email_subject'] ); ?>" class="regular-text"></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="email_body">متن ایمیل</label></th>
						<td><input name="email_body" type="text" id="email_body" value="<?php esc_attr_e( $pardakht['email_body'] ); ?>" class="regular-text"></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="schedule_time">زمان دریافت وضعیت های سفارش</label></th>
						<td><input name="schedule_time" type="number" min="1" id="schedule_time" value="<?php esc_attr_e( $pardakht['schedule_time'] ); ?>" class="regular-text"><br>زمان بندی دریافت وضعیت های سفارش از سایت پرداخت - به دقیقه وارد شود</td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="free_send_online">فعال کردن ارسال رایگان برای پرداخت آنلاین</label></th>
						<td><input name="free_send_online" type="checkbox" id="free_send_online" value="yes" <?php checked( $pardakht['free_send_online'], 'yes', true ); ?>></td>
					</tr>
					<tr valign="top">
					<tr valign="top">
						<th scope="row"><label for="free_send">فعال کردن ارسال رایگان</label></th>
						<td><input name="free_send" type="checkbox" id="free_send" value="yes" <?php checked( $pardakht['free_send'], 'yes', true ); ?>></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="free_min_amount">ارسال رایگان براساس قیمت</label></th>
						<td><input name="free_min_amount" type="number" min="0" id="free_min_amount" value="<?php esc_attr_e( $pardakht['free_min_amount'] ); ?>" class="regular-text"></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="free_min_number">ارسال رایگان براساس تعداد</label></th>
						<td><input name="free_min_number" type="number" min="0" id="free_min_number" value="<?php esc_attr_e( $pardakht['free_min_number'] ); ?>" class="regular-text"></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="free_text">متن نمایش ارسال رایگان</label></th>
						<td><input name="free_text" type="text" id="free_text" value="<?php esc_attr_e( $pardakht['free_text'] ); ?>" class="regular-text"></td>
					</tr>
				</tbody>
			</table>
			<p>
				<input type="submit" value="ذخیره" id="submit" class="button-primary" name="submit">
			</p>
		</form>
	</div>
<?php
}