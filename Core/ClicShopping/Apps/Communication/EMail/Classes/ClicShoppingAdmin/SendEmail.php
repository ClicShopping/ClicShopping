<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Communication\EMail\Classes\ClicShoppingAdmin;

use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;
use ClicShopping\Apps\Configuration\TemplateEmail\Classes\ClicShoppingAdmin\TemplateEmailAdmin;
use ClicShopping\OM\Hash;
use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Is;
use ClicShopping\OM\Registry;

/**
 * Handles the resumable, batched send of an admin e-mail to customers.
 * Recipients are queued in the shared :table_newsletters_customers_temp work queue
 * under batch_type = 0 (the Newsletter app uses batch_type = 1) so both apps can run
 * concurrent sends without colliding.
 */
class SendEmail
{
  /**
   * Discriminator value used in the shared work queue.
   * 0 = EMail app, 1 = Newsletter app (see Schema/MariaDb/newsletters_customers_temp.txt).
   */
  public const BATCH_TYPE = 0;

  private mixed $app;
  private mixed $db;

  public function __construct()
  {
    $this->app = Registry::get('EMail');
    $this->db = $this->app->db;
  }

  /**
   * Queues the selected recipients into the shared work queue (batch_type = 0) for a
   * resumable send. Audience is selected by $criteria:
   *  '***'   all validated customers, '**D' newsletter subscribers,
   *  'group' B2B customers (group id != 0), otherwise a single e-mail address.
   * For the single-address case a customers_notes entry is also recorded.
   *
   * @param string $criteria The audience selector posted by the form.
   * @return void
   */
  public function populateEmailRecipients(string $criteria): void
  {
    // Clear any leftover e-mail rows from a previous run (scoped to batch_type = 0).
    $this->db->delete('newsletters_customers_temp', ['batch_type' => self::BATCH_TYPE]);

    switch ($criteria) {
      case '***':
        $Qmail = $this->db->prepare('select customers_firstname,
                                            customers_lastname,
                                            customers_email_address
                                     from :table_customers
                                     where customers_email_validation = 0
                                    ');
        $Qmail->execute();
        break;

      case '**D':
        $Qmail = $this->db->prepare('select customers_firstname,
                                            customers_lastname,
                                            customers_email_address
                                     from :table_customers
                                     where customers_newsletter = 1
                                     and customers_email_validation = 0
                                    ');
        $Qmail->execute();
        break;

      case 'group':
        $Qmail = $this->db->prepare('select customers_firstname,
                                            customers_lastname,
                                            customers_email_address
                                     from :table_customers
                                     where customers_group_id != 0
                                     and customers_email_validation = 0
                                    ');
        $Qmail->execute();
        break;

      default:
        $email_address = HTML::sanitize($criteria);

        $this->saveSingleRecipientNote($email_address);

        $Qmail = $this->db->prepare('select customers_firstname,
                                            customers_lastname,
                                            customers_email_address
                                     from :table_customers
                                     where customers_email_address = :customers_email_address
                                     and customers_email_validation = 0
                                    ');
        $Qmail->bindValue(':customers_email_address', $email_address);
        $Qmail->execute();
        break;
    }

    while ($Qmail->fetch()) {
      if (Is::EmailAddress($Qmail->value('customers_email_address'))) {
        $this->db->save('newsletters_customers_temp', [
          'batch_type' => self::BATCH_TYPE,
          'newsletters_id' => 0,
          'customers_firstname' => $Qmail->value('customers_firstname'),
          'customers_lastname' => $Qmail->value('customers_lastname'),
          'customers_email_address' => $Qmail->value('customers_email_address')
        ]);
      }
    }
  }

  /**
   * Records a customers_notes entry for the single-recipient case (mirrors the
   * previous synchronous behaviour). Uses the message stored in the session.
   *
   * @param string $email_address The recipient e-mail address.
   * @return void
   */
  private function saveSingleRecipientNote(string $email_address): void
  {
    $batch = $_SESSION['email_batch'] ?? null;

    if ($batch === null || empty($batch['message'])) {
      return;
    }

    $Qcustomer = $this->db->prepare('select customers_id
                                     from :table_customers
                                     where customers_email_address = :customers_email_address
                                     and customers_email_validation = 0
                                    ');
    $Qcustomer->bindValue(':customers_email_address', $email_address);
    $Qcustomer->execute();

    if ($Qcustomer->fetch()) {
      $customers_id = $Qcustomer->valueInt('customers_id');

      if (!empty($customers_id)) {
        $this->db->save('customers_notes', [
          'customers_id' => $customers_id,
          'customers_notes' => $batch['subject'] . ' <br />' . $batch['message'],
          'customers_notes_date' => 'now()',
          'user_administrator' => AdministratorAdmin::getUserAdmin()
        ]);
      }
    }
  }

  /**
   * Sends one time-bounded slice of the queued recipients (batch_type = 0) and is
   * fully resumable: each recipient is deleted from the queue as soon as the mail is
   * handed to the transport. Returns false while recipients remain (the caller must
   * re-enter to continue), true once the queue is empty.
   *
   * @return bool True when every queued recipient has been processed.
   */
  public function sendEmailBatch(): bool
  {
    $batch = $_SESSION['email_batch'] ?? null;

    if ($batch === null) {
      return true;
    }

    $CLICSHOPPING_Mail = Registry::get('Mail');

    $signature = TemplateEmailAdmin::getTemplateEmailSignature();
    $footer = TemplateEmailAdmin::getTemplateEmailTextFooter();

    $message = $batch['message'] . '<br />' . $signature . '<br />' . $footer;
    $message = str_replace('src="/', 'src="' . HTTP::getShopUrlDomain(), $message);

    $CLICSHOPPING_Mail->addHtmlCkeditor($message);

    // Bound the work done in a single request (max_execution_time = 0 => unlimited;
    // fall back to a conservative budget so the send is still chunked).
    $max_execution_time = (int)ini_get('max_execution_time');
    $time_budget = $max_execution_time > 0 ? 0.7 * $max_execution_time : 20.0;
    $time_start = array_sum(array_map('floatval', explode(' ', PAGE_PARSE_START_TIME)));

    $slice_size = 50;
    $paused = false;

    do {
      $Qrecipients = $this->db->prepare('select customers_firstname,
                                                customers_lastname,
                                                customers_email_address
                                         from :table_newsletters_customers_temp
                                         where batch_type = :batch_type
                                         limit ' . $slice_size . '
                                        ');
      $Qrecipients->bindInt(':batch_type', self::BATCH_TYPE);
      $Qrecipients->execute();
      $recipients = $Qrecipients->fetchAll();

      if (\count($recipients) === 0) {
        break;
      }

      foreach ($recipients as $value) {
        $CLICSHOPPING_Mail->send(
          $value['customers_email_address'],
          HTML::sanitize(STORE_NAME),
          $batch['from'],
          Hash::displayDecryptedDataText($value['customers_firstname']) . ' ' . Hash::displayDecryptedDataText($value['customers_lastname']),
          $batch['subject']
        );

        $Qdelete = $this->db->prepare('delete
                                       from :table_newsletters_customers_temp
                                       where batch_type = :batch_type
                                       and customers_email_address = :customers_email_address
                                      ');
        $Qdelete->bindInt(':batch_type', self::BATCH_TYPE);
        $Qdelete->bindValue(':customers_email_address', $value['customers_email_address']);
        $Qdelete->execute();

        if ((microtime(true) - $time_start) > $time_budget) {
          $paused = true;
          break 2;
        }
      }
    } while (true);

    if ($paused) {
      $Qremaining = $this->db->prepare('select count(customers_email_address) as num_customers_email_address
                                        from :table_newsletters_customers_temp
                                        where batch_type = :batch_type
                                       ');
      $Qremaining->bindInt(':batch_type', self::BATCH_TYPE);
      $Qremaining->execute();

      if ($Qremaining->valueInt('num_customers_email_address') > 0) {
        return false;
      }
    }

    unset($_SESSION['email_batch']);

    return true;
  }
}
