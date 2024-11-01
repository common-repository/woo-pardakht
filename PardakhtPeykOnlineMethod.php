<?php
if(!class_exists('WC_Shipping_Method'))
	return;
class PR_Pardakht_Peyk_Online_Method extends WC_Shipping_Method
{
	var $w_unit = '';

	public function __construct()
	{
		$this->id = 'pr_pardakht_peyk_online';
		$this->method_title = 'پیک - پرداخت آنلاین';

		$this->init_form_fields();
		$this->init_settings();

		foreach ( $this->settings as $setting_key => $value )
		{
			$this->$setting_key = $value;
		}

		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	public function init_form_fields()
	{
		$this->form_fields = array(
			'enabled' => array(
				'title'     => 'فعال / غیر فعال کردن',
				'label'     => 'فعال کردن پیک - پرداخت آنلاین',
				'type'      => 'checkbox',
				'default'   => 'no',
			),
			'title' => array(
				'title'     => 'عنوان روش',
				'type'      => 'text',
				'default'   => 'پیک - پرداخت آنلاین',
			),
			'min_amount' => array(
				'title' 		=> 'حداقل مبلغ سفارش',
				'type' 			=> 'number',
				'custom_attributes' => array(
					'step'	=> 'any',
					'min'	=> '0'
				),
				'description' 	=> 'کمترین میزان خرید برای فعال شدن این روش ارسال',
				'default' 		=> '0',
				'placeholder'	=> '0'
			),
		);
	}

	function is_available( $package )
	{
		global $woocommerce;
		$customer = $woocommerce->customer;
		$has_min_amount = false;

		if ( $this->enabled == 'no' ) return false;

		if ( !in_array( get_woocommerce_currency(),  array( 'IRR', 'IRT' )  ) ) return false;

		if ( PR_unit != 'g' && PR_unit != 'kg' )
			return false;

		if ( PR_username == '' || PR_password == '' )
			return false;

		$cityID = explode('-', $package['destination']['city']);
		$cityID = $cityID[0];

		if( !$cityID ) return false;

		$weight = 0;
		$unit = ( PR_unit == 'g' ) ? 1 : 1000;
		$product_price = ( get_woocommerce_currency() == 'IRT' ) ? $woocommerce->cart->subtotal * 10 : $woocommerce->cart->subtotal;
		if ( sizeof( $woocommerce->cart->get_cart() ) > 0 && ( $customer->get_shipping_city() ) )
		{
			foreach ( $woocommerce->cart->get_cart() as $item_id => $values )
			{
				$_product = $values['data'];

				if ( $_product->exists() && $values['quantity'] > 0 )
				{
					if ( !$_product->is_virtual() )
						$weight += $_product->get_weight() * $unit * $values['quantity'];
				}
			}
		}

		$data = array(
			'price' => $product_price,
			'weight' => $weight,
			'deliveryID' => '8',
			'paymentID' => '2',
			'destinationCityID' => $cityID,
		);

		$response = inquiryDeliveryPriceByUsername( $data );

		if ( $response == false )
			return false;

		if ( isset( $woocommerce->cart->cart_contents_total ) )
		{
			if ( $woocommerce->cart->prices_include_tax )
				$total = $woocommerce->cart->cart_contents_total + array_sum( $woocommerce->cart->taxes );
			else
				$total = $woocommerce->cart->cart_contents_total;

			if ( $total >= $this->min_amount )
				$has_min_amount = true;
		}

		if ( $has_min_amount ) $is_available = true;

		return apply_filters( 'woocommerce_shipping_' . $this->id . '_is_available', $is_available );
	}

	public function calculate_shipping( $package )
	{
		global $woocommerce;
		$customer = $woocommerce->customer;

		if( empty( $package['destination']['city'] ) )
		{
			$rate = array(
				'id' 		=> $this->id,
				'label' 	=> $this->title,
				'cost' 		=> 0
			);
			$this->add_rate( $rate );
		}

		$this->shipping_total = 0;
		$weight = 0;
		$unit = ( PR_unit == 'g' ) ? 1 : 1000;

		$data = array();
		if ( sizeof( $woocommerce->cart->get_cart() ) > 0 && ( $customer->get_shipping_city() ) )
		{
			foreach ( $woocommerce->cart->get_cart() as $item_id => $values )
			{
				$_product = $values['data'];

				if ( $_product->exists() && $values['quantity'] > 0 )
				{
					if ( !$_product->is_virtual() )
						$weight += $_product->get_weight() * $unit * $values['quantity'];
				}
			}

			$data['weight'] = $weight;
			$data['deliveryID'] = '8'; // sefareshi
			$data['paymentID'] = '2'; // online

			if ( $weight )
				$this->get_shipping_response( $data, $package );
		}
	}

	function get_shipping_response( $data = false, $package )
	{
		global $woocommerce;

		$rates = array();
		$customer = $woocommerce->customer;

		$product_price = ( get_woocommerce_currency() == 'IRT' ) ? $woocommerce->cart->subtotal * 10 : $woocommerce->cart->subtotal;

		$customer_city = $package['destination']['city'];
		if ( !$customer_city || $customer_city <= 0 )
			return false;

		$shipping_data = array(
			'price' => $product_price,
			'weight' => $data['weight'],
			'deliveryID' => $data['deliveryID'],
			'paymentID' => $data['paymentID'],
			'destinationCityID' => $customer_city
		);

		$rates = $this->pardakht_shipping( $shipping_data );

		$rate = ( get_woocommerce_currency() == 'IRT' ) ? $rates / 10 : $rates;

		$my_rate = array(
			'id' => $this->id,
			'label' => $this->title,
			'cost' => $rate,
		);

		$this->add_rate( $my_rate );
	}

	function pardakht_shipping( $data = false )
	{
		global $woocommerce;

		if( pr_pardakht_is_free_send($woocommerce) === true || pr_pardakht_is_free_send_online($woocommerce) === true)
			return 0;

		$response = inquiryDeliveryPriceByUsername( $data );

		return ($response == false) ? 0 : $response;
	}
}