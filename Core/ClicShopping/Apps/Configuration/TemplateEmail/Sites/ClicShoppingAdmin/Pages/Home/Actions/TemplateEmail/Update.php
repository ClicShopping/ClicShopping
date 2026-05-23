<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\TemplateEmail\Sites\ClicShoppingAdmin\Pages\Home\Actions\TemplateEmail;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Update extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('TemplateEmail');
  }

  public function execute()
  {
    $CLICSHOPPING_Hooks = Registry::get('Hooks');
    $CLICSHOPPING_Language = Registry::get('Language');

    $template_email_id = HTML::sanitize($_GET['ID']);

    $languages = $CLICSHOPPING_Language->getLanguages();

    for ($i = 0, $n = \count($languages); $i < $n; $i++) {
      $template_language_id = $languages[$i]['id'];

      $sql_data_array['template_email_name'] = HTML::sanitize($_POST['template_email_name'][$template_language_id]);
      $sql_data_array['template_email_short_description'] = HTML::sanitize($_POST['template_email_short_description'][$template_language_id]);
      $sql_data_array['template_email_description'] = $_POST['template_email_description'][$template_language_id];

      $sql_data_array = array_merge($sql_data_array);

      $this->app->db->save('template_email_description', $sql_data_array, ['template_email_id' => (int)$template_email_id,
          'language_id' => (int)$template_language_id
        ]
      );
    }

    $CLICSHOPPING_Hooks->call('TemplateEmail', 'Update');

    $this->app->redirect('TemplateEmail');
  }
}