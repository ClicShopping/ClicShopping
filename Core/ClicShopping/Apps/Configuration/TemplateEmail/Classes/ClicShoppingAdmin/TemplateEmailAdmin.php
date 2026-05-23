<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\TemplateEmail\Classes\ClicShoppingAdmin;

use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;

class TemplateEmailAdmin
{
  /**
   * Retrieves the name of a template email based on the specified template email ID and language ID.
   *
   * @param int $template_email_id The unique identifier of the template email.
   * @param int $language_id The unique identifier of the language. If not provided, the current language's ID will be used.
   * @return string The name of the template email.
   */
  public static function getTemplateEmailName(int $template_email_id, int $language_id): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    if (!$language_id) $language_id = $CLICSHOPPING_Language->getId();

    $QtemplateEmail = $CLICSHOPPING_Db->prepare('select template_email_name
                                                  from :table_template_email_description
                                                  where template_email_id = :template_email_id
                                                  and language_id = :language_id
                                                 ');
    $QtemplateEmail->bindInt(':template_email_id', (int)$template_email_id);
    $QtemplateEmail->bindInt(':language_id', (int)$language_id);

    $QtemplateEmail->execute();

    return $QtemplateEmail->value('template_email_name');
  }

  /**
   * Retrieves the short description of a template email based on the provided template email ID
   * and language ID.
   *
   * @param int $template_email_id The ID of the template email to retrieve the short description for.
   * @param int $language_id The language ID for which the template email short description is retrieved.
   *                         If not provided, the current language ID is used.
   * @return string The short description of the template email.
   */
  public static function getTemplateEmailShortDescription(int $template_email_id, int $language_id): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    if (!$language_id) $language_id = $CLICSHOPPING_Language->getId();

    $QtemplateEmail = $CLICSHOPPING_Db->prepare('select template_email_short_description
                                                  from :table_template_email_description
                                                  where template_email_id = :template_email_id
                                                  and language_id = :language_id
                                                 ');
    $QtemplateEmail->bindInt(':template_email_id', (int)$template_email_id);
    $QtemplateEmail->bindInt(':language_id', (int)$language_id);

    $QtemplateEmail->execute();

    return $QtemplateEmail->value('template_email_short_description');
  }

  /**
   * Retrieves the description of a template email based on the template email ID and language ID.
   *
   * @param int $template_email_id The ID of the template email to fetch the description for.
   * @param int $language_id The ID of the language for which the email description is being retrieved. Defaults to the currently set language ID if not provided.
   * @return string The description of the requested template email.
   */
  public static function getTemplateEmailDescription(int $template_email_id, int $language_id): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    if (!$language_id) $language_id = $CLICSHOPPING_Language->getId();

    $QtemplateEmail = $CLICSHOPPING_Db->prepare('select template_email_description
                                                  from :table_template_email_description
                                                  where template_email_id = :template_email_id
                                                  and language_id = :language_id
                                                ');
    $QtemplateEmail->bindInt(':template_email_id', (int)$template_email_id);
    $QtemplateEmail->bindInt(':language_id', (int)$language_id);

    $QtemplateEmail->execute();

    return $QtemplateEmail->value('template_email_description');
  }

  /**
   * Retrieves the email footer text template from the database, replaces predefined
   * variable placeholders with their corresponding dynamic values, and returns the final email footer text.
   *
   * The method fetches the email footer content for the currently set language,
   * and replaces placeholders such as {{store_name}}, {{store_owner_email_address}}, and {{http_shop}}
   * with their respective values.
   *
   * @return string The processed email text footer with all placeholders replaced by their actual values.
   */

  public static function getTemplateEmailTextFooter(): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    $QtemplateEmail = $CLICSHOPPING_Db->prepare('select te.template_email_variable,
                                                         ted.template_email_description
                                                  from :table_template_email te,
                                                       :table_template_email_description ted
                                                  where te.template_email_variable = :template_email_variable
                                                  and te.template_email_id = ted.template_email_id
                                                  and ted.language_id = :language_id
                                               ');
    $QtemplateEmail->bindValue(':template_email_variable', 'TEMPLATE_EMAIL_TEXT_FOOTER');
    $QtemplateEmail->bindInt(':language_id', (int)$CLICSHOPPING_Language->getId());


    $QtemplateEmail->execute();

    $template_email_footer = $QtemplateEmail->value('template_email_description');

    $keywords = array('/{{store_name}}/',
      '/{{store_owner_email_address}}/',
      '/{{http_shop}}/'
    );

    $replaces = [
      STORE_NAME,
      STORE_OWNER_EMAIL_ADDRESS,
      HTTP::getShopUrlDomain()
    ];


    $template_email_footer = preg_replace($keywords, $replaces, $template_email_footer);

    return $template_email_footer;
  }

