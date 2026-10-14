<?php
/**
 * Agent Objectives Action
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 */
namespace ClicShopping\Apps\Configuration\ChatGpt\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class AgentObjectives extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_ChatGpt = Registry::get('ChatGpt');

    $this->page->setFile('agent_objectives.php');
    $this->page->data['action'] = 'AgentObjectives';

    $CLICSHOPPING_ChatGpt->loadDefinitions('Sites/ClicShoppingAdmin/agent_objectives');
  }
}
