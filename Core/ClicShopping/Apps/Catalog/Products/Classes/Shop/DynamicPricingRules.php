<?php
  /**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  namespace ClicShopping\Apps\Catalog\Products\Classes\Shop;

  use ClicShopping\OM\Cache;
  use ClicShopping\OM\Registry;
  use InvalidArgumentException;

  /**
   * Class DynamicPricingRules
   *
   * This class applies dynamic pricing rules to products based on stock levels and sales data.
   * It adjusts the product price according to predefined business rules, such as increasing the price
   * when stock is low and sales are high, or decreasing the price for clearance when stock is high and sales are low.
   * All price changes are logged for historical tracking.
   */
  class DynamicPricingRules {
    private const ALLOWED_OPERATORS = ['>', '<', '>=', '<=', '==', '!='];
    private const ALLOWED_LOGICAL_OPERATORS = ['AND', 'OR'];
    private const ALLOWED_VARIABLES = ['stock', 'sales'];
    private const MAX_CONDITION_LENGTH = 500;
    private const MAX_CLAUSES = 10;
    protected mixed $db;

    /**
     * Constructor.
     *
     * Initializes the DynamicPricingRules class and retrieves the database object from the Registry.
     */
    public function __construct() {
      $this->db = Registry::get('Db');
    }

    /**
     * Applies dynamic pricing rules to a product.
     *
     * Adjusts the product price based on stock and sales data using predefined business rules.
     *
     * @param int $product_id The product ID.
     * @param float $base_price The base price of the product.
     * @return float The final price after applying dynamic rules.
     * @param int $customer_group_id The customer group ID for rule application (default is 0).
     */
    public function apply(int $product_id, float $base_price, int $customer_group_id = 0): mixed
    {
      $cache_key = 'dynamic_pricing_rules';
      $cache = new Cache($cache_key);
// Try to get rules from cache
      $cached_rules = null;
      $cached_rules = $cache->get();

      if (!is_array($cached_rules)) {
        $cached_rules = null;
      }

      $stock = $this->getStock($product_id);
      $sales = $this->getSalesLast30Days($product_id);
      $isOnPromotion = $this->isProductOnPromotion($product_id);
      $isDynamicPromotion = $this->isProductOnDynamicPromotion($product_id);
      $promotion_applied = false;

      if ($cached_rules === null) {
        // If not in cache, fetch from database
        $Qrules = $this->db->prepare('SELECT rules_id,
                                           rules_name,
                                           rules_condition,
                                           rules_type,
                                           rules_value,
                                           rules_priority,
                                           rules_status,
                                           rules_status_special,
                                           rules_status_promotion,
                                           customers_group
                                     FROM :table_dynamic_pricing_rules
                                     WHERE rules_status = 1
                                     ORDER BY rules_priority ASC
                                  ');
        $Qrules->execute();
        $cached_rules = $Qrules->fetchAll();

        // Save the result to cache for future use
        $cache->save($cached_rules);
      }

      $finalPrice = $base_price;

      foreach ($cached_rules as $rule) {
        $rule_id = $rule['rules_id'];
        $condition = $rule['rules_condition'];
        $ruleName = $rule['rules_name'];
        $ruleType = $rule['rules_type'];
        $ruleValue = (float)$rule['rules_value'];
        $status_special = (int)$rule['rules_status_special'];
        $status_promotion = (int)$rule['rules_status_promotion'];
        $rule_customer_group = (int)$rule['customers_group'];

        $variables = [
          'stock' => $stock,
          'sales' => $sales
        ];

        if (!$this->evaluate($condition, $variables)) {
          continue;
        }

        // Règle matchée mais ignorée (promotion en cours protégée, ou mauvais groupe client)
        if (($isOnPromotion && $status_special == 0) || ($rule_customer_group > 0 && $rule_customer_group != $customer_group_id)) {
          continue;
        }

        // Calcul du prix final selon le type de règle
        switch ($ruleType) {
          case 'percentage_decrease':
            $finalPrice = $base_price * (1 - $ruleValue / 100);
            break;
          case 'percentage_increase':
            $finalPrice = $base_price * (1 + $ruleValue / 100);
            break;
          case 'fixed_price':
            $finalPrice = $ruleValue;
            break;
          default:
            $finalPrice = $base_price;
        }

        $this->logVariation($rule_id, $product_id, $base_price, $finalPrice, $ruleName);

        // Gestion de la promotion catalogue (specials)
        if ($status_promotion == 1) {
          $promotion_applied = true;

          if ($isDynamicPromotion === false && $isOnPromotion === false) {
            // Aucune promotion existante : on crée une promotion dynamique
            $insert_array = [
              'products_id' => (int)$product_id,
              'specials_new_products_price' => (float)$finalPrice,
              'specials_date_added' => 'now()',
              'status' => 1,
              'customers_group_id' => 0,
              'source' => 'dynamic'
            ];
            $this->db->save('specials', $insert_array);
          } elseif ($isDynamicPromotion === true) {
            // La promotion existante est dynamique : on la met à jour
            $update_array = [
              'specials_new_products_price' => (float)$finalPrice,
              'specials_last_modified' => 'now()',
              'status' => 1
            ];
            $this->db->save('specials', $update_array, [
              'products_id' => (int)$product_id,
              'customers_group_id' => 0,
              'source' => 'dynamic'
            ]);
          }
          // Si $isOnPromotion === true mais $isDynamicPromotion === false :
          // la promotion est manuelle → on ne la touche pas
        }

        // On prend la première règle qui matche (priorité ASC), on sort de la boucle
        break;
      }

      // Supprime la promotion dynamique uniquement si aucune règle ne l'a reconduite.
      // Les promotions manuelles (source != 'dynamic') ne sont jamais supprimées.
      $this->deleteSpecials($product_id, $promotion_applied, $isDynamicPromotion);

      return $finalPrice;
    }

    /**
     * Retrieves the product stock quantity with proper error handling.
     *
     * @param int $product_id The product ID.
     * @return int The quantity in stock.
     */
    private function getStock(int $product_id): int
    {
      $Qstock = $this->db->prepare('SELECT products_quantity 
                                  FROM :table_products 
                                  WHERE products_id = :pid
                                  LIMIT 1');

      $Qstock->bindInt(':pid', $product_id);
      $Qstock->execute();

      // Retourne la quantité de stock. Si le produit n'existe pas ou la quantité est NULL,
      // valueInt() retourne 0 par défaut, ce qui est le comportement souhaité.
      return $Qstock->valueInt('products_quantity') ?? 0;
    }

    /*
    * Delete the special.
    *
    * @param int $product_id The product ID.
    * @return void The quantity in stock.
    */

    /**
     * Retrieves the number of sales for the last 30 days.
     *
     * @param int $product_id The product ID.
     * @return int|string The number of sales in the last 30 days.
     */
    private function getSalesLast30Days(int $product_id): int|string
    {
      $Qsales = $this->db->prepare('SELECT SUM(op.products_quantity) as total
                                  FROM :table_orders_products op
                                  JOIN :table_orders o ON o.orders_id = op.orders_id
                                  WHERE op.products_id = :pid
                                  AND o.date_purchased >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                                  ');
      $Qsales->bindInt(':pid', $product_id);
      $Qsales->execute();

      return $Qsales->valueDecimal('total') ?? 0;
    }

    /**
     * Checks if a product is currently on a dynamically-generated promotion (created by this engine).
     * Manual promotions (source != 'dynamic') are excluded so they are never auto-deleted.
     *
     * @param int $products_id The product ID.
     * @return bool True if a dynamic promotion exists, false otherwise.
     */
    public function isProductOnDynamicPromotion(int $products_id): bool
    {
      $Qpromo = $this->db->prepare('SELECT COUNT(*) AS total
                                  FROM :table_specials s
                                  INNER JOIN :table_dynamic_pricing_history h
                                    ON h.products_id = s.products_id
                                    AND h.dynamic_price = s.specials_new_products_price
                                  WHERE s.products_id = :products_id
                                  AND s.status = 1
                                  AND s.customers_group_id = 0
                                  AND (s.expires_date IS NULL OR s.expires_date > NOW())
                                  AND (s.scheduled_date IS NULL OR s.scheduled_date <= NOW())
                                 ');
      $Qpromo->bindInt(':products_id', $products_id);
      $Qpromo->execute();

      return ($Qpromo->valueInt('total') > 0);
    }

    /**
     * Checks if a product is currently on promotion.
     *
     * @param int $products_id The product ID.
     * @return bool True if the product is on promotion, false otherwise.
     */
    public function isProductOnPromotion(int $products_id): bool
    {
      $Qpromo = $this->db->prepare('SELECT COUNT(*) AS total
                                  FROM :table_specials
                                  WHERE products_id = :products_id
                                  AND status = 1
                                  AND (expires_date IS NULL OR expires_date > NOW())
                                  AND (scheduled_date IS NULL OR scheduled_date <= NOW())
                                 ');
      $Qpromo->bindInt(':products_id', $products_id);
      $Qpromo->execute();

      return ($Qpromo->valueInt('total') > 0);
    }

    /**
     * Evaluate a condition string with given variables (version sécurisée).
     *
     * @param string $condition The condition string (e.g., "stock < 10 AND sales > 50").
     * @param array $variables An associative array of variable names and their values.
     * @return bool True if the condition is met, false otherwise.
     */
    public function evaluate(string $condition, array $variables): bool
    {
      // Validation préliminaire
      $this->validateCondition($condition);
      $this->validateVariables($variables);

      $condition = trim($condition);

      // Cas simple : une seule clause
      if (!$this->containsLogicalOperators($condition)) {
        return $this->evaluateClause($condition, $variables);
      }

      // Cas complexe : clauses multiples avec opérateurs logiques
      return $this->evaluateComplexCondition($condition, $variables);
    }

    /**
     * Validate the condition string for safety and correctness.
     *
     * @param string $condition The condition string to validate.
     * @throws InvalidArgumentException If the condition is invalid.
     */
    private function validateCondition(string $condition): void
    {
      if (empty(trim($condition))) {
        throw new InvalidArgumentException('Condition cannot be empty');
      }

      if (strlen($condition) > self::MAX_CONDITION_LENGTH) {
        throw new InvalidArgumentException('Condition too long');
      }

      // Vérifier les caractères dangereux
      if (preg_match('/[;(){}[\]`$\\\\]/', $condition)) {
        throw new InvalidArgumentException('Condition contains forbidden characters');
      }

      // Vérifier la présence de mots-clés SQL dangereux
      $forbiddenKeywords = ['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'DROP', 'UNION', 'EXEC'];
      foreach ($forbiddenKeywords as $keyword) {
        if (stripos($condition, $keyword) !== false) {
          throw new InvalidArgumentException('Condition contains forbidden SQL keywords');
        }
      }
    }

    /**
     * Validate the provided variables.
     *
     * @param array $variables An associative array of variable names and their values.
     * @throws InvalidArgumentException If any variable is invalid.
     */
    private function validateVariables(array $variables): void
    {
      foreach ($variables as $name => $value) {
        if (!in_array($name, self::ALLOWED_VARIABLES, true)) {
          throw new InvalidArgumentException("Variable '{$name}' is not allowed");
        }

        if (!is_numeric($value)) {
          throw new InvalidArgumentException("Variable '{$name}' must be numeric");
        }
      }
    }

    /**
     * Check if the condition contains logical operators.
     *
     * @param string $condition The condition string.
     * @return bool True if logical operators are present, false otherwise.
     */
    private function containsLogicalOperators(string $condition): bool
    {
      foreach (self::ALLOWED_LOGICAL_OPERATORS as $operator) {
        if (stripos($condition, " {$operator} ") !== false) {
          return true;
        }
      }
      return false;
    }

    /**
     * Evaluate a single clause with strict validation (version sécurisée).
     *
     * @param string $clause The clause to evaluate.
     * @param array $variables An associative array of variable names and their values.
     * @return bool The result of the clause evaluation.
     * @throws InvalidArgumentException If clause is invalid
     */
    private function evaluateClause(string $clause, array $variables): bool
    {
      $clause = trim($clause);

      // Cas BETWEEN avec validation stricte
      if ($this->isBetweenClause($clause)) {
        return $this->evaluateBetweenClause($clause, $variables);
      }

      // Cas opérateur de comparaison standard
      if ($this->isComparisonClause($clause)) {
        return $this->evaluateComparisonClause($clause, $variables);
      }

      throw new InvalidArgumentException("Invalid clause format: {$clause}");
    }

    /** Check if the clause is a BETWEEN clause.
     *
     * @param string $clause The clause string.
     * @return bool True if it's a BETWEEN clause, false otherwise.
     */
    private function isBetweenClause(string $clause): bool
    {
      return stripos($clause, ' BETWEEN ') !== false;
    }

    /**
     * Evaluate a BETWEEN clause with strict validation.
     *
     * @param string $clause The BETWEEN clause (e.g., "stock BETWEEN 10 AND 50").
     * @param array $variables An associative array of variable names and their values.
     * @return bool The result of the BETWEEN evaluation.
     * @throws InvalidArgumentException If the clause format is invalid or variables are incorrect.
     */
    private function evaluateBetweenClause(string $clause, array $variables): bool
    {
      $pattern = '/^(\w+)\s+BETWEEN\s+(-?\d+(?:\.\d+)?)\s+AND\s+(-?\d+(?:\.\d+)?)$/i';

      if (!preg_match($pattern, $clause, $matches)) {
        throw new InvalidArgumentException("Invalid BETWEEN clause format: {$clause}");
      }

      $variableName = $matches[1];
      $minValue = (float) $matches[2];
      $maxValue = (float) $matches[3];

      // Validation de la variable
      if (!in_array($variableName, self::ALLOWED_VARIABLES, true)) {
        throw new InvalidArgumentException("Variable '{$variableName}' is not allowed");
      }

      if (!isset($variables[$variableName])) {
        throw new InvalidArgumentException("Variable '{$variableName}' not provided");
      }

      // Validation logique
      if ($minValue > $maxValue) {
        throw new InvalidArgumentException("Invalid BETWEEN range: min > max");
      }

      $value = (float) $variables[$variableName];

      return $value >= $minValue && $value <= $maxValue;
    }

    /**
     * Check if the clause contains a comparison operator.
     *
     * @param string $clause The clause string.
     * @return bool True if it contains a comparison operator, false otherwise.
     */
    private function isComparisonClause(string $clause): bool
    {
      foreach (self::ALLOWED_OPERATORS as $operator) {
        if (str_contains($clause, $operator)) {
          return true;
        }
      }
      return false;
    }

    /**
     * Evaluate a comparison clause with strict validation.
     *
     * @param string $clause The comparison clause (e.g., "stock < 10").
     * @param array $variables An associative array of variable names and their values.
     * @return bool The result of the comparison evaluation.
     * @throws InvalidArgumentException If the clause format is invalid or variables/operators are incorrect.
     */
    private function evaluateComparisonClause(string $clause, array $variables): bool
    {
      // Pattern plus strict pour éviter les injections
      $pattern = '/^(\w+)\s*(>=|<=|>|<|==|!=)\s*(-?\d+(?:\.\d+)?)$/';

      if (!preg_match($pattern, $clause, $matches)) {
        throw new InvalidArgumentException("Invalid comparison clause format: {$clause}");
      }

      $variableName = $matches[1];
      $operator = $matches[2];
      $compareValue = (float) $matches[3];

      // Validation de la variable
      if (!in_array($variableName, self::ALLOWED_VARIABLES, true)) {
        throw new InvalidArgumentException("Variable '{$variableName}' is not allowed");
      }

      if (!isset($variables[$variableName])) {
        throw new InvalidArgumentException("Variable '{$variableName}' not provided");
      }

      // Validation de l'opérateur
      if (!in_array($operator, self::ALLOWED_OPERATORS, true)) {
        throw new InvalidArgumentException("Operator '{$operator}' is not allowed");
      }

      $value = (float) $variables[$variableName];

      return match ($operator) {
        '>' => $value > $compareValue,
        '<' => $value < $compareValue,
        '>=' => $value >= $compareValue,
        '<=' => $value <= $compareValue,
        '==' => abs($value - $compareValue) < PHP_FLOAT_EPSILON,
        '!=' => abs($value - $compareValue) >= PHP_FLOAT_EPSILON,
        default => throw new InvalidArgumentException("Unsupported operator: {$operator}")
      };
    }

    /**
     * Evaluate complex conditions with multiple clauses and logical operators.
     *
     * @param string $condition The full condition string.
     * @param array $variables An associative array of variable names and their values.
     * @return bool The result of the condition evaluation.
     * @throws InvalidArgumentException If the condition structure is invalid.
     */
    private function evaluateComplexCondition(string $condition, array $variables): bool
    {
      // Parser sécurisé pour les opérateurs logiques
      $tokens = $this->tokenizeCondition($condition);

      $tokenCount = count($tokens);
      if ($tokenCount > self::MAX_CLAUSES * 2 - 1) {
        throw new InvalidArgumentException('Too many clauses in condition');
      }

      // Évaluer la première clause
      $result = $this->evaluateClause($tokens[0], $variables);

      // Traiter les opérateurs et clauses suivants
      for ($i = 1; $i < $tokenCount; $i += 2) {
        if ($i + 1 >= $tokenCount) {
          throw new InvalidArgumentException('Invalid condition structure');
        }

        $operator = strtoupper(trim($tokens[$i]));
        $nextClause = trim($tokens[$i + 1]);

        if (!in_array($operator, self::ALLOWED_LOGICAL_OPERATORS, true)) {
          throw new InvalidArgumentException("Invalid logical operator: {$operator}");
        }

        $clauseResult = $this->evaluateClause($nextClause, $variables);

        $result = ($operator === 'AND') ? ($result && $clauseResult) : ($result || $clauseResult);
      }

      return $result;
    }

    /**
     * Tokenize the condition string into clauses and logical operators.
     *
     * @param string $condition The condition string.
     * @return array An array of tokens (clauses and operators).
     * @throws InvalidArgumentException If tokenization fails.
     */
    private function tokenizeCondition(string $condition): array
    {
      $pattern = '/\s+(' . implode('|', self::ALLOWED_LOGICAL_OPERATORS) . ')\s+/i';
      $tokens = preg_split($pattern, $condition, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

      if ($tokens === false || empty($tokens)) {
        throw new InvalidArgumentException('Failed to parse condition');
      }

      return array_map('trim', $tokens);
    }

    /**
     * Save historic log of price variations.
     *
     * @param int $rules_id
     * @param int $product_id
     * @param float|int $base_price
     * @param float|int $final_price
     * @param string $rule
     * @param string $source
     */
    private function logVariation(int $rules_id, int $product_id, float|int $base_price, float|int $final_price, string $rule, string $source = 'system'): void
    {
      $insert_array = [
        'rules_id' => $rules_id,
        'products_id' => $product_id,
        'base_price' => $base_price,
        'dynamic_price' => $final_price,
        'rule_applied' => $rule,
        'date_added' => 'now()',
        'source' => $source
      ];

      $this->db->save('dynamic_pricing_history', $insert_array);
    }

    /**
     * Deletes a promotion created by the dynamic pricing engine only.
     * Promotions created manually (source != 'dynamic') are never touched.
     *
     * @param int $product_id The product ID.
     * @param bool $promotion_applied Whether a dynamic rule applied a promotion this run.
     * @param bool $isDynamicPromotion Whether the current active promotion was created by this engine.
     */
    public function deleteSpecials(int $product_id, bool $promotion_applied, bool $isDynamicPromotion): void
    {
      if ($promotion_applied === false && $isDynamicPromotion === true) {
        $array_sql = [
          'products_id' => (int)$product_id,
          'customers_group_id' => 0,
          'source' => 'dynamic'
        ];

        $this->db->delete('specials', $array_sql);
      }
    }
  }