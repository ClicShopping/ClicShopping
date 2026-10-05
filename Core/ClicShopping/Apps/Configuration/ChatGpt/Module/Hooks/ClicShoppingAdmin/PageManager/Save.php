<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Module\Hooks\ClicShoppingAdmin\PageManager;


use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;
use ClicShopping\OM\HTML;

use ClicShopping\Apps\Configuration\ChatGpt\ChatGpt as ChatGptApp;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\PageManagerEmbedder;


class Save implements HooksInterface
{
  public mixed $app;
  
  /**
   * Class constructor.
   *
   * Initializes the ChatGptApp instance in the Registry if it doesn't already exist,
   * and loads the necessary definitions for the application.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('ChatGpt')) {
      Registry::set('ChatGpt', new ChatGptApp());
    }

    $this->app = Registry::get('ChatGpt');
  }

  /**
   * Executes the necessary processes based on the provided GET and POST parameters related to category handling.
   *
   * Checks if GPT functionality is enabled and processes category-related inputs to update database records
   * such as descriptions, SEO data (title, description, keywords),
   *
   * @return bool false when GPT or embedding is disabled, true otherwise
   */
  public function execute()
  {
    if (Gpt::checkGptStatus() === false || !defined('CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING') || CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING == 'False' || !defined('CLICSHOPPING_APP_CHATGPT_RA_STATUS') || CLICSHOPPING_APP_CHATGPT_RA_STATUS == 'False') {
        error_log("PageManager: GPT or Embedding disabled, skipping");
      return false;
    }

    if (isset($_GET['Save'], $_GET['PageManager'])) {
      if (isset($_POST['pages_id'])) {
        $pages_id = HTML::sanitize($_POST['pages_id']);
      } else {
        $QpageManager = $this->app->db->prepare('select pages_id
                                                 from :table_pages_manager
                                                 order by pages_id DESC
                                                 limit 1
                                               ');
        $QpageManager->execute();
        $pages_id = $QpageManager->valueInt('pages_id');
      }

      $Qcheck = $this->app->db->prepare('select id
                                        from :table_pages_manager_embedding
                                        where entity_id = :entity_id
                                        ');
      $Qcheck->bindInt(':entity_id', $pages_id);
      $Qcheck->execute();

      $insert_embedding = false;

      if ($Qcheck->fetch() === false) {
        $insert_embedding = true;
      }

      $QpageManager = $this->app->db->prepare('select pm.pages_id,
                                                      pm.page_type,       
                                                      pmd.pages_title,
                                                      pmd.pages_html_text,
                                                      pmd.page_manager_head_title_tag,
                                                      pmd.page_manager_head_desc_tag,
                                                      pmd.page_manager_head_keywords_tag,
                                                      pmd.language_id
                                               from  :table_pages_manager pm,
                                                     :table_pages_manager_description pmd
                                               where pm.pages_id = :pages_id
                                               and pm.pages_id = pmd.pages_id 
                                               and page_type = 4
                                              ');
      $QpageManager->bindInt(':pages_id', $pages_id);
      $QpageManager->execute();

      $page_manager_array = $QpageManager->fetchAll();

      $embedder = new PageManagerEmbedder();

      foreach ($page_manager_array as $item) {
        $embedder->embed($item, !$insert_embedding);
      }
    }

    return true;
  }
}
