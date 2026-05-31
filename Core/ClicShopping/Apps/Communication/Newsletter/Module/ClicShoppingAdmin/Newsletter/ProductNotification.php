<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Communication\Newsletter\Module\ClicShoppingAdmin\Newsletter;

use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Is;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Communication\Newsletter\Newsletter as AppNewsletter;
use ClicShopping\Apps\Configuration\TemplateEmail\Classes\ClicShoppingAdmin\TemplateEmailAdmin;

/**
 * Newsletter module targeting customers who subscribed to product notifications.
 *
 * The audience is built from the products_notifications table (per-product watchers)
 * and from customers who enabled global product notifications. Because that audience
 * is usually small and targeted, sending is performed synchronously.
 */
class ProductNotification
{
  public mixed $app;
  public bool $show_chooseAudience;
  public string $title;
  public string $content;

  private string $emailFrom;

  /**
   * @param string $title The title of the newsletter.
   * @param string $content The content of the newsletter.
   * @return void
   */
  public function __construct(string $title, string $content)
  {
    if (!Registry::exists('Newsletter')) {
      Registry::set('Newsletter', new AppNewsletter());
    }

    $this->app = Registry::get('Newsletter');
    $this->app->loadDefinitions('modules/newsletter');

    $this->show_chooseAudience = true;
    $this->title = $title;
    $this->content = $content;
    $this->emailFrom = HTML::sanitize(STORE_OWNER_EMAIL_ADDRESS);
  }

  /**
   * Checks whether the Newsletter App is enabled.
   *
   * @return bool
   */
  public function checkStatus(): bool
  {
    if (!\defined('CLICSHOPPING_APP_NEWSLETTER_NL_STATUS') || CLICSHOPPING_APP_NEWSLETTER_NL_STATUS == 'False') {
      return false;
    }

    return true;
  }

