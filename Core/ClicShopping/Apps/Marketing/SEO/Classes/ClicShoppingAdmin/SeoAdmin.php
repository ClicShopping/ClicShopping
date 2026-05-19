<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\Apps\Marketing\SEO\Classes\ClicShoppingAdmin;

use ClicShopping\OM\Registry;

class SeoAdmin
{
  private const SEO_TITLE_MAX_LENGTH = 70;
  private const SEO_DESCRIPTION_MAX_LENGTH = 160;
  private const SEO_KEYWORDS_MAX_COUNT = 12;

  protected int $manufacturers_id;
  protected int $language_id;
  protected int $product_id;
  protected int $category_id;

  /**
   * Normalize the SEO Title
   * @param string $value
   * @return string
   */
  public static function normalizeSeoTitle(string $value): string
  {
    $title = trim(strip_tags($value));

    return mb_substr($title, 0, self::SEO_TITLE_MAX_LENGTH);
  }


  /**
   * Normalize the SEO Description
   * @param string $value
   * @return string
   */
  public static function normalizeSeoDescription(string $value): string
  {
    $description = preg_replace('/\s+/u', ' ', trim(strip_tags($value)));

    return mb_substr((string)$description, 0, self::SEO_DESCRIPTION_MAX_LENGTH);
  }

  /**
   * Normalize the SEO Keywords
   * @param string $value
   * @return string
   */
  public static function normalizeSeoKeywords(string $value): string
  {
    $cleanValue = str_replace([';', '|'], ',', strip_tags($value));
    $keywords = array_filter(array_map(static fn($keyword) => trim($keyword), explode(',', $cleanValue)));
    $keywords = array_values(array_unique($keywords));
    $keywords = array_slice($keywords, 0, self::SEO_KEYWORDS_MAX_COUNT);

    return implode(', ', $keywords);
  }

  /**
   *
   * Retrieves the SEO default language title H1 from the database based on the provided SEO ID and language ID.
   *
   * @param int $seo_id The ID of the SEO entry.
   * @param int $language_id The ID of the language.
   * @return string The SEO default language title H1.
   */
  public static function getSeoDefaultLanguageTitleH1(int $seo_id, int $language_id): string
  {
    $Qseo = Registry::get('Db')->get('seo', 'seo_defaut_language_title_h1', ['seo_id' => $seo_id, 'language_id' => $language_id]);

    return $Qseo->value('seo_defaut_language_title_h1');
  }

  /**
   * Retrieves the default language title for SEO based on the provided SEO ID and language ID.
   *
   * @param int $seo_id The ID of the SEO entry to retrieve the default language title for.
   * @param int $language_id The ID of the language to retrieve the title in.
   *
   * @return string The default language title for the specified SEO entry.
   */
  public static function getSeoDefaultLanguageTitle(int $seo_id, int $language_id): string
  {
    $Qseo = Registry::get('Db')->get('seo', 'seo_defaut_language_title', ['seo_id' => $seo_id, 'language_id' => $language_id]);

    return $Qseo->value('seo_defaut_language_title');
  }

  /**
   * Retrieves the default language description for a given SEO entry based on the provided SEO ID and language ID.
   *
   * @param int $seo_id The ID of the SEO entry for which the default language description is to be retrieved.
   * @param int $language_id The ID of the language for which the description is requested.
   * @return string The default language description for the specified SEO entry and language.
   */
  public static function getSeoDefaultLanguageDescription(int $seo_id, int $language_id): string
  {
    $Qseo = Registry::get('Db')->get('seo', 'seo_defaut_language_description', ['seo_id' => $seo_id, 'language_id' => $language_id]);

    return $Qseo->value('seo_defaut_language_description');
  }

