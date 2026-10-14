<?php
/**
 * Class SE
 *
 * Represents the SEO module configuration for the ClicShoppingAdmin in the ClicShopping e-commerce system.
 * Responsible for managing the activation, deactivation, and metadata of the SEO app module.
 */

namespace ClicShopping\Apps\Marketing\SEO\Module\ClicShoppingAdmin\Config\SE;

class SE extends \ClicShopping\Apps\Marketing\SEO\Module\ClicShoppingAdmin\Config\ConfigAbstract
{

  protected $pm_code = 'seo';

  public bool $is_uninstallable = true;
  public int|null $sort_order = 400;

  protected function init()
  {
    $this->title = $this->app->getDef('module_se_title');
    $this->short_title = $this->app->getDef('module_se_short_title');
    $this->introduction = $this->app->getDef('module_se_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_SEO_SE_STATUS') && (trim(CLICSHOPPING_APP_SEO_SE_STATUS) != '');
  }

  public function install()
  {
    parent::install();

    if (\defined('MODULE_MODULES_SEO_INSTALLED')) {
      $installed = explode(';', MODULE_MODULES_SEO_INSTALLED);
    }

    $installed[] = $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code;

    $this->app->saveCfgParam('MODULE_MODULES_SEO_INSTALLED', implode(';', $installed));
  }

  public function uninstall()
  {
    parent::uninstall();

    $installed = explode(';', MODULE_MODULES_SEO_INSTALLED);
    $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed, true);

    if ($installed_pos !== false) {
      unset($installed[$installed_pos]);

      $this->app->saveCfgParam('MODULE_MODULES_SEO_INSTALLED', implode(';', $installed));
    }
  }
}