  /**
   * Builds the audience for a product notification newsletter.
   *
   * Combines watchers of the selected products (or every watcher when the "global"
   * flag is set) with customers who enabled global product notifications. Recipients
   * are de-duplicated by customer id and filtered on a valid e-mail address.
   *
   * @return array<int, array{firstname: string, lastname: string, email_address: string}>
   */
  private function getAudience(): array
  {
    $audience = [];

    if (isset($_POST['global']) && $_POST['global'] === 'true') {
      $Qproducts = $this->app->db->get([
        'customers c',
        'products_notifications pn'
      ], [
        'distinct pn.customers_id',
        'c.customers_firstname',
        'c.customers_lastname',
        'c.customers_email_address'
      ], [
        'c.customers_id' => ['rel' => 'pn.customers_id'],
        'c.customers_email_validation' => 0
      ]);
    } else {
      $chosen = [];

      foreach (($_POST['chosen'] ?? []) as $id) {
        if (is_numeric($id) && !\in_array((int)$id, $chosen, true)) {
          $chosen[] = (int)$id;
        }
      }

      if (\count($chosen) === 0) {
        return [];
      }

      $placeholders = array_map(static function ($k) {
        return ':products_id_' . $k;
      }, array_keys($chosen));

      $Qproducts = $this->app->db->prepare('select distinct pn.customers_id,
                                                    c.customers_firstname,
                                                    c.customers_lastname,
                                                    c.customers_email_address
                                             from :table_customers c,
                                                  :table_products_notifications pn
                                             where c.customers_id = pn.customers_id
                                             and c.customers_email_validation = 0
                                             and pn.products_id in (' . implode(', ', $placeholders) . ')
                                            ');

      foreach ($chosen as $k => $v) {
        $Qproducts->bindInt(':products_id_' . $k, $v);
      }

      $Qproducts->execute();
    }

    while ($Qproducts->fetch()) {
      if (Is::EmailAddress($Qproducts->value('customers_email_address'))) {
        $audience[$Qproducts->valueInt('customers_id')] = [
          'firstname' => $Qproducts->value('customers_firstname'),
          'lastname' => $Qproducts->value('customers_lastname'),
          'email_address' => $Qproducts->value('customers_email_address')
        ];
      }
    }

    // customers who opted in to global product notifications
    $Qcustomers = $this->app->db->get([
      'customers c',
      'customers_info ci'
    ], [
      'c.customers_id',
      'c.customers_firstname',
      'c.customers_lastname',
      'c.customers_email_address'
    ], [
      'c.customers_id' => ['rel' => 'ci.customers_info_id'],
      'ci.global_product_notifications' => 1,
      'c.customers_email_validation' => 0
    ]);

    while ($Qcustomers->fetch()) {
      if (Is::EmailAddress($Qcustomers->value('customers_email_address'))) {
        $audience[$Qcustomers->valueInt('customers_id')] = [
          'firstname' => $Qcustomers->value('customers_firstname'),
          'lastname' => $Qcustomers->value('customers_lastname'),
          'email_address' => $Qcustomers->value('customers_email_address')
        ];
      }
    }

    return $audience;
  }

  /**
   * Generates the JavaScript and HTML structure for selecting and managing a list of products as part of the notification audience.
   *
   * @return string Returns the constructed HTML and JavaScript string for the audience selection interface.
   */
  public function chooseAudience(): string
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

    $page = (int)($_GET['page'] ?? 1);
    $nID = (int)($_GET['nID'] ?? 0);

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
      'document.write(\'<input type="button" value="' . $this->app->getDef('button_global') . '" style="width: 8em;" onclick="document.location=\\\'' . $this->app->link('Send&page=' . $page . '&nID=' . $nID . '&action=confirm&global=true') . '\\\'">\');' . "\n" .
      '//--></script><noscript><a href="' . $this->app->link('Send&page=' . $page . '&nID=' . $nID . '&action=confirm&global=true') . '">[ ' . $this->app->getDef('button_global') . ' ]</a></noscript>';

    $chooseAudience_string .= '    <td class="pageHeading text-end"><table border="0" cellspacing="0" cellpadding="0">' .
      '     <form name="notifications" action="' . $this->app->link('Send&page=' . $page . '&nID=' . $nID . '&action=confirm') . '" method="post" onSubmit="return selectAll(\'notifications\', \'chosen[]\')">' . "\n" .
      '      <tr>' .
      '          <td class="text-end">' . HTML::button($this->app->getDef('button_send'), null, null, 'primary') . '</td>' .
      '          <td>&nbsp;</td>' .
      '          <td class="text-end"><a href="' . $this->app->link('Newsletter&page=' . $page . '&nID=' . $nID) . '">' . HTML::button($this->app->getDef('button_cancel'), null, null, 'danger') . '</a></td>' .
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
      '    <td class="text-center"><b>' . $this->app->getDef('text_selected_products') . '</b><br />' . HTML::selectMenu('chosen[]', [], '', 'size="20" style="width: 20em;" multiple') . '</td>' . "\n" .
      '  </tr>' . "\n" .
      '</table></form>';

    return $chooseAudience_string;
  }

