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

// delete this newsletter's leftover entries in the temp table for initialization
// (scoped so a concurrent send of another newsletter is not wiped)
    $Qdelete = $this->app->db->prepare('delete from :table_newsletters_customers_temp
                                        where newsletters_id = :newsletters_id
                                       ');
    $Qdelete->bindInt(':newsletters_id', $this->fileId);
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
                                         where newsletters_id = :newsletters_id
                                       ');
    $Qcheck->bindInt(':newsletters_id', (int)$newsletter_id);
    $Qcheck->execute();

    if ($Qcheck->valueInt('num_customers_email_address') == 0) {
      // ------------------------------------------
      // copy customers account in temp newsletter
      // ------------------------------------------
      $this->app->db->delete('newsletters_customers_temp', ['newsletters_id' => (int)$newsletter_id]);

      while ($Qmail->fetch()) {
        if (Is::EmailAddress($Qmail->value('customers_email_address'))) {
          $data_array = [
            'newsletters_id' => (int)$newsletter_id,
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
                                                              where newsletters_id = :newsletters_id
                                                            ');
    $QmailNewsletterAccountTemp->bindInt(':newsletters_id', (int)$newsletter_id);
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
                                            where newsletters_id = :newsletters_id
                                            and customers_email_address = :customers_email_address
                                          ');
      $Qdelete->bindInt(':newsletters_id', (int)$newsletter_id);
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
   * Populates the temporary recipients table from the subscribed customers, once.
   *
   * The table acts as the work queue for a resumable mass send: it is filled only
   * when empty (for this newsletter) so that resuming an interrupted send continues
   * with the remaining recipients instead of starting over (which would create
   * duplicates). Rows are scoped by newsletters_id so concurrent sends of different
   * newsletters do not collide on this shared table.
   *
   * @param int $newsletter_id The newsletter owning the queued recipients.
   * @return void
   */
  private function populateRecipientsTemp(int $newsletter_id): void
  {
    $Qcheck = $this->app->db->prepare('select count(customers_email_address) as num_customers_email_address
                                         from :table_newsletters_customers_temp
                                         where newsletters_id = :newsletters_id
                                        ');
    $Qcheck->bindInt(':newsletters_id', $newsletter_id);
    $Qcheck->execute();

    // Already populated: we are resuming an in-progress send.
    if ($Qcheck->valueInt('num_customers_email_address') > 0) {
      return;
    }

    if ($this->languageId == 0) {
      $Qmail = $this->app->db->prepare('select customers_firstname,
                                                customers_lastname,
                                                customers_email_address
                                         from :table_customers
                                         where customers_newsletter = 1
                                         and (customers_group_id = :customers_group_id or customers_group_id = 99)
                                         and customers_email_validation = 0
                                        ');
      $Qmail->bindInt(':customers_group_id', $this->customerGroupId);
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

    $batch = [];
    $batch_size = 100;

    while ($Qmail->fetch()) {
      if (Is::EmailAddress($Qmail->value('customers_email_address'))) {
        $batch[] = [
          'newsletters_id' => $newsletter_id,
          'customers_firstname' => $Qmail->value('customers_firstname'),
          'customers_lastname' => $Qmail->value('customers_lastname'),
          'customers_email_address' => $Qmail->value('customers_email_address')
        ];
      }

      if (\count($batch) >= $batch_size) {
        $this->app->db->save('newsletters_customers_temp', $batch);
        $batch = [];
      }
    }

    if (!empty($batch)) {
      $this->app->db->save('newsletters_customers_temp', $batch);
    }
  }

  /**
   * Sends the newsletter using CKEditor, including HTML content and email signature.
   *
   * The send is performed by batches over a bounded time budget and is fully
   * resumable: recipients are queued in :table_newsletters_customers_temp and each
   * one is deleted from the queue as soon as it is sent. When the time budget is
   * exhausted the method returns false so the caller can re-enter and process the
   * remaining recipients; it returns true once the queue is empty and the newsletter
   * has been flagged as sent. This prevents both the previous fatal (array timer)
   * and the duplicate sends on interruption.
   *
   * @param int $newsletter_id The ID of the newsletter being sent.
   * @return bool True when every recipient has been processed, false when more remain.
   */
  public function sendCkeditor(int $newsletter_id): bool
  {
    if (!$this->checkStatus()) {
      return true;
    }

    $CLICSHOPPING_Mail = Registry::get('Mail');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    $template_email_signature = TemplateEmailAdmin::getTemplateEmailSignature();
    $template_email_newsletter_footer = TemplateEmailAdmin::getTemplateEmailNewsletterTextFooter();
    $email_footer = '<br />' . $template_email_signature . '<br />' . $template_email_newsletter_footer;

    // Bound the work done in a single request. max_execution_time = 0 means
    // unlimited; fall back to a conservative budget so we still chunk the send.
    $max_execution_time = (int)ini_get('max_execution_time');
    $time_budget = $max_execution_time > 0 ? 0.7 * $max_execution_time : 20.0;
    $time_start = array_sum(array_map('floatval', explode(' ', PAGE_PARSE_START_TIME)));

    // Fill the work queue once (no-op when resuming).
    $this->populateRecipientsTemp($newsletter_id);

    $subject = $this->app->getDef('text_send_newsletter_subject', ['store_name' => STORE_NAME]);

    if ($this->createFile == 1) {
      $message = html_entity_decode('<p class="text-center">' . $this->app->getDef('text_send_newsletter_email', ['store_name' => STORE_NAME, 'store_owner_email_address' => STORE_OWNER_EMAIL_ADDRESS]) . '</p>' . $this->content . $this->app->getDef('text_send_newsletter', ['store_name' => STORE_NAME]) . HTTP::getShopUrlDomain() . 'sources/public/newsletter/newsletter_' . $this->fileId . '.html<br /><br />' . $email_footer);
    } else {
      $message = html_entity_decode('<p class="text-center">' . $this->app->getDef('text_send_newsletter_email', ['store_name' => STORE_NAME, 'store_owner_email_address' => STORE_OWNER_EMAIL_ADDRESS]) . '</p>' . $this->content . $email_footer);
    }

    $message = str_replace('src="/', 'src="' . HTTP::getShopUrlDomain(), $message);

    $CLICSHOPPING_Mail->addHtmlCkeditor($message);

    // Process the queue by small slices, deleting each recipient as soon as the
    // mail is handed to the transport, until the queue is empty or time runs out.
    $slice_size = 50;
    $paused = false;

    do {
      $Qrecipients = $this->app->db->prepare('select customers_firstname,
                                                      customers_lastname,
                                                      customers_email_address
                                               from :table_newsletters_customers_temp
                                               where newsletters_id = :newsletters_id
                                               limit ' . $slice_size . '
                                              ');
      $Qrecipients->bindInt(':newsletters_id', $newsletter_id);
      $Qrecipients->execute();
      $recipients = $Qrecipients->fetchAll();

      if (\count($recipients) === 0) {
        break;
      }

      foreach ($recipients as $value) {
        $CLICSHOPPING_Mail->send(
          $value['customers_email_address'],
          HTML::sanitize(STORE_NAME),
          $this->emailFrom,
          $value['customers_firstname'] . ' ' . $value['customers_lastname'],
          $subject
        );

        $Qdelete = $this->app->db->prepare('delete
                                            from :table_newsletters_customers_temp
                                            where newsletters_id = :newsletters_id
                                            and customers_email_address = :customers_email_address
                                           ');
        $Qdelete->bindInt(':newsletters_id', $newsletter_id);
        $Qdelete->bindValue(':customers_email_address', $value['customers_email_address']);
        $Qdelete->execute();

        if ((microtime(true) - $time_start) > $time_budget) {
          $paused = true;
          break 2;
        }
      }
    } while (true);

    // Paused on the time budget: only ask for a resume if recipients still remain.
    // (If the budget tripped right after the last recipient the queue is empty and
    // we fall through to completion, avoiding a repopulate-and-resend.)
    if ($paused) {
      $Qremaining = $this->app->db->prepare('select count(customers_email_address) as num_customers_email_address
                                              from :table_newsletters_customers_temp
                                              where newsletters_id = :newsletters_id
                                             ');
      $Qremaining->bindInt(':newsletters_id', $newsletter_id);
      $Qremaining->execute();

      if ($Qremaining->valueInt('num_customers_email_address') > 0) {
        return false;
      }
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
}
