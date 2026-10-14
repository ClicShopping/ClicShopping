<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM;

use EmailValidator\EmailValidator;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

/**
 * This class manages the creation and sending of emails using the PHPMailer library.
 * It supports both text and HTML emails, attachments, and can be configured
 * to use different email transports, including SMTP with different secure methods.
 * It also provides functionalities for adding custom headers, setting charset,
 * and managing recipients.
 */
class Mail
{
  protected string $html;
  protected string $text;
  protected string $html_text;
  protected string $lf;
  protected string $fileError;

//Enable SMTP debugging
// 0 = off (for production use)
// 1 = client messages
// 2 = client and server messages
  protected PHPMailer $phpMail;

  /**
   * Constructor method for initializing the PHPMailer instance and configuring mailing settings.
   *
   * It sets up the mail encoding, character set, word wrap, and X-Mailer signature.
   * Additionally, it determines the line feed format and defines the path for email error logging.
   *
   * @return void
   */
  public function __construct()
  {
    $this->phpMail = new PHPMailer();

    $this->phpMail->XMailer = 'ClicShopping ' . CLICSHOPPING::getVersion();
    $this->phpMail->CharSet = PHPMailer::CHARSET_UTF8;
    $this->phpMail->WordWrap = 998;
    $this->phpMail->Encoding = 'quoted-printable';

    /*
    //Configure message signing (the actual signing does not occur until sending)
          $phpMail->sign('/path/to/cert.crt', //The location of your certificate file
                        '/path/to/cert.key', //The location of your private key file
                        'yourSecretPrivateKeyPassword', //The password you protected your private key with (not the Import Password! may be empty but parameter must not be omitted!)
                        '/path/to/certchain.pem' //The location of your chain file
                        );
    */


    if (EMAIL_LINEFEED == 'CRLF') {
      $this->lf = "\r\n";
    } else {
      $this->lf = "\n";
    }

    $this->fileError = CLICSHOPPING::getWorkDirectory('Log') . '/phpmail_error-' . date('Ymd') . '.log';
  }

  /**
   * Sends an email using the configured PHPMailer instance and email transport method.
   *
   * The method determines the email transport type (e.g., SMTP, Gmail, or sendmail)
   * and sets up the PHPMailer instance accordingly. Additionally, it handles
   * optional debugging output if enabled. If the email fails to send, it logs
   * any encountered errors and returns false. Otherwise, it clears addresses
   * and attachments and returns true upon successful email dispatch.
   *
   * @return bool True if the email is successfully sent, false otherwise.
   */
  protected function sendPhpMailer(): bool
  {
    $filename_log = $this->fileError;

    if (EMAIL_TRANSPORT == 'smtp' || EMAIL_TRANSPORT == 'gmail') {
      try {
        if (DEBUG_EMAIL == 'true') {
          $this->phpMail->SMTPDebug = SMTP::DEBUG_SERVER;

          $this->phpMail->Debugoutput = function ($str, $level) {
            $filename = $this->fileError;
            $data = date('Y-m-d H:i:s') . "\t" . "\t$level\t$str\n";
            $flags = FILE_APPEND | LOCK_EX;

            file_put_contents($filename, $data, $flags);
          };
        }

        $this->phpMail->IsSMTP();

        $this->phpMail->Host = EMAIL_SMTP_HOSTS;
        $this->phpMail->SMTPAuth = EMAIL_SMTP_AUTHENTICATION;

        $this->phpMail->Username = EMAIL_SMTP_USER;
        $this->phpMail->Password = Hash::displayDecryptedDataText(EMAIL_SMTP_PASSWORD);

        if (EMAIL_SMTP_SECURE != 'no') {
          if (EMAIL_SMTP_SECURE == 'tls') {
            $this->phpMail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->phpMail->Port = 587;
          } elseif (EMAIL_SMTP_SECURE == 'ssl') {
            $this->phpMail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $this->phpMail->Port = 465;
          } else {
            $this->phpMail->SMTPSecure = EMAIL_SMTP_SECURE;
            $this->phpMail->Port = EMAIL_SMTP_PORT;
          }
        }
      } catch (Exception $e) {
        file_put_contents($filename_log, gmdate('Y-m-d H:i:s') . "\n$e->errorMessage\n", FILE_APPEND | LOCK_EX);
        $error = true;
      }
    } else {
      try {
        $this->phpMail->isSendmail();
      } catch (Exception $e) {
        file_put_contents($filename_log, gmdate('Y-m-d H:i:s') . "\n$e->errorMessage\n", FILE_APPEND | LOCK_EX);
        $error = true;
      }
    }

    $error = false;

    if (!$this->phpMail->send()) {
      $error = true;
    }

    $this->phpMail->clearAddresses();
    $this->phpMail->clearAttachments();

    if ($error === true) {
      return false;
    } else {
      return true;
    }
  }