  /**
   * Retrieves the default language keywords for SEO configuration.
   *
   * @param int $seo_id The unique identifier for the SEO entity.
   * @param int $language_id The unique identifier for the language.
   * @return string The SEO default language keywords.
   */
  public static function getSeoDefaultLanguageKeywords(int $seo_id, int $language_id): string
  {
    $Qseo = Registry::get('Db')->get('seo', 'seo_defaut_language_keywords', ['seo_id' => $seo_id, 'language_id' => $language_id]);

    return $Qseo->value('seo_defaut_language_keywords');
  }

  /**
   * Retrieves the default language footer for SEO configuration based on the provided SEO ID and language ID.
   *
   * @param int $seo_id The identifier of the SEO entity.
   * @param int $language_id The identifier of the language.
   *
   * @return string The default language footer associated with the provided SEO ID and language ID.
   */
  public static function getSeoDefaultLanguageFooter(int $seo_id, int $language_id): string
  {
    $Qseo = Registry::get('Db')->get('seo', 'seo_defaut_language_footer', ['seo_id' => $seo_id, 'language_id' => $language_id]);

    return $Qseo->value('seo_defaut_language_footer');
  }

  /**
   * Retrieves the SEO title for a specific product information in a given language.
   *
   * @param int $seo_id The SEO identifier for the requested product information.
   * @param int $language_id The language identifier for which the SEO title is requested.
   * @return string The SEO title associated with the given identifiers.
   */
  public static function getSeoProductsInfoTitle(int $seo_id, int $language_id): string
  {
    $Qseo = Registry::get('Db')->get('seo', 'seo_language_products_info_title', ['seo_id' => $seo_id, 'language_id' => $language_id]);

    return $Qseo->value('seo_language_products_info_title');
  }

  /**
   * Retrieves the SEO product information description based on the given SEO ID and language ID.
   *
   * @param int $seo_id The ID of the SEO entry.
   * @param int $language_id The ID of the language.
   * @return string The description of the SEO product information.
   */
  public static function getSeoProductsInfoDescription(int $seo_id, int $language_id): string
  {
    $Qseo = Registry::get('Db')->get('seo', 'seo_language_products_info_description', ['seo_id' => $seo_id, 'language_id' => $language_id]);

    return $Qseo->value('seo_language_products_info_description');
  }

  /**
   * Retrieves the SEO keywords information for a product based on the provided SEO ID and language ID.
   *
   * @param int $seo_id The unique identifier for the SEO entry.
   * @param int $language_id The unique identifier for the language.
   *
   * @return string Returns the retrieved SEO keywords as a string.
   */
  public static function getSeoProductsInfoKeywords(int $seo_id, int $language_id): string
  {
    $Qseo = Registry::get('Db')->get('seo', 'seo_language_products_info_keywords', ['seo_id' => $seo_id, 'language_id' => $language_id]);

    return $Qseo->value('seo_language_products_info_keywords');
  }

  /**
   * Retrieves the SEO title for new products based on the given SEO ID and language ID.
   *
   * @param int $seo_id The unique identifier for the SEO entry.
   * @param int $language_id The identifier for the specific language.
   * @return string The SEO title for new products in the specified language.
   */
  public static function getSeoProductsNewTitle(int $seo_id, int $language_id): string
  {
    $Qseo = Registry::get('Db')->get('seo', 'seo_language_products_new_title', ['seo_id' => $seo_id, 'language_id' => $language_id]);

    return $Qseo->value('seo_language_products_new_title');
  }

  /**
   * Retrieves the new product description for SEO purposes based on the provided SEO ID and language ID.
   *
   * @param int $seo_id The identifier for the SEO entry.
   * @param int $language_id The identifier for the language.
   * @return string The new product description for the specified SEO and language IDs.
   */
  public static function getSeoProductsNewDescription(int $seo_id, int $language_id): string
  {
    $Qseo = Registry::get('Db')->get('seo', 'seo_language_products_new_description', ['seo_id' => $seo_id, 'language_id' => $language_id]);

    return $Qseo->value('seo_language_products_new_description');
  }

