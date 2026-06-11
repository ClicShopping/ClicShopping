<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\RA\Params;

use ClicShopping\AI\DomainsAI\Shared\Embedding\NewVector;
use ClicShopping\OM\HTML;

class embedding_model extends \ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = 'GPT‑4.1-mini';
  public int|null $sort_order = 40;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_chatgpt_embedding_model_title');
    $this->description = $this->app->getDef('cfg_chatgpt_embedding_model_description');
  }

  public function getInputField()
  {
    $value = $this->getInputValue();

    $array = NewVector::getEmbeddingModel();

    $input = HTML::selectField($this->key, $array, $value, 'id="embedding_model_title"');

    return $input;
  }
}