  /**
   * Converts linefeeds in a string by replacing occurrences of a specified linefeed with another.
   *
   * @param string $from The linefeed string to be replaced.
   * @param string $to The linefeed string to replace with.
   * @param string $string The input string where the replacement occurs.
   * @return string The resulting string after linefeed replacement.
   */
  private function convertLinefeeds(string $from, string $to, string $string = ''): string
  {
    // Validate parameters to prevent TypeError
    if (!is_string($from)) {
      throw new \InvalidArgumentException('Parameter $from must be a string, ' . gettype($from) . ' given');
    }
    if (!is_string($to)) {
      throw new \InvalidArgumentException('Parameter $to must be a string, ' . gettype($to) . ' given');
    }
    if (!is_string($string)) {
      throw new \InvalidArgumentException('Parameter $string must be a string, ' . gettype($string) . ' given');
    }

    return str_replace($from, $to, $string);
  }

  /**
   * Adds plain text to the email content after converting linefeeds.
   *
   * @param string $text The plain text content to add. Defaults to an empty string.
   * @return void
   */
  public function addText(string $text = '')
  {
    $this->phpMail->IsHTML(false);

    // Sequential conversion of each line ending type to avoid TypeError
    $processedText = $text;
    $lineEndings = ["\r\n", "\n", "\r"];

    foreach ($lineEndings as $ending) {
      $processedText = $this->convertLinefeeds($ending, $this->lf, $processedText);
    }

    $this->text = $processedText;
  }

  /**
   * Adds HTML content and optional plain text content to the email message.
   *
   * @param string $html The HTML content to be added to the email.
   * @param string $text Optional plain text content, used as a fallback for email clients that do not support HTML.
   * @param mixed $images_dir Optional directory path for embedded images used within the HTML content.
   * @return void
   */
  public function addHtml(string $html, string $text = '', mixed $images_dir = NULL)
  {
    $this->phpMail->IsHTML(true);
    // Correction : passage des caractères de saut de ligne individuellement
    $this->html = $this->convertLinefeeds("\r\n", '<br />', $html);
    $this->html = $this->convertLinefeeds("\n", '<br />', $this->html);
    $this->html = $this->convertLinefeeds("\r", '<br />', $this->html);

    // Même chose pour le texte
    $this->html_text = $this->convertLinefeeds("\r\n", $this->lf, $text);
    $this->html_text = $this->convertLinefeeds("\n", $this->lf, $this->html_text);
    $this->html_text = $this->convertLinefeeds("\r", $this->lf, $this->html_text);

    if (isset($images_dir)) {
      $this->html = $this->phpMail->msgHTML($this->html, $images_dir);
    }
  }

  /**
   * Adds HTML content to the email using CKEditor formatting.
   *
   * @param string $html The HTML content to be added to the email.
   * @param string|null $text Optional plain text content as an alternative for non-HTML email clients.
   * @param string|null $images_dir Optional directory path for embedded images within the HTML content.
   * @return void
   */

  public function addHtmlCkeditor(string $html, ?string $text = NULL, ?string $images_dir = NULL): void
  {
    $this->phpMail->IsHTML(true);

    // Correction : traitement individuel des sauts de ligne
    $this->html = $this->convertLinefeeds("\r\n", '', $html);
    $this->html = $this->convertLinefeeds("\n", '', $this->html);
    $this->html = $this->convertLinefeeds("\r", '', $this->html);

    if ($text !== null) {
      $this->html_text = $this->convertLinefeeds("\r\n", $this->lf, $text);
      $this->html_text = $this->convertLinefeeds("\n", $this->lf, $this->html_text);
      $this->html_text = $this->convertLinefeeds("\r", $this->lf, $this->html_text);
    }

    if (isset($images_dir)) {
      $this->html = $this->phpMail->msgHTML($this->html, $images_dir);
    }
  }

  /**
   * Sets the content transfer encoding for the email.
   *
   * @param string $encoding The desired content transfer encoding type (e.g., "base64", "quoted-printable").
   * @return void
   */
  public function setContentTransferEncoding(string $encoding): void
  {
    $this->phpMail->Encoding = $encoding;
  }

  /**
   * Adds a CC (carbon copy) recipient to the email.
   *
   * @param string $email_address The email address of the recipient to be added as a CC.
   * @param string|null $name The name of the recipient, optional.
   * @return bool True on success, false on failure.
   */
  public function addCC(string $email_address, ?string $name = null)
  {
    return $this->phpMail->addCC($email_address, $name);
  }