  /**
   * Retrieves the SEO keywords for new products based on the provided SEO ID and language ID.
   *
   * @param int $seo_id The ID of the SEO entry.
   * @param int $language_id The ID of the language.
   * @return string The SEO keywords for new products.
   */
  public static function getSeoProductsNewKeywords(int $seo_id, int $language_id): string
  {
    $Qseo = Registry::get('Db')->get('seo', 'seo_language_products_new_keywords', ['seo_id' => $seo_id, 'language_id' => $language_id]);

    return $Qseo->value('seo_language_products_new_keywords');
  }

  /**
   * Retrieves the SEO title for special products based on the provided SEO ID and language ID.
   *
   * @param int $seo_id The unique identifier for the SEO entry.
   * @param int $language_id The identifier for the language to retrieve the title in.
   *
   * @return string The SEO title for special products.
   */
  public static function getSeoProductsSpecialsTitle(int $seo_id, int $language_id): string
  {
    $Qseo = Registry::get('Db')->get('seo', 'seo_language_special_title', ['seo_id' => $seo_id, 'language_id' => $language_id]);

    return $Qseo->value('seo_language_special_title');
  }

  /**
   * Retrieves the SEO description for special products based on the given SEO ID and language ID.
   *
   * @param int $seo_id The unique identifier for the SEO entry.
   * @param int $language_id The ID of the language for the SEO description.
   * @return string The SEO description for the special products.
   */
  public static function getSeoProductsSpecialsDescription(int $seo_id, int $language_id): string
  {
    $Qseo = Registry::get('Db')->get('seo', 'seo_language_special_description', ['seo_id' => $seo_id, 'language_id' => $language_id]);

    return $Qseo->value('seo_language_special_description');
  }

  /**
   * Retrieves the special keywords for SEO based on the specified SEO ID and language ID.
   *
   * @param int $seo_id The identifier for the SEO entry.
   * @param int $language_id The identifier for the language.
   * @return string Returns the SEO special keywords for the specified parameters.
   */
  public static function getSeoProductsSpecialsKeywords(int $seo_id, int $language_id): string
  {
    $Qseo = Registry::get('Db')->get('seo', 'seo_language_special_keywords', ['seo_id' => $seo_id, 'language_id' => $language_id]);

    return $Qseo->value('seo_language_special_keywords');
  }

  /**
   * Retrieves the SEO title for product reviews based on the provided SEO ID and language ID.
   *
   * @param int $seo_id The identifier for the SEO entry.
   * @param int $language_id The identifier for the language.
   * @return string The SEO title for product reviews.
   */
  public static function getSeoProductsReviewsTitle(int $seo_id, int $language_id): string
  {
    $Qseo = Registry::get('Db')->get('seo', 'seo_language_reviews_title', ['seo_id' => $seo_id, 'language_id' => $language_id]);

    return $Qseo->value('seo_language_reviews_title');
  }

  /**
   * Retrieves the SEO description for product reviews based on the given SEO ID and language ID.
   *
   * @param int $seo_id The SEO identifier.
   * @param int $language_id The language identifier.
   * @return string The SEO description for product reviews.
   */
  public static function getSeoProductsReviewsDescription(int $seo_id, int $language_id): string
  {
    $Qseo = Registry::get('Db')->get('seo', 'seo_language_reviews_description', ['seo_id' => $seo_id, 'language_id' => $language_id]);

    return $Qseo->value('seo_language_reviews_description');
  }

  /**
   * Retrieves SEO reviews keywords for products based on the provided SEO ID and language ID.
   *
   * @param int $seo_id The ID of the SEO entry.
   * @param int $language_id The ID of the language.
   * @return string The keywords for product reviews in the specified language.
   */
  public static function getSeoProductsReviewsKeywords(int $seo_id, int $language_id): string
  {
    $Qseo = Registry::get('Db')->get('seo', 'seo_language_reviews_keywords', ['seo_id' => $seo_id, 'language_id' => $language_id]);

    return $Qseo->value('seo_language_reviews_keywords');
  }

