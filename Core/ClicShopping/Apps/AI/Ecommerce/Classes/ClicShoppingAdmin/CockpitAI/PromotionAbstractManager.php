<?php

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI;

use ClicShopping\OM\Registry;

abstract class PromotionAbstractManager {
  protected $db;
  protected string $table;
  protected int $maxLimit;

  public function __construct(string $table, int $maxLimit) {
    $this->db = Registry::get('Db');
    $this->table = $table;
    $this->maxLimit = $maxLimit;
  }

  public function add(int $productId): bool {
    if ($this->checkExists($productId) || $this->countActive() >= $this->maxLimit) {
      return false;
    }
    return $this->db->save(str_replace(':table_', '', $this->table), [
      'products_id' => $productId,
      'status' => 1,
      'date_added' => 'now()'
    ]);
  }

  public function checkExists(int $productId): bool {
    $Q = $this->db->prepare("SELECT count(*) as total FROM {$this->table} WHERE products_id = :pId AND status = 1");
    $Q->bindInt(':pId', $productId);
    $Q->execute();
    return ((int)$Q->fetch()['total'] > 0);
  }

  public function countActive(): int {
    $Q = $this->db->prepare("SELECT count(*) as total FROM {$this->table} WHERE status = 1");
    $Q->execute();
    return (int)$Q->fetch()['total'];
  }

  public function remove(int $productId): bool {

    return $this->db->delete(str_replace(':table_', '', $this->table), ['products_id' => $productId]);
   // return false;
  }
}