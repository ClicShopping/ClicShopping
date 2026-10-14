<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Configuration\Antispam\Module\ClicShoppingAdmin\Config\AM\Params;

use ClicShopping\OM\HTML;

class tell_a_friend extends \ClicShopping\Apps\Configuration\Antispam\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = 'False';
  public int|null $sort_order = 120;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_antispam_tell_a_friend_title');
    $this->description = $this->app->getDef('cfg_antispam_tell_a_friend_description');
  }

  public function getInputField()
  {
    $value = $this->getInputValue();

    $input = HTML::radioField($this->key, 'True', $value, 'id="' . $this->key . '1" autocomplete="off"') . $this->app->getDef('cfg_antispam_tell_a_friend_true') . ' ';
    $input .= HTML::radioField($this->key, 'False', $value, 'id="' . $this->key . '2" autocomplete="off"') . $this->app->getDef('cfg_antispam_tell_a_friend_false');

    return $input;
  }
}