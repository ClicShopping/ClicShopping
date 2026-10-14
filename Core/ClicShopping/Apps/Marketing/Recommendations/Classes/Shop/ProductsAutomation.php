<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Recommendations\Classes\Shop;

use ClicShopping\OM\Registry;

class ProductsAutomation
{
  /**
   * Retrieves the average score for a specified product based on its recommendations.
   *
   * @return mixed The average score of the product, or null if no score is available.
   */
  private static function getProductAverageScore(): mixed
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');

    $QmostRecommended = $CLICSHOPPING_Db->prepare('SELECT pr.products_id, 
                                                          avg(pr.score) as average_score
                                                    FROM :table_products_recommendations pr
                                                    WHERE pr.products_id = :products_id
                                                    GROUP BY pr.products_id
                                                   ');

    $QmostRecommended->bindDecimal(':products_id', $CLICSHOPPING_ProductsCommon->getID());

    $QmostRecommended->execute();

    return $QmostRecommended->valueDecimal('average_score');
  }

  //********************************************
  // Favorites Automation
  //********************************************

  /**
   * Evaluates the product's average score and updates its favorite status accordingly.
   *
   * If the average score exceeds the defined minimum score, the product is added to favorites.
   * If the average score is below 0, the product is removed from favorites.
   * If none of the conditions are met, the method returns -1.
   *
   * @return int|null Returns the favorite ID if the product is added to favorites,
   *                  null if the product is removed, or -1 if no action is performed.
   */
  public static function favorites():  int|null
  {
    $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');

    $avg_core = self::getProductAverageScore();

    if ($avg_core > (float)CLICSHOPPING_APP_RECOMMENDATIONS_PR_FAVORITES_MIN_SCORE) {
      return self::createFavorites($CLICSHOPPING_ProductsCommon->getID());
    } elseif ($avg_core < 0)  {
      return self::deleteFavorites($CLICSHOPPING_ProductsCommon->getID());
    } else {
      return -1;
    }
  }

  /**
   * Creates a new favorite entry for a given product ID if it does not already exist.
   *
   * @param int $id The product ID to add to the favorites.
   * @return bool Returns true if the favorite entry was created successfully, false if the entry already exists or the operation failed.
   */
  private static function createFavorites(int $id): bool
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qresult = $CLICSHOPPING_Db->get('products_favorites', 'products_id', ['products_id' => $id]);

    if ($Qresult->fetch() === false) {
      $sql_array = [
        'products_id' => $id,
        'products_favorites_date_added' => 'now()',
        'scheduled_date' => null,
        'expires_date' => null,
        'status' => 1
      ];

      $result = $CLICSHOPPING_Db->save('products_favorites', $sql_array);

      return $result !== false; // Return true for success, false for failure
    }

    return false;
  }

  /**
   * Deletes a favorite product from the products_favorites table based on the provided product ID.
   *
   * @param int $id The ID of the product to be removed from favorites.
   * @return bool Returns true if the product was successfully deleted, or false if the deletion failed or the product ID was not found.
   */
  private static function deleteFavorites(int $id): bool
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qresult = $CLICSHOPPING_Db->get('products_favorites', ['products_id' => $id]);

    if ($Qresult->fetch() === true) {
      $sql_array = [
        'products_id' => $id,
      ];

      $result = $CLICSHOPPING_Db->delete('products_favorites', $sql_array);

      return $result !== false; // Return true for success, false for failure
    }

    return false;
  }


  //********************************************
  // featured Automation
  //********************************************

  /**
   * Determines the status of a product based on its average score and updates its featured status accordingly.
   *
   * @return int|null Returns the result of creating or deleting the featured status, or -1 if no action is taken.
   */
  public static function featured():  int|null
  {
    $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');

    $avg_core = self::getProductAverageScore();

    if ($avg_core > (float)CLICSHOPPING_APP_RECOMMENDATIONS_PR_FEATURED_MIN_SCORE) {
      return self::createFeatured($CLICSHOPPING_ProductsCommon->getID());
    } elseif ($avg_core < 0)  {
      return self::deleteFeatured($CLICSHOPPING_ProductsCommon->getID());
    } else {
      return -1;
    }
  }

  /**
   * Creates a featured product entry in the database if it does not already exist.
   *
   * @param int $id The product ID to be marked as featured.
   *
   * @return bool Returns true if the featured product was successfully created,
   *              or false if the product was already featured or the operation failed.
   */
  private static function createFeatured(int $id): bool
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qresult = $CLICSHOPPING_Db->get('products_featured', 'products_id', ['products_id' => $id]);

    if ($Qresult->fetch() === false) {
      $sql_array = [
        'products_id' => $id,
        'products_featured_date_added' => 'now()',
        'scheduled_date' => null,
        'expires_date' => null,
        'status' => 1
      ];

      $result = $CLICSHOPPING_Db->save('products_featured', $sql_array);

      return $result !== false; // Return true for success, false for failure
    }

    return false;
  }

  /**
   * Deletes a featured product from the database based on the given product ID.
   *
   * @param int $id The ID of the product to delete from the featured products list.
   * @return bool Returns true if the deletion was successful, false otherwise.
   */
  private static function deleteFeatured(int $id): bool
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qresult = $CLICSHOPPING_Db->get('products_featured', ['products_id' => $id]);

    if ($Qresult->fetch() === true) {
      $sql_array = [
        'products_id' => $id,
      ];

      $result = $CLICSHOPPING_Db->delete('products_featured', $sql_array);

      return $result !== false; // Return true for success, false for failure
    }

    return false;
  }
}