  /**
   * Generates and returns the confirmation string for the newsletter sending process,
   * including hidden fields, summary of audience details, and action buttons.
   *
   * @return string The generated confirmation HTML string containing audience details and action buttons.
   */
  public function confirm(): string
  {
    $page = (int)($_GET['page'] ?? 1);
    $nID = (int)($_GET['nID'] ?? 0);

    $audience = $this->getAudience();

    $is_global = isset($_GET['global']) && $_GET['global'] === 'true';

    $chosen = [];

    if (!$is_global) {
      foreach (($_POST['chosen'] ?? []) as $id) {
        if (is_numeric($id) && !\in_array((int)$id, $chosen, true)) {
          $chosen[] = (int)$id;
        }
      }
    }

    $confirm_button_string = '';

    if (\count($audience) > 0) {
      if ($is_global) {
        $confirm_button_string .= HTML::hiddenField('global', 'true');
      } else {
        foreach ($chosen as $value) {
          $confirm_button_string .= HTML::hiddenField('chosen[]', $value);
        }
      }

      $confirm_button_string .= HTML::button($this->app->getDef('button_submit'), null, null, 'primary') . ' ';
    }

    $confirm_string = '    <td class="pageHeading text-end"><table border="0" cellspacing="0" cellpadding="0">' .
      '      <tr>' . HTML::form('confirm', $this->app->link('ConfirmSend&page=' . $page . '&nID=' . $nID)) .
      '          <td  class="text-end">' . $confirm_button_string . '</td>' .
      '          <td>&nbsp;</td>' .
      '          <td class="text-end">' . HTML::button($this->app->getDef('button_back'), null, $this->app->link('Send&page=' . $page . '&nID=' . $nID), 'primary') . '</td>' .
      '          <td>&nbsp;</td>' .
      '          <td class="text-end">' . HTML::button($this->app->getDef('button_cancel'), null, $this->app->link('Newsletter&page=' . $page . '&nID=' . $nID), 'danger') . '</td>' .
      '        </tr>' .
      '      </table></td>' .
      '    </tr>' .
      '  </table></td>' .
      '</tr>' .
      '<tr>' .
      '  <td>&nbsp;</td>' .
      '</tr></form>';

    $confirm_string .= '<table border="0" cellspacing="0" cellpadding="2">' . "\n" .
      '  <tr>' . "\n" .
      '    <td class="main"><p style="color:#ff0000;"><strong>' . $this->app->getDef('text_count_customers') . ' ' . \count($audience) . '</strong></p></td>' . "\n" .
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

  /**
   * Sends the product notification newsletter (HTML / CKEditor) to its audience.
   *
   * The product notification audience is targeted and small, so the send is done
   * in a single request; the method always reports completion (true) to the caller.
   *
   * @param int $newsletter_id The ID of the newsletter to be sent.
   * @return bool Always true (the send completes within a single request).
   */
  public function sendCkeditor(int $newsletter_id): bool
  {
    if (!$this->checkStatus()) {
      return true;
    }

    $CLICSHOPPING_Mail = Registry::get('Mail');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    $audience = $this->getAudience();

    if (\count($audience) === 0) {
      return true;
    }

    $template_email_signature = TemplateEmailAdmin::getTemplateEmailSignature();
    $template_email_newsletter_footer = TemplateEmailAdmin::getTemplateEmailNewsletterTextFooter();
    $email_footer = '<br />' . $template_email_signature . '<br />' . $template_email_newsletter_footer;

    $subject = $this->app->getDef('text_send_newsletter_subject', ['store_name' => STORE_NAME]);

    $message = html_entity_decode($this->content . '<br /><br />' . $this->app->getDef('text_unsubscribe') . ' ' . HTTP::getShopUrlDomain() . 'index.php?Account&Newsletters' . $email_footer);
    $message = str_replace('src="/', 'src="' . HTTP::getShopUrlDomain(), $message);

    $CLICSHOPPING_Mail->addHtmlCkeditor($message);

    foreach ($audience as $value) {
      $CLICSHOPPING_Mail->send(
        $value['email_address'],
        HTML::sanitize(STORE_NAME),
        $this->emailFrom,
        $value['firstname'] . ' ' . $value['lastname'],
        $subject
      );
    }

    $Qupdate = $this->app->db->prepare('update :table_newsletters
                                        set date_sent = now(),
                                            status = 1
                                        where newsletters_id = :newsletters_id
                                       ');
    $Qupdate->bindInt(':newsletters_id', $newsletter_id);
    $Qupdate->execute();

    $CLICSHOPPING_Hooks->call('Newsletter', 'NewsletterSendCkEditor');

    return true;
  }

  /**
   * Sends the product notification newsletter as plain text to its audience.
   *
   * @param int $newsletter_id The ID of the newsletter to be sent.
   * @return void
   */
  public function send(int $newsletter_id): void
  {
    if (!$this->checkStatus()) {
      return;
    }

    $CLICSHOPPING_Mail = Registry::get('Mail');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    $audience = $this->getAudience();

    if (\count($audience) === 0) {
      return;
    }

    $subject = $this->app->getDef('text_send_newsletter_subject', ['store_name' => STORE_NAME]);
    $text = strip_tags($this->content) . ' ' . $this->app->getDef('text_unsubscribe') . ' ' . HTTP::getShopUrlDomain() . 'index.php?Account&Newsletters';

    $CLICSHOPPING_Mail->addText($text);

    foreach ($audience as $value) {
      $CLICSHOPPING_Mail->send(
        $value['email_address'],
        HTML::sanitize(STORE_NAME),
        $this->emailFrom,
        $value['firstname'] . ' ' . $value['lastname'],
        $subject
      );
    }

    $Qupdate = $this->app->db->prepare('update :table_newsletters
                                        set date_sent = now(),
                                            status = 1
                                        where newsletters_id = :newsletters_id
                                       ');
    $Qupdate->bindInt(':newsletters_id', $newsletter_id);
    $Qupdate->execute();

    $CLICSHOPPING_Hooks->call('Newsletter', 'NewsletterSend');
  }
}