  /**
   * Retrieves the SEO title for favorites based on the given SEO ID and language ID.
   *
   * @param int $seo_id The identifier for the SEO entry.
   * @param int $language_id The identifier for the language.
   * @return string The SEO title for favorites.
   */
  public static function getSeoFavoritesTitle(int $seo_id, int $language_id): string
  {
    $Qseo = Registry::get('Db')->get('seo', 'seo_language_favorites_title', ['seo_id' => $seo_id, 'language_id' => $language_id]);

    return $Qseo->value('seo_language_favorites_title');
  }

  /**
   * Retrieves the SEO description for a favorite item based on the provided SEO ID and language ID.
   *
   * @param int $seo_id The unique identifier for the SEO entry.
   * @param int $language_id The unique identifier for the language.
   *
   * @return string Returns the retrieved SEO description as a string.
   */
  public static function getSeoFavoritesDescription(int $seo_id, int $language_id): string
  {
    $Qseo = Registry::get('Db')->get('seo', 'seo_language_favorites_description', ['seo_id' => $seo_id, 'language_id' => $language_id]);

    return $Qseo->value('seo_language_favorites_description');
  }

  /**
   * Retrieves the SEO keywords information for favorites based on the provided SEO ID and language ID.
   *
   * @param int $seo_id The unique identifier for the SEO entry.
   * @param int $language_id The unique identifier for the language.
   *
   * @return string Returns the retrieved SEO keywords for favorites as a string.
   */
  public static function getSeoFavoritesKeywords(int $seo_id, int $language_id): string
  {
    $Qseo = Registry::get('Db')->get('seo', 'seo_language_favorites_keywords', ['seo_id' => $seo_id, 'language_id' => $language_id]);

    return $Qseo->value('seo_language_favorites_keywords');
  }

  /**
   * Retrieves the SEO featured title based on the provided SEO ID and language ID.
   *
   * @param int $seo_id The unique identifier for the SEO entry.
   * @param int $language_id The unique identifier for the language.
   *
   * @return string Returns the retrieved SEO featured title as a string.
   */
  public static function getSeoFeaturedTitle(int $seo_id, int $language_id): string
  {
    $Qseo = Registry::get('Db')->get('seo', 'seo_language_featured_title', ['seo_id' => $seo_id, 'language_id' => $language_id]);

    return $Qseo->value('seo_language_featured_title');
  }

  /**
   * Retrieves the SEO featured description based on the provided SEO ID and language ID.
   *
   * @param int $seo_id The unique identifier for the SEO entry.
   * @param int $language_id The unique identifier for the language.
   *
   * @return string Returns the retrieved SEO featured description as a string.
   */
  public static function getSeoFeaturedDescription(int $seo_id, int $language_id): string
  {
    $Qseo = Registry::get('Db')->get('seo', 'seo_language_featured_description', ['seo_id' => $seo_id, 'language_id' => $language_id]);

    return $Qseo->value('seo_language_featured_description');
  }

  /**
   * Retrieves the SEO featured keywords based on the provided SEO ID and language ID.
   *
   * @param int $seo_id The unique identifier for the SEO entry.
   * @param int $language_id The unique identifier for the language.
   *
   * @return string Returns the retrieved SEO featured keywords as a string.
   */
  public static function getSeoFeaturedKeywords(int $seo_id, int $language_id): string
  {
    $Qseo = Registry::get('Db')->get('seo', 'seo_language_featured_keywords', ['seo_id' => $seo_id, 'language_id' => $language_id]);

    return $Qseo->value('seo_language_featured_keywords');
  }

