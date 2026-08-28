<?php

  /**
   * Copyright (c) 2008–2026 Loic Richard
   *
   * Licensed under AGPLv3 or commercial license.
   * See LICENSE file.
   */

  namespace ClicShopping\Service\ClicShoppingAdmin;

  use ClicShopping\OM\CLICSHOPPING;
  use ClicShopping\OM\Interfaces\ServiceInterface;
  use ClicShopping\OM\Registry;
  use ClicShopping\Apps\Configuration\CompliancePolicyRules\Classes\Shared\CompliancePolicyRules as CompliancePolicyRulesClass;

  /**
   * Service class responsible for managing the currency system in the shop.
   * This service initializes and configures the currency settings based on various factors
   * such as session data, URL parameters, and application defaults.
   */
  class CompliancePolicyRules implements ServiceInterface
  {
    /**
     * Initializes the application currency settings by verifying the existence of required files,
     * setting up the compliance registry, and managing the session currency based on user input or default settings.
     *
     * @return bool Returns true if the initialization process completes successfully; otherwise, false.
     */
    public static function start(): bool
    {
      if (is_file(CLICSHOPPING::BASE_DIR . 'Apps/Configuration/CompliancePolicyRules/Classes/Shared/CompliancePolicyRules.php')) {
        if (!Registry::exists('CompliancePolicyRules')) {
         Registry::set('CompliancePolicyRules', new CompliancePolicyRulesClass());
        }
        return true;
      } else {
        return false;
      }
    }

    /**
     * Stops the execution or operation of a process.
     *
     * @return bool Returns true indicating the stop operation has been executed successfully.
     */
    public static function stop(): bool
    {
      return true;
    }
  }
