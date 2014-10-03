<?php
require_once('lib/easypost-php/lib/easypost.php');


    add_action('woocommerce_product_options_shipping', 'add_pricing_input');

    add_action('save_post', 'set_customs_value');


    function add_pricing_input($content)
    {
       global $post;
       woocommerce_wp_text_input(array(
           'id'    => '_customs_value', 
           'class' => 'short', 
           'name'  => 'wc_customs_value', 
           'type'  => 'number',
           'label' => __( 'Customs Value', 'woocommerce' ), 
          )
       );
    }

    function set_customs_value($post)
    {
        error_log(var_export($_POST,1));
        if($_POST['wc_customs_value']){
                add_post_meta($_POST['post_ID'], '_customs_value', $_POST['wc_customs_value']);
        }
    }







class ES_WC_EasyPost extends WC_Shipping_Method {
  function __construct() {
    $this->id = 'easypost';
    $this->has_fields      = true;
    $this->init_form_fields();   
    $this->init_settings();   

    $this->title = __('Easy Post Integration', 'woocommerce');
   
    $this->usesandboxapi      = strcmp($this->settings['test'], 'yes') == 0;
    $this->testApiKey 		    = $this->settings['test_api_key'  ];
    $this->liveApiKey 		    = $this->settings['live_api_key'  ];
    $this->handling 		    = $this->settings['handling'] ? $this->settings['handling'] : 0;
    $this->filters            = explode(",", $this->settings['filter_rates']);
error_log($this->settings['filter_rates']);
    $this->secret_key         = $this->usesandboxapi ? $this->testApiKey : $this->liveApiKey;

    \EasyPost\EasyPost::setApiKey($this->secret_key);

    $this->enabled = $this->settings['enabled']; 
    
    add_action('woocommerce_update_options_shipping_' . $this->id , array($this, 'process_admin_options'));
    add_action('woocommerce_checkout_order_processed', array(&$this, 'purchase_order' ));
    error_log('c');
  
  }
  public function init_form_fields()
  {
    $this->form_fields = array(
      'enabled' => array(
        'title' => __( 'Enable/Disable', 'woocommerce' ),
        'type' => 'checkbox',
        'label' => __( 'Enabled', 'woocommerce' ),
        'default' => 'yes'
      ),
      'filter_rates' => array(
        'title' => __( 'Filter these rates', 'woocommerce' ),
        'type' => 'text',
        'label' => __( 'Fitler (Comma Seperated)', 'woocommerce' ),
        'default' => ('LibraryMail,MediaMail'),
      ),

      'test' => array(
        'title' => __( 'Test Mode', 'woocommerce' ),
        'type' => 'checkbox',
        'label' => __( 'Enabled', 'woocommerce' ),
        'default' => 'yes'
      ),
      'test_api_key' => array(
        'title' => "Test Api Key",
        'type' => 'text',
        'label' => __( 'Test Api Key', 'woocommerce' ),
        'default' => ''
      ),
      'live_api_key' => array(
        'title' => "Live Api Key",
        'type' => 'text',
        'label' => __( 'Live Api Key', 'woocommerce' ),
        'default' => ''
      ),
      'handling' => array(
        'title' => "Handling Charge",
        'type' => 'text',
        'label' => __( 'Handling Charge', 'woocommerce' ),
        'default' => '0'
      ),
      'company' => array(
        'title' => "Company",
        'type' => 'text',
        'label' => __( 'Company', 'woocommerce' ),
        'default' => ''
      ),
      'street1' => array(
        'title' => 'Address',
        'type' => 'text',
        'label' => __( 'Address', 'woocommerce' ),
        'default' => ''
      ),
      'street2' => array(
        'title' => 'Address2',
        'type' => 'text',
        'label' => __( 'Address2', 'woocommerce' ),
        'default' => ''
      ),
      'city' => array(
        'title' => 'City',
        'type' => 'text',
        'label' => __( 'City', 'woocommerce' ),
        'default' => ''
      ),
      'state' => array(
        'title' => 'State',
        'type' => 'text',
        'label' => __( 'State', 'woocommerce' ),
        'default' => ''
      ),
      'zip' => array(
        'title' => 'Zip',
        'type' => 'text',
        'label' => __( 'ZipCode', 'woocommerce' ),
        'default' => ''
      ),
      'phone' => array(
        'title' => 'Phone',
        'type' => 'text',
        'label' => __( 'Phone', 'woocommerce' ),
        'default' => ''
      ),

    );

  }