  /**
   * Retrieves the SEO description for a manufacturer based on the provided manufacturer ID and language ID.
   *
   * @param int|null $manufacturers_id The unique identifier for the manufacturer. Can be null if not specified.
   * @param int $language_id The unique identifier for the language.
   *
   * @return string Returns the retrieved SEO description as a string.
   */
  public static function getManufacturerSeoDescription( int|null $manufacturers_id, int $language_id): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qmanufacturers = $CLICSHOPPING_Db->prepare('select manufacturer_seo_description
                                                  from :table_manufacturers_info
                                                  where manufacturers_id = :manufacturers_id
                                                  and languages_id = :language_id
                                                ');

    $Qmanufacturers->bindInt(':manufacturers_id', $manufacturers_id);
    $Qmanufacturers->bindInt(':language_id', $language_id);
    $Qmanufacturers->execute();


    return $Qmanufacturers->value('manufacturer_seo_description');
  }

  /**
   * Retrieves the SEO title for a manufacturer based on the provided manufacturer ID and language ID.
   *
   * @param int|null $manufacturers_id The unique identifier for the manufacturer. Can be null.
   * @param int $language_id The unique identifier for the language.
   *
   * @return string Returns the retrieved manufacturer SEO title as a string.
   */
  public static function getManufacturerSeoTitle( int|null $manufacturers_id, int $language_id)
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qmanufacturers = $CLICSHOPPING_Db->prepare('select manufacturer_seo_title
                                                    from :table_manufacturers_info
                                                    where manufacturers_id = :manufacturers_id
                                                    and languages_id = :language_id
                                                  ');

    $Qmanufacturers->bindInt(':manufacturers_id', $manufacturers_id);
    $Qmanufacturers->bindInt(':language_id', $language_id);
    $Qmanufacturers->execute();

    return $Qmanufacturers->value('manufacturer_seo_title');
  }

  /**
   * Retrieves the SEO keyword associated with a manufacturer based on the provided manufacturer ID and language ID.
   *
   * @param int|null $manufacturers_id The unique identifier for the manufacturer, or null if no specific manufacturer is provided.
   * @param int $language_id The unique identifier for the language.
   *
   * @return string Returns the retrieved SEO keyword as a string.
   */
  public static function getManufacturerSeoKeyword( int|null $manufacturers_id, int $language_id): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qmanufacturers = $CLICSHOPPING_Db->prepare('select manufacturer_seo_keyword
                                                    from :table_manufacturers_info
                                                    where manufacturers_id = :manufacturers_id
                                                    and languages_id = :language_id
                                                  ');

    $Qmanufacturers->bindInt(':manufacturers_id', $manufacturers_id);
    $Qmanufacturers->bindInt(':language_id', $language_id);
    $Qmanufacturers->execute();

    return $Qmanufacturers->value('manufacturer_seo_keyword');
  }

  /**
   * Retrieves the SEO title for a product based on the provided product ID and language ID.
   *
   * @param string $product_id The unique identifier for the product.
   * @param int $language_id The unique identifier for the language.
   *
   * @return string Returns the retrieved SEO title as a string.
   */
  public static function getProductsSeoTitle(string $product_id, int $language_id): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qproduct = $CLICSHOPPING_Db->prepare('select products_head_title_tag
                                             from :table_products_description
                                             where products_id = :products_id
                                             and language_id = :language_id
                                            ');
    $Qproduct->bindInt(':products_id', $product_id);
    $Qproduct->bindInt(':language_id', $language_id);

    $Qproduct->execute();

    return $Qproduct->value('products_head_title_tag');
  }

  /**
   * Retrieves the SEO description for a product based on the provided product ID and language ID.
   *
   * @param string $product_id The unique identifier for the product.
   * @param int $language_id The unique identifier for the language.
   *
   * @return string Returns the retrieved SEO description as a string.
   */
  public static function getProductsSeoDescription(string $product_id, int $language_id): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qproduct = $CLICSHOPPING_Db->prepare('select products_head_desc_tag
                                             from :table_products_description
                                             where products_id = :products_id
                                             and language_id = :language_id
                                           ');
    $Qproduct->bindInt(':products_id', $product_id);
    $Qproduct->bindInt(':language_id', $language_id);

    $Qproduct->execute();

    return $Qproduct->value('products_head_desc_tag');
  }

