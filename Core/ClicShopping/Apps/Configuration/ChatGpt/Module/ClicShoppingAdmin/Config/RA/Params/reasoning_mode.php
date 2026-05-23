<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\RA\Params;

use ClicShopping\AI\DomainsAI\CoreAI\Embedding\NewVector;
use ClicShopping\OM\HTML;

class reasoning_mode extends \ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = 'chain_of_thought';
  public int|null $sort_order = 120;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_chatgpt_reasoning_mode_title');
    $this->description = $this->app->getDef('cfg_chatgpt_reasoning_mode_description');
  }

  public function getInputField()
  {
    $value = $this->getInputValue();

    $array = self::getReasoningModoe();

    $input = HTML::selectField($this->key, $array, $value, 'id="reasoning_mode_title"');

    return $input;
  }

  /**
   * Reasonning modelisting
   *
   * @return array Tableau des modèles d'embedding disponibles
   */
  private static function getReasoningModoe(): array
  {
    $array = [
      ['id' => 'chain_of_thought', 'text' => 'Chain of thought (COT)'],
      ['id' => 'tree_of_thought', 'text' => 'Tree of thought (TOT)'],
      ['id' => 'self_consistency', 'text' => 'Self consistency (SC)'],
    ];

    return $array;
  }
}
