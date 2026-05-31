<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Payment\Stripe\Module\Payment;

use ClicShopping\Apps\Payment\Stripe\Stripe as StripeApp;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Common\B2BCommon;
use Stripe\PaymentIntent;
use Stripe\Stripe as StripeAPI;

class ST implements \ClicShopping\OM\Modules\PaymentInterface
{
  public string $code;
  public $title;
  public $description;
  public $enabled = false;
  public mixed $app;
  protected $currency;
  public $signature;
  public $public_title;
  public int|null $sort_order = 0;
  protected $api_version;
  public $group;

  protected $intent;
  protected $private_key;
  protected $public_key;

  public function __construct()
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');

    if (Registry::exists('Order')) {
      $CLICSHOPPING_Order = Registry::get('Order');
    }

    if (!Registry::exists('Stripe')) {
      Registry::set('Stripe', new StripeApp());
    }

    $this->app = Registry::get('Stripe');
    $this->app->loadDefinitions('Module/Shop/ST/ST');

    $this->signature = 'Stripe|' . $this->app->getVersion() . '|1.0';
    $this->api_version = $this->app->getApiVersion();

    $this->code = 'ST';
    $this->title = $this->app->getDef('module_stripe_title');
    $this->public_title = $this->app->getDef('module_stripe_public_title');

// Activation module du paiement selon les groupes B2B
    if (\defined('CLICSHOPPING_APP_STRIPE_ST_STATUS')) {
      if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
        if (B2BCommon::getPaymentUnallowed($this->code)) {
          if (CLICSHOPPING_APP_STRIPE_ST_STATUS == 'True') {
            $this->enabled = true;
          } else {
            $this->enabled = false;
          }
}
      } else {
        if (CLICSHOPPING_APP_STRIPE_ST_NO_AUTHORIZE == 'True' && $CLICSHOPPING_Customer->getCustomersGroupID() == 0) {
          if ($CLICSHOPPING_Customer->getCustomersGroupID() == 0) {
            if (CLICSHOPPING_APP_STRIPE_ST_STATUS == 'True') {
              $this->enabled = true;
            } else {
              $this->enabled = false;
            }
}
        }
}

      if ((int)CLICSHOPPING_APP_STRIPE_ST_PREPARE_ORDER_STATUS_ID > 0) {
        $this->order_status = CLICSHOPPING_APP_STRIPE_ST_PREPARE_ORDER_STATUS_ID;
      }

      if ($this->enabled === true) {
        if (isset($CLICSHOPPING_Order) && \is_object($CLICSHOPPING_Order)) {
          $this->update_status();
        }
}

      if (CLICSHOPPING_APP_STRIPE_ST_SERVER_PROD == 'True') {
        $this->private_key = CLICSHOPPING_APP_STRIPE_ST_PRIVATE_KEY;
        $this->public_key = CLICSHOPPING_APP_STRIPE_ST_PUBLIC_KEY;
      } else {
        $this->private_key = CLICSHOPPING_APP_STRIPE_ST_PRIVATE_KEY_TEST;
        $this->public_key = CLICSHOPPING_APP_STRIPE_ST_PUBLIC_KEY_TEST;
      }

