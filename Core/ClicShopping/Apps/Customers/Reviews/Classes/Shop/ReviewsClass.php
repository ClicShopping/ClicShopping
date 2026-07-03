<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Reviews\Classes\Shop;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Hash;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use function defined;
use function is_null;

class ReviewsClass
{
  protected mixed $productsCommon;
  private mixed $db;
  private mixed $lang;
  private mixed $hooks;
  protected mixed $customer;
  protected int $reviews_number_comments;
  protected int $reviews_number_word;

  /**
   * Constructor method to initialize the class properties using the application registry and
   * default values for reviews-related configurations.
   *
   * @return void
   */
  public function __construct()
  {
    $this->productsCommon = Registry::get('ProductsCommon');
    $this->db = Registry::get('Db');
    $this->lang = Registry::get('Language');
    $this->customer = Registry::get('Customer');
    $this->hooks = Registry::get('Hooks');

    if (defined('MODULE_PRODUCTS_INFO_REVIEWS_NUMBER_WORDS')) {
      $this->reviews_number_word = (int)MODULE_PRODUCTS_INFO_REVIEWS_NUMBER_WORDS;
    } else {
      $this->reviews_number_word = 0;
    }

    if (defined('MODULE_PRODUCTS_INFO_REVIEWS_NUMBER_COMMENTS')) {
      $this->reviews_number_comments = (int)MODULE_PRODUCTS_INFO_REVIEWS_NUMBER_COMMENTS;
    } else {
      $this->reviews_number_comments = 0;
    }
  }