  /**
   * Retrieves the text footer template for email newsletters.
   *
   * This method fetches the template for the email newsletter footer from the database,
   * replaces predefined placeholders with corresponding values (e.g., store name, email address, shop URL),
   * and returns the processed template.
   *
   * @return string Processed email newsletter text footer template with placeholders replaced by actual values.
   */
  public static function getTemplateEmailNewsletterTextFooter(): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    $QtemplateEmail = $CLICSHOPPING_Db->prepare('select te.template_email_variable,
                                                         ted.template_email_description
                                                  from :table_template_email te,
                                                       :table_template_email_description ted
                                                  where te.template_email_variable = :template_email_variable
                                                  and te.template_email_id = ted.template_email_id
                                                  and ted.language_id = :language_id
                                                ');
    $QtemplateEmail->bindValue(':template_email_variable', 'TEMPLATE_EMAIL_NEWSLETTER_TEXT_FOOTER');
    $QtemplateEmail->bindInt(':language_id', $CLICSHOPPING_Language->getId());


    $QtemplateEmail->execute();

    $template_email_newsletter_footer = $QtemplateEmail->value('template_email_description');

    $keywords = array('/{{store_name}}/',
      '/{{store_owner_email_address}}/',
      '/{{http_shop}}/'
    );

    $replaces = array(STORE_NAME,
      STORE_OWNER_EMAIL_ADDRESS,
      HTTP::getShopUrlDomain()
    );

    $template_email_newsletter_footer = preg_replace($keywords, $replaces, $template_email_newsletter_footer);

    return $template_email_newsletter_footer;
  }

  /**
   * Retrieves the email signature template, performs variable replacements with dynamic values, and returns the processed template text.
   *
   * @return string The processed email signature template with dynamic values replaced.
   */
  public static function getTemplateEmailSignature(): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    $QtemplateEmail = $CLICSHOPPING_Db->prepare('select te.template_email_variable,
                                                           ted.template_email_description
                                                    from :table_template_email te,
                                                         :table_template_email_description ted
                                                    where te.template_email_variable = :template_email_variable
                                                    and te.template_email_id = ted.template_email_id
                                                    and ted.language_id = :language_id
                                                  ');
    $QtemplateEmail->bindValue(':template_email_variable', 'TEMPLATE_EMAIL_SIGNATURE');
    $QtemplateEmail->bindInt(':language_id', $CLICSHOPPING_Language->getId());


    $QtemplateEmail->execute();


    $template_email_signature = $QtemplateEmail->value('template_email_description');

    $keywords = array('/{{store_name}}/',
      '/{{store_owner_email_address}}/',
      '/{{http_shop}}/'

    );

    $replaces = [
      STORE_NAME,
      STORE_OWNER_EMAIL_ADDRESS,
      HTTP::getShopUrlDomain()
    ];

    $template_email_signature = preg_replace($keywords, $replaces, $template_email_signature);

    return $template_email_signature;
  }

  /**
   * Retrieves the welcome email template specifically tailored for administrators.
   *
   * The method fetches the template from the database based on the template variable,
   * replaces dynamic placeholders with actual values such as store name, store owner email address,
   * and shop URL domain, then returns the final formatted email template content.
   *
   * @return string The formatted welcome email template for administrators.
   */
  public static function getTemplateEmailWelcomeAdmin(): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    $QtemplateEmail = $CLICSHOPPING_Db->prepare('select te.template_email_variable,
                                                         ted.template_email_description
                                                  from :table_template_email te,
                                                       :table_template_email_description ted
                                                  where te.template_email_variable = :template_email_variable
                                                  and te.template_email_id = ted.template_email_id
                                                  and ted.language_id = :language_id
                                                ');
    $QtemplateEmail->bindValue(':template_email_variable', 'TEMPLATE_EMAIL_WELCOME_ADMIN');
    $QtemplateEmail->bindInt(':language_id', $CLICSHOPPING_Language->getId());


    $QtemplateEmail->execute();

    $template_email_welcome_admin = $QtemplateEmail->value('template_email_description');

    $keywords = [
      '/{{store_name}}/',
      '/{{store_owner_email_address}}/',
      '/{{http_shop}}/'
    ];

    $replaces = [
      STORE_NAME,
      STORE_OWNER_EMAIL_ADDRESS,
      HTTP::getShopUrlDomain()
    ];

    $template_email_welcome_admin = preg_replace($keywords, $replaces, $template_email_welcome_admin);

    return $template_email_welcome_admin;
  }