  /**
   * Retrieves the SEO keywords for a product based on the provided product ID and language ID.
   *
   * @param string $product_id The unique identifier for the product.
   * @param int $language_id The unique identifier for the language.
   *
   * @return string Returns the product's SEO keywords as a string.
   */
  public static function getProductsSeoKeywords(string $product_id, int $language_id): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qproduct = $CLICSHOPPING_Db->prepare('select products_head_keywords_tag
                                             from :table_products_description
                                             where products_id = :products_id
                                             and language_id = :language_id
                                           ');
    $Qproduct->bindInt(':products_id', $product_id);
    $Qproduct->bindInt(':language_id', $language_id);

    $Qproduct->execute();

    return $Qproduct->value('products_head_keywords_tag');
  }

  /**
   * Retrieves the SEO tag for a product based on the provided product ID and language ID.
   *
   * @param string $product_id The unique identifier for the product.
   * @param int $language_id The unique identifier for the language.
   *
   * @return string Returns the retrieved SEO tag as a string.
   */
  public static function getProductsSeoTag(string $product_id, int $language_id): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qproduct = $CLICSHOPPING_Db->prepare('select products_head_tag
                                             from :table_products_description
                                             where products_id = :products_id
                                             and language_id = :language_id
                                           ');
    $Qproduct->bindInt(':products_id', $product_id);
    $Qproduct->bindInt(':language_id', $language_id);

    $Qproduct->execute();

    return $Qproduct->value('products_head_tag');
  }

  /**
   * Retrieves the SEO URL for a product based on the provided product ID and language ID.
   *
   * @param string $products_id The unique identifier for the product.
   * @param int $language_id The unique identifier for the language. If not provided, the default language ID is used.
   *
   * @return string Returns the retrieved SEO URL as a string.
   */
  public static function getProductsSeoUrl(string $products_id, int $language_id): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    if (!$language_id) $language_id = $CLICSHOPPING_Language->getId();

    $Qseo = $CLICSHOPPING_Db->prepare('select products_seo_url
                                        from :table_products_description
                                        where products_id = :products_id
                                        and language_id = :language_id
                                      ');
    $Qseo->bindInt(':products_id', $products_id);
    $Qseo->bindInt(':language_id', $language_id);

    $Qseo->execute();

    return $Qseo->value('products_seo_url');
  }

  /**
   * Retrieves the SEO URL for a category based on the provided category ID and language ID.
   *
   * @param int|null $category_id The unique identifier for the category, or null if not provided.
   * @param int $language_id The unique identifier for the language.
   *
   * @return string Returns the retrieved SEO URL for the category as a string.
   */
  public static function getCategoriesSeoUrl( int|null $category_id, int $language_id): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    if (!$language_id) $language_id = $CLICSHOPPING_Language->getId();

    $Qseo = $CLICSHOPPING_Db->prepare('select categories_seo_url
                                        from :table_categories_description
                                        where categories_id = :categories_id
                                        and language_id = :language_id
                                      ');
    $Qseo->bindInt(':categories_id', $category_id);
    $Qseo->bindInt(':language_id', $language_id);

    $Qseo->execute();

    return $Qseo->value('categories_seo_url');
  }

  /**
   * Retrieves the SEO title tag for a category based on the provided category ID and language ID.
   *
   * @param int|null $category_id The unique identifier for the category. It can be null if not specified.
   * @param int $language_id The unique identifier for the language. If null, the system's default language ID will be used.
   *
   * @return string Returns the retrieved SEO title tag as a string.
   */
  public static function getCategoriesSeoTitle( int|null $category_id, int $language_id): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    if (!$language_id) $language_id = $CLICSHOPPING_Language->getId();

