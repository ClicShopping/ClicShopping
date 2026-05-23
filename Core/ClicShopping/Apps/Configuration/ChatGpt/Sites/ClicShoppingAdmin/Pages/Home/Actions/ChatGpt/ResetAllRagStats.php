<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Sites\ClicShoppingAdmin\Pages\Home\Actions\ChatGpt;

use ClicShopping\OM\Registry;

class ResetAllRagStats extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
    $CLICSHOPPING_ChatGpt = Registry::get('ChatGpt');

    if (isset($_POST['confirm_reset']) && $_POST['confirm_reset'] === 'yes') {
      try {
        // Supprimer toutes les statistiques
        $CLICSHOPPING_ChatGpt->exec('DELETE FROM :table_rag_statistics');
        
        // Supprimer toutes les interactions
        $CLICSHOPPING_ChatGpt->exec('DELETE FROM :table_rag_interactions');
        
        // Réinitialiser les auto-increment
        $CLICSHOPPING_ChatGpt->exec('ALTER TABLE :table_rag_statistics AUTO_INCREMENT = 1');
        $CLICSHOPPING_ChatGpt->exec('ALTER TABLE :table_rag_interactions AUTO_INCREMENT = 1');
        
        $CLICSHOPPING_MessageStack->add($CLICSHOPPING_ChatGpt->getDef('success_reset_all_stats'), 'success', 'chatgpt');
        
      } catch (\Exception $e) {
        $CLICSHOPPING_MessageStack->add('Erreur lors de la réinitialisation: ' . $e->getMessage(), 'error', 'chatgpt');
      }
    }

    $CLICSHOPPING_ChatGpt->redirect('DashBoard');
  }
}
