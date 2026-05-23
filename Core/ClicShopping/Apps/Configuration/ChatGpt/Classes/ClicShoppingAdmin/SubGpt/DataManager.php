<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt;

use ClicShopping\OM\Registry;
use ClicShopping\OM\Hash;
use ClicShopping\OM\HTTP;
use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;
use ClicShopping\AI\Security\InputValidator;
use DateTimeImmutable;

/**
 * DataManager
 *
 * Manages GPT data persistence and analytics.
 * Extracted from Gpt.php as part of code refactoring (Task 9).
 *
 * Responsibilities:
 * - Save GPT queries and responses to database
 * - Calculate error rates
 * - Manage audit trails
 * - Track usage statistics
 */
class DataManager
{
  /**
   * Saves data to the database, including question details, audit trials.
   *
   * @param string $question The question being saved.
   * @param string $result The result or response to the question.
   * @param array|null $auditExtra Optional additional data for auditing purposes, such as embeddings context, similarity scores, and processing chain.
   * @param bool $force Force save regardless of saveGpt parameter
   * @return void
   * @throws \Exception
   */
  public static function saveData(string $question, string $result, ?array $auditExtra = [], bool $force = false): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    // Validate and sanitize the saveGpt parameter from POST data
    $saveData = isset($_POST['saveGpt']) ?
      InputValidator::validateParameter(
        $_POST['saveGpt'],
        'int',
        0,
        [
          'min' => 0,
          'max' => 1
        ]
      ) : 0;

    if ($saveData === 1 && $force === false) {
      // Validate and sanitize the question and result before saving to database
      $validatedQuestion = InputValidator::validateParameter(
        $question,
        'string',
        '',
        [
          'maxLength' => 4096,
          'escape' => true
        ]
      );

      $validatedResult = InputValidator::validateParameter(
        $result,
        'string',
        '',
        [
          'maxLength' => 8192,
          'escape' => true
        ]
      );

      // Validate the user admin value
      $validatedUserAdmin = InputValidator::validateParameter(
        AdministratorAdmin::getUserAdmin(),
        'string',
        'system',
        [
          'maxLength' => 255,
          'pattern' => '/^[a-zA-Z0-9_\-\.\s]+$/'
        ]
      );

      // Audit trail
      $auditPayload = [
        'session' => [
          'id' => session_id(),
          'ip' => HTTP::getIpAddress() ?? null,
          'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ],
        'embeddings_context' => $auditExtra['embeddings_context'] ?? [],
        'similarity_scores' => $auditExtra['similarity_scores'] ?? [],
        'processing_chain' => $auditExtra['processing_chain'] ?? []
      ];

      $timestamp = (new DateTimeImmutable())->format('Y-m-d H:i:s');

      $auditPayload['hash'] = Hash::encryptDatatext($validatedUserAdmin . session_id() . $timestamp);

      $array_sql = [
        'question' => $validatedQuestion,
        'response' => $validatedResult,
        'date_added' => 'now()',
        'user_admin' => $validatedUserAdmin,
        'audit_data' => json_encode($auditPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
      ];

      try {
        $CLICSHOPPING_Db->save('gpt', $array_sql);
      } catch (\Exception $e) {
        error_log("Erreur lors de la sauvegarde du log GPT dans la base de données: " . $e->getMessage());
      }
    }
  }

  /**
   * Calculates the error rate of GPT responses by analyzing specific response patterns and comparing them to total entries.
   *
   * @return bool|float Returns the calculated error rate as a percentage if computations are successful, or false if there is no data available.
   */
  public static function getErrorRateGpt(): bool|float
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $result = false;

    $Qtotal = $CLICSHOPPING_Db->prepare('select count(gpt_id) as total_id
                                           from :table_gpt
                                          ');
    $Qtotal->execute();

    $result_total_chat = $Qtotal->valueInt('avg');

    $QtotalResponse = $CLICSHOPPING_Db->prepare('select count(response) as total
                                                   from :table_gpt
                                                   where (response like :response or response like :response1)
                                                   and user_admin like :user_admin
                                                  ');
    $QtotalResponse->bindValue(':response', '%I\'m sorry but I do not find%');
    $QtotalResponse->bindValue(':response1', '%Je suis désolé mais je n\'ai pas trouvé d\'informations%');
    $QtotalResponse->bindValue(':user_admin', '%Chatbot Front Office%');

    $QtotalResponse->execute();

    $result_no_response = $QtotalResponse->valueDecimal('total');

    if ($result_no_response > 0) {
      $result = ($result_no_response / $result_total_chat) * 100 . '%';
    }

    return $result;
  }
}
