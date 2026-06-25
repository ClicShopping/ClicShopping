<?php
  /**
   * Copyright (c) 2008–2026 Loic Richard
   *
   * Licensed under AGPLv3 or commercial license.
   * See LICENSE file.
   */

  declare(strict_types=1);

  namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\Shop\Llms;

  use ClicShopping\OM\Registry;
  /**
   * Provides category data for llms.txt generation.
   *
   * This class is responsible for retrieving active categories
   * from the catalog database and converting them into a simple
   * normalized structure consumable by the Llms generator.
   */
  final class CategoryProvider
  {
    /**
     * Maximum number of categories exported.
     *
     * Limiting the result set keeps generated files compact
     * and avoids unnecessary database load.
     */
    private const int LIMIT = 50;

    /**
     * Database connection retrieved from the application registry.
     */
    private readonly object $db;

    public function __construct()
    {
      // Reuse the shared database connection managed by the application.
      $this->db = Registry::get('Db');
    }

    /**
     * Retrieve active categories for a specific language.
     *
     * Only enabled categories are returned. Results are sorted
     * according to the catalog configuration and restricted to
     * a predefined maximum number of entries.
     *
     * @param int $languageId Current language identifier.
     *
     * @return array<int, array{
     *     id:int|string,
     *     name:string,
     *     description:string
     * }>
     */
    public function getCategories(int $languageId): array
    {
      $categories = [];

      $Qcategories = $this->db->prepare('select c.categories_id,
                                              cd.categories_name,
                                              cd.categories_description
                                         from :table_categories c,
                                              :table_categories_description cd
                                        where c.categories_id = cd.categories_id
                                          and cd.language_id = :language_id
                                          and c.status = 1
                                        order by c.sort_order,
                                                 cd.categories_name
                                        limit :limit
                                      ');

      $Qcategories->bindInt(':language_id', $languageId);
      $Qcategories->bindInt(':limit', self::LIMIT);
      $Qcategories->execute();

      while ($Qcategories->fetch()) {
        $categories[] = [
          'id' => $Qcategories->value('categories_id'),

          // Category title displayed to users and AI agents.
          'name' => $Qcategories->value('categories_name'),

          // HTML is removed to provide clean plain-text content
          // suitable for Markdown and llms.txt generation.
          'description' => strip_tags(
            $Qcategories->value('categories_description')
          ),
        ];
      }

      return $categories;
    }
  }