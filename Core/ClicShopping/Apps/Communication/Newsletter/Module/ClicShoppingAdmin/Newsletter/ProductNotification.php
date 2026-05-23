<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Communication\Newsletter\Newsletter as AppNewsletter;

class productNotification
{
  public $show_chooseAudience;
  public $title;
  public $content;

  /**
   * Constructor for initializing the newsletter application.
   *
   * @param string $title The title of the newsletter.
   * @param string $content The content of the newsletter.
   *
   * @return void
   */
  public function __construct($title, $content)
  {

    if (!Registry::exists('Newsletter')) {
      Registry::set('Newsletter', new AppNewsletter());
    }

    /**
     *
     */
      $this->app = Registry::get('Newsletter');

    $this->show_chooseAudience = true;
    $this->title = $title;
    $this->content = $content;
  }

  /**
   * Generates the JavaScript and HTML structure for selecting and managing a list of products as part of the notification audience.
   *
   * Retrieves the list of available products based on the current language and their status, and then constructs an interactive UI
   * allowing the user to add or remove products from the selection. Includes JavaScript functionality for moving items between available
   * and selected lists and ensuring valid submission.
   *
   * @return string Returns the constructed HTML and JavaScript string for the audience selection interface.
   */
  public function chooseAudience()
  {
    $CLICSHOPPING_Language = Registry::get('Language');

    $products_array = [];

    $Qproducts = $this->app->db->get([
      'products p',
      'products_description pd'
    ], [
      'pd.products_id',
      'pd.products_name'
    ], [
      'pd.language_id' => (int)$CLICSHOPPING_Language->getId(),
      'pd.products_id' => ['rel' => 'p.products_id'],
      'p.products_status' => '1',
      'p.products_view' => '1',
      'p.products_archive' => '0',
    ],
      'pd.products_name'
    );

    while ($Qproducts->fetch()) {
      $products_array[] = [
        'id' => $Qproducts->valueInt('products_id'),
        'text' => $Qproducts->value('products_name')
      ];
    }

    $chooseAudience_string = '<script type="text/javascript"><!--
function mover(move) {
  if (move == \'remove\') {
    for (x=0; x<(document.notifications.products.length); x++) {
      if (document.notifications.products.options[x].selected) {
        with(document.notifications.elements[\'chosen[]\']) {
          options[options.length] = new Option(document.notifications.products.options[x].text,document.notifications.products.options[x].value);
        }
        document.notifications.products.options[x] = null;
        x = -1;
      }
    }
  }
  if (move == \'add\') {
    for (x=0; x<(document.notifications.elements[\'chosen[]\'].length); x++) {
      if (document.notifications.elements[\'chosen[]\'].options[x].selected) {
        with(document.notifications.products) {
          options[options.length] = new Option(document.notifications.elements[\'chosen[]\'].options[x].text,document.notifications.elements[\'chosen[]\'].options[x].value);
        }
        document.notifications.elements[\'chosen[]\'].options[x] = null;
        x = -1;
      }
    }
  }
  return true;
}

function selectAll(FormName, SelectBox) {
  temp = "document." + FormName + ".elements[\'" + SelectBox + "\']";
  Source = eval(temp);

  for (x=0; x<(Source.length); x++) {
    Source.options[x].selected = "true";
  }

  if (x<1) {
    alert(\'' . $this->app->getDef('js_please_select_products') . '\');
    return false;
  } else {
    return true;
  }
}
//--></script>';

    $global_button = '<script language="javascript"><!--' . "\n" .
      'document.write(\'<input type="button" value="' . $this->app->getDef('button_global') . '" style="width: 8em;" onclick="document.location=\\\'' . $this->app->link('Newsletter&page=' . (int)$_GET['page'] . '&nID=' . (int)$_GET['nID'] . '&action=confirm&global=true') . '\\\'">\');' . "\n" .
      '//--></script><noscript><a href="' . $this->app->link('Newsletter&page=' . (int)$_GET['page'] . '&nID=' . (int)$_GET['nID'] . '&action=confirm&global=true') . '">[ ' . $this->app->getDef('button_global') . ' ]</a></noscript>';

    $chooseAudience_string .= '    <td class="pageHeading text-end"><table border="0" cellspacing="0" cellpadding="0">' .
      '     <form name="notifications" action="' . $this->app->link('Newsletter&page=' . (int)$_GET['page'] . '&nID=' . (int)$_GET['nID'] . '&action=confirm') . '" method="post" onSubmit="return selectAll(\'notifications\', \'chosen[]\')">' . "\n" .
      '      <tr>' .
      '          <td class="text-end">' . HTML::button($this->app->getDef('button_send'), null, null, 'primary') . '</td>' .
      '          <td>&nbsp;</td>' .
      '          <td class="text-end"><a href="' . $this->app->link('Newsletter&page=' . (int)$_GET['page'] . '&nID=' . (int)$_GET['nID']) . '">' . HTML::button($this->app->getDef('button_cancel'), null, null, 'danger') . '</a></td>' .
      '        </tr>' .
      '      </table></td>' .
      '    </tr>' .
      '  </table></td>' .
      '</tr>' .
      '<tr>' .
      '  <td>&nbsp;</td>' .
      '</tr>';

    $chooseAudience_string .= '<table border="0" width="100%" cellspacing="0" cellpadding="2"><tr>' . "\n" .
      '  <tr>' . "\n" .
      '    <td class="text-center"><b>' . $this->app->getDef('text_products') . '</b><br />' . HTML::selectMenu('products', $products_array, '', 'size="20" style="width: 20em;" multiple') . '</td>' . "\n" .
      '    <td class="text-center">&nbsp;<br />' . $global_button . '<br /><br /><br /><input type="button" value="' . $this->app->getDef('button_select') . '" style="width: 8em;" onClick="mover(\'remove\');"><br /><br /><input type="button" value="' . $this->app->getDef('button_unselect') . '" style="width: 8em;" onClick="mover(\'add\');"></td>' . "\n" .
      '    <td class="text-center"><b>' . $this->app->getDef('text_selected_products') . '</b><br />' . HTML::selectMenu('chosen[]', array(), '', 'size="20" style="width: 20em;" multiple') . '</td>' . "\n" .
      '  </tr>' . "\n" .
      '</table></form>';

    return $chooseAudience_string;
  }

  /**
   * Generates and returns the confirmation string for the newsletter sending process,
   * including hidden fields, summary of audience details, and action buttons.
   *
   * This method calculates the audience for the newsletter based on global or product-specific
   * parameters. Depending on the input criteria, it retrieves the relevant customer IDs and formats
   * the confirmation output with buttons and audience details for further processing.
   *
   * @return string The generated confirmation HTML string containing audience details and action buttons.
   */
  public function confirm()
  {
    $audience = [];

    if (isset($_GET['global']) && ($_GET['global'] == 'true')) {

      $Qproducts = $this->app->db->get('products_notifications', 'distinct customers_id');

      while ($Qproducts->fetch()) {
        $audience[$Qproducts->valueInt('customers_id')] = '1';
      }

      $Qcustomers = $this->app->db->get('customers_info', 'customers_info_id', ['global_product_notifications' => '1']);

      while ($Qcustomers->fetch()) {
        $audience[$Qcustomers->valueInt('customers_info_id')] = '1';
      }

    } else {
      $chosen = [];

      foreach ($_POST['chosen'] as $id) {
        if (is_numeric($id) && !\in_array($id, $chosen)) {
          $chosen[] = $id;
        }
      }

      $ids = array_map(function ($k) {
        return ':products_id_' . $k;
      },
        array_keys($chosen)
      );

      $Qproducts = $this->app->db->prepare('select distinct customers_id
                                              from :table_products_notifications
                                              where products_id in (' . implode(', ', $ids) . ')
                                             ');

      foreach ($chosen as $k => $v) {
        $Qproducts->bindInt(':products_id_' . $k, $v);
      }

      $Qproducts->execute();

      while ($Qproducts->fetch()) {
        $audience[$Qproducts->valueInt('customers_id')] = '1';
      }

      $Qcustomers = $this->app->db->get('customers_info', 'customers_info_id', ['global_product_notifications' => '1']);

      while ($Qcustomers->fetch()) {
        $audience[$Qcustomers->valueInt('customers_info_id')] = '1';
      }
    }

    if (\count($audience) > 0) {
      if (isset($_GET['global']) && ($_GET['global'] == 'true')) {
        $confirm_button_string .= HTML::hiddenField('global', 'true');
      } else {
        for ($i = 0, $n = \count($chosen); $i < $n; $i++) {
          $confirm_button_string .= HTML::hiddenField('chosen[]', $chosen[$i]);
        }
      }
      $confirm_button_string .= HTML::button($this->app->getDef('button_submit'), null, null, 'primary') . ' ';
    }

    $confirm_string = '    <td class="pageHeading text-end"><table border="0" cellspacing="0" cellpadding="0">' .
      '      <tr>' . HTML::form('confirm', $this->app->link('Newsletter&ConfirmSend&page=' . (int)$_GET['page'] . '&nID=' . (int)$_GET['nID'])) .
      '          <td  class="text-end">' . $confirm_button_string . '</td>' .
      '          <td>&nbsp;</td>' .
      '          <td class="text-end">' . HTML::button($this->app->getDef('button_back'), null, $this->app->link('Newsletter&page=' . (int)$_GET['page'] . '&nID=' . (int)$_GET['nID'] . '&action=send'), 'primary') . '</a></td>' .
      '          <td>&nbsp;</td>' .
      '          <td class="text-end">' . HTML::button($this->app->getDef('button_cancel'), null, $this->app->link('Newsletter&page=' . (int)$_GET['page'] . '&nID=' . (int)$_GET['nID']), 'danger') . '</a></td>' .
      '        </tr>' .
      '      </table></td>' .
      '    </tr>' .
      '  </table></td>' .
      '</tr>' .
      '<tr>' .
      '  <td>&nbsp;</td>' .
      '</tr>';

    $confirm_string .= '<table border="0" cellspacing="0" cellpadding="2">' . "\n" .
      '  <tr>' . "\n" .
      '    <td class="main"><p style="color:#ff0000;"><strong>' . $this->app->getDef('text_count_customers', ['audience' => \count($audience)]) . '</strong></p></td>' . "\n" .
      '  </tr>' . "\n" .
      '  <tr>' . "\n" .
      '    <td>&nbsp;</td>' . "\n" .
      '  </tr>' . "\n" .
      '  <tr>' . "\n" .
      '    <td class="main"><strong>' . $this->title . '</strong></td>' . "\n" .
      '  </tr>' . "\n" .
      '  <tr>' . "\n" .
      '    <td>&nbsp;</td>' . "\n" .
      '  </tr>' . "\n" .
      '  <tr>' . "\n" .
      '    <td class="main">' . $this->content . '</td>' . "\n" .
      '  </tr>' . "\n" .
      '  <tr>' . "\n" .
      '    <td>&nbsp;</td>' . "\n" .
      '  </tr>' . "\n" .
      '</table>';

    return $confirm_string;
  }

// Envoie du mail sans gestion de Fckeditor

  /**
   * Sends a newsletter to the appropriate audience based on the provided newsletter ID.
   *
   * @param int $newsletter_id The ID of the newsletter to be sent.
   * @return bool Returns false if the newsletter feature is disabled, otherwise none.
   */
  public function send($newsletter_id)
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Mail = Registry::get('Mail');

    if (!\defined('CLICSHOPPING_APP_NEWSLETTER_NL_STATUS') || CLICSHOPPING_APP_NEWSLETTER_NL_STATUS == 'False') {
      return false;
    }

    $audience = [];

    if (isset($_POST['global']) && ($_POST['global'] == 'true')) {

      $Qproducts = $CLICSHOPPING_Db->get([
        'customers c',
        'products_notifications pn'
      ], [
        'distinct pn.customers_id',
        'c.customers_firstname',
        'c.customers_lastname',
        'c.customers_email_address'
      ], [
          'c.customers_id' => [
            'rel' => 'pn.customers_id'
          ]
        ]
      );

      while ($Qproducts->fetch()) {
        $audience[$Qproducts->valueInt('customers_id')] = [
          'firstname' => $Qproducts->value('customers_firstname'),
          'lastname' => $Qproducts->value('customers_lastname'),
          'email_address' => $Qproducts->value('customers_email_address')
        ];
      }

      $Qcustomers = $CLICSHOPPING_Db->get([
        'customers c',
        'customers_info ci'
      ], [
        'c.customers_id',
        'c.customers_firstname',
        'c.customers_lastname',
        'c.customers_email_address'
      ], [
          'c.customers_id' => [
            'rel' => 'ci.customers_info_id'
          ],
          'ci.global_product_notifications' => '1',
          'c.customers_email_validation' => '0'
        ]
      );

      while ($Qcustomers->fetch()) {
        $audience[$Qcustomers->valueInt('customers_id')] = [
          'firstname' => $Qcustomers->value('customers_firstname'),
          'lastname' => $Qcustomers->value('customers_lastname'),
          'email_address' => $Qcustomers->value('customers_email_address')
        ];
      }
    } else {
      $chosen = [];

      foreach ($_POST['chosen'] as $id) {
        if (is_numeric($id) && !\in_array($id, $chosen)) {
          $chosen[] = $id;
        }
      }

      $ids = array_map(function ($k) {
        return ':products_id_' . $k;
      }, array_keys($chosen)
      );

      $Qproducts = $CLICSHOPPING_Db->prepare('select distinct pn.customers_id,
                                                         c.customers_firstname,
                                                         c.customers_lastname,
                                                         c.customers_email_address
                                           from :table_customers c,
                                           :table_products_notifications pn
                                           where c.customers_id = pn.customers_id
                                           and pn.products_id in (' . implode(', ', $ids) . ')
                                         ');

      foreach ($chosen as $k => $v) {
        $Qproducts->bindInt(':products_id_' . $k, $v);
      }

      $Qproducts->execute();

      while ($Qproducts->fetch()) {
        $audience[$Qproducts->valueInt('customers_id')] = [
          'firstname' => $Qproducts->value('customers_firstname'),
          'lastname' => $Qproducts->value('customers_lastname'),
          'email_address' => $Qproducts->value('customers_email_address')
        ];
      }


      $Qcustomers = $CLICSHOPPING_Db->get([
        'customers c',
        'customers_info ci'
      ], [
        'c.customers_id',
        'c.customers_firstname',
        'c.customers_lastname',
        'c.customers_email_address'
      ], [
          'c.customers_id' => [
            'rel' => 'ci.customers_info_id'
          ],
          'ci.global_product_notifications' => '1',
          'c.customers_email_validation' => '0'
        ]
      );

      while ($Qcustomers->fetch()) {
        $audience[$Qcustomers->valueInt('customers_id')] = array('firstname' => $Qcustomers->value('customers_firstname'),
          'lastname' => $Qcustomers->value('customers_lastname'),
          'email_address' => $Qcustomers->value('customers_email_address'));
      }
    } //end else

// Build the text version
    $text = strip_tags($this->content);

    $CLICSHOPPING_Mail->addText($text . $this->app->getDef('text_unsubscribe') . HTTP::getShopUrlDomain() . 'index.php?Account&Newsletters');

    foreach ($audience as $key => $value) {
      $CLICSHOPPING_Mail->send($value['email_address'], $value['firstname'] . ' ' . $value['lastname'], null, $this->app->getDef('email_from'), $this->title);
    }

    $newsletter_id = HTML::sanitize($newsletter_id);

    $CLICSHOPPING_Db->save('newsletters', ['date_sent' => 'now()',
      'status' => '1'
    ], [
        'newsletters_id' => (int)$newsletter_id
      ]
    );

  } //end function send


// Envoie du mail avec gestion des images pour Fckeditor et Imanager.
  /**
   * Sends a newsletter using CKEditor to the specified audience.
   *
   * @param int $newsletter_id The ID of the newsletter to be sent.
   * @return bool Returns false if the newsletter application is not active or if sending fails; otherwise, no return value.
   */
  public function sendCkeditor($newsletter_id)
  {
    if (!\defined('CLICSHOPPING_APP_NEWSLETTER_NL_STATUS') || CLICSHOPPING_APP_NEWSLETTER_NL_STATUS == 'False') {
      return false;
    }

    $CLICSHOPPING_Db = Registry::get('Db');

    $audience = [];

    if (isset($_POST['global']) && ($_POST['global'] == 'true')) {
      $Qproducts = $CLICSHOPPING_Db->prepare('select distinct pn.customers_id,
                                                        c.customers_firstname,
                                                        c.customers_lastname,
                                                        c.customers_email_address
                                        from :table_customers c,
                                             :table_products_notifications pn
                                        where c.customers_id = pn.customers_id
                                        and customers_email_validation = 0
                                        ');

      $Qproducts->execute();

      while ($Qproducts->fetch()) {
        $audience[$Qproducts->valueInt('customers_id')] = [
          'firstname' => $Qproducts->value('customers_firstname'),
          'lastname' => $Qproducts->value('customers_lastname'),
          'email_address' => $Qproducts->value('customers_email_address')
        ];
      }

      $Qcustomers = $CLICSHOPPING_Db->get([
        'customers c',
        'customers_info ci'
      ], [
        'c.customers_id',
        'c.customers_firstname',
        'c.customers_lastname',
        'c.customers_email_address'
      ], [
          'c.customers_id' => [
            'rel' => 'ci.customers_info_id'
          ],
          'ci.global_product_notifications' => '1',
          'c.customers_email_validation' => '0'
        ]
      );


      while ($Qcustomers->fetch()) {
        $audience[$Qcustomers->valueInt('customers_id')] = [
          'firstname' => $Qcustomers->value('customers_firstname'),
          'lastname' => $Qcustomers->value('customers_lastname'),
          'email_address' => $Qcustomers->value('customers_email_address')
        ];
      }
    } else {
      $chosen = $_POST['chosen'];

      $ids = implode(',', $chosen);

      $Qproducts = $CLICSHOPPING_Db->prepare('select distinct pn.customers_id,
                                                        c.customers_firstname,
                                                        c.customers_lastname,
                                                        c.customers_email_address
                                         from :table_customers c,
                                              :table_products_notifications pn
                                         where c.customers_id = pn.customers_id
                                         and pn.products_id in ( :products_id )
                                         and customers_email_validation = 0
                                        ');
      $Qproducts->binInt('products_id', $ids);
      $Qproducts->execute();

      while ($Qproducts->fetch()) {
        $audience[$Qproducts->valueInt('customers_id')] = array('firstname' => $Qproducts->value('customers_firstname'),
          'lastname' => $Qproducts->value('customers_lastname'),
          'email_address' => $Qproducts->value('customers_email_address')
        );
      }

      $Qcustomers = $CLICSHOPPING_Db->get([
        'customers c',
        'customers_info ci'
      ], [
        'c.customers_id',
        'c.customers_firstname',
        'c.customers_lastname',
        'c.customers_email_address'
      ], [
          'c.customers_id' => [
            'rel' => 'ci.customers_info_id'
          ],
          'ci.global_product_notifications' => '1',
          'c.customers_email_validation' => '0'
        ]
      );

      while ($Qproducts->fetch()) {
        $audience[$Qproducts->valueInt('customers_id')] = [
          'firstname' => $Qproducts->value('customers_firstname'),
          'lastname' => $Qproducts->value('customers_lastname'),
          'email_address' => $Qproducts->value('customers_email_address')
        ];
      }

    } // end else

    $message = html_entity_decode($this->content . $this->app->getDef('text_unsubscribe') . HTTP::getShopUrlDomain() . 'index.php?Account&Newsletters');
    $CLICSHOPPING_Mail = str_replace('src="/', 'src="' . HTTP::getShopUrlDomain() . '/', $message);

    $CLICSHOPPING_Mail->addHtmlCkeditor($message);

    foreach ($audience as $key => $value) {
      $CLICSHOPPING_Mail->send($value['email_address'], $value['firstname'] . ' ' . $value['lastname'], null, $this->app->getDef('email_from'), $this->title);
    }

    $CLICSHOPPING_Db->save('newsletters', [
      'date_sent' => 'now()',
      'status' => '1'
    ], [
        'newsletters_id' => (int)$newsletter_id
      ]
    );

  }
} // end class
