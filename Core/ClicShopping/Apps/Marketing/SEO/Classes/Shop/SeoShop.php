<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\Apps\Marketing\SEO\Classes\Shop;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class SeoShop
{
  private mixed $db;
  private mixed $lang;

  protected string $seoDefaultTitle;
  protected string $seoDefaultDescription;
  protected string $seoDefaultKeywords;
  protected string $seoDefaultProductsNewTitle;
  protected string $seoDefaultProductsNewDescription;
  protected string $seoDefaultProductsNewKeywords;
  protected string $getSeoSpecialsTitle;
  protected string $seoDefaultSpecialsDescription;
  protected string $seoDefaultSpecialsKeywords;
  protected string $seoDefaultReviewsTitle;
  protected string $seoDefaultReviewsDescription;
  protected string $seoDefaultReviewsKeywords;
  protected string $seoDefaultFavoritesTitle;
  protected string $seoDefaultFavoritesDescription;
  protected string $seoDefaultFavoritesKeywords;
  protected string $seoDefaultFeaturedTitle;
  protected string $seoDefaultFeaturedDescription;
  protected string $seoDefaultFeaturedKeywords;
  protected string $keywordsAdvancedPage;
  protected string $descriptionAdvancedPage;
  protected string $titleAdvancedPage;

  /**
   * Initializes the class and sets up the default SEO settings, language, and database connections.
   *
   * @return void
   */
  public function __construct()
  {
    $this->db = Registry::get('Db');
    $this->lang = Registry::get('Language');

    $this->getDefaultSeo();
    $this->getDefaultSeoProductsNew();
    $this->getDefaultSeoSpecials();
    $this->getDefaultSeoReviews();
    $this->getDataAdvancedSearchPage();
    $this->getDefaultSeoFeatured();
  }

  /**
   * Nettoie et assemble les segments SEO
   * @param array $parts
   * @return string
   */
  private function clean(array $parts): string
  {
    return implode(', ', array_unique(array_filter(array_map('trim', $parts))));
  }
  
  /**
   * Retrieves the default SEO settings for the current language.
   *
   * @return void Sets the default SEO title, description, and keywords properties for the application.
   */
  public function getDefaultSeo()
  {
    $Qseo = $this->db->prepare('select seo_defaut_language_title,
                                          seo_defaut_language_keywords,
                                          seo_defaut_language_description
                                    from :table_seo
                                    where seo_id = 1
                                    and language_id = :language_id
                                   ');

    $Qseo->bindInt(':language_id', $this->lang->getId());
    $Qseo->execute();

    $this->seoDefaultTitle = $Qseo->value('seo_defaut_language_title');
    $this->seoDefaultDescription = $Qseo->value('seo_defaut_language_description');
    $this->seoDefaultKeywords = $Qseo->value('seo_defaut_language_keywords');
  }

  /**
   * Retrieves the SEO title for the index page.
   *
   * @return string|null Returns the SEO index title or null if not set.
   */
  public function getSeoIndexTitle(): ?string
  {
    if (empty($this->seoDefaultTitle)) {
      $result = HTML::outputProtected(STORE_NAME);
    } else {
      $result = HTML::outputProtected($this->seoDefaultTitle) . ', ' . HTML::outputProtected(STORE_NAME);
    }

    return $result;
  }

  /**
   * Retrieves the SEO index description.
   *
   * @return string|null Returns the SEO index description based on the default description or store name, or null if not set.
   */
  public function getSeoIndexDescription(): ?string
  {
    if (empty($this->seoDefaultDescription)) {
      $result = HTML::outputProtected(STORE_NAME);
    } else {
      $result = HTML::outputProtected($this->seoDefaultDescription);
    }

    return $result;
  }

  /**
   * Retrieves the SEO index keywords.
   *
   * @return string|null Returns the SEO keywords for the index page or null if not set.
   */
  public function getSeoIndexKeywords(): ?string
  {
    if (empty($this->seoDefaultKeywords)) {
      $result = HTML::outputProtected(STORE_NAME);
    } else {
      $result = HTML::outputProtected($this->seoDefaultKeywords);
    }

    return $result;
  }

  /**
   * Retrieves the default SEO information for the "new products" page.
   *
   * @return void This method sets the default SEO title, description, and keywords for the "new products" page in the corresponding properties.
   */
  public function getDefaultSeoProductsNew()
  {
    $Qseo = $this->db->prepare('select seo_language_products_new_title,
                                        seo_language_products_new_keywords,
                                        seo_language_products_new_description
                                  from :table_seo
                                  where seo_id = 1
                                  and language_id = :language_id
                                ');
    $Qseo->bindInt(':language_id', $this->lang->getId());
    $Qseo->execute();

    $this->seoDefaultProductsNewTitle = $Qseo->value('seo_language_products_new_title');
    $this->seoDefaultProductsNewDescription = $Qseo->value('seo_language_products_new_description');
    $this->seoDefaultProductsNewKeywords = $Qseo->value('seo_language_products_new_keywords');
  }

  /**
   * Retrieves the SEO title for the products new page.
   *
   * @return string|null Returns the SEO title for the products new page or null if not set.
   */
  public function getSeoProductsNewTitle(): ?string
  {
    if (empty($this->seoDefaultProductsNewTitle)) {
      $result = CLICSHOPPING::getDef('text_products_news_seo_title') . ', ' . HTML::outputProtected(STORE_NAME);
    } else {
      $result = HTML::outputProtected($this->seoDefaultProductsNewTitle) . ', ' . HTML::outputProtected(STORE_NAME);
    }

    return $result;
  }

  /**
   * Retrieves the SEO description for the "new products" page.
   *
   * @return string|null Returns the protected SEO description of the "new products" page or the store name if no custom description is set.
   */
  public function getSeoProductsNewDescription(): ?string
  {
    if (empty($this->seoDefaultProductsNewDescription)) {
      $result = HTML::outputProtected(STORE_NAME);
    } else {
      $result = HTML::outputProtected($this->seoDefaultProductsNewDescription);
    }

    return $result;
  }

  /**
   * Retrieves the SEO keywords for new products.
   *
   * @return string|null Returns the SEO keywords for new products or the store name if not explicitly set.
   */
  public function getSeoProductsNewKeywords(): ?string
  {
    if (empty($this->seoDefaultProductsNewKeywords)) {
      $result = HTML::outputProtected(STORE_NAME);
    } else {
      $result = HTML::outputProtected($this->seoDefaultProductsNewKeywords);
    }

    return $result;
  }

  /**
   * Retrieves the default SEO specials data including title, keywords, and description
   * for a specific language.
   *
   * @return void
   */
  public function getDefaultSeoSpecials()
  {
    $Qseo = $this->db->prepare('select seo_language_special_title,
                                         seo_language_special_keywords,
                                         seo_language_special_description
                                  from :table_seo
                                  where seo_id = 1
                                  and language_id = :language_id
                                ');
    $Qseo->bindInt(':language_id', $this->lang->getId());
    $Qseo->execute();

    $this->getSeoSpecialsTitle = $Qseo->value('seo_language_special_title');
    $this->seoDefaultSpecialsKeywords = $Qseo->value('seo_language_special_keywords');
    $this->seoDefaultSpecialsDescription = $Qseo->value('seo_language_special_description');
  }

  /**
   * Retrieves the SEO title for the specials page.
   *
   * @return string|null Returns the SEO title for the specials page or null if not set.
   */
  public function getSeoSpecialsTitle(): ?string
  {
    if (empty($this->getSeoSpecialsTitle)) {
      $result = CLICSHOPPING::getDef('text_specials_seo_title') . ', ' . HTML::outputProtected(STORE_NAME);
    } else {
      $result = HTML::outputProtected($this->getSeoSpecialsTitle) . ', ' . HTML::outputProtected(STORE_NAME);
    }

    return $result;
  }

  /**
   * Retrieves the SEO description for the specials page.
   *
   * @return string|null Returns the protected SEO description for the specials page or null if not set.
   */
  public function getSeoSpecialsDescription(): ?string
  {
    if (empty($this->seoDefaultSpecialsDescription)) {
      $result = HTML::outputProtected(STORE_NAME);
    } else {
      $result = HTML::outputProtected($this->seoDefaultSpecialsDescription);
    }

    return $result;
  }

  /**
   * Retrieves the SEO keywords for the specials page.
   *
   * @return string|null Returns the SEO keywords for the specials page or null if not set.
   */
  public function getSeoSpecialsKeywords(): ?string
  {
    if (empty($this->seoDefaultSpecialsKeywords)) {
      $result = HTML::outputProtected(STORE_NAME);
    } else {
      $result = HTML::outputProtected($this->seoDefaultSpecialsKeywords);
    }

    return $result;
  }

  /**
   * Retrieves the default SEO reviews metadata such as title, description, and keywords for a specific language.
   *
   * @return void
   */
  public function getDefaultSeoReviews()
  {
    $Qseo = $this->db->prepare('select seo_language_reviews_title,
                                         seo_language_reviews_keywords,
                                         seo_language_reviews_description
                                  from :table_seo
                                  where seo_id = 1
                                  and language_id = :language_id
                                ');
    $Qseo->bindInt(':language_id', $this->lang->getId());
    $Qseo->execute();

    $this->seoDefaultReviewsTitle = $Qseo->value('seo_language_reviews_title');
    $this->seoDefaultReviewsDescription = $Qseo->value('seo_language_reviews_description');
    $this->seoDefaultReviewsKeywords = $Qseo->value('seo_language_reviews_keywords');
  }

  /**
   * Retrieves the SEO title for the reviews page.
   *
   * @return string|null Returns the SEO title for the reviews page or null if not set.
   */
  public function getSeoReviewsTitle(): ?string
  {
    if (empty($this->seoDefaultReviewsTitle)) {
      $result = CLICSHOPPING::getDef('text_reviews_seo_title') . ', ' . HTML::outputProtected(STORE_NAME);
    } else {
      $result = HTML::outputProtected($this->seoDefaultReviewsTitle) . ', ' . HTML::outputProtected(STORE_NAME);
    }

    return $result;
  }

  /**
   * Retrieves the SEO description for the reviews page.
   *
   * @return string|null Returns the SEO reviews description or null if not set.
   */
  public function getSeoReviewsDescription(): ?string
  {
    if (empty($this->seoDefaultReviewsDescription)) {
      $result = HTML::outputProtected(STORE_NAME);
    } else {
      $result = HTML::outputProtected($this->seoDefaultReviewsDescription);
    }

    return $result;
  }

  /**
   * Retrieves the SEO keywords for reviews.
   *
   * @return string|null Returns the SEO keywords for reviews or the store name if no keywords are set.
   */
  public function getSeoReviewsKeywords(): ?string
  {
    if (empty($this->seoDefaultReviewsKeywords)) {
      $result = HTML::outputProtected(STORE_NAME);
    } else {
      $result = HTML::outputProtected($this->seoDefaultReviewsKeywords);
    }

    return $result;
  }

  /**
   * Retrieves the default SEO favorites data, including title, description, and keywords,
   * from the database for the current language.
   *
   * @return void
   */
  public function getDefaultSeoFavorites()
  {
    $Qseo = $this->db->prepare('select seo_language_favorites_title,
                                         seo_language_favorites_keywords,
                                         seo_language_favorites_description
                                  from :table_seo
                                  where seo_id = 1
                                  and language_id = :language_id
                                ');
    $Qseo->bindInt(':language_id', $this->lang->getId());
    $Qseo->execute();

    $this->seoDefaultFavoritesTitle = $Qseo->value('seo_language_favorites_title');
    $this->seoDefaultFavoritesDescription = $Qseo->value('seo_language_favorites_description');
    $this->seoDefaultFavoritesKeywords = $Qseo->value('seo_language_favorites_keywords');
  }

  /**
   * Retrieves the SEO title for the favorites page.
   *
   * @return string|null Returns the generated SEO title for the favorites page, or null if not available.
   */
  public function getSeoFavoritesTitle(): ?string
  {
    if (empty($this->seoDefaultFavoritesTitle)) {
      $result = CLICSHOPPING::getDef('text_favorites_seo_title') . ', ' . HTML::outputProtected(STORE_NAME);
    } else {
      $result = HTML::outputProtected($this->seoDefaultFavoritesTitle) . ', ' . HTML::outputProtected(STORE_NAME);
    }

    return $result;
  }

  /**
   * Retrieves the SEO description for the favorites page. If a custom description is not provided,
   * it defaults to the protected output of the store name.
   *
   * @return string|null The SEO description for the favorites page or null if not set.
   */
  public function getSeoFavoritesDescription(): ?string
  {
    if (empty($this->seoDefaultFavoritesDescription)) {
      $result = HTML::outputProtected(STORE_NAME);
    } else {
      $result = HTML::outputProtected($this->seoDefaultFavoritesDescription);
    }

    return $result;
  }

  /**
   * Retrieves the SEO favorite keywords, with a fallback to the store name if none are defined.
   *
   * @return string|null The processed SEO favorite keywords or the store name if no keywords are set.
   */
  public function getSeoFavoritesKeywords(): ?string
  {
    if (empty($this->seoDefaultFavoritesKeywords)) {
      $result = HTML::outputProtected(STORE_NAME);
    } else {
      $result = HTML::outputProtected($this->seoDefaultFavoritesKeywords);
    }

    return $result;
  }

  /**
   * Retrieves the default SEO featured data such as title, keywords, and description for a specific language.
   *
   * @return void
   */
  public function getDefaultSeoFeatured()
  {
    $Qseo = $this->db->prepare('select seo_language_featured_title,
                                         seo_language_featured_keywords,
                                         seo_language_featured_description
                                  from :table_seo
                                  where seo_id = 1
                                  and language_id = :language_id
                                ');
    $Qseo->bindInt(':language_id', $this->lang->getId());
    $Qseo->execute();

    $this->seoDefaultFeaturedTitle = $Qseo->value('seo_language_featured_title');
    $this->seoDefaultFeaturedDescription = $Qseo->value('seo_language_featured_description');
    $this->seoDefaultFeaturedKeywords = $Qseo->value('seo_language_featured_keywords');
  }
  
  /**
   * Retrieves the SEO featured title for a page, combining either the default SEO title
   * or a fallback text with the store name.
   *
   * @return string|null The generated SEO featured title or null if not set.
   */
  public function getSeoFeaturedTitle(): ?string
  {
    if (empty($this->seoDefaultFeaturedTitle)) {
      $result = CLICSHOPPING::getDef('text_featured_seo_title') . ', ' . HTML::outputProtected(STORE_NAME);
    } else {
      $result = HTML::outputProtected($this->seoDefaultFeaturedTitle) . ', ' . HTML::outputProtected(STORE_NAME);
    }

    return $result;
  }

  /**
   * Retrieves the SEO featured description, using a default value if none is set.
   *
   * @return string|null The protected output of the SEO featured description or the store name if no description is provided.
   */
  public function getSeoFeaturedDescription(): ?string
  {
    if (empty($this->seoDefaultFeaturedDescription)) {
      $result = HTML::outputProtected(STORE_NAME);
    } else {
      $result = HTML::outputProtected($this->seoDefaultFeaturedDescription);
    }

    return $result;
  }

  /**
   * Retrieves the SEO featured keywords for the store.
   *
   * @return string|null The processed SEO featured keywords or the store name if not set.
   */
  public function getSeoFeaturedKeywords(): ?string
  {
    if (empty($this->seoDefaultFeaturedKeywords)) {
      $result = HTML::outputProtected(STORE_NAME);
    } else {
      $result = HTML::outputProtected($this->seoDefaultFeaturedKeywords);
    }

    return $result;
  }

  /**
   * Processes the advanced search keywords from user input, sanitizes them,
   * and assigns a title for the advanced search page based on the provided keywords.
   *
   * @return void
   */
  public function getDataAdvancedSearchPage()
  {

    if (isset($_POST['keywords'])) {
      $keywords = HTML::sanitize($_POST['keywords']);
    } else {
      $keywords = '';
    }

    if (!empty($keywords)) {
      if (empty($this->seoDefaultTitle)) {
        $this->titleAdvancedPage = $keywords;
      } else {
        $this->titleAdvancedPage = $keywords . ',  ' . HTML::sanitize($this->seoDefaultTitle);
      }
    } else {
      $this->titleAdvancedPage = HTML::sanitize($keywords);
    }
  }

  /**
   *
   * @return string|null Returns the title of the advanced search page or null if not set.
   */
  public function getAdvancedSearchTitle(): ?string
  {
    return $this->titleAdvancedPage;
  }

  /**
   * Retrieves the description for the advanced search page.
   *
   * @return string|null Returns the description of the advanced search page or null if not set.
   */
  public function getAdvancedSearchDescription(): ?string
  {
    return $this->descriptionAdvancedPage;
  }

  /**
   *
   * @return string|null The advanced search keywords for the page.
   */
  public function getAdvancedSearchKeywords(): ?string
  {
    return $this->keywordsAdvancedPage;
  }

  /**
   * Retrieves the SEO URL for a specified category and language.
   *
   * @param int $category_id The ID of the category.
   * @param int $language_id The ID of the language. If not provided, the default language ID will be used.
   * @return string The SEO URL of the category.
   */
  public function getCategoriesSeoUrl(int $category_id, int $language_id): string
  {
    if (!$language_id) $language_id = $this->lang->getId();

    $Qseo = $this->db->prepare('select categories_seo_url
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
   * Retrieves the SEO URL for a specific product based on its ID and language ID.
   *
   * @param string|null $products_id The ID of the product for which the SEO URL is to be retrieved. Can be null.
   * @param int $language_id The ID of the language for which the SEO URL is to be retrieved.
   * @return string The SEO URL of the specified product.
   */
  public function getProductsSeoUrl(?string $products_id, int $language_id): string
  {
    if (!$language_id) $language_id = $this->lang->getId();

    $Qseo = $this->db->prepare('select products_seo_url
                                  from :table_products_description
                                  where products_id = :products_id
                                  and language_id = :language_id
                                ');
    $Qseo->bindInt(':products_id', $products_id);
    $Qseo->bindInt(':language_id', $language_id);

    $Qseo->execute();

    return $Qseo->value('products_seo_url');
  }
}
