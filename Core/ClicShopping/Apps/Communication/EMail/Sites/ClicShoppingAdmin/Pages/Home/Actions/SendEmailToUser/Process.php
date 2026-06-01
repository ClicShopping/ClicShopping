<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Communication\EMail\Sites\ClicShoppingAdmin\Pages\Home\Actions\SendEmailToUser;

use ClicShopping\Apps\Communication\EMail\Classes\ClicShoppingAdmin\SendEmail;
use ClicShopping\OM\HTML;
use ClicShopping\OM\RateLimiter;
use ClicShopping\OM\Registry;

class Process extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  protected $from;
  protected $subject;
  protected $messageMail;
  public mixed $app;

  public function __construct()
  {
    $this->from = HTML::sanitize($_POST['from'] ?? '');
    $this->subject = HTML::sanitize($_POST['subject'] ?? '');
    $this->messageMail = $_POST['message'] ?? '';
    $this->app = Registry::get('EMail');
  }

  public function execute()
  {
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    // Rate-limit the start of a new send to avoid accidental double submissions.
    $limiter = new RateLimiter(['send_email' => 30]);
    $rate_check = $limiter->check('send_email');

    if (!$rate_check['allowed']) {
      $CLICSHOPPING_MessageStack->add($rate_check['message'], 'warning', 'email');
      $this->app->redirect('EMail');
      return;
    }

    if (!isset($_POST['customers_email_address'])) {
      $CLICSHOPPING_MessageStack->add($this->app->getDef('error_email_sent'), 'error', 'email');
      $this->app->redirect('EMail');
      return;
    }

    // Persist the e-mail content so every resume request can keep sending.
    $_SESSION['email_batch'] = [
      'from' => $this->from,
      'subject' => $this->subject,
      'message' => $this->messageMail
    ];

    // Queue the selected recipients (batch_type = 0), then hand off to the SendProgress
    // page that performs the resumable, batched send.
    (new SendEmail())->populateEmailRecipients(HTML::sanitize($_POST['customers_email_address']));

    $limiter->record('send_email');

    $this->app->redirect('SendProgress');
  }
}