    $Qseo = $CLICSHOPPING_Db->prepare('select categories_head_title_tag
                                          from :table_categories_description
                                          where categories_id = :categories_id
                                          and language_id = :language_id
                                        ');
    $Qseo->bindInt(':categories_id', $category_id);
    $Qseo->bindInt(':language_id', $language_id);

    $Qseo->execute();

    return $Qseo->value('categories_head_title_tag');
  }

  /**
   * Retrieves the SEO description for a category based on the provided category ID and language ID.
   *
   * @param int|null $category_id The unique identifier for the category. Can be null if the category ID is not provided.
   * @param int $language_id The unique identifier for the language.
   *
   * @return string Returns the retrieved SEO description for the specified category.
   */
  public static function getCategoriesSeoDescription( int|null $category_id, int $language_id): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    if (!$language_id) $language_id = $CLICSHOPPING_Language->getId();

    $Qseo = $CLICSHOPPING_Db->prepare('select categories_head_desc_tag
                                              from :table_categories_description
                                              where categories_id = :categories_id
                                              and language_id = :language_id
                                            ');
    $Qseo->bindInt(':categories_id', $category_id);
    $Qseo->bindInt(':language_id', $language_id);

    $Qseo->execute();

    return $Qseo->value('categories_head_desc_tag');
  }

  /**
   * Retrieves the SEO keywords information for a category based on the given category ID and language ID.
   *
   * @param int|null $category_id The unique identifier for the category, or null if no category is specified.
   * @param int $language_id The unique identifier for the language.
   *
   * @return string Returns the retrieved SEO keywords for the specified category as a string.
   */
  public static function getCategoriesSeoKeywords( int|null $category_id, int $language_id): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    if (!$language_id) $language_id = $CLICSHOPPING_Language->getId();

    $Qseo = $CLICSHOPPING_Db->prepare('select categories_head_keywords_tag
                                              from :table_categories_description
                                              where categories_id = :categories_id
                                              and language_id = :language_id
                                            ');
    $Qseo->bindInt(':categories_id', $category_id);
    $Qseo->bindInt(':language_id', $language_id);

    $Qseo->execute();

    return $Qseo->value('categories_head_keywords_tag');
  }

  /**
   * Retrieves the SEO recommendations title for a specific language based on the provided SEO ID.
   *
   * @param int $seo_id The unique identifier for the SEO entry.
   * @param int $language_id The unique identifier for the language.
   *
   * @return string Returns the retrieved SEO recommendations title as a string.
   */
  public static function getSeoRecommendationsLanguageTitle(int $seo_id, int $language_id): string
  {
    $Qseo = Registry::get('Db')->get('seo', 'seo_language_recommendations_title', ['seo_id' => $seo_id, 'language_id' => $language_id]);

    return $Qseo->value('seo_language_recommendations_title');
  }

  /**
   * Retrieves the SEO recommendations description for a specific language based on the provided SEO ID and language ID.
   *
   * @param int $seo_id The unique identifier for the SEO entry.
   * @param int $language_id The unique identifier for the language.
   *
   * @return string Returns the retrieved SEO recommendations description as a string.
   */
  public static function getSeoRecommendationsLanguageDescription(int $seo_id, int $language_id): string
  {
    $Qseo = Registry::get('Db')->get('seo', 'seo_language_recommendations_description', ['seo_id' => $seo_id, 'language_id' => $language_id]);

    return $Qseo->value('seo_language_recommendations_description');
  }

  /**
   * Retrieves the SEO recommendation keywords based on the provided SEO ID and language ID.
   *
   * @param int $seo_id The unique identifier for the SEO entry.
   * @param int $language_id The unique identifier for the language.
   *
   * @return string Returns the retrieved SEO recommendation keywords as a string.
   */
  public static function getSeoRecommendationsLanguageKeywords(int $seo_id, int $language_id): string
  {
    $Qseo = Registry::get('Db')->get('seo', 'seo_language_recommendations_keywords', ['seo_id' => $seo_id, 'language_id' => $language_id]);

    return $Qseo->value('seo_language_recommendations_keywords');
  }
}