  /**
   * Adds a "BCC" (blind carbon copy) recipient to the email.
   *
   * @param string $email_address The email address of the recipient to add as BCC.
   * @param string|null $name Optional name associated with the BCC email address.
   * @return bool True on success, false on failure.
   */
  public function addBCC(string $email_address, ?string $name = null)
  {
    return $this->phpMail->addBCC($email_address, $name);
  }

  /**
   * Clears all "To" recipients from the email.
   *
   * @return void
   */
  public function clearTo(): void
  {
    $this->phpMail->clearAllRecipients();
  }

  /**
   * Sets the character set for the email.
   *
   * @param string $charset The character set to be used (e.g., 'UTF-8').
   * @return void
   */
  public function setCharset(string $charset): void
  {
    $this->phpMail->CharSet = $charset;
  }

  /**
   *
   * @param string $key The name of the header to add.
   * @param string $value The value of the header to add.
   * @return void No return value.
   */
  public function addHeader(string $key, string $value): void
  {
    $this->phpMail->addCustomHeader($key, $value);
  }

  /**
   * Clears all custom headers from the email instance.
   *
   * @return void
   */
  public function clearHeaders(): void
  {
    $this->phpMail->clearCustomHeaders();
  }

  /**
   * Retrieves the mailer instance.
   *
   * @return mixed The mailer instance.
   */
  public function getMailer(): mixed
  {
    return $this->phpMail;
  }

  /**
   * Adds an attachment to the email.
   *
   * @param string $path The file path to the attachment.
   * @param string $name The name of the attachment. Optional, defaults to an empty string.
   * @param string $encoding The encoding of the attachment. Optional, defaults to 'base64'.
   * @param string $type The MIME type of the attachment. Optional, defaults to an empty string.
   * @param string $disposition The disposition of the attachment, such as 'attachment' or 'inline'. Optional, defaults to 'attachment'.
   * @return void
   */
  public function addAttachment(string $path, string $name = '', string $encoding = 'base64', string $type = '', string $disposition = 'attachment')
  {
    $this->phpMail->AddAttachment($path, $name, $encoding, $type, $disposition);
  }

  /**
   * Sends an email using the configured PHPMailer instance.
   *
   * @param string|null $to_addr The recipient's email address. Can be null.
   * @param string|null $from_name The sender's name. Can be null.
   * @param string|null $from_addr The sender's email address. Can be null.
   * @param string|null $to_name The recipient's name. Defaults to an empty string.
   * @param string $subject The subject of the email. Defaults to an empty string.
   * @param bool $reply_to Indicates whether to use a predefined reply-to address. Defaults to false.
   * @return bool True if the email process completes, regardless of actual sending status.
   */
  public function send(?string $to_addr, ?string $from_name, ?string $from_addr, ?string $to_name = '', string $subject = '', bool $reply_to = false): bool
  {
    if ((strstr($to_name, "\n") !== false) || (strstr($to_name, "\r") !== false)) {
      return false;
    }

    if ((strstr($to_addr, "\n") !== false) || (strstr($to_addr, "\r") !== false)) {
      return false;
    }

    if ((strstr($subject, "\n") !== false) || (strstr($subject, "\r") !== false)) {
      return false;
    }

    if ((strstr($from_name ?? '', "\n") !== false) || (strstr($from_name ?? '', "\r") !== false)) {
      return false;
    }

    if ((strstr($from_addr ?? '', "\n") !== false) || (strstr($from_addr ?? '', "\r") !== false)) {
      return false;
    }

    //Set who the message is to be sent from

    $this->phpMail->setFrom($from_addr, STORE_NAME);

    //Set who the message is to be sent to
    $this->phpMail->AddAddress($to_addr, $to_name ?? '');

    // check if the email is correct or not
// If not the email is not sent
    $error_email = false;

    if ($this->validateDomainEmail($to_addr) === false || $this->excludeEmailDomain($to_addr) === true || $this->temporaryEmailExcluded($to_addr) === true) {
      $error_email = true;
    }

    //Set an alternative reply-to address
    if ($reply_to) {
      $this->phpMail->AddReplyTo(EMAIL_SMTP_REPLYTO, STORE_NAME);
    } else {
      $this->phpMail->AddReplyTo($from_addr, $from_name);
    }

    $this->phpMail->Subject = $subject;

    if (!empty($this->html)) {
      $this->phpMail->Body = $this->html;

      if (!empty($this->html_text)) {
        $this->phpMail->AltBody = $this->html_text;
      } else {
        $this->phpMail->AltBody = HTML::sanitize(STORE_NAME);
      }
    } else {
      $this->phpMail->Body = $this->text;
      $this->phpMail->AltBody = HTML::sanitize(STORE_NAME);
    }

    if ($error_email === false) {
      $this->sendPhpMailer();
    }

    return true;
  }


