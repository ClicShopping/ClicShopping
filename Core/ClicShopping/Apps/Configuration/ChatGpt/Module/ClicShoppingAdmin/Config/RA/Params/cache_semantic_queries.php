<?php

  namespace ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\RA\Params;

  use ClicShopping\OM\HTML;

  class cache_semantic_queries extends \ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
  {
    public $default = 'False';
    public int|null $sort_order = 80;

    protected function init()
    {
      $this->title = $this->app->getDef('cfg_chatgpt_cache_semantic_queries_title');
      $this->description = $this->app->getDef('cfg_chatgpt_cache_semantic_queries_description');
    }

    public function getInputField()
    {
      $value = $this->getInputValue();

      $input = HTML::radioField($this->key, 'True', $value, 'id="' . $this->key . '1" autocomplete="off"') . $this->app->getDef('cfg_chatgpt_cache_semantic_queries_status_true') . ' ';
      $input .= HTML::radioField($this->key, 'False', $value, 'id="' . $this->key . '2" autocomplete="off"') . $this->app->getDef('cfg_chatgpt_cache_semantic_queries_status_false');

      return $input;
    }
  }