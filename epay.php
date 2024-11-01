<?php

add_action( 'plugins_loaded', 'woocommerce_pardakht_init', 0 );
$pardakht_menu = get_option( 'pr_pardakht_settings' );
function woocommerce_pardakht_init() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	class WC_Pardakht_Pay extends WC_Payment_Gateway {
		protected $msg = array();

		public function __construct() {
			// Go wild in here
			$this->id           = 'pardakht';
			$this->method_title = 'پرداخت آنلاین شرکت پرداخت';
			$this->icon         = WP_PLUGIN_URL . "/" . plugin_basename( dirname( __FILE__ ) ) . '/icon.png';
			$this->has_fields   = false;
			$this->init_form_fields();
			$this->init_settings();
			$this->title = $this->settings['title'];

			$this->merchantid       = $this->settings['merchantid'];
			$this->redirect_page_id = $this->settings['redirect_page_id'];

			$this->msg['reversal'] = "";
			$this->msg['status']   = "";
			$this->msg['message']  = "";
			$this->msg['class']    = "";

			add_action( 'woocommerce_api_pr_pardakht_online', array( $this, 'check_pardakht_response' ) );
			add_action( 'valid-pardakht-request', array( $this, 'successful_request' ) );
			if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
						$this,
						'process_admin_options'
					) );
			} else {
				add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
			}
			add_action( 'woocommerce_receipt_pardakht', array( $this, 'receipt_page' ) );
			add_action( 'woocommerce_thankyou_pardakht', array( $this, 'thankyou_page' ) );
		}

		function init_form_fields() {

			$this->form_fields = array(
				'enabled'          => array(
					'title'   => __( 'فعال / غیر فعال کردن', 'mrova' ),
					'type'    => 'checkbox',
					'label'   => __( 'انتخاب وضعیت درگاه پرداخت شرکت پرداخت', 'mrova' ),
					'default' => 'no'
				),
				'title'            => array(
					'title'       => __( 'عنوان درگاه', 'mrova' ),
					'type'        => 'text',
					'description' => __( 'عنوان درگاه در هنگام انتخاب درگاه پرداخت', 'mrova' ),
					'default'     => __( 'پرداخت از طریق درگاه پرداخت شرکت پرداخت', 'mrova' )
				),
				'description'      => array(
					'title'       => __( 'توضیحات درگاه', 'mrova' ),
					'type'        => 'textarea',
					'description' => __( 'توضیحات نوشته شده در زیر لوگوی درگاه هنگام پرداخت', 'mrova' ),
					'default'     => __( 'پرداخت با استفاده از درگاه برداخت شرکت پرداخت از طریق کلبه کارت های بانکی عضو شتاب', 'mrova' )
				),
			);


		}

		/**
		 * Admin Panel Options
		 * - Options for bits like 'title' and availability on a country-by-country basis
		 **/
		public function admin_options() {
			echo '<h3>درگاه پرداخت شرکت پرداخت</h3>';

			echo '<table class="form-table">';
			$this->generate_settings_html();
			echo '</table>';

		}

		/**
		 *  There are no payment fields for pardakht, but we want to show the description if set.
		 **/
		function payment_fields() {
			if ( $this->description ) {
				echo wpautop( wptexturize( $this->description ) );
			}
		}


		function thankyou_page($order_id)
		{
			return;
		}
		/**
		 * Receipt Page
		 **/
		function receipt_page( $order ) {
			global $woocommerce;
			$redirect_url = add_query_arg( 'wc-api', 'pr_pardakht_online', get_site_url() . '/' );
			try {
				$client = @new soapclient( PR_online_wsdl , array("exceptions" => 1) );
			} catch (Exception $e) {
				throw new Exception(PR_cantconnect, 1);
			}
			unset( $woocommerce->session->pardakht_id );
			$woocommerce->session->pardakht_id = $order;

			$rand_num = rand( 1000, 9999 );
			$pardakht_order_id = get_post_meta($order, '_pr_pardakht_order_id', true);
			$pardakht_order_id = is_array($pardakht_order_id) ? $pardakht_order_id[0] : $pardakht_order_id;

			$pardakht_order = $rand_num . $pardakht_order_id;

			$call_back_url = $redirect_url;
			$local_date = date('ymd');
			$local_time = time('His');
			$status = $client->bpPayRequest( '', PR_username, PR_password, $pardakht_order, '', $local_date, $local_time, '', $call_back_url, '' );
			$status = explode( ',', $status );
			$token = $status[1];
			$status_code = $status[0];

			if ( $status_code == 0 )
			{
				update_post_meta( $order, '_pr_pardakht_online_order_id', $pardakht_order );
				update_post_meta( $order, '_pr_pardakht_token', $token );

				$sts = 'محصول در انتظار پرداخت است';

				$status = 'pending';
				$order = new WC_Order( $order );
				$order->update_status( $status, $sts );
				$order->add_order_note( $sts );
			}
			else
			{
				$sts = 'محصول در سیستم پرداخت ثبت نشد';
				$status = 'failed';
				$order = new WC_Order( $order );
				$order->update_status( $status, $sts );
				$order->add_order_note( $sts );

				throw new Exception('در اتصال به درگاه پرداخت خطايي رخ داده است ! وضعيت خطا : ' . $status_code, 1);
			}

			$html = '<p>از سفارش شما متشکريم ، تا انتقال به درگاه پرداخت چند لحظه منتظر بمانيد ...</p>';
			$html .= '
			<form id="pr_checkout_confirmation" method="post" action="http://server1.pardakht.ir/?pg=orderOnlinePayment" style="margin:0px">
			<input type="hidden" name="RefId" value="'.$token.'">
			<input type="submit" class="button" value="در صورت عدم انتقال اینجا کلیک کنید">
			</form><script>setTimeout(function(){document.getElementById("pr_checkout_confirmation").submit()},5000);</script>';
			echo $html;
		}

		/**
		 * Process the payment and return the result
		 **/
		function process_payment( $order_id ) {
			$order = new WC_Order( $order_id );
			return array( 'result' => 'success', 'redirect' => $order->get_checkout_payment_url( true ) );
		}

		/**
		 * Check for valid pardakht server callback
		 **/
		function check_pardakht_response() {
			$resCode = $_POST['ResCode'];
			if($resCode == 0) {
				global $woocommerce;
				try {
					$client = @new soapclient( PR_online_wsdl, array( "exceptions" => 1 ) );
				} catch ( Exception $e ) {
					throw new Exception( PR_cantconnect, 1 );
				}

				$order_id = $woocommerce->session->pardakht_id;
				$pardakht_online_order_id = get_post_meta( $order_id, '_pr_pardakht_online_order_id', true );

				$resCode = $client->bpVerifyRequest( '', PR_username, PR_password, $pardakht_online_order_id, $pardakht_online_order_id, '' );
			}
			$order = new WC_Order( $order_id );
			if( $resCode == 0 )
			{
				$message = 'پرداخت با موفقیت انجام گردید.';

				$order->payment_complete();
				$order->add_order_note( $message );
				$redirect_url = add_query_arg( array( 'key' => $order->order_key, 'order' => $order_id, 'pardakht_type' => 'online' ), wc_get_page_permalink( 'thanks' )  );
				wp_redirect( $redirect_url );
				exit;

			}
			else
			{
				$message = 'خطا در عمليات پرداخت !'  . ' کد خطا: ' . $resCode;
				$order->update_status( 'failed' );
				$order->add_order_note( $message);
				$redirect_url = add_query_arg( array('wc_error'=>urlencode($message)), wc_get_page_permalink('shop')  );
				wp_redirect( $redirect_url );
				exit;
			}

		}


		// get all pages
		function get_pages( $title = false, $indent = true ) {
			$wp_pages  = get_pages( 'sort_column=menu_order' );
			$page_list = array();
			if ( $title ) {
				$page_list[] = $title;
			}
			foreach ( $wp_pages as $page ) {
				$prefix = '';
				// show indented child pages?
				if ( $indent ) {
					$has_parent = $page->post_parent;
					while ( $has_parent ) {
						$prefix .= ' - ';
						$next_page  = get_page( $has_parent );
						$has_parent = $next_page->post_parent;
					}
				}
				// add to page list array array
				$page_list[ $page->ID ] = $prefix . $page->post_title;
			}

			return $page_list;
		}

	}

	/**
	 * Add the Gateway to WooCommerce
	 **/
	function woocommerce_add_pardakht_gateway( $methods ) {
		$methods[] = 'WC_Pardakht_Pay';

		return $methods;
	}

	add_filter( 'woocommerce_payment_gateways', 'woocommerce_add_pardakht_gateway' );
}