  function calculate_shipping($packages = array())
  {
    
    global $woocommerce;

    $customer = $woocommerce->customer;
    try
    {
      $to_address = \EasyPost\Address::create(
        array(
          "street1" => $customer->get_address(),
          "street2" => $customer->get_address_2(),
          "city"    => $customer->get_city(),
          "state"   => $customer->get_state(),
          "zip"     => $customer->get_postcode(),
        )
      );


      $from_address = \EasyPost\Address::create(
        array(
          "company" => $this->settings['company'],
          "street1" => $this->settings['street1'],
          "street2" => $this->settings['street2'],
          "city"    => $this->settings['city'],
          "state"   => $this->settings['state'],
          "zip"     => $this->settings['zip'],
          "phone"   => $this->settings['phone']
        )
      );
      $cart_weight = $woocommerce->cart->cart_contents_weight;
      
      $length = array();
      $width  = array();
      $height = array();
      foreach($woocommerce->cart->get_cart() as $package)
      {
        $item = get_product($package['product_id']);
        $dimensions = explode('x', trim(str_replace('cm','',$item->get_dimensions())));
        $length[] = $dimensions[0]; 
        $width[]  = $dimensions[1];
        $height[] = $dimensions[2] * $package['quantity'];

      }
      $parcel = \EasyPost\Parcel::create(
        array(
          "length"             => max($length),
          "width"              => max($width),
          "height"             => array_sum($height),
          "predefined_package" => null,
          "weight"             => $cart_weight
        )
      );
      $shipment = \EasyPost\Shipment::create(
        array(
          "to_address"   => $to_address,
          "from_address" => $from_address,
          "parcel"       => $parcel
        )
      );

      $created_rates = \EasyPost\Rate::create($shipment);
      foreach($created_rates as $r)
      {
        $rate = array(
          'id' => sprintf("%s-%s|%s", $r->carrier, $r->service, $shipment->id),
          'label' => sprintf("%s %s", $r->carrier , $r->service),
          'cost' => $r->rate + $this->handling,
          'calc_tax' => 'per_item'
        );

        $filter_out = !empty($this->filters) ? $this->filters : array('LibraryMail', 'MediaMail');
        error_log(var_export($filter_out,1));
        {
          if (!in_array($r->service, $filter_out)) 
          {
            // Register the rate
            $this->add_rate( $rate );
          }
        } 

        }
      } 
      catch(Exception $e)
      {
        // EasyPost Error - Lets Log.
        error_log(var_export($e,1));
        //mail('seanvoss@gmail.com', 'Error from WordPress - EasyPost', var_export($e,1));

      }
  }

  function purchase_order($order_id)
  {
    try
    {
       global $woocommerce;

      $chosen_shipping_methods = $woocommerce->session->get( 'chosen_shipping_methods' );
      error_log(var_export($chosen_shipping_methods,1));
      $order        = &new WC_Order($order_id);
      $shipping     = $order->get_shipping_address();

      $method = $order->get_shipping_methods();
      $method = array_values($method);
      $shipping_method = $method[0]['method_id'];
      $ship_arr = explode('|',$shipping_method);
      if(count($ship_arr) >= 2)
      {

        $shipment = \EasyPost\Shipment::retrieve(array('id' => $ship_arr[1]));
        $shipment->to_address->name = sprintf("%s %s", $order->shipping_first_name, $order->shipping_last_name);
        $shipment->to_address->phone = $order->billing_phone;
        $parcel = \EasyPost\Parcel::create(
          array(
               "length"             => $shipment->parcel->length,
               "width"              => $shipment->parcel->width,
               "height"             => $shipment->parcel->height,
               "predefined_package" => null,
               "weight"             => $shipment->parcel->weight,
          )
        );
        $from_address = \EasyPost\Address::create(
          array(
            "company" => $shipment->from_address->company,
            "street1" => $shipment->from_address->street1,
            "street2" => $shipment->from_address->street2,
            "city"    => $shipment->from_address->city,
            "state"   => $shipment->from_address->state,
            "zip"     => $shipment->from_address->zip,
            "phone"   => $shipment->from_address->phone,
          )
        );

        $to_address = \EasyPost\Address::create(
          array(
            "name"    => sprintf("%s %s", $order->shipping_first_name, $order->shipping_last_name),
            "street1" => $shipment->to_address->street1,
            "street2" => $shipment->to_address->street2,
            "city"    => $shipment->to_address->city,
            "state"   => $shipment->to_address->state,
            "zip"     => $shipment->to_address->zip,
            "phone"   => $order->billing_phone
          )
        );

        
        $shipment = \EasyPost\Shipment::create(
          array(
            "from_address" => $from_address,
            "to_address"   => $to_address,
            "parcel"       => $parcel,
          )
        );

        $rates = $shipment->get_rates();
        foreach($shipment->rates as $idx => $r)
        {
          if(sprintf("%s-%s", $r->carrier , $r->service) == $ship_arr[0])
          {
            $index = $idx;
            break;
          }
        }
        $shipment->buy($shipment->rates[$index]);
        update_post_meta( $order_id, 'easypost_shipping_label', $shipment->postage_label->label_url);
        $order->add_order_note(
          sprintf(
              "Shipping label available at: '%s'",
              $shipment->postage_label->label_url
          )
        );
      }
    }
    catch(Exception $e)
    {
      //mail('seanvoss@gmail.com', 'Error from WordPress - EasyPost', var_export($e,1));
    }
  }


}
function add_easypost_method( $methods ) {
  $methods[] = 'ES_WC_EasyPost'; return $methods;
}

add_filter('woocommerce_shipping_methods',         'add_easypost_method' );