  /**
   * Retrieves the template email description for the admin coupon email.
   * This method fetches the email template based on the specified variable, replaces defined placeholders with actual values, and returns the resulting template string.
   *
   * @return string Returns the processed email template description for the admin coupon email.
   */
  public static function getTemplateEmailCouponAdmin(): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    $QtemplateEmail = $CLICSHOPPING_Db->prepare('select te.template_email_variable,
                                                         ted.template_email_description
                                                  from :table_template_email te,
                                                       :table_template_email_description ted
                                                  where te.template_email_variable = :template_email_variable
                                                  and te.template_email_id = ted.template_email_id
                                                  and ted.language_id = :language_id
                                                ');
    $QtemplateEmail->bindValue(':template_email_variable', 'TEMPLATE_EMAIL_TEXT_COUPON');
    $QtemplateEmail->bindInt(':language_id', $CLICSHOPPING_Language->getId());


    $QtemplateEmail->execute();

    $template_email_coupon_admin = $QtemplateEmail->value('template_email_description');

    $keywords = [
      '/{{store_name}}/',
      '/{{store_owner_email_address}}/',
      '/{{http_shop}}/'
    ];

    $replaces = [
      STORE_NAME,
      STORE_OWNER_EMAIL_ADDRESS,
      HTTP::getShopUrlDomain()
    ];

    $template_email_coupon_admin = preg_replace($keywords, $replaces, $template_email_coupon_admin);

    return $template_email_coupon_admin;
  }

  /**
   * Retrieves the text for the "intro command" email template, replacing placeholder variables with their corresponding values.
   *
   * This method fetches the template description from the database based on the template variable
   * `TEMPLATE_EMAIL_INTRO_COMMAND` and the current language. It processes the retrieved text by replacing certain
   * placeholders like `{{store_name}}`, `{{store_owner_email_address}}`, and `{{http_shop}}` with dynamic values.
   *
   * @return string The processed email template text for the "intro command" email.
   */

  public static function getTemplateEmailIntroCommand(): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    $QtemplateEmail = $CLICSHOPPING_Db->prepare('select te.template_email_variable,
                                                           ted.template_email_description
                                                    from :table_template_email te,
                                                         :table_template_email_description ted
                                                    where te.template_email_variable = :template_email_variable
                                                    and te.template_email_id = ted.template_email_id
                                                    and ted.language_id = :language_id
                                                  ');
    $QtemplateEmail->bindValue(':template_email_variable', 'TEMPLATE_EMAIL_INTRO_COMMAND');
    $QtemplateEmail->bindInt(':language_id', $CLICSHOPPING_Language->getId());


    $QtemplateEmail->execute();

    $template_email_intro_command = $QtemplateEmail->value('template_email_description');

    $keywords = [
      '/{{store_name}}/',
      '/{{store_owner_email_address}}/',
      '/{{http_shop}}/'
    ];

    $replaces = [STORE_NAME,
      STORE_OWNER_EMAIL_ADDRESS,
      HTTP::getShopUrlDomain()
    ];

    $template_email_intro_command = preg_replace($keywords, $replaces, $template_email_intro_command);

    return $template_email_intro_command;
  }

  /**
   * Retrieves the description of a template email based on a given template email variable.
   *
   * @param string $string The template email variable to search for.
   * @return string The description of the template email corresponding to the provided variable.
   */
  public static function getTemplateEmailDescriptionByTemplateVariable(string $string): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    $QtemplateEmail = $CLICSHOPPING_Db->prepare('select te.template_email_variable,
                                                           ted.template_email_description
                                                    from :table_template_email te,
                                                         :table_template_email_description ted
                                                    where te.template_email_variable = :template_email_variable
                                                    and te.template_email_id = ted.template_email_id
                                                    and ted.language_id = :language_id
                                                  ');
    $QtemplateEmail->bindValue(':template_email_variable', $string);
    $QtemplateEmail->bindInt(':language_id', $CLICSHOPPING_Language->getId());


    $QtemplateEmail->execute();

    return $QtemplateEmail->value('template_email_description');
  }
}