  /**
   * Adds an image as an attachment to the email with inline disposition.
   *
   * @param string $file The path to the image file to be attached.
   * @return bool True on success, false on failure.
   */
  public function addImage(string $file)
  {
    return $this->phpMail->addAttachment($file, '', PHPMailer::ENCODING_BASE64, '', 'inline');
  }

  /**
   * Sends an email with the specified parameters. Allows for both HTML and plain text formats depending on configuration.
   *
   * @param string|null $to_email_address The recipient's email address. Defaults to null.
   * @param string|null $to_name The recipient's name. Defaults to null.
   * @param string $email_subject The subject of the email. Defaults to an empty string.
   * @param string $email_text The body content of the email. Defaults to an empty string.
   * @param string $from_email_name The name of the sender. Defaults to an empty string.
   * @param string $from_email_address The sender's email address. Defaults to an empty string.
   * @return bool Returns false if sending emails is disabled via configuration. Otherwise, returns no value explicitly.
   */
  public function clicMail(string|null $to_email_address = null, ?string $to_name = null, string $email_subject = '', string $email_text = '', string $from_email_name = '', string $from_email_address = '')
  {
    if (SEND_EMAILS == 'false') {
      return false;
    }

    // Build the text version
    $text = strip_tags($email_text);

    if (EMAIL_USE_HTML == 'true') {
      $this->addHtml($email_text, $text);
      $this->phpMail->AltBody = HTML::sanitize(STORE_NAME);
    } else {
      $this->addText($text);
      $this->phpMail->AltBody = HTML::sanitize(STORE_NAME);
    }

    // Send message
    $this->send($to_email_address, $from_email_name, $from_email_address, $to_name, $email_subject);
  }

  /**
   * Validates if the provided email is a valid domain email.
   *
   * @param string|null $email The email address to validate. Can be null.
   * @return bool Returns true if the email address is valid; otherwise, false.
   */
  public function validateDomainEmail(?string $email): bool
  {
    if (Is::EmailAddress($email, true) === false) {
      return false;
    }

    return true;
  }

  /**
   * Checks if the provided email belongs to a banned domain as defined in the configuration
   * and verifies the validity of the email address.
   *
   * @param string|null $email The email address to validate and check against the banned domain list. Defaults to an empty string.
   * @return bool Returns true if the email belongs to a banned domain or is invalid, otherwise false.
   */
  public function excludeEmailDomain(string|null $email = ''): bool
  {
    if (SEND_EMAILS == 'true') {
      if (filter_var($email, FILTER_VALIDATE_EMAIL) && !empty($email)) {
        $bannedDomainList = explode(',', CONFIGURATION_EXLCLUDE_EMAIL_DOMAIN);

        $contactEmailAddresses = [
          $email
        ];

        $config = [
          'checkMxRecords' => true,
          'checkBannedListedEmail' => true,
          'bannedList' => $bannedDomainList,
        ];

        $result = new EmailValidator($config);

        foreach ($contactEmailAddresses as $value) {
          $result = $result->validate($value);

          if ($result === false) {
            return true; // could be redirect but becarefull with an order
          }
        }
      }
    }

    return false;
  }

  /**
   * Checks if the provided email address belongs to a temporary or disposable email domain.
   *
   * @param string|null $email The email address to check.
   * @return bool Returns true if the email domain is excluded; otherwise, false.
   */
  private function temporaryEmailExcluded(string|null $email = ''): bool
  {
    if (SEND_EMAILS == 'true') {
      $temp_domains = [
        '10minutemail.com',
        'guerrillamail.com',
        'mailinator.com',
        'temp-mail.org',
        'trashmail.com',
        'yopmail.com',
        'maildrop.cc',
        'dispostable.com',
        'fakeinbox.com',
        'getairmail.com',
        'throwawaymail.com',
        'tempinbox.com',
        'moakt.com',
        'mytrashmail.com',
        'emailondeck.com',
        'mintemail.com',
        'mailnesia.com',
        'spambog.com',
        'spamgourmet.com',
        'sharklasers.com',
        'grr.la',
        'maildrop.cc',
        'anonbox.net',
        '0wnd.net',
        'binkmail.com',
        'spambox.us'
      ];


      $domain = strtolower(substr(strrchr($email, "@"), 1));

      if (in_array($domain, $temp_domains)) {
        return true;
      }
    }

    return false;
  }
}