<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Communication\PageManager\Classes\ClicShoppingAdmin;

use ClicShopping\OM\Registry;
/**
 * Class providing administrative methods related to page management.
 */
class PageManagerAdmin
{

  protected int $pages_id;
  protected int $language_id;

  /**
   * Retrieves the title of a page from the pages manager description table based on the provided page ID and language ID.
   *
   * @param int $pages_id The ID of the page whose title is to be retrieved.
   * @param int|null $language_id The ID of the language. If 0 or null, the default language ID will be used.
   * @return string The title of the specified page.
   */
  public static function getPageManagerTitle(int $pages_id,  int|null $language_id): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    if ($language_id == 0) $language_id = $CLICSHOPPING_Language->getId();

    $QpageManager = $CLICSHOPPING_Db->prepare('select pages_title
                                                  from :table_pages_manager_description
                                                  where pages_id = :pages_id
                                                  and language_id = :language_id
                                                 ');
    $QpageManager->bindInt(':pages_id', (int)$pages_id);
    $QpageManager->bindInt(':language_id', (int)$language_id);

    $QpageManager->execute();

    return $QpageManager->value('pages_title');
  }


  /**
   * Retrieves the head title tag of a page from the pages manager description table based on the page ID and language ID.
   *
   * @param int $pages_id The ID of the page.
   * @param int|null $language_id The ID of the language. If null or 0, the default language ID will be used.
   * @return string The head title tag of the specified page.
   */
  public static function getPageManagerHeadTitleTag(int $pages_id,  int|null $language_id): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    if ($language_id == 0) $language_id = $CLICSHOPPING_Language->getId();

    $QpageManager = $CLICSHOPPING_Db->prepare('select page_manager_head_title_tag
                                                  from :table_pages_manager_description
                                                  where pages_id = :pages_id
                                                  and language_id = :language_id
                                                 ');
    $QpageManager->bindInt(':pages_id', (int)$pages_id);
    $QpageManager->bindInt(':language_id', (int)$language_id);

    $QpageManager->execute();

    return $QpageManager->value('page_manager_head_title_tag');
  }

  /**
   * Retrieves the head description tag for a page based on the given page ID and language ID.
   *
   * @param int $pages_id The ID of the page for which the head description tag is retrieved.
   * @param int|null $language_id The ID of the language. If null or 0, the default language ID is used.
   * @return string The head description tag associated with the given page and language.
   */
  public static function getPageManagerHeadDescTag(int $pages_id,  int|null $language_id): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    if ($language_id == 0) $language_id = $CLICSHOPPING_Language->getId();

    $QpageManager = $CLICSHOPPING_Db->prepare('select page_manager_head_desc_tag
                                                from :table_pages_manager_description
                                                where pages_id = :pages_id
                                                and language_id = :language_id
                                               ');
    $QpageManager->bindInt(':pages_id', (int)$pages_id);
    $QpageManager->bindInt(':language_id', (int)$language_id);

    $QpageManager->execute();

    return $QpageManager->value('page_manager_head_desc_tag');
  }

  /**
   * Retrieves the keyword meta tag for a specific page and language from the page manager.
   *
   * @param int $pages_id The ID of the page for which to retrieve the keyword meta tag.
   * @param int|null $language_id The ID of the language. If set to null or 0, the default language ID will be used.
   * @return string The keyword meta tag associated with the specified page and language.
   */
  public static function getPageManagerHeadKeywordsTag(int $pages_id,  int|null $language_id): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    if ($language_id == 0) $language_id = $CLICSHOPPING_Language->getId();

    $QpageManager = $CLICSHOPPING_Db->prepare('select page_manager_head_keywords_tag
                                                from :table_pages_manager_description
                                                where pages_id = :pages_id
                                                and language_id = :language_id
                                               ');
    $QpageManager->bindInt(':pages_id', (int)$pages_id);
    $QpageManager->bindInt(':language_id', (int)$language_id);

    $QpageManager->execute();

    return $QpageManager->value('page_manager_head_keywords_tag');
  }
}