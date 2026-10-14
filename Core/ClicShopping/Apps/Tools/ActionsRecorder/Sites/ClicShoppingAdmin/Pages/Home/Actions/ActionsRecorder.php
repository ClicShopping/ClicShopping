<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\ActionsRecorder\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class ActionsRecorder extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_ActionsRecorder = Registry::get('ActionsRecorder');

    $this->page->setFile('actions_recorder.php');
    $this->page->data['action'] = 'ActionsRecorder';

    $CLICSHOPPING_ActionsRecorder->loadDefinitions('Sites/ClicShoppingAdmin/ActionsRecorder');
  }
}