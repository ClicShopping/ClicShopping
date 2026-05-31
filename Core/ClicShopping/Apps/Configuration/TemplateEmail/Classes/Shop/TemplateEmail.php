<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\TemplateEmail\Classes\Shop;

use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;

class TemplateEmail
{
  /**
   * Retrieves the template email name based on the provided template email ID and language ID.
   *
   * @param int $template_email_id The ID of the template email.
   * @param int $language_id The ID of the language.
   *
   * @return string The name of the template email corresponding to the provided IDs.
   */
  public static function getTemplateEmailName(int $template_email_id, int $language_id): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $QtemplateEmail = $CLICSHOPPING_Db->prepare('select template_email_name
                                                  from :table_template_email_description
                                                  where template_email_id = :template_email_id
                                                  and language_id = :language_id
                                                 ');
    $QtemplateEmail->bindInt(':template_email_id', (int)$template_email_id);
    $QtemplateEmail->bindInt(':language_id', (int)$language_id);
    $QtemplateEmail->execute();

    $template_email_name = $QtemplateEmail->fetch();

    return $template_email_name['template_email_name'];
  }


  /**
   * Retrieves the short description of an email template based on the specified template email ID and language ID.
   *
   * @param int $template_email_id The ID of the email template.
   * @param int $language_id The ID of the language.
   * @return string The short description of the email template.
   */
  public static function getTemplateEmailShortDescription(int $template_email_id, int $language_id): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $QtemplateEmailShortDescription = $CLICSHOPPING_Db->prepare('select template_email_short_description
                                                                  from :table_template_email_description
                                                                  where template_email_id = :template_email_id
                                                                  and language_id = :language_id
                                                                 ');
    $QtemplateEmailShortDescription->bindInt(':template_email_id', $template_email_id);
    $QtemplateEmailShortDescription->bindInt(':language_id', $language_id);
    $QtemplateEmailShortDescription->execute();

    $template_email_short_description = $QtemplateEmailShortDescription->fetch();

    return $template_email_short_description['template_email_short_description'];
  }

  /**
   * Retrieves the description of a template email based on its ID and associated language ID.
   *
   * @param int $template_email_id The ID of the template email.
   * @param int $language_id The ID of the language associated with the template email.
   * @return string Returns the description of the template email.
   */
  public static function getTemplateEmailDescription(int $template_email_id, int $language_id): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $QtemplateEmailDescription = $CLICSHOPPING_Db->prepare('select template_email_description
                                                              from :table_template_email_description
                                                              where template_email_id = :template_email_id
                                                              and language_id = :language_id
                                                             ');
    $QtemplateEmailDescription->bindInt(':template_email_id', $template_email_id);
    $QtemplateEmailDescription->bindInt(':language_id', $language_id);
    $QtemplateEmailDescription->execute();

    $template_email_description = $QtemplateEmailDescription->fetch();

    return $template_email_description['template_email_description'];
  }

  /**
   * Retrieves the email footer text template from the database, replaces specific placeholders
   * with their corresponding dynamic values, and returns the formatted email footer content.
   *
   * @return string The formatted email footer text after replacing placeholders.
   */
  public static function getTemplateEmailTextFooter(): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    $QtextTemplateEmailFooter = $CLICSHOPPING_Db->prepare('select te.template_email_variable,
                                                              ted.template_email_description
                                                       from :table_template_email te,
                                                            :table_template_email_description  ted
                                                       where te.template_email_variable = :template_email_variable
                                                       and te.template_email_id = ted.template_email_id
                                                       and ted.language_id = :language_id
                                                      ');

    $QtextTemplateEmailFooter->bindValue(':template_email_variable', 'TEMPLATE_EMAIL_TEXT_FOOTER');
    $QtextTemplateEmailFooter->bindInt(':language_id', (int)$CLICSHOPPING_Language->getId());
    $QtextTemplateEmailFooter->execute();

    $template_email_footer = $QtextTemplateEmailFooter->value('template_email_description');

    $keywords = ['/{{store_name}}/',
      '/{{store_owner_email_address}}/',
      '/{{http_shop}}/'
    ];

    $replaces = [
      STORE_NAME,
      STORE_OWNER_EMAIL_ADDRESS,
      HTTP::getShopUrlDomain()
    ];

    $template_email_footer = preg_replace($keywords, $replaces, $template_email_footer);

    return $template_email_footer;
  }


  /**
   * Retrieves the template email signature with placeholders replaced by actual store information.
   *
   * @return string Returns the processed email signature template with store details substituted in place of placeholders.
   */
  public static function getTemplateEmailSignature(): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    $QtextTemplateEmailSignature = $CLICSHOPPING_Db->prepare('select te.template_email_variable,
                                                              ted.template_email_description
                                                       from :table_template_email te,
                                                            :table_template_email_description  ted
                                                       where te.template_email_variable = :template_email_variable
                                                       and te.template_email_id = ted.template_email_id
                                                       and ted.language_id = :language_id
                                                      ');

    $QtextTemplateEmailSignature->bindValue(':template_email_variable', 'TEMPLATE_EMAIL_SIGNATURE');
    $QtextTemplateEmailSignature->bindInt(':language_id', $CLICSHOPPING_Language->getId());
    $QtextTemplateEmailSignature->execute();

    $template_email_signature = $QtextTemplateEmailSignature->value('template_email_description');

    $keywords = ['/{{store_name}}/',
      '/{{store_owner_email_address}}/',
      '/{{http_shop}}/'
    ];

    $replaces = [
      STORE_NAME,
      STORE_OWNER_EMAIL_ADDRESS,
      HTTP::getShopUrlDomain()
    ];

    $template_email_signature = preg_replace($keywords, $replaces, $template_email_signature);

    return $template_email_signature;
  }


