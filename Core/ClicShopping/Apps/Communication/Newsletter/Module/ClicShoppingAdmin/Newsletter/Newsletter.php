<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Communication\Newsletter\Module\ClicShoppingAdmin\Newsletter;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\FileSystem;
use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Is;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Communication\Newsletter\Newsletter as AppNewsletter;
use ClicShopping\Apps\Configuration\TemplateEmail\Classes\ClicShoppingAdmin\TemplateEmailAdmin;

class Newsletter
{
  public mixed $app;
  public bool $show_chooseAudience;
  public string $title;
  public string $content;

  private int $languageId;
  private int $customerGroupId;
  private int $createFile;
  private int $newsletterNoAccount;
  private int $fileId;
  private string $emailFrom;

  /**
   * Constructor method for initializing the newsletter object and loading required data and configurations.
   *
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

    $this->show_chooseAudience = false;
    $this->title = $title;
    $this->content = $content;
    $this->emailFrom = HTML::sanitize(STORE_OWNER_EMAIL_ADDRESS);
    $this->newsletterNoAccount = (int)($_GET['ana'] ?? 0);
    $this->fileId = (int)($_GET['nID'] ?? 0);
    $this->languageId = (int)($_GET['nlID'] ?? 0);
    $this->customerGroupId = (int)($_GET['cgID'] ?? 0);
    $this->createFile = (int)($_GET['ac'] ?? 0);
  }

  /**
   * Chooses the appropriate audience for an action or content.
   *
   * @return bool Returns false if no audience is selected.
   */
  public function chooseAudience(): bool
  {
    return false;
  }

