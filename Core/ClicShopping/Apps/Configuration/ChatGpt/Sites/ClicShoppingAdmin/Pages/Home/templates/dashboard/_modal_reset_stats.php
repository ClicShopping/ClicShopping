<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 * Shared "Reset all RAG stats" confirmation modal (Developper + Data Scientist).
 */
?>
<?php if ($config['chatgpt_enabled']): ?>
<!-- Modal de confirmation pour réinitialiser les stats -->
<div class="modal fade" id="resetStatsModal" tabindex="-1" aria-labelledby="resetStatsModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="resetStatsModalLabel"><?php echo $CLICSHOPPING_ChatGpt->getDef('modal_reset_stats_title'); ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('modal_reset_stats_warning'); ?></strong> <?php echo $CLICSHOPPING_ChatGpt->getDef('modal_reset_stats_description'); ?></p>
        <ul>
          <li><?php echo $CLICSHOPPING_ChatGpt->getDef('modal_reset_stats_item1'); ?></li>
          <li><?php echo $CLICSHOPPING_ChatGpt->getDef('modal_reset_stats_item2'); ?></li>
          <li><?php echo $CLICSHOPPING_ChatGpt->getDef('modal_reset_stats_item3'); ?></li>
        </ul>
        <p class="text-danger"><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('modal_reset_stats_irreversible'); ?></strong></p>
        <p><?php echo $CLICSHOPPING_ChatGpt->getDef('modal_reset_stats_confirm'); ?></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $CLICSHOPPING_ChatGpt->getDef('modal_reset_stats_cancel'); ?></button>
        <form method="post" action="<?php echo $CLICSHOPPING_ChatGpt->link('ChatGpt&ResetAllRagStats'); ?>" style="display: inline;">
          <input type="hidden" name="confirm_reset" value="yes">
          <button type="submit" class="btn btn-danger"><?php echo $CLICSHOPPING_ChatGpt->getDef('modal_reset_stats_yes'); ?></button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