  /**
   * Retrieves the template email welcome catalog content with replaced placeholders.
   *
   * This method fetches the email template description for a welcome catalog message
   * from the database, replaces predefined placeholders with their corresponding values,
   * and returns the processed email content.
   *
   * @return string The email template content with placeholders replaced by actual values.
   */
  public static function getTemplateEmailWelcomeCatalog()
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    $QtextTemplateEmailWelcomeCatalog = $CLICSHOPPING_Db->prepare('select te.template_email_variable,
                                                                        ted.template_email_description
                                                                 from :table_template_email te,
                                                                      :table_template_email_description  ted
                                                                 where te.template_email_variable = :template_email_variable
                                                                 and te.template_email_id = ted.template_email_id
                                                                 and ted.language_id = :language_id
                                                                ');

    $QtextTemplateEmailWelcomeCatalog->bindValue(':template_email_variable', 'TEMPLATE_EMAIL_WELCOME');
    $QtextTemplateEmailWelcomeCatalog->bindInt(':language_id', (int)$CLICSHOPPING_Language->getId());
    $QtextTemplateEmailWelcomeCatalog->execute();

    $template_email_welcome_catalog = $QtextTemplateEmailWelcomeCatalog->value('template_email_description');

    $keywords = ['/{{store_name}}/',
      '/{{store_owner_email_address}}/',
      '/{{http_shop}}/'
    ];

    $replaces = [
      STORE_NAME,
      STORE_OWNER_EMAIL_ADDRESS,
      HTTP::getShopUrlDomain()
    ];

    $template_email_welcome_catalog = preg_replace($keywords, $replaces, $template_email_welcome_catalog);

    return $template_email_welcome_catalog;
  }

  /**
   * Retrieves the email template for the coupon catalog, replaces placeholders
   * with actual values such as store name, owner email address, and shop URL,
   * and returns the processed template as a string.
   *
   * @return string The processed email template for the coupon catalog.
   */
  public static function getTemplateEmailCouponCatalog(): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    $QtextTemplateEmailCouponCatalog = $CLICSHOPPING_Db->prepare('select te.template_email_variable,
                                                                          ted.template_email_description
                                                                   from :table_template_email te,
                                                                        :table_template_email_description  ted
                                                                   where te.template_email_variable = :template_email_variable
                                                                   and te.template_email_id = ted.template_email_id
                                                                   and ted.language_id = :language_id
                                                                  ');

    $QtextTemplateEmailCouponCatalog->bindValue(':template_email_variable', 'TEMPLATE_EMAIL_TEXT_COUPON');
    $QtextTemplateEmailCouponCatalog->bindInt(':language_id', (int)$CLICSHOPPING_Language->getId());
    $QtextTemplateEmailCouponCatalog->execute();

    $template_email_coupon_catalog = $QtextTemplateEmailCouponCatalog->value('template_email_description');

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

    $template_email_coupon_catalog = preg_replace($keywords, $replaces, $template_email_coupon_catalog);

    return $template_email_coupon_catalog;
  }

  /**
   * Retrieves and processes the template email introduction command.
   *
   * Selects the email template variable and its associated description from the database
   * based on defined parameters, processes placeholders within the template description,
   * and replaces them with corresponding store data.
   *
   * @return string The processed template email introduction command with placeholders replaced.
   */
  public static function getTemplateEmailIntroCommand(): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    $QtextTemplateEmailIntroCommand = $CLICSHOPPING_Db->prepare('select te.template_email_variable,
                                                                          ted.template_email_description
                                                                   from :table_template_email te,
                                                                        :table_template_email_description  ted
                                                                   where te.template_email_variable = :template_email_variable
                                                                   and te.template_email_id = ted.template_email_id
                                                                   and ted.language_id = :language_id
                                                                  ');

    $QtextTemplateEmailIntroCommand->bindValue(':template_email_variable', 'TEMPLATE_EMAIL_INTRO_COMMAND');
    $QtextTemplateEmailIntroCommand->bindInt(':language_id', (int)$CLICSHOPPING_Language->getId());
    $QtextTemplateEmailIntroCommand->execute();

    $template_email_intro_command = $QtextTemplateEmailIntroCommand->value('template_email_description');

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

    $template_email_intro_command = preg_replace($keywords, $replaces, $template_email_intro_command);

    return $template_email_intro_command;
  }

  /**
   * Extracts and validates email addresses enclosed within angle brackets from a given contact string.
   *
   * @param string $contactString The input string containing email addresses enclosed in angle brackets.
   * @return array An array of valid email addresses extracted from the input string. Returns an empty array if no valid email addresses are found.
   */
  public static function getExtractEmailAddress(string $contactString): array
  {
    if (!preg_match_all('/<(?<emails>[^>]+)>/', $contactString, $matches)) {
      return [];
    }

    return array_filter(array_map(static function (string $email): ?string {
      $sanitizedEmail = filter_var($email, FILTER_SANITIZE_EMAIL);

      return filter_var($sanitizedEmail, FILTER_VALIDATE_EMAIL) ? $sanitizedEmail : null;
    }, $matches['emails']));
  }
}