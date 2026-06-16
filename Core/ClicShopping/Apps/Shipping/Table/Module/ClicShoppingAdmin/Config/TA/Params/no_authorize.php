<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Shipping\Table\Module\ClicShoppingAdmin\Config\TA\Params;

use ClicShopping\OM\HTML;

class no_authorize extends \ClicShopping\Apps\Shipping\Table\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = 'True';
  public int|null $sort_order = 20;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_table_no_authorize_title');
    $this->description = $this->app->getDef('cfg_table_no_authorize_desc');
  }

  public function getInputField()
  {
    $value = $this->getInputValue();

    $input = HTML::radioField($this->key, 'True', $value, 'id="' . $this->key . '1" autocomplete="off"') . $this->app->getDef('cfg_table_no_authorize_true') . ' ';
    $input .= HTML::radioField($this->key, 'False', $value, 'id="' . $this->key . '0" autocomplete="off"') . $this->app->getDef('cfg_table_no_authorize_false') . '<br />';

    return $input;
  }
}
