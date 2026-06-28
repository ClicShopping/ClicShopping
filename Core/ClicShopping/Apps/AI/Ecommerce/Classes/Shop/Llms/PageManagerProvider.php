<?php
  /**
   * Copyright (c) 2008–2026 Loic Richard
   *
   * Licensed under AGPLv3 or commercial license.
   * See LICENSE file.
   */

  declare(strict_types=1);

  namespace ClicShopping\Apps\AI\Ecommerce\Classes\Shop\Llms;

  use ClicShopping\OM\Registry;
  /**
   * Provides category data for llms.txt generation.
   *
   * This class is responsible for retrieving active pages manager
   * from the catalog database and converting them into a simple
   * normalized structure consumable by the Llms generator.
   */
  final class PageManagerProvider
  {
    /**
     * Database connection retrieved from the application registry.
     */
    private readonly object $db;

    public function __construct()
    {
      $this->db = Registry::get('Db');
    }

    /**
     * Retrieve active pages manager for a specific language.
     *
     * Only enabled pages manager are returned.
     *
     * @param int $languageId Current language identifier.
     *
     * @return array<int, array{
     *     id:int|string,
     *     name:string,
     *     description:string
     * }>
     */
    public function getPages(int $languageId): array
    {
      $pages = [];

      $QPages = $this->db->prepare('select p.pages_id,
                                            p.customers_group_id,
                                            pd.pages_title,
                                            pd.pages_html_text as description
                                       from :table_pages_manager p,
                                            :table_pages_manager_description pd
                                      where p.pages_id = pd.pages_id
                                        and pd.language_id = :language_id
                                        and p.status = 1
                                        and (p.pages_id = 4 or p.pages_id = 5)
					                              and (p.customers_group_id = 0 or  p.customers_group_id = 99)
                                      ');

      $QPages->bindInt(':language_id', $languageId);
      $QPages->execute();

      while ($QPages->fetch()) {
        $pages[] = [
          'id' => $QPages->valueInt('pages_id'),
          'name' => $QPages->value('pages_title'),
          'description' => strip_tags($QPages->value('description')),
        ];
      }

      return $pages;
    }
  }