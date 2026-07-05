<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\Shop;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;

use function is_null;

class GptShop
{
  /**
   * Checks the current status of the GPT system.
   *
   * @return bool Returns true if the GPT system is operational, false otherwise.
   */
  public static function checkGptStatus(): bool
  {
    return Gpt::checkGptStatus();
  }

  /**
   * Retrieves the GPT envirnment information
   *
   * @return string Returns envirnment information.
   */
  public static function getEnvironment(): string
  {
    return  Gpt::getEnvironment();
  }

  /**
   * Retrieves the AJAX URL for the ChatGPT chatbot endpoint or an empty string based on the provided parameter.
   *
   * @param bool $chatGpt Determines whether to return the ChatGPT AJAX URL. If true, returns the URL; if false, returns an empty string.
   * @return string The AJAX URL for the ChatGPT chatbot or an empty string.
   */
  public static function getAjaxUrl(bool $chatGpt = true): string
  {
    if ($chatGpt === true) {
      $url = CLICSHOPPING::getConfig('http_server', 'ClicShoppingAdmin') . CLICSHOPPING::getConfig('http_path', 'Shop') . 'ext/ajax/ai/chatbot_gpt.php';
    } else {
      $url = CLICSHOPPING::getConfig('http_server', 'Shop') . CLICSHOPPING::getConfig('http_path', 'Shop') . 'ext/ajax/ai/chatbot_gpt.php';
    }

    return $url;
  }

  /**
   * Fetches a GPT response based on the provided question and optional parameters.
   *
   * @param string $question The question to be processed and sent to the GPT model.
   * @param int|null $maxtoken Optional. Maximum number of tokens for the response. Defaults to 200 if null.
   * @param float|null $temperature Optional. The sampling temperature to control randomness. Defaults to 0.5 if null.
   * @return string|bool Returns the GPT response as a string, or false if GPT is not available.
   */
  public static function getGptResponse(string $question,  int|null $maxtoken = null, ?float $temperature = null)
  {
    if (self::checkGptStatus() === false) {
      return false;
    }

    $question = HTML::sanitize($question);

    if (is_null($maxtoken)) {
      $maxtoken = 200;
    }

    if (is_null($temperature)) {
      $temperature = 0.5;
    }

    if (str_starts_with(Gpt::defaultModel(), 'gpt')) {
      $engine = Gpt::defaultModel();
      $response = Gpt::getGptResponse($question, $maxtoken, $temperature, $engine);
    } else {
      //ollama
      $response = Gpt::getGptResponse($question, $maxtoken, $temperature);
    }

    return $response;
  }

  /**
   * Checks if the total tokens used today exceed the maximum allowed tokens per day.
   *
   * @param int $max_token The maximum allowable tokens for the current day.
   * @return bool Returns true if the total tokens used today are within the allowed limit, false otherwise.
   */
  public static function checkMaxTokenPerDay(int $max_token): bool
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $QtokenTotal = $CLICSHOPPING_Db->query('SELECT SUM(tokens_total) AS total
                                             FROM :table_rag_statistics
                                             WHERE DATE(date_added) = CURDATE()
                                            ');
    $total_accepted = $QtokenTotal->value('total');

    if ($total_accepted > $max_token) {
      return false;
    } else {
      return true;
    }
  }

  /**
   * Searches for products based on a question and search result data and returns a response string.
   *
   * @param string $question The user's query or question related to the product search.
   * @param string|array $result The search result data, either as a JSON-encoded string or an associative array.
   *                              Used to determine the search keywords for querying the database.
   *
   * @return string A response string indicating the result of the product search, which may include a chatbot response
   *                if a relevant product is found. If no product is found, a default response message is returned.
   */
  public static function productSearch(string $question, string|array $result): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_Customer = Registry::get('Customer');

    if (is_string($result)) {
      $result = trim($result);
      $result = str_replace("'", '"', $result);

      $result_array = json_decode($result, true);
    } else {
      $result_array = $result;
    }

    if (is_array($result_array)) {
      $searchQueries = [];

      foreach ($result_array as $i => $word) {
        $searchQueries[] = '%' . $word . '%';

        if ($i >= 2) {
          break;
        }
      }

      if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
        $query = 'select p.products_id,
                           pd.products_name
                from :table_products p left join :table_products_groups g on p.products_id = g.products_id,
                     :table_products_description pd
                where p.products_status = 1
                and p.products_id = pd.products_id
                and pd.language_id = :language_id
                and g.products_group_view = 1
                and p.products_status = 1
                and p.products_archive = 0
                and (g.customers_group_id = :customers_group_id or g.customers_group_id = 99)            
                ';
      } else {
        $query = 'select p.products_id,
                            pd.products_name
                    from :table_products p left join :table_products_groups g on p.products_id = g.products_id,
                         :table_products_description pd
                    where p.products_status = 1
                    and p.products_id = pd.products_id
                    and pd.language_id = :language_id
                    and p.products_view = 1
                    and p.products_status = 1
                    and p.products_archive = 0
                  ';
      }

      if (!empty($searchQueries)) {
        $searchConditions = implode(' or ', array_map(function ($search) {
          return 'pd.products_name like \'' . $search . '\'';
        }, $searchQueries));

        $query .= ' and (' . $searchConditions . ')';
      }

      $query .= ' limit 1';

      $Qproducts = $CLICSHOPPING_Db->prepare($query);
      $Qproducts->bindInt(':language_id', $CLICSHOPPING_Language->getId());

      if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
        $Qproducts->bindInt(':customers_group_id', $CLICSHOPPING_Customer->getCustomersGroupID());
      }

      $Qproducts->execute();

      $question_result = $Qproducts->value('products_name');

      if (!empty($question_result)) {
        $result = CLICSHOPPING::getDef('text_chatbot_ok', ['question' => $question]);
        $result .= GptShop::getGptResponse($question);
      } else {
        $result = CLICSHOPPING::getDef('text_chatbot_not_ok');
      }
    } else {
      $result = CLICSHOPPING::getDef('text_chatbot_not_ok');
    }

    return $result;
  }
}