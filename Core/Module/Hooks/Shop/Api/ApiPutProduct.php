<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\OM\Module\Hooks\Shop\Api;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Configuration\Api\Classes\Shop\ApiSecurity;

class ApiPutProduct
{
  /**
   * Met à jour un produit avec ses informations et ses relations.
   *
   * @param array $productData Les données du produit à mettre à jour
   * @return array|false Retourne les données mises à jour ou false en cas d'erreur
   */
  private static function updateProduct(array $productData): array|false
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    try {
      $products_id = (int)$productData['products_id'];

      // Mise à jour de la table products
      $productFields = [
        'products_model' => $productData['products_model'] ?? null,
        'products_quantity' => isset($productData['products_quantity']) ? (int)$productData['products_quantity'] : null,
        'products_weight' => isset($productData['products_weight']) ? (float)$productData['products_weight'] : null,
        'products_quantity_alert' => isset($productData['products_quantity_alert']) ? (int)$productData['products_quantity_alert'] : null,
        'products_sku' => $productData['products_sku'] ?? null,
        'products_upc' => $productData['products_upc'] ?? null,
        'products_ean' => $productData['products_ean'] ?? null,
        'products_jan' => $productData['products_jan'] ?? null,
        'products_isbn' => $productData['products_isbn'] ?? null,
        'products_mpn' => $productData['products_mpn'] ?? null,
        'products_price' => isset($productData['products_price']) ? (float)$productData['products_price'] : null,
        'products_dimension_width' => isset($productData['products_dimension_width']) ? (float)$productData['products_dimension_width'] : null,
        'products_dimension_height' => isset($productData['products_dimension_height']) ? (float)$productData['products_dimension_height'] : null,
        'products_dimension_depth' => isset($productData['products_dimension_depth']) ? (float)$productData['products_dimension_depth'] : null,
        'products_volume' => isset($productData['products_volume']) ? (float)$productData['products_volume'] : null,
      ];

      $updateFields = [];
      $updateParams = [];

      foreach ($productFields as $field => $value) {
        if ($value !== null) {
          $updateFields[] = $field . ' = :' . $field;
          $updateParams[':' . $field] = $value;
        }
      }

      if (!empty($updateFields)) {
        // products_last_modified must use the SQL NOW() function, not a bound
        // parameter (a bound 'now()' is stored as the literal string).
        $sql = 'UPDATE :table_products SET ' . implode(', ', $updateFields) . ', products_last_modified = now() WHERE products_id = :products_id';
        $updateParams[':products_id'] = $products_id;

        $Qupdate = $CLICSHOPPING_Db->prepare($sql);
        foreach ($updateParams as $key => $value) {
          if (is_int($value)) {
            $Qupdate->bindInt($key, $value);
          } elseif (is_float($value)) {
            $Qupdate->bindValue($key, $value);
          } else {
            $Qupdate->bindValue($key, $value);
          }
        }
        $Qupdate->execute();
      }

      // Mise à jour de la description du produit
      if (isset($productData['products_name']) || isset($productData['products_description'])) {
        $language_id = isset($productData['language_id']) ? (int)$productData['language_id'] : 1;

        $descFields = [];
        $descParams = [];

        if (isset($productData['products_name'])) {
          $descFields[] = 'products_name = :products_name';
          $descParams[':products_name'] = $productData['products_name'];
        }

        if (isset($productData['products_description'])) {
          $descFields[] = 'products_description = :products_description';
          $descParams[':products_description'] = $productData['products_description'];
        }

        if (!empty($descFields)) {
          $sql = 'UPDATE :table_products_description SET ' . implode(', ', $descFields) .
            ' WHERE products_id = :products_id AND language_id = :language_id';
          $descParams[':products_id'] = $products_id;
          $descParams[':language_id'] = $language_id;

          $Qdesc = $CLICSHOPPING_Db->prepare($sql);
          foreach ($descParams as $key => $value) {
            if (is_int($value)) {
              $Qdesc->bindInt($key, $value);
            } else {
              $Qdesc->bindValue($key, $value);
            }
          }
          $Qdesc->execute();
        }
      }

      // Gestion des catégories (products_to_categories)
      if (isset($productData['categories'])) {
        // Valider et filtrer les catégories existantes
        $validCategories = [];
        if (is_array($productData['categories']) && !empty($productData['categories'])) {
          foreach ($productData['categories'] as $category_id) {
            $category_id = (int)$category_id;
            if ($category_id > 0) {
              // Vérifier que la catégorie existe
              $QcheckCategory = $CLICSHOPPING_Db->prepare('SELECT COUNT(*) as count FROM :table_categories WHERE categories_id = :categories_id');
              $QcheckCategory->bindInt(':categories_id', $category_id);
              $QcheckCategory->execute();

              if ($QcheckCategory->valueInt('count') > 0) {
                $validCategories[] = $category_id;
              } else {
                // Log pour catégorie inexistante
                ApiSecurity::logSecurityEvent('Category not found during product update', [
                  'categories_id' => $category_id,
                  'products_id' => $products_id
                ]);
              }
            }
          }
        }

        // Replace semantics: when the request provides a "categories" array we
        // clear the current relations and re-insert the validated ones. The DELETE
        // is no longer gated on a non-empty set, so an explicit empty/whitelisted-out
        // list correctly detaches the product (every removed id is logged above).
        $QdeleteCategories = $CLICSHOPPING_Db->prepare('DELETE FROM :table_products_to_categories WHERE products_id = :products_id');
        $QdeleteCategories->bindInt(':products_id', $products_id);
        $QdeleteCategories->execute();

        foreach ($validCategories as $category_id) {
          $QinsertCategory = $CLICSHOPPING_Db->prepare('INSERT INTO :table_products_to_categories (products_id, categories_id) VALUES (:products_id, :categories_id)');
          $QinsertCategory->bindInt(':products_id', $products_id);
          $QinsertCategory->bindInt(':categories_id', $category_id);
          $QinsertCategory->execute();
        }
      }

      // Recompute per-customer-group prices only when the base price is part of this
      // request. Previously this ran on every PUT and overwrote group prices even for
      // a name/description/category-only update.
      if (isset($productData['products_price'])) {
        self::updateProductGroups($products_id);
      }

      // Retourner les données mises à jour
      return self::getUpdatedProduct($products_id, $productData['language_id'] ?? 1);

    } catch (\Exception $e) {
      ApiSecurity::logSecurityEvent('Product update error', [
        'error' => $e->getMessage(),
        'products_id' => $products_id ?? null
      ]);

      return false;
    }
  }

  /**
   * Met à jour les groupes de produits (inspiré de UpdateAllPrice.php)
   *
   * @param int $products_id L'ID du produit
   */
  private static function updateProductGroups(int $products_id): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    try {
      // Récupérer le prix de base du produit
      $QproductPrice = $CLICSHOPPING_Db->prepare('SELECT products_price FROM :table_products WHERE products_id = :products_id');
      $QproductPrice->bindInt(':products_id', $products_id);
      $QproductPrice->execute();

      if ($QproductPrice->rowCount() > 0) {
        $basePrice = (float)$QproductPrice->value('products_price');

        // Récupérer tous les groupes clients
        $QcustomersGroups = $CLICSHOPPING_Db->prepare('SELECT customers_group_id, customers_group_discount FROM :table_customers_groups');
        $QcustomersGroups->execute();

        while ($QcustomersGroups->fetch()) {
          $group_id = (int)$QcustomersGroups->valueInt('customers_group_id');
          $group_discount = (float)$QcustomersGroups->value('customers_group_discount');

          // Calculer le prix avec remise
          $discountedPrice = $basePrice;
          if ($group_discount > 0) {
            $discountedPrice = $basePrice * (1 - ($group_discount / 100));
          }

          // Vérifier si l'entrée existe déjà
          $QcheckGroup = $CLICSHOPPING_Db->prepare('SELECT COUNT(*) as count FROM :table_products_groups WHERE products_id = :products_id AND customers_group_id = :customers_group_id');
          $QcheckGroup->bindInt(':products_id', $products_id);
          $QcheckGroup->bindInt(':customers_group_id', $group_id);
          $QcheckGroup->execute();

          if ($QcheckGroup->valueInt('count') > 0) {
            // Mettre à jour l'entrée existante
            $QupdateGroup = $CLICSHOPPING_Db->prepare('UPDATE :table_products_groups SET products_price = :products_price, last_modified = now() WHERE products_id = :products_id AND customers_group_id = :customers_group_id');
            $QupdateGroup->bindValue(':products_price', $discountedPrice);
            $QupdateGroup->bindInt(':products_id', $products_id);
            $QupdateGroup->bindInt(':customers_group_id', $group_id);
            $QupdateGroup->execute();
          } else {
            // Insérer une nouvelle entrée
            $QinsertGroup = $CLICSHOPPING_Db->prepare('INSERT INTO :table_products_groups (products_id, customers_group_id, products_price, date_added) VALUES (:products_id, :customers_group_id, :products_price, now())');
            $QinsertGroup->bindInt(':products_id', $products_id);
            $QinsertGroup->bindInt(':customers_group_id', $group_id);
            $QinsertGroup->bindValue(':products_price', $discountedPrice);
            $QinsertGroup->execute();
          }
        }
      }
    } catch (\Exception $e) {
      ApiSecurity::logSecurityEvent('Product groups update error', [
        'error' => $e->getMessage(),
        'products_id' => $products_id
      ]);
    }
  }

  /**
   * Récupère les données mises à jour du produit
   *
   * @param int $products_id L'ID du produit
   * @param int $language_id L'ID de la langue
   * @return array Les données du produit mises à jour
   */
  private static function getUpdatedProduct(int $products_id, int $language_id): array
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $sql = 'SELECT p.*, pd.products_name, pd.products_description, pd.language_id
            FROM :table_products p
            JOIN :table_products_description pd ON p.products_id = pd.products_id
            WHERE p.products_id = :products_id AND pd.language_id = :language_id';

    $Qproduct = $CLICSHOPPING_Db->prepare($sql);
    $Qproduct->bindInt(':products_id', $products_id);
    $Qproduct->bindInt(':language_id', $language_id);
    $Qproduct->execute();

    if ($Qproduct->rowCount() > 0) {
      $row = $Qproduct->fetch();

      // Récupérer les catégories associées
      $QgetCategories = $CLICSHOPPING_Db->prepare('SELECT categories_id FROM :table_products_to_categories WHERE products_id = :products_id');
      $QgetCategories->bindInt(':products_id', $products_id);
      $QgetCategories->execute();

      $categories = [];
      while ($QgetCategories->fetch()) {
        $categories[] = (int)$QgetCategories->valueInt('categories_id');
      }

      return [
        'products_id' => (int)$row['products_id'],
        'language_id' => (int)$row['language_id'],
        'products_name' => $row['products_name'],
        'products_description' => $row['products_description'],
        'products_model' => $row['products_model'],
        'products_quantity' => (int)$row['products_quantity'],
        'products_weight' => (float)$row['products_weight'],
        'products_quantity_alert' => (int)$row['products_quantity_alert'],
        'products_sku' => $row['products_sku'],
        'products_upc' => $row['products_upc'],
        'products_ean' => $row['products_ean'],
        'products_jan' => $row['products_jan'],
        'products_isbn' => $row['products_isbn'],
        'products_mpn' => $row['products_mpn'],
        'products_price' => (float)$row['products_price'],
        'products_dimension_width' => (float)$row['products_dimension_width'],
        'products_dimension_height' => (float)$row['products_dimension_height'],
        'products_dimension_depth' => (float)$row['products_dimension_depth'],
        'products_volume' => (float)$row['products_volume'],
        'categories' => $categories,
        'last_modified' => $row['products_last_modified']
      ];
    }

    return [];
  }

  /**
   * Valide les données du produit
   *
   * @param array $data Les données à valider
   * @return array Les erreurs de validation
   */
  private static function validateProductData(array $data): array
  {
    $errors = [];

    // Validation de l'ID du produit
    if (!isset($data['products_id']) || !is_numeric($data['products_id']) || (int)$data['products_id'] <= 0) {
      $errors[] = 'products_id is required and must be a positive integer';
    }

    // Validation des champs numériques
    $numericFields = ['products_quantity', 'products_weight', 'products_quantity_alert', 'products_price',
      'products_dimension_width', 'products_dimension_height', 'products_dimension_depth', 'products_volume'];

    foreach ($numericFields as $field) {
      if (isset($data[$field]) && !is_numeric($data[$field])) {
        $errors[] = $field . ' must be numeric';
      }
    }

    // Validation des catégories
    if (isset($data['categories']) && !is_array($data['categories'])) {
      $errors[] = 'categories must be an array';
    }

    return $errors;
  }

  /**
   * Exécute l'appel API pour mettre à jour un produit.
   *
   * @return array|false Un tableau des données du produit mis à jour ou false si les paramètres sont manquants.
   */
  public function execute()
  {
    // Centralised authentication: token resolution (header or legacy query/body),
    // rotation-aware validation and per-action rate limiting in one call.
    if (ApiSecurity::authenticateRequest('put_product') === false) {
      return false;
    }

    // Récupération des données JSON du corps de la requête
    $input = file_get_contents('php://input');
    $productData = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
      ApiSecurity::logSecurityEvent('Invalid JSON in product PUT request', ['json_error' => json_last_error_msg()]);
      return false;
    }

    // A valid-but-scalar body ("null", "5", "\"x\"") decodes without a JSON error
    // yet is not an object; guard before it reaches the array-typed helpers.
    if (!is_array($productData)) {
      ApiSecurity::logSecurityEvent('Malformed payload in product PUT request (expected a JSON object)');
      return false;
    }

    // Sanitisation des données
    if (isset($productData['products_id'])) {
      $productData['products_id'] = HTML::sanitize($productData['products_id']);
      ApiSecurity::secureGetId($productData['products_id']);
    }

    // Validation des données
    $validationErrors = self::validateProductData($productData);
    if (!empty($validationErrors)) {
      ApiSecurity::logSecurityEvent('Validation errors in product PUT request', ['errors' => $validationErrors]);
      return ['errors' => $validationErrors];
    }

    // Vérification que le produit existe
    $CLICSHOPPING_Db = Registry::get('Db');
    $QcheckProduct = $CLICSHOPPING_Db->prepare('SELECT COUNT(*) as count FROM :table_products WHERE products_id = :products_id');
    $QcheckProduct->bindInt(':products_id', (int)$productData['products_id']);
    $QcheckProduct->execute();

    if ($QcheckProduct->valueInt('count') === 0) {
      ApiSecurity::logSecurityEvent('Product not found in PUT request', ['products_id' => $productData['products_id']]);
      return ['error' => 'Product not found'];
    }

    // Mise à jour du produit
    $result = self::updateProduct($productData);

    if ($result !== false) {
      ApiSecurity::logSecurityEvent('Product updated successfully', ['products_id' => $productData['products_id']]);
    }

    return $result;
  }
}