      $this->sort_order = \defined('CLICSHOPPING_APP_STRIPE_ST_SORT_ORDER') ? CLICSHOPPING_APP_STRIPE_ST_SORT_ORDER : 0;
    }
}

  public function update_status()
  {
    $CLICSHOPPING_Order = Registry::get('Order');

    if (($this->enabled === true) && ((int)CLICSHOPPING_APP_STRIPE_ST_ZONE > 0)) {
      $check_flag = false;

      $Qcheck = $this->app->db->get('zones_to_geo_zones', 'zone_id', [
        'geo_zone_id' => CLICSHOPPING_APP_STRIPE_ST_ZONE,
        'zone_country_id' => $CLICSHOPPING_Order->delivery['country']['id']
      ],
        'zone_id'
      );

      while ($Qcheck->fetch()) {
        if (($Qcheck->valueInt('zone_id') < 1) || ($Qcheck->valueInt('zone_id') === $CLICSHOPPING_Order->delivery['zone_id'])) {
          $check_flag = true;
          break;
        }
}

      if ($check_flag === false) {
        $this->enabled = false;
      }
}
  }

  public function javascript_validation()
  {
    return false;
  }

  public function selection()
  {
    $CLICSHOPPING_Template = Registry::get('Template');

    if (!empty(CLICSHOPPING_APP_STRIPE_ST_LOGO)) {
      if (!empty(CLICSHOPPING_APP_STRIPE_ST_LOGO) && is_file($CLICSHOPPING_Template->getDirectoryTemplateImages() . 'logos/payment/' . CLICSHOPPING_APP_STRIPE_ST_LOGO)) {
        $this->public_title = $this->public_title . '&nbsp;&nbsp;&nbsp;' . HTML::image($CLICSHOPPING_Template->getDirectoryTemplateImages() . 'logos/payment/' . CLICSHOPPING_APP_STRIPE_ST_LOGO);
      } else {
        $this->public_title = $this->public_title;
      }
}

    return [
      'id' => $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code,
      'module' => $this->public_title
    ];
  }

  /**
   * pre_confirmation_check()
   **/
  public function pre_confirmation_check()
  {
//      $CLICSHOPPING_Template = Registry::get(('Template'));
//      $result = $CLICSHOPPING_Template->addBlock($this->getSubmitCardDetailsJavascript(), 'footer_scripts');
    return false;
  }

  public function confirmation()
  {
    $CLICSHOPPING_Order = Registry::get('Order');
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Address = Registry::get('Address');

    StripeAPI::setApiKey($this->private_key);

    $customer_id = $CLICSHOPPING_Customer->getId();
    $currency = mb_strtoupper($CLICSHOPPING_Order->info['currency']);
    $total_amount = $CLICSHOPPING_Order->info['total'] * 100;
    $total_amount = str_replace('.', '', $total_amount);  // Chargeble amount

    $metadata = ['customer_id' => (int)$customer_id,
      'customer_name' => $CLICSHOPPING_Customer->getName(),
      'customer_laster_name' => $CLICSHOPPING_Customer->getLastName(),
      'company' => HTML::output($CLICSHOPPING_Order->customer['company'])
    ];

    $i = 0;

    if (\count($CLICSHOPPING_Order->products) < 7) {
      foreach ($CLICSHOPPING_Order->products as $product) {
        $i++;

        $metadata['product_' . $i . '_name'] = $product['name'];
        $metadata['product_' . $i . '_model'] = $product['model'];
        $metadata['product_' . $i . '_id'] = $product['id'];
        $metadata['product_' . $i . '_qty'] = $product['qty'];
        $metadata['product_' . $i . '_price'] = $product['price'];
        $metadata['product_' . $i . '_tax'] = $product['tax'];
      }
}

    if (CLICSHOPPING_APP_STRIPE_ST_TRANSACTION_METHOD == 'automatic') {
      $capture_method = 'automatic';
    } else {
      $capture_method = 'manual';
    }

// have to create intent before loading the javascript because it needs the intent id
    if (isset($_SESSION['stripe_payment_intent_id'])) {
      $stripe_payment_intent_id = HTML::sanitize($_SESSION['stripe_payment_intent_id']);

      try {
        $this->intent = PaymentIntent::retrieve(retrieve(['id' => $stripe_payment_intent_id]));
        // $this->event_log($customer_id, 'page retrieve intent', $stripe_payment_intent_id, $this->intent);
        $this->intent->amount = $total_amount; //$CLICSHOPPING_Order->info['total'],
        $this->intent->currency = $currency;
        $this->intent->metadata = $metadata;

        $this->intent->save();
//          $response = $this->intent->save();

      } catch (exception $err) {
        //$this->event_log($customer_id, 'page create intent', $stripe_payment_intent_id, $err->getMessage());
// failed to save existing intent, so create new one
        unset($stripe_payment_intent_id);
      }
}

    if (!isset($stripe_payment_intent_id)) {
      $description = STORE_NAME . ' - Order date time : ' . date('Y-m-d H:i:s');

      $token = $_POST['stripeToken'];

      $params = [
        'amount' => $total_amount,
        'currency' => $currency,
        'source' => $token,
        'setup_future_usage' => 'off_session',
        'description' => $description,
        'capture_method' => $capture_method,
        'metadata' => $metadata
      ];

      $this->intent = PaymentIntent::create($params);

      // $this->event_log($customer_id, "page create intent", json_encode($params), $this->intent);
      $stripe_payment_intent_id = $this->intent->id;

      unset($_SESSION['stripe_payment_intent_id']);
    }

    $content = '';

    if (CLICSHOPPING_APP_STRIPE_ST_SERVER_PROD == 'False') {
      $content .= '<div class="alert alert-warning"> ' . $this->app->getDef('text_stripe_alert_mode_test') . '</div>';
    }

    $content .= $this->app->getDef('text_stripe_title');
    $content .= '<div class="mt-1"></div>';
// have to create intent before loading the javascript because it needs the intent id
    $content .= '<input type="hidden" id="intent_id" value="' . HTML::output($stripe_payment_intent_id) . '" />' .
      '<input type="hidden" id="secret" value="' . HTML::output($this->intent->client_secret) . '" />';

    $content .= '<div class="form-row">';
    $content .= '
        <div id="stripe_table_new_card">
          <div><label for="cardholder-name" class="control-label">' . $this->app->getDef('text_stripe_credit_card_owner') . '</label>
          <div><input type="text" id="cardholder-name" class="form-control" value="' . HTML::output($CLICSHOPPING_Order->billing['firstname'] . ' ' . $CLICSHOPPING_Order->billing['lastname']) . '" required></text></div>
        </div>
        <div class="mt-1"></div>
        <div>
          <label for="card-element" class="control-label">' . $this->app->getDef('text_stripe_credit_card_type') . '</label>
            <div id="card-element"  class="col-md-5 col-12">         
              <div class="group">
                <label>
                  <span>Card number</span>
                  <div id="card-number-element" class="field"></div>
                </label>
                <label>
                  <span>Expiry date</span>
                  <div id="card-expiry-element" class="field"></div>
                </label>
                <label>
                  <span>CVC</span>
                  <div id="card-cvc-element" class="field"></div>
                </label>
              </div>
            </div>
        <!-- Used to display Element errors. -->
          <div id="card-errors" role="alert" class="messageStackError payment-errors"></div>
      </div>
      ';

//https://stripe.com/docs/payments/accept-a-payment-charges#web
//4242424242424241
    $content .= '<input type="hidden" id="city" value="' . $CLICSHOPPING_Order->billing['city'] . '" />';
    $content .= '<input type="hidden" id="line1" value="' . HTML::output($CLICSHOPPING_Order->customer['street_address']) . '" />';
    $content .= '<input type="hidden" id="line2" value="' . HTML::output($CLICSHOPPING_Order->billing['suburb']) . '" />';
    $content .= '<input type="hidden" id="postal_code" value="' . HTML::output($CLICSHOPPING_Order->customer['postcode']) . '" />';
    $content .= '<input type="hidden" id="state" value="' . $CLICSHOPPING_Address->getZoneName($CLICSHOPPING_Order->billing['country']['id'], $CLICSHOPPING_Order->billing['zone_id'], $CLICSHOPPING_Order->billing['state']) . '" />';
    $content .= '<input type="hidden" id="country" value="' . $CLICSHOPPING_Order->billing['country']['iso_code_2'] . '" />';
    $content .= '<input type="hidden" id="email_address" value="' . HTML::output($CLICSHOPPING_Order->customer['email_address']) . '" />';
    $content .= '<input type="hidden" id="customer_id" value="' . HTML::output($customer_id) . '" />';

    $content .= $this->getSubmitCardDetailsJavascript();

    $confirmation = ['title' => $content];

    return $confirmation;
  }

  public function process_button()
  {
    return false;
  }

  /***********************************************************
   * before_process
   ***********************************************************/
  public function before_process()
  {
    return false;
  }

  public function after_process()
  {
    $CLICSHOPPING_Order = Registry::get('Order');

    $orders_id = $CLICSHOPPING_Order->getLastOrderId();

    if (empty($orders_id) || $orders_id == 0 || \is_null($orders_id)) {
      $Qorder = $CLICSHOPPING_Order->db->prepare('select orders_id
                                                    from :table_orders                                                    
                                                    order by orders_id desc
                                                    limit 1
                                                   ');
      $Qorder->execute();

      $orders_id = $Qorder->valueInt('orders_id');
    }

    $comment = $this->app->getDef('text_reference_transaction');

    if (CLICSHOPPING_APP_STRIPE_ST_ORDER_STATUS_ID == 0) {
      $new_order_status = DEFAULT_ORDERS_STATUS_ID;
    } else {
      $new_order_status = CLICSHOPPING_APP_STRIPE_ST_ORDER_STATUS_ID;
    }

    $sql_data_array = [
      'orders_id' => $orders_id,
      'orders_status_id' => (int)$new_order_status,
      'date_added' => 'now()',
      'customer_notified' => '0',
      'comments' => $comment
    ];

    $this->app->db->save('orders_status_history', $sql_data_array);

    $sql_data_array = ['orders_status' => (int)$new_order_status];
    $sql_insert = ['orders_id' => (int)$orders_id];

    $this->app->db->save('orders', $sql_data_array, $sql_insert);
  }

  public function get_error()
  {
    $message = $this->app->getDef('module_stripe_error_general');

    if (isset($_GET['error']) && !empty($_GET['error'])) {
      switch ($_GET['error']) {
        case 'cardstored':
          $message = $this->app->getDef('module_stripe_error_stripe');
          break;
      }
}

    $error = ['title' => $this->app->getDef('module_stripe_error_title'),
      'error' => $message
    ];

    return $error;
  }

  public function check()
  {
    return \defined('CLICSHOPPING_APP_STRIPE_ST_STATUS') && (trim(CLICSHOPPING_APP_STRIPE_ST_STATUS) != '');
  }

  public function install()
  {
    $this->app->redirect('Configure&Install&module=Stripe');
  }

  public function remove()
  {
    $this->app->redirect('Configure&Uninstall&module=Stripe');
  }

  public function keys()
  {
    return array('CLICSHOPPING_APP_STRIPE_ST_SORT_ORDER');
  }

  public function getSubmitCardDetailsJavascript(mixed $intent = null)
  {
    $stripe_publishable_key = $this->public_key;

    $js = <<<EOD
<script src="https://js.stripe.com/v3/"></script>

<script>
// Set your publishable key: remember to change this to your live publishable key in production
// See your keys here: https://dashboard.stripe.com/account/apikeys

var stripe = Stripe('{$stripe_publishable_key}');
var elements = stripe.elements();

// Custom styling can be passed to options when creating an Element.
var style = {
 base: {
    color: '#32325d',
    lineHeight: '24px',
    fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
    fontSmoothing: 'antialiased',
    fontSize: '16px',
    '::placeholder': {
      color: '#aab7c4'
    }
},
  invalid: {
    color: '#fa755a',
    iconColor: '#fa755a'
  }
};

// Create an instance of the card Element.
var card = elements.create('card', {style: style});

// Add an instance of the card Element into the `card-element` <div>.
card.mount('#card-element');

// Handle real-time validation errors from the card Element.
card.addEventListener('change', function(event) {
  var displayError = document.getElementById('card-errors');
  if (event.error) {
    displayError.textContent = event.error.message;
  } else {
    displayError.textContent = '';
  }
});

// Handle form submission
  var form = document.getElementById('checkout_confirmation');
  form.addEventListener('process_button', function(event) {
  event.preventDefault();

  stripe.createToken(card).then(function(result) {
    if (result.error) {
      // Inform the user if there was an error
      var errorElement = document.getElementById('card-errors');
      errorElement.textContent = result.error.message;
    } else {
      // Send the token to your server
      stripeTokenHandler(result.token);
    }
});
});  

function stripeTokenHandler(token) {
  // Insert the token ID into the form so it gets submitted to the server
  var form = document.getElementById('checkout_confirmation');
  var hiddenInput = document.createElement('input');
  hiddenInput.setAttribute('type', 'hidden');
  hiddenInput.setAttribute('name', 'stripeToken');
  hiddenInput.setAttribute('value', token.id);

  form.appendChild(hiddenInput);

  // Submit the form, uncomment
  //alert("Created a token with value: "+token.id)
  form.submit();
}   

/*
// Set your publishable key: remember to change this to your live publishable key in production
// See your keys here: https://dashboard.stripe.com/account/apikeys
var stripe = Stripe('{$stripe_publishable_key}');
var cardName = {$card_name};
var elements = stripe.elements();

// Set up Stripe.js and Elements to use in checkout form
var elements = stripe.elements();
var style = {
  base: {
    color: "#32325d",
  }
};

var card = elements.create("card", { style: style });
card.mount("#card-element");

cardElement.on('change', ({error}) => {
  let displayError = document.getElementById('card-errors');
  if (error) {
    displayError.textContent = error.message;
  } else {
    displayError.textContent = '';
  }
});

var form = document.getElementById('checkout_confirmation);

form.addEventListener('submit', function(ev) {
  ev.preventDefault();
  // If the client secret was rendered server-side as a data-secret attribute
  // on the <form> element, you can retrieve it here by calling `form.dataset.secret`
  stripe.confirmCardPayment(clientSecret, {
    payment_method: {
      card: card,
      billing_details: {
        name: cardName
      }
}
  }).then(function(result) {
    if (result.error) {
      // Show error to your customer (e.g., insufficient funds)
      console.log(result.error.message);
    } else {
      // The payment has been processed!
      if (result.paymentIntent.status === 'succeeded') {
        // Show a success message to your customer
        // There's a risk of the customer closing the window before callback
        // execution. Set up a webhook or plugin to listen for the
        // payment_intent.succeeded event that handles any business critical
        // post-payment actions.
      }
}
  });
});
*/
</script>
EOD;

    return $js;
  }
}