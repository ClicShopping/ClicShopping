<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\Apps\Configuration\Antispam\Module\ClicShoppingAdmin\Config\AM\Params;

use ClicShopping\OM\HTML;

class reviews_write extends \ClicShopping\Apps\Configuration\Antispam\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = 'False';
  public int|null $sort_order = 40;

  /**
   * Initialize the parameter
   * Sets title, description, and generates default secret if not already set
   */
  protected function init()
  {
    $this->title = $this->app->getDef('cfg_antispam_reviews_write_title');
    $this->description = $this->app->getDef('cfg_antispam_reviews_write_description');
  }

  /**
   * Get the input field for the admin interface
   * Displays the secret in a password field with regenerate option
   * 
   * @return string HTML input field
   */
  public function getInputField()
  {
    $value = $this->getInputValue();

    $input = HTML::radioField($this->key, 'True', $value, 'id="' . $this->key . '1" autocomplete="off"') . $this->app->getDef('cfg_antispam_reviews_write_true') . ' ';
    $input .= HTML::radioField($this->key, 'False', $value, 'id="' . $this->key . '2" autocomplete="off"') . $this->app->getDef('cfg_antispam_reviews_write_false');

    return $input;
  }
}