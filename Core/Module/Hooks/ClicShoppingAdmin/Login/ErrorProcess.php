<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\ClicShoppingAdmin\Login;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Is;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Configuration\TemplateEmail\Classes\ClicShoppingAdmin\TemplateEmailAdmin;

class ErrorProcess
{
  /**
   * Executes the method responsible for sending a security alert email when an unauthorized access attempt is detected on the admin panel.
   * Utilizes session data, username sanitization, and IP address details to build a detailed report.
   * The report includes information such as IP address, host name, referer, geo-location data (city, country, region), and a Google Maps link.
   * The report is then sent via email using the configured mail settings.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_Mail = Registry::get('Mail');
// send an email if someone try to connect on admin panel without authorization
// get ip and infos
    if (SEND_EMAILS == 'true' && CONFIGURATION_EMAIL_SECURITY == 'true') {
      if (isset($_SESSION['redirect_origin']) && isset($_SESSION['redirect_origin']['auth_user']) && !isset($_POST['username'])) {
        $username = HTML::sanitize($_SESSION['redirect_origin']['auth_user']);
      } else {
        $username = HTML::sanitize($_POST['username']);
      }

      $ip = HTTP::getIpAddress();

      if (Is::IpAddress($ip)) {
        $host = @gethostbyaddr($ip);
        $referer = $_SERVER['HTTP_REFERER'];
        $details = file_get_contents("https://ipinfo.io/{$ip}/geo");

        if ($details !== false) {
          $details = json_decode($details);

          $country = $details->country;
          $city = $details->city;
          $region = $details->region;
          $localisation = $details->loc;
          $google_map = CLICSHOPPING::getDef('report_sender_ip_address', ['IP' => $ip]) . ' : https://www.google.com/maps/place/' . $localisation;
          $new_info_ip = CLICSHOPPING::getDef('report_sender_ip_address', ['IP' => $ip]) . ' <a href="https://whatismyipaddress.com/ip/' . $ip . '">https://whatismyipaddress.com/ip/' . $ip . '</a>';

// build report
          $report = date("D M j G:i:s Y") . "\n\n";
          $report .= CLICSHOPPING::getDef('report_access_login', ['IP' => $ip]);
          $report .= "\n" . CLICSHOPPING::getDef('report_sender_host_name', ['HOST' => $host]);
          $report .= "\n" . CLICSHOPPING::getDef('report_sender_username', ['USERNAME' => $username]);
          $report .= "\n" . 'City : ' . $city;
          $report .= "\n" . 'Country : ' . $country;
          $report .= "\n" . 'Region : ' . $region;
          $report .= "\n" . 'Referer : ' . $referer;
          $report .= "\n\n" . $google_map;
          $report .= "\n" . $new_info_ip;
          $report .= "\n" . CLICSHOPPING::getConfig('http_server', 'ClicShoppingAdmin');
          $report .= "\n\n" . TemplateEmailAdmin::getTemplateEmailTextFooter();

          $to_addr = STORE_OWNER_EMAIL_ADDRESS;
          $from_name = STORE_NAME;
          $from_addr = STORE_OWNER_EMAIL_ADDRESS;
          $to_name = STORE_NAME;
          $subject = CLICSHOPPING::getDef('report_email_subject');

          $CLICSHOPPING_Mail->addHtml($report);
          $CLICSHOPPING_Mail->send($to_addr, $from_name, $from_addr, $to_name, $subject);
        }
      }
    }
  }
}