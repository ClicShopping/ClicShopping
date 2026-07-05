<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin;

use ClicShopping\OM\Registry;
use ClicShopping\OM\Hash;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt\ModelManager;

/**
 * AiModelsAdmin
 *
 * Business logic for the LLM model catalog administration (tables ai_models_*).
 * Read/write helpers with default/fallback uniqueness, API-key encryption and
 * catalog cache invalidation. No presentation logic (templates live in Sites/).
 */
class AiModelsAdmin
{
  /**
   * Lists the AI providers ordered by display order.
   *
   * @return array<int,array{id:int,code:string,sort_order:int}>
   */
  public static function getProviders(): array
  {
    $db = Registry::get('Db');

    $Q = $db->prepare("select ai_model_provider_id, 
                              ai_model_provider_code, 
                              sort_order
                     from :table_ai_models_provider
                     order by sort_order");
    $Q->execute();

    $rows = [];

    foreach ($Q->fetchAll() as $r) {
      $rows[] = [
        'id' => (int)$r['ai_model_provider_id'],
        'code' => $r['ai_model_provider_code'],
        'sort_order' => (int)$r['sort_order'],
      ];
    }

    return $rows;
  }

  /**
   * Lists every model joined to its provider code, ordered by provider then model sort order.
   *
   * @return array<int,array<string,mixed>>
   */
  public static function getModels(): array
  {
    $db = Registry::get('Db');

    $Q = $db->prepare('select n.ai_model_name_id, 
                              n.ai_model_provider_id, 
                              n.model_technical_name,
                              n.model_display_name, 
                              n.ai_model_description, 
                              n.ai_model_status,
                              n.ai_model_status_default, 
                              n.ai_model_status_fallback,
                              n.ai_model_token_input_price, 
                              n.ai_model_token_output_price,
                              n.ai_model_context_window, 
                              n.ai_model_ai_capable, 
                              n.sort_order,
                              p.ai_model_provider_code
                       from :table_ai_models_name n
                       inner join :table_ai_models_provider p on p.ai_model_provider_id = n.ai_model_provider_id
                       order by p.sort_order, n.sort_order
                       ');
    $Q->execute();

    return $Q->fetchAll();
  }

  /**
   * Returns a single model row by id, or null when absent.
   *
   * @return array<string,mixed>|null
   */
  public static function getModel(int $id): ?array
  {
    $db = Registry::get('Db');

    $Q = $db->prepare('select * 
                       from :table_ai_models_name 
                       where ai_model_name_id = :id
                     ');
    $Q->bindInt(':id', $id);
    $Q->execute();

    $row = $Q->fetch();

    return $row === false ? null : $row;
  }

  /**
   * Toggles a model's general activation status.
   */
  public static function setStatus(int $id, int $status): void
  {
    $db = Registry::get('Db');
    $db->save('ai_models_name', ['ai_model_status' => ($status === 1 ? 1 : 0)], ['ai_model_name_id' => $id]);

    ModelManager::clearCatalogCache();
  }

  /**
   * Marks $id as THE default model. Enforces global uniqueness: the current default
   * (there is at most one) is reset to 0 first, then $id is set to 1.
   */
  public static function setDefault(int $id): void
  {
    $db = Registry::get('Db');
    $db->save('ai_models_name', ['ai_model_status_default' => 0], ['ai_model_status_default' => 1]);
    $db->save('ai_models_name', ['ai_model_status_default' => 1], ['ai_model_name_id' => $id]);

    ModelManager::clearCatalogCache();
  }

  /**
   * Marks $id as THE technical fallback model. Same global-uniqueness enforcement as setDefault().
   */
  public static function setFallback(int $id): void
  {
    $db = Registry::get('Db');
    $db->save('ai_models_name', ['ai_model_status_fallback' => 0], ['ai_model_status_fallback' => 1]);
    $db->save('ai_models_name', ['ai_model_status_fallback' => 1], ['ai_model_name_id' => $id]);

    ModelManager::clearCatalogCache();
  }

  /**
   * @return bool true when $id is the current default model (protected from deletion).
   */
  public static function isDefault(int $id): bool
  {
    $db = Registry::get('Db');
    $Q = $db->prepare('select ai_model_status_default 
                      from :table_ai_models_name 
                      where ai_model_name_id = :id
                      ');
    $Q->bindInt(':id', $id);
    $Q->execute();

    $row = $Q->fetch();

    return $row !== false && (int)$row['ai_model_status_default'] === 1;
  }

  /**
   * Deletes a model. Fail-closed: refuses (returns false) when $id is the default model.
   */
  public static function deleteModel(int $id): bool
  {
    if (self::isDefault($id)) {
      return false;
    }

    $db = Registry::get('Db');
    $Q = $db->prepare('delete 
                  from :table_ai_models_name 
                  where ai_model_name_id =:id
                 ');

    $Q->bindInt(':id', $id);
    $Q->execute();

    ModelManager::clearCatalogCache();

    return true;
  }

  /**
   * Inserts a model row and returns its new id. When the row carries default/fallback = 1,
   * uniqueness is enforced afterwards via setDefault()/setFallback().
   *
   * @param array<string,mixed> $data column => value (raw, already validated by the caller)
   */
  public static function insertModel(array $data): int
  {
    $db = Registry::get('Db');
    $db->save('ai_models_name', $data);
    $id = (int)$db->lastInsertId();

    if (!empty($data['ai_model_status_default'])) {
      self::setDefault($id);
    }
    if (!empty($data['ai_model_status_fallback'])) {
      self::setFallback($id);
    }

    ModelManager::clearCatalogCache();

    return $id;
  }

  /**
   * Updates a model row. Enforces default/fallback uniqueness when those flags are set to 1.
   *
   * @param array<string,mixed> $data column => value
   */
  public static function updateModel(int $id, array $data): void
  {
    $db = Registry::get('Db');
    $db->save('ai_models_name', $data, ['ai_model_name_id' => $id]);

    if (!empty($data['ai_model_status_default'])) {
      self::setDefault($id);
    }
    if (!empty($data['ai_model_status_fallback'])) {
      self::setFallback($id);
    }

    ModelManager::clearCatalogCache();
  }

  /**
   * Reads a provider's API credential, decrypting the key for display.
   *
   * @return array{api_key_plain:string,organisation:?string}
   */
  public static function getProviderCredential(int $providerId): array
  {
    $db = Registry::get('Db');
    $Q = $db->prepare('select ai_model_provider_api_key, 
                           ai_model_organisation
                     from :table_ai_models_api 
                     where ai_model_provider_id = :ai_model_provider_id
                     ');

    $Q->bindInt(':ai_model_provider_id', $providerId);
    $Q->execute();

    $row = $Q->fetch();

    return [
      'api_key_plain' => $row === false ? '' : Hash::displayDecryptedDataText($row['ai_model_provider_api_key']),
      'organisation' => $row === false ? null : $row['ai_model_organisation'],
    ];
  }

  /**
   * Encrypts and stores a provider's API key + organisation (one credential row per provider).
   */
  public static function saveProviderCredential(int $providerId, string $plainKey, ?string $org): void
  {
    $db = Registry::get('Db');
    $db->save('ai_models_api', [
      'ai_model_provider_api_key' => Hash::encryptDatatext($plainKey),
      'ai_model_organisation' => ($org === '' ? null : $org),
    ], ['ai_model_provider_id' => $providerId]);

    ModelManager::clearCatalogCache();
  }

  /**
   * Checks whether a model already exists for a given provider + technical name
   * (the UNIQUE key idx_provider_model). Pass $excludeId to ignore the row being edited.
   * Lets the caller show a MessageStack error instead of hitting the DB duplicate-key exception.
   */
  public static function modelExists(int $providerId, string $technicalName, ?int $excludeId = null): bool
  {
    $db = Registry::get('Db');

    $sql = "select ai_model_name_id
            from :table_ai_models_name
            where ai_model_provider_id = :pid 
            and model_technical_name = :name";

    if ($excludeId !== null) {
      $sql .= " and ai_model_name_id <> :exclude";
    }

    $Q = $db->prepare($sql);
    $Q->bindInt(':pid', $providerId);
    $Q->bindValue(':name', $technicalName);

    if ($excludeId !== null) {
      $Q->bindInt(':exclude', $excludeId);
    }

    $Q->execute();

    return $Q->fetch() !== false;
  }
}
