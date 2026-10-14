<?php

  namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubFactors;

  class CreationDateFactor implements FactorInterface
  {
    private int $ageDays;
    private int $maxCatalogDays;

    public function __construct(int $ageDays, int $maxCatalogDays) {
      $this->ageDays = $ageDays;
      $this->maxCatalogDays = $maxCatalogDays > 0 ? $maxCatalogDays : 365;
    }

    public function getType(): string { return 'count'; }
    public function getStatus(): string { return 'valid'; }
    public function getValue(): int { return $this->ageDays; }

    /**
     * REQ-SC-01 : Formule 1 - (jours_produit / jours_max)
     */
    public function normalize(): float {
      $ratio = min(1.0, $this->ageDays / $this->maxCatalogDays);
      return (float)(1.0 - $ratio);
    }
  }