  /**
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
   * Executes the confirmation process for newsletter management, including file creation,
   * customer data validation, and UI rendering for confirmation and related actions.
   *
   * @return string Returns the confirmation string containing HTML content including buttons and messages for newsletters.
   */
  public function confirm(): string
  {
    $CLICSHOPPING_Hooks = Registry::get('Hooks');
    $CLICSHOPPING_Language = Registry::get('Language');

// delete all entries in the table newsletter temp for initilization
//
    $Qdelete = $this->app->db->prepare('delete from :table_newsletters_customers_temp');
    $Qdelete->execute();

    $file_name = '';

// ----------------------
// customer with an account
// ----------------------
    if ($this->languageId == 0) {
      $Qmail = $this->app->db->prepare('select count(*) as count
                                          from :table_customers
                                          where customers_newsletter = 1
                                          and (customers_group_id = :customers_group_id or customers_group_id = 99)
                                          and customers_email_validation = 0
                                        ');
      $Qmail->bindInt(':customers_group_id', (int)$this->customerGroupId);

      $Qmail->execute();
    } else {
      $Qmail = $this->app->db->prepare('select count(*) as count
                                          from :table_customers
                                          where customers_newsletter = 1
                                          and (languages_id = :languages_id or languages_id = 0)
                                          and (customers_group_id = :customers_group_id or customers_group_id = 99)
                                          and customers_email_validation = 0
                                        ');
      $Qmail->bindInt(':customers_group_id', (int)$this->customerGroupId);
      $Qmail->bindInt(':languages_id', $CLICSHOPPING_Language->getId());

      $Qmail->execute();
    }

    if ($this->createFile == 1) {
// newsletter file inserted in the pub directory
      if (function_exists('file_put_contents')) {
        $file_newsletter = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'sources/public/newsletter/newsletter_' . $this->fileId . '.html';
        $directory = '<a href="' . CLICSHOPPING::getConfig('http_server', 'Shop') . '/sources/public/newsletter/newsletter_' . $this->fileId . '.html" target="_blank" rel="noreferrer">' . CLICSHOPPING::getConfig('http_server', 'Shop') . '/sources/public/newsletter/newsletter_' . $this->fileId . '.html</a>';
// ----------------------
// creating document
// ----------------------
        $content = '<!DOCTYPE html>
                    <html ' . $this->app->getDef('html_params') . '>
                    <head>
                      <meta charset="' . $this->app->getDef('charset') . '" />
                      <meta http-equiv="X-UA-Compatible" content="IE=edge">
                      <meta name="robots" content="noindex,nofollow" />
                      <meta name="viewport" content="width=device-width, initial-scale=1">
                      <title>' . $this->title . '</title>
                      <meta name="description" content ="' . $this->title . '">
                     </head>
                    <body>
                      ' . $this->content . '
                    </body>
                   </html>
                  ';

// Write the contents back to the file
        if (FileSystem::isWritable(CLICSHOPPING::getConfig('dir_root', 'Shop') . 'sources/public/newsletter')) {
          file_put_contents($file_newsletter, $content, LOCK_EX);
        }
      }

      if (FileSystem::isWritable(CLICSHOPPING::getConfig('dir_root', 'Shop') . 'sources/public/newsletter')) {
        $file_name = '<div class="alert alert-success text-center" role="alert">';
        $file_name .= '<p class="text-center"><strong>' . $this->app->getDef('text_file_newsletter') . '</strong> newsletter_' . (int)$this->fileId . '.html<br /><span style="color:#ff0000;"><strong>' . $this->app->getDef('text_file_directories') . '</b></strong> ' . $directory . '</span></p>';
        $file_name .= '</div>';
      } else {
        $file_name = '<div class="alert alert-warning text-center" role="alert">';
        $file_name .= 'Newsletter no created : <strong>' . CLICSHOPPING::getConfig('dir_root', 'Shop') . 'sources/public/newsletter -  no writable</strong>';
        $file_name .= '</div>';
      }
    }

// ----------------------
// Display a button if subcription is > 0
// ----------------------
    if (SEND_EMAILS == 'true' && $Qmail->valueInt('count') > 0) {
      $send_button = '<span class="float-end">' . HTML::button($this->app->getDef('button_send'), null, $this->app->link('ConfirmSend&page=' . (int)$_GET['page'] . '&nID=' . $this->fileId . '&nlID=' . $this->languageId . '&cgID=' . $this->customerGroupId . '&ac=' . $this->createFile . '&ana=' . $this->newsletterNoAccount), 'success', null) . '</span>';
    } else {
      $send_button = '';
    }

    $confirm_string = '';

    $confirm_string .= '
      <div class="contentBody">
        <div class="row" id="newsletterButton">
          <div class="col-md-12">
            <div class="card card-block headerCard">
              <div class="row">
                <span class="col-md-12">
      ';
    $confirm_string .= $send_button;
    $confirm_string .= '<span class="float-end">' . HTML::button($this->app->getDef('button_cancel'), null, $this->app->link('Newsletter&page=' . (int)$_GET['page'] . '&nID=' . $this->fileId), 'warning') . '&nbsp;</span>';
    $confirm_string .= '
                </span>
              </div>
            </div>
          </div>
        </div>
    ';

    $confirm_string .= '<div class="mt-1"></div>';

    $confirm_string .= '<div id="newsletterBody">' . "\n";
    $confirm_string .= '<div class="text-center alert alert-info" id="newsletterAlert">';
    $confirm_string .= '<div id="newsletterCount"><strong>' . $this->app->getDef('text_count_customers') . ' ' . $Qmail->valueInt('count') . '<strong></div>';
    $confirm_string .= '</div>' . "\n";

    $confirm_string .= $file_name . "\n";
    $confirm_string .= '<div class="mt-1"></div>' . "\n";
    $confirm_string .= '<div><strong>' . $this->title . '</strong></div>' . "\n";
    $confirm_string .= '<div class="mt-1"></div>' . "\n";
    $confirm_string .= '<div>' . $this->content . '</div>' . "\n";
    $confirm_string .= '<div class="mt-1"></div>';
    $confirm_string .= '</div>';
    $confirm_string .= '</div>';

    $confirm_string .= $CLICSHOPPING_Hooks->output('Newsletter', 'NewsletterContentPreAction', null, 'display');

    return $confirm_string;
  }

  /**
   * Sends the newsletter to customers who have subscribed to it.
   * It retrieves customer data, processes the email content, and sends the emails in batches.
   * It also handles error checking and temporary storage of customer data.
   *
   * @param int $newsletter_id The ID of the newsletter being sent.
   * @return mixed
   */
  public function send(int $newsletter_id): void
  {
    $CLICSHOPPING_Mail = Registry::get('Mail');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');
    $CLICSHOPPING_Language = Registry::get('Language');

    $this->checkStatus();

    if ($this->languageId == 0) {
      $Qmail = $this->app->db->prepare('select customers_firstname,
                                               customers_lastname,
                                               customers_email_address
                                        from :table_customers
                                        where customers_newsletter = 1
                                        and (customers_group_id = :customers_group_id or customers_group_id = 99)
                                        and customers_email_validation = 0
                                       ');
      $Qmail->bindInt(':customers_group_id', (int)$this->customerGroupId);
      $Qmail->execute();
    } else {
      $Qmail = $this->app->db->prepare('select customers_firstname,
                                                 customers_lastname,
                                                 customers_email_address
                                          from :table_customers
                                          where customers_newsletter = 1
                                          and (languages_id = :languages_id or languages_id = 0)
                                          and (customers_group_id = :customers_group_id or customers_group_id = 99)
                                          and customers_email_validation = 0
                                          ');
      $Qmail->bindInt(':customers_group_id', (int)$this->customerGroupId);
      $Qmail->bindInt(':languages_id', $CLICSHOPPING_Language->getId());
      $Qmail->execute();
    } //end $this->languageId

    $max_execution_time = 0.8 * (int)ini_get('max_execution_time');
    $time_start = explode(' ', PAGE_PARSE_START_TIME);

    // ----------------------
    // if the file is created
    // ----------------------
    if ($this->createFile == 1) {
      $CLICSHOPPING_Mail->addText('<p class="text-center">' . $this->app->getDef('text_send_newsletter_email', ['store_owner_email_address' => STORE_OWNER_EMAIL_ADDRESS]) . '</p>' . $this->content . ' ' . $this->app->getDef('text_send_newsletter', ['store_name' => STORE_NAME]) . ' ' . HTTP::getShopUrlDomain() . 'sources/public/newsletter/newsletter_' . $this->fileId . '.html<br /><br />' . TEXT_UNSUBSCRIBE . HTTP::getShopUrlDomain() . 'index.php?Account&Newsletters');
    } else {
      $CLICSHOPPING_Mail->addText('<p class="text-center">' . $this->app->getDef('text_send_newsletter_email', ['store_owner_email_address' => STORE_OWNER_EMAIL_ADDRESS]) . '</p>' . $this->content . ' ' . $this->app->getDef('text_send_newsletter', ['store_name' => STORE_NAME]) . ' ' . HTTP::getShopUrlDomain() . 'index.php?Account&Newsletters');
    }

    // ------------------------------------------
    // copy e-mails to a temporary table if that table is empty
    // ------------------------------------------
    $Qcheck = $this->app->db->prepare('select count(customers_email_address) as num_customers_email_address
                                         from :table_newsletters_customers_temp
                                       ');
    $Qcheck->execute();

    if ($Qcheck->valueInt('num_customers_email_address') == 0) {
      // ------------------------------------------
      // copy customers account in temp newsletter
      // ------------------------------------------
      $this->app->db->delete('newsletters_customers_temp');

      while ($Qmail->fetch()) {
        if (Is::EmailAddress($Qmail->value('customers_email_address'))) {
          $data_array = [
            'customers_firstname' => addslashes($Qmail->value('customers_firstname')),
            'customers_lastname' => addslashes($Qmail->value('customers_lastname')),
            'customers_email_address' => $Qmail->value('customers_email_address')
          ];

          $this->app->db->save('newsletters_customers_temp', $data_array);
        }
      }  // end while
    } else {
      echo '<div class="alert alert-warning text-center">There is a problem with your newsletters_customers_temp database, please, click cancel to go back and retry.</div>';
    }

    $QmailNewsletterAccountTemp = $this->app->db->prepare('select customers_firstname,
                                                                     customers_lastname,
                                                                     customers_email_address
                                                              from :table_newsletters_customers_temp
                                                            ');
    $QmailNewsletterAccountTemp->execute();

    while ($QmailNewsletterAccountTemp->fetch()) {
      $time_end = explode(' ', microtime());
      $timer_total = number_format(($time_end[1] + $time_end[0] - ($time_start[1] + $time_start[0])), 3);

      if ($timer_total > $max_execution_time) {
        echo("<meta http-equiv=\"refresh\" content=\"12\">");
      }

      $CLICSHOPPING_Mail->send($QmailNewsletterAccountTemp->value('customers_email_address'), $QmailNewsletterAccountTemp->value('customers_firstname') . ' ' . $QmailNewsletterAccountTemp->value('customers_lastname'), null, $this->emailFrom, $this->title);

      // delete all entry in the table
      $Qdelete = $this->app->db->prepare('delete
                                            from :table_newsletters_customers_temp
                                            where customers_email_address = :customers_email_address
                                          ');
      $Qdelete->bindValue(':customers_email_address', $QmailNewsletterAccountTemp->value('customers_email_address'));
      $Qdelete->execute();
    } //end while

    $newsletter_id = HTML::sanitize($newsletter_id);

    $Qupdate = $this->app->db->prepare('update :table_newsletters
                                        set date_sent = now(),
                                              status = 1
                                        where newsletters_id = :newsletters_id
                                       ');
    $Qupdate->bindInt(':newsletters_id', $newsletter_id);
    $Qupdate->execute();

    $CLICSHOPPING_Hooks->call('Newsletter', 'NewsletterSend');
  } // end function

// ***************************************************
//                     HTML NEWSLETTER
// **************************************************

  /**
   * Sends the newsletter using CKEditor, including HTML content and email signature.
   * It retrieves customer data, processes the email content, and sends the emails in batches.
   * It also handles error checking and temporary storage of customer data.
   *
   * @return mixed
   */
  public function sendCkeditor(): void
  {
    $CLICSHOPPING_Mail = Registry::get('Mail');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    $this->checkStatus();
// ----------------------
//customer witt account
// ----------------------

    $template_email_signature = TemplateEmailAdmin::getTemplateEmailSignature();
    $template_email_newsletter_footer = TemplateEmailAdmin::getTemplateEmailNewsletterTextFooter();
    $email_footer = '<br />' . $template_email_signature . '<br />' . $template_email_newsletter_footer;

    $max_execution_time = 0.8 * (int)ini_get('max_execution_time');
    $time_start = explode(' ', PAGE_PARSE_START_TIME);

    if ($this->languageId == 0) {
      $Qmail = $this->app->db->prepare('select customers_firstname,
                                                 customers_lastname,
                                                 customers_email_address
                                          from :table_customers
                                          where customers_newsletter = 1
                                          and (customers_group_id = :customers_group_id or customers_group_id = 99)
                                        and customers_email_validation = 0
                                         ');
      $Qmail->bindInt(':customers_group_id', (int)$this->customerGroupId);
      $Qmail->execute();
    } else {
      $Qmail = $this->app->db->prepare('select customers_firstname,
                                                 customers_lastname,
                                                 customers_email_address
                                          from :table_customers
                                          where customers_newsletter = 1
                                          and (languages_id = :languages_id or languages_id = 0)
                                          and (customers_group_id = :customers_group_id or customers_group_id = 99)
                                          and customers_email_validation = 0
                                         ');
      $Qmail->bindInt(':customers_group_id', $this->customerGroupId);
      $Qmail->bindInt(':languages_id', $this->languageId);
      $Qmail->execute();
    }

    $Qcheck = $this->app->db->prepare('select count(customers_email_address) as num_customers_email_address
                                         from :table_newsletters_customers_temp
                                        ');
    $Qcheck->execute();

    if ($Qcheck->value('num_customers_email_address') == 0) {
// ------------------------------------------
// copy customers account in temp newsletter
// ------------------------------------------
      $this->app->db->delete('newsletters_customers_temp');

      $batch = [];
      $batch_size = 100;
      $iteration = 0;
      $check_interval = 100;

      while ($Qmail->fetch()) {
        if (Is::EmailAddress($Qmail->value('customers_email_address'))) {
          $batch[] = [
            'customers_firstname' => $Qmail->value('customers_firstname'),
            'customers_lastname' => $Qmail->value('customers_lastname'),
            'customers_email_address' => $Qmail->value('customers_email_address')
          ];
        }

        if (count($batch) >= $batch_size) {
          $this->app->db->save('newsletters_customers_temp', $batch);
          $batch = [];
        }

        if (($iteration++ % $check_interval) === 0) {
          if ((microtime(true) - $time_start) > $max_execution_time) {
            if (!empty($batch)) {
              $this->app->db->save('newsletters_customers_temp', $batch);
            }

            echo("<meta http-equiv=\"refresh\" content=\"12\">");
          }
        }
      }
    } else {
      echo '<br />';
      echo '<span class="text-warning text-center">There is a pb with newsletters_customers_temp Database, Click Cancel to go back and retry.</span><br />';
    }

    $QmailNewsletterAccountTemp = $this->app->db->prepare('select customers_firstname,
                                                                   customers_lastname,
                                                                   customers_email_address
                                                            from :table_newsletters_customers_temp
                                                         ');
    $QmailNewsletterAccountTemp->execute();
    $send_newsletter = $QmailNewsletterAccountTemp->fetchAll();

    $subject = $this->app->getDef('text_send_newsletter_subject', ['store_name' => STORE_NAME]);

    if ($this->createFile == 1) {
      $message = html_entity_decode('<p class="text-center">' . $this->app->getDef('text_send_newsletter_email', ['store_name' => STORE_NAME, 'store_owner_email_address' => STORE_OWNER_EMAIL_ADDRESS]) . '</p>' . $this->content . $this->app->getDef('text_send_newsletter', ['store_name' => STORE_NAME]) . HTTP::getShopUrlDomain() . 'sources/public/newsletter/newsletter_' . $this->fileId . '.html<br /><br />' . $email_footer);
    } else {
      $message = html_entity_decode('<p class="text-center">' . $this->app->getDef('text_send_newsletter_email', ['store_name' => STORE_NAME, 'store_owner_email_address' => STORE_OWNER_EMAIL_ADDRESS]) . '</p>' . $this->content . $email_footer);
    }

    $message = str_replace('src="/', 'src="' . HTTP::getShopUrlDomain(), $message);

    $CLICSHOPPING_Mail->addHtmlCkeditor($message);

    foreach ($send_newsletter as $value) {
      $CLICSHOPPING_Mail->send($value['customers_email_address'], $value['customers_firstname'] . ' ' . $value['customers_lastname'], $this->emailFrom, null, $subject);
    }

    $CLICSHOPPING_Hooks->call('Newsletter', 'NewsletterSendCkEditor');
  }
}