  /**
   * Retrieves the total number of reviews for a specific product based on customer group and language.
   *
   * @return int The total number of reviews.
   */
  public function getTotalReviews(): int
  {
    if ($this->customer->getCustomersGroupID() == 0 || $this->customer->getCustomersGroupID() == 99) {
      $Qcheck = $this->db->prepare('select count(r.reviews_id) as total
                                      from :table_reviews r,
                                           :table_reviews_description rd
                                      where r.products_id = :products_id
                                      and rd.languages_id = :languages_id
                                      and r.reviews_id = rd.reviews_id
                                      and r.status = 1
                                      and r.customers_group_id = 0
                                      ');
      $Qcheck->bindInt(':products_id', $this->productsCommon->getId());
      $Qcheck->bindInt(':languages_id', $this->lang->getId());
      $Qcheck->execute();
    } else {
      $Qcheck = $this->db->prepare('select count(r.reviews_id) as total
                                      from :table_reviews r,
                                           :table_reviews_description rd
                                      where r.products_id = :products_id
                                      and rd.languages_id = :languages_id
                                      and r.reviews_id = rd.reviews_id
                                      and r.status = 1
                                      and r.customers_group_id > 0
                                      ');
      $Qcheck->bindInt(':products_id', $this->productsCommon->getId());
      $Qcheck->bindInt(':languages_id', $this->lang->getId());
      $Qcheck->execute();
    }

    return $Qcheck->valueInt('total');
  }

  /**
   * Retrieves review data for a specific product, including details such as review text, rating, date added, status,
   * customer name, customer ID, and customer tag. The method filters reviews based on the customer's group ID and
   * limits the number of reviews and text length according to predefined parameters.
   *
   * @return object Prepared database statement containing review data for the specified product.
   */
  public function getData()
  {
    if ($this->customer->getCustomersGroupID() == 0) {
      $Qreviews = $this->db->prepare('select r.reviews_id,
                                                 left(rd.reviews_text, :limitText) as reviews_text,
                                                 r.reviews_rating,
                                                 r.date_added,
                                                 r.status,
                                                 r.customers_name,
                                                 r.customers_id,
                                                 r.customers_tag
                                          from :table_reviews r,
                                               :table_reviews_description rd
                                          where r.products_id = :products_id
                                          and r.reviews_id = rd.reviews_id
                                          and rd.languages_id = :languages_id
                                          and r.status = 1
                                          and r.customers_group_id = 0
                                          order by r.reviews_rating desc,
                                                  r.date_added desc
                                          limit :limit
                                          ');
      $Qreviews->bindInt(':products_id', $this->productsCommon->getId());
      $Qreviews->bindInt(':languages_id', $this->lang->getId());
      $Qreviews->bindInt(':limitText', $this->reviews_number_word);
      $Qreviews->bindInt(':limit', $this->reviews_number_comments);
      $Qreviews->execute();
    } else {
      $Qreviews = $this->db->prepare('select r.reviews_id,
                                                 left(rd.reviews_text, :limitText ) as reviews_text,
                                                 r.reviews_rating,
                                                 r.date_added,
                                                 r.status,
                                                 r.customers_name,
                                                 r.customers_id,
                                                 r.customers_tag
                                         from :table_reviews r,
                                              :table_reviews_description rd
                                         where r.products_id = :products_id
                                         and r.reviews_id = rd.reviews_id
                                         and rd.languages_id = :languages_id
                                         and r.status = 1
                                         and r.customers_group_id > 0
                                         order by r.reviews_rating desc,
                                                  r.date_added desc
                                         limit :limit
                                        ');
      $Qreviews->bindInt(':products_id', $this->productsCommon->getId());
      $Qreviews->bindInt(':languages_id', $this->lang->getId());
      $Qreviews->bindInt(':limitText', $this->reviews_number_word);
      $Qreviews->bindInt(':limit', $this->reviews_number_comments);
      $Qreviews->execute();
    }

    $this->getPageSetTotalRows = $Qreviews->getPageSetTotalRows();

    return $Qreviews;
  }

  /**
   * Retrieves the total number of rows in the current page set.
   *
   * @return int The total number of rows in the page set.
   */
  public function getPageSetTotalRows(): int
  {
    return $this->getPageSetTotalRows;
  }

  /**
   * Retrieves review data for the specified review ID, filtered by customer group and language.
   *
   * @param int|null $id The ID of the review to retrieve. Can be null.
   * @return bool|array Returns the review data as an associative array if a valid review ID is provided; otherwise, returns false.
   */
  public function getDataReviews( int|null $id): bool|array
  {
    $reviews_id = HTML::sanitize($id);

    if (!is_null($reviews_id) && is_numeric($reviews_id)) {
      if ($this->customer->getCustomersGroupID() == 0 || $this->customer->getCustomersGroupID() == 99) {
        $Qreviews = $this->db->prepare('select r.reviews_id,
                                                rd.reviews_text,
                                                r.reviews_rating,
                                                r.date_added,
                                                r.customers_name,
                                                r.customers_id,
                                                r.customers_tag
                                          from :table_reviews r,
                                               :table_reviews_description rd
                                          where r.reviews_id = :reviews_id
                                          and r.reviews_id = rd.reviews_id
                                          and rd.languages_id = :languages_id
                                          and r.status = 1
                                          and r.customers_group_id = 0
                                          ');
        $Qreviews->bindInt(':reviews_id', $reviews_id);
        $Qreviews->bindInt(':languages_id', $this->lang->getId());
        $Qreviews->execute();
      } else {
        $Qreviews = $this->db->prepare('select r.reviews_id,
                                                  rd.reviews_text,
                                                  r.reviews_rating,
                                                  r.date_added,
                                                  r.customers_name,
                                                  r.customers_id,
                                                  r.customers_tag
                                          from :table_reviews r,
                                               :table_reviews_description rd
                                          where r.reviews_id = :reviews_id
                                          and r.reviews_id = rd.reviews_id
                                          and rd.languages_id = :languages_id
                                          and r.status = 1
                                          and r.customers_group_id > 0
                                          ');
        $Qreviews->bindInt(':reviews_id', $reviews_id);
        $Qreviews->bindInt(':languages_id', $this->lang->getId());
        $Qreviews->execute();
      }

      return $Qreviews->fetch();
    } else {
      return false;
    }
  }

  /**
   * Increments the view counter (`reviews_read`) of a single review.
   *
   * Called once per single-review view (ReviewsInfo page). Previously the counter
   * was never incremented, so it stayed at 0.
   *
   * @param int $reviewsId Review being viewed.
   * @return void
   */
  public function incrementReadCount(int $reviewsId): void
  {
    if ($reviewsId <= 0) {
      return;
    }

    $Qupdate = $this->db->prepare('update :table_reviews
                                   set reviews_read = reviews_read + 1
                                   where reviews_id = :reviews_id');
    $Qupdate->bindInt(':reviews_id', $reviewsId);
    $Qupdate->execute();
  }

  /**
   * Saves a review entry into the database. Depending on the customer's group,
   * it updates the reviews and reviews description tables with the provided
   * review data. Invokes hooks after the entry is saved.
   *
   * @return void
   */
  public function saveEntry(): void
  {
    if ($this->customer->getCustomersGroupID() == 0) {
      $array_sql = [
        'products_id' => (int)$this->productsCommon->getID(),
        'customers_id' => (int)$this->customer->getID(),
        'customers_name' => Hash::encryptDatatext($this->customer->getName()),
        'reviews_rating' => (int)($_POST['rating'] ?? 0),
        'date_added' => 'now()',
        'last_modified' => 'now()',
        'status' => 0,
        'customers_group_id' => 0
      ];

      $this->db->save('reviews', $array_sql);
    } else {
      $array_sql = [
        'products_id' => (int)$this->productsCommon->getID(),
        'customers_id' => (int)$this->customer->getID(),
        'customers_name' => Hash::encryptDatatext($this->customer->getName()),
        'reviews_rating' => (int)($_POST['rating'] ?? 0),
        'date_added' => 'now()',
        'last_modified' => 'now()',
        'status' => 0,
        'customers_group_id' => (int)$this->customer->getCustomersGroupID()
      ];

      $this->db->save('reviews', $array_sql);
    }

    $insert_id = $this->db->lastInsertId();

    $array_sql = [
      'reviews_id' => (int)$insert_id,
      'languages_id' => (int)$this->lang->getId(),
      'reviews_text' => HTML::sanitize($_POST['review'] ?? '')
    ];

    $this->db->save('reviews_description', $array_sql);

    $this->hooks->call('Reviews', 'SaveEntry');
  }

  /**
   * Sends an email notification to the customer and the store owner based on the review comment settings.
   *
   * This method sends two emails:
   * 1. A notification email to the customer with personalized content derived from store settings.
   * 2. A notification email to the store owner containing details about the product and store settings.
   *
   * The method uses the `CLICSHOPPING_Mail` functionality to compose and send the emails.
   * It checks the `REVIEW_COMMENT_SEND_EMAIL` configuration to determine whether emails should be sent.
   *
   * @return void
   */
  public function sendEmail(): void
  {
    $CLICSHOPPING_Mail = Registry::get('Mail');

    if (REVIEW_COMMENT_SEND_EMAIL == 'true') {
      $email_text = CLICSHOPPING::getDef('email_text_customer', ['store_name' => STORE_NAME]);

      $to_addr = $this->customer->getEmailAddress();
      $from_name = STORE_NAME;
      $from_addr = STORE_OWNER_EMAIL_ADDRESS;
      $to_name = $this->customer->getLastName();
      $subject = CLICSHOPPING::getDef('email_subject_customer', ['store_name' => STORE_NAME]);

      $CLICSHOPPING_Mail->addHtml($email_text);
      $CLICSHOPPING_Mail->send($to_addr, $from_name, $from_addr, $to_name, $subject);

      //admin
      $email_text = CLICSHOPPING::getDef('email_text', ['store_name' => STORE_NAME]);

      $to_addr = STORE_OWNER_EMAIL_ADDRESS;
      $from_name = STORE_NAME;
      $from_addr = STORE_OWNER_EMAIL_ADDRESS;
      $to_name = STORE_NAME;
      $subject = CLICSHOPPING::getDef('email_subject', ['store_name' => STORE_NAME]);

      $CLICSHOPPING_Mail->addHtml($email_text . '<br />' . $this->productsCommon->getProductsName());
      $CLICSHOPPING_Mail->send($to_addr, $from_name, $from_addr, $to_name, $subject);
    }
  }

  /**
   * Deletes a review from the database, including its associated descriptions.
   *
   * @param int $review_id The ID of the review to be deleted.
   *
   * @return void
   */
  public function deleteReviews(int $review_id): void
  {
    $Odelete = $this->db->prepare('delete
                                    from :table_reviews
                                    where reviews_id = :reviews_id
                                    ');
    $Odelete->bindInt(':reviews_id', $review_id);
    $Odelete->execute();

    $Odelete = $this->db->prepare('delete
                                     from :table_reviews_description
                                     where reviews_id = :reviews_id
                                    ');
    $Odelete->bindInt(':reviews_id', $review_id);
    $Odelete->execute();
  }

  /**
   * Calculates the average rating of product reviews for the specified product.
   *
   * @param int $products_id The ID of the product for which the average rating is calculated.
   * @param bool $all_language Indicates whether the average rating should be calculated across all languages (true) or only for the current language (false).
   * @return float|null The average rating of the product reviews, or null if no reviews are available.
   */
  public function getAverageProductReviews(int $products_id, bool $all_language = false): ?float
  {
    if ($all_language === false) {
      if ($this->customer->getCustomersGroupID() == 0 || $this->customer->getCustomersGroupID() == 99) {
        $Qcheck = $this->db->prepare('select count(r.reviews_id) as reviews_total, 
                                                sum(r.reviews_rating) as sum_reviews
                                        from :table_reviews r,
                                             :table_reviews_description rd
                                        where r.products_id = :products_id
                                        and r.reviews_id = rd.reviews_id
                                        and r.status = 1
                                        and r.customers_group_id = 0
                                        ');
        $Qcheck->bindInt(':products_id', $products_id);
        $Qcheck->execute();

      } else {
        $Qcheck = $this->db->prepare('select count(r.reviews_id) as reviews_total, 
                                               sum(r.reviews_rating) as sum_reviews
                                      from :table_reviews r,
                                           :table_reviews_description rd
                                      where r.products_id = :products_id
                                      and r.reviews_id = rd.reviews_id
                                      and r.status = 1
                                      and r.customers_group_id > 0
                                      ');
        $Qcheck->bindInt(':products_id', $products_id);
        $Qcheck->execute();
      }
    } else {
      if ($this->customer->getCustomersGroupID() == 0 || $this->customer->getCustomersGroupID() == 99) {
        $Qcheck = $this->db->prepare('select count(r.reviews_id) as reviews_total, 
                                               sum(r.reviews_rating) as sum_reviews
                                      from :table_reviews r,
                                           :table_reviews_description rd
                                      where r.products_id = :products_id
                                      and rd.languages_id = :languages_id
                                      and r.reviews_id = rd.reviews_id
                                      and r.status = 1
                                      and r.customers_group_id = 0
                                      ');
        $Qcheck->bindInt(':products_id', $products_id);
        $Qcheck->bindInt(':languages_id', $this->lang->getId());
        $Qcheck->execute();
      } else {
        $Qcheck = $this->db->prepare('select count(r.reviews_id) as reviews_total, 
                                               sum(r.reviews_rating) as sum_reviews
                                      from :table_reviews r,
                                           :table_reviews_description rd
                                      where r.products_id = :products_id
                                      and rd.languages_id = :languages_id
                                      and r.reviews_id = rd.reviews_id
                                      and r.status = 1
                                      and r.customers_group_id > 0
                                      ');
        $Qcheck->bindInt(':products_id', $products_id);
        $Qcheck->bindInt(':languages_id', $this->lang->getId());
        $Qcheck->execute();
      }
    }

    if ($Qcheck->valueInt('reviews_total') > 0) {
      $average = $Qcheck->valueInt('sum_reviews') / $Qcheck->valueInt('reviews_total');
    } else {
      $average = 0;
    }

    return $average;
  }

  /**
   * Retrieves the highest product review rating for a given product, optionally filtering by language.
   *
   * @param int $products_id The ID of the product for which the best review will be retrieved.
   * @param bool $all_language If set to true, considers all languages; otherwise, filters by the current language.
   * @return int|null Returns the highest review rating for the product, or null if no reviews exist.
   */
  public function getBestProductReviews(int $products_id, bool $all_language = false):  int|null
  {
    if ($all_language === false) {
      if ($this->customer->getCustomersGroupID() == 0 || $this->customer->getCustomersGroupID() == 99) {
        $Qcheck = $this->db->prepare('select count(r.reviews_id) as reviews_total, 
                                                max(r.reviews_rating) as max_reviews
                                        from :table_reviews r,
                                             :table_reviews_description rd
                                        where r.products_id = :products_id
                                        and r.reviews_id = rd.reviews_id
                                        and r.status = 1
                                        and r.customers_group_id = 0
                                        ');
        $Qcheck->bindInt(':products_id', $products_id);
        $Qcheck->execute();

      } else {
        $Qcheck = $this->db->prepare('select count(r.reviews_id) as reviews_total, 
                                               max(r.reviews_rating) as max_reviews
                                      from :table_reviews r,
                                           :table_reviews_description rd
                                      where r.products_id = :products_id
                                      and r.reviews_id = rd.reviews_id
                                      and r.status = 1
                                      and r.customers_group_id > 0
                                      ');
        $Qcheck->bindInt(':products_id', $products_id);
        $Qcheck->execute();
      }
    } else {
      if ($this->customer->getCustomersGroupID() == 0 || $this->customer->getCustomersGroupID() == 99) {
        $Qcheck = $this->db->prepare('select count(r.reviews_id) as reviews_total, 
                                               max(r.reviews_rating) as max_reviews
                                      from :table_reviews r,
                                           :table_reviews_description rd
                                      where r.products_id = :products_id
                                      and rd.languages_id = :languages_id
                                      and r.reviews_id = rd.reviews_id
                                      and r.status = 1
                                      and r.customers_group_id = 0
                                      ');
        $Qcheck->bindInt(':products_id', $products_id);
        $Qcheck->bindInt(':languages_id', $this->lang->getId());
        $Qcheck->execute();
      } else {
        $Qcheck = $this->db->prepare('select count(r.reviews_id) as reviews_total, 
                                               max(r.reviews_rating) as max_reviews
                                      from :table_reviews r,
                                           :table_reviews_description rd
                                      where r.products_id = :products_id
                                      and rd.languages_id = :languages_id
                                      and r.reviews_id = rd.reviews_id
                                      and r.status = 1
                                      and r.customers_group_id > 0
                                      ');
        $Qcheck->bindInt(':products_id', $products_id);
        $Qcheck->bindInt(':languages_id', $this->lang->getId());
        $Qcheck->execute();
      }
    }

    if ($Qcheck->valueInt('reviews_total') > 0) {
      $max = $Qcheck->valueInt('max_reviews');
    } else {
      $max = 1;
    }

    return $max;
  }

  /**
   * Retrieves the name of the author for the given product ID.
   *
   * @param int $products_id The ID of the product to retrieve the author's name for.
   * @return string The formatted name of the author. Returns an empty string if no author is found.
   */
  public function getAuthor(int $products_id): string
  {
    $Qauthor = $this->db->prepare('select r.customers_name
                                    from :table_reviews r
                                    where r.products_id = :products_id
                                    and r.status = 1
                                    limit 1
                                   ');
    $Qauthor->bindInt(':products_id', $products_id);
    $Qauthor->execute();

    if (!empty($Qauthor->value('customers_name'))) {
      $author = '*** ' . HTML::outputProtected(substr(Hash::displayDecryptedDataText($Qauthor->value('customers_name')) . ' ', 4, -4)) . ' ***';
    } else {
      $author = '';
    }

    return $author;
  }

  /**
   * Gets the count of reviews for a specific product.
   *
   * @param int $products_id The ID of the product for which the reviews count is retrieved.
   * @return int The total count of active reviews for the specified product. Returns 1 if no reviews are found.
   */
  public function getCount(int $products_id): int
  {
    $Qcount = $this->db->prepare('select count(r.reviews_id) as reviews_total
                                    from :table_reviews r
                                    where r.products_id = :products_id
                                    and r.status = 1
                                   ');
    $Qcount->bindInt(':products_id', $products_id);
    $Qcount->execute();

    if ($Qcount->valueInt('reviews_total')) {
      $count = $Qcount->valueInt('reviews_total');
    } else {
      $count = 1;
    }

    return $count;
  }
}