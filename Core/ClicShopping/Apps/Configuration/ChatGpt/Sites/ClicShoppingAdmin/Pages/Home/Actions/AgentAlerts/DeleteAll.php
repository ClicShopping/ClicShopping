<?php
/**
 * Delete handled user reports
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Sites\ClicShoppingAdmin\Pages\Home\Actions\AgentAlerts;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

/**
 * Removes the user reports of the selected kinds of request: the alert was handled, or it never
 * deserved one. Only the rag_feedback rows go — the questions and the answers they point at are
 * kept, so a later diagnosis still has its history.
 */
class DeleteAll extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  private const WINDOW_DAYS = 7;

  public function execute()
  {
    $CLICSHOPPING_ChatGpt = Registry::get('ChatGpt');

    // An empty selection must delete NOTHING: an unrestricted DELETE empties the table.
    if (isset($_POST['selected'], $_GET['DeleteAll']) && \is_array($_POST['selected']) && $_POST['selected'] !== []) {
      $types = array_values(array_unique(array_map('strval', $_POST['selected'])));
      $prefix = CLICSHOPPING::getConfig('db_table_prefix');

      $placeholders = [];

      foreach (array_keys($types) as $i) {
        $placeholders[] = ':type_' . $i;
      }

      $Qdelete = $CLICSHOPPING_ChatGpt->db->prepare('delete f
                                                    from `' . $prefix . 'rag_feedback` f
                                                    left join `' . $prefix . 'rag_interactions` i
                                                      on f.interaction_id = i.client_interaction_id
                                                    where f.feedback_type = "negative"
                                                    and f.date_added >= date_sub(now(), interval ' . self::WINDOW_DAYS . ' day)
                                                    and coalesce(i.request_type, "unknown") in (' . implode(', ', $placeholders) . ')
                                                   ');

      foreach ($types as $i => $type) {
        $Qdelete->bindValue(':type_' . $i, $type);
      }

      // execute() returns false on a SQL error without throwing: "0 row" must not read as success.
      if ($Qdelete->execute() === false) {
        error_log('AgentAlerts DeleteAll: ' . implode(' ', $Qdelete->errorInfo()));
      }
    }

    $CLICSHOPPING_ChatGpt->redirect('AgentAlerts');
  }
}
