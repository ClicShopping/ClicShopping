<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Reviews\Classes\ClicShoppingAdmin;

use ClicShopping\OM\Registry;
/**
 * Class ReviewsAdmin
 *
 * Provides administrative functionalities for managing customer reviews,
 * including counting tags, setting review statuses, managing sentiments,
 * and retrieving vote statistics.
 */
class ReviewsAdmin
{
  /**
   * Counts the total number of customer tags by retrieving and processing tag data
   * from the reviews table in the database.
   *
   * @return int The total count of customer tags.
   */
  public static function countCustomersTags(): int
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qreviews = $CLICSHOPPING_Db->prepare('select customers_tag
                                               from :table_reviews
                                              ');
    $Qreviews->execute();

    $reviews_array = $Qreviews->fetchAll();
    $total = 0;

    foreach ($reviews_array as $value) {
      $customer_tag_array = explode(',', $value['customers_tag']);
      $total += count($customer_tag_array);
    }

    return $total;
  }

  /**
   * Checks if the count of customer tags exceeds a predefined limit.
   *
   * @return bool Returns true if the number of customer tags is greater than 300, otherwise false.
   */
  public static function CountTagCountWarning(): bool
  {
    if (self::countCustomersTags() > 300) {
      return true;
    } else {
      return false;
    }
  }

  /**
   * Updates the status of a review in the database based on the given ID and status.
   *
   * @param int $id The ID of the review to be updated.
   * @param int|null $status The new status to set for the review (1 for active, 0 for inactive).
   *                          If null or an unexpected value is provided, the method returns -1.
   *
   * @return bool|int Returns true if the status was successfully updated, false on failure,
   *                  or -1 if the provided status is invalid.
   */
  public static function getReviewsStatus(int $id,  int|null $status)
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    if ($status == 1) {
      return $CLICSHOPPING_Db->save('reviews', [
        'status' => 1,
        'last_modified' => 'now()'
      ],
        ['reviews_id' => (int)$id]
      );

    } elseif ($status == 0) {
      return $CLICSHOPPING_Db->save('reviews', ['status' => 0,
        'last_modified' => 'now()'
      ],
        ['reviews_id' => (int)$id]
      );

    } else {
      return -1;
    }
  }

  /**
   * Updates the sentiment approval status of a review based on the provided status.
   *
   * @param int $id The ID of the review to update.
   * @param int|null $status The status to set for sentiment approval. Use 1 for approval, 0 for disapproval, or null for an invalid status.
   * @return bool|int Returns true if the update was successful, false on failure, or -1 if the status was invalid.
   */
  public static function getReviewsSentimentApprovedStatus(int $id,  int|null $status)
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    if ($status == 1) {
      return $CLICSHOPPING_Db->save('reviews_sentiment', [
        'sentiment_approved' => 1,
        'date_modified' => 'now()'
      ],
        ['reviews_id' => (int)$id]
      );

    } elseif ($status == 0) {
      return $CLICSHOPPING_Db->save('reviews_sentiment', [
        'sentiment_approved' => 0,
        'date_modified' => 'now()'
      ],
        ['reviews_id' => (int)$id]
      );

    } else {
      return -1;
    }
  }

  /**
   * Retrieves the sentiment description based on the given sentiment ID and language ID.
   *
   * @param int $id The unique identifier of the sentiment.
   * @param int $language_id The unique identifier of the language. If not provided, the default language ID will be used.
   * @return string The sentiment description corresponding to the specified ID and language.
   */
  public static function getSentimentDescription(int $id, int $language_id): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    if (!$language_id) $language_id = $CLICSHOPPING_Language->getId();

    $Qdescription = $CLICSHOPPING_Db->prepare('select description
                                            from :table_reviews_sentiment_description
                                            where id = :id
                                            and language_id = :language_id
                                          ');

    $Qdescription->bindInt(':id', $id);
    $Qdescription->bindInt(':language_id', $language_id);

    $Qdescription->execute();

    return $Qdescription->value('description');
  }

  /**
   * Retrieves the total count of "yes" votes from the reviews vote table.
   *
   * @return int The total number of "yes" votes.
   */
  public static function getTotalReviewsVoteYes(): int
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qvote = $CLICSHOPPING_Db->prepare('select count(vote) as vote_yes
                                        from :table_reviews_vote
                                        where vote = 1
                                        and reviews_id <> 0
                                        ');

    $Qvote->execute();

    return $Qvote->valueInt('vote_yes');
  }

  /**
   * Retrieves the total number of reviews with a "No" vote.
   *
   * @return int The total count of "No" votes in the reviews.
   */
  public static function getTotalReviewsVoteNo(): int
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qvote = $CLICSHOPPING_Db->prepare('select count(vote) as vote_no
                                            from :table_reviews_vote
                                            where vote = 0
                                            and reviews_id <> 0
                                        ');

    $Qvote->execute();

    return $Qvote->valueInt('vote_no');
  }


  /**
   * Counts the total number of positive sentiment votes (vote_yes) for a specified product.
   *
   * @param int $products_id The ID of the product for which the positive sentiment votes will be counted.
   * @return int The total count of positive sentiment votes (vote_yes) for the specified product.
   */
  public static function getTotalReviewsSentimentVoteYes(int $products_id): int
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qvote = $CLICSHOPPING_Db->prepare('select count(vote) as vote_yes
                                        from :table_reviews_vote
                                        where sentiment = 1
                                        and reviews_id <> 0
                                        and products_id = :products_id
                                        ');

    $Qvote->bindInt(':products_id', $products_id);
    $Qvote->execute();

    return $Qvote->valueInt('vote_yes');
  }

  /**
   * Retrieves the total count of negative sentiment votes for a specific product.
   *
   * @param int $products_id The ID of the product for which the negative sentiment vote count is retrieved.
   * @return int The total number of negative sentiment votes for the specified product.
   */
  public static function getTotalReviewsSentimentVoteNo(int $products_id): int
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qvote = $CLICSHOPPING_Db->prepare('select count(vote) as vote_no
                                        from :table_reviews_vote
                                        where sentiment = 0
                                        and reviews_id  <> 0
                                        and products_id = :products_id
                                      ');
    $Qvote->bindInt(':products_id', $products_id);
    $Qvote->execute();

    return $Qvote->valueInt('vote_no');
  }
}