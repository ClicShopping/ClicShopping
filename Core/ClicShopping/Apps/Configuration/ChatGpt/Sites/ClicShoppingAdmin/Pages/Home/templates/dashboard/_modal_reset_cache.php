<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 * Shared "Reset cache" modal + its JS config (Developper + Data Scientist).
 * Three logical scopes: DB cache, disk AI (Rag) cache, logs.
 */
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
?>
<?php if ($config['chatgpt_enabled']): ?>
<!-- ============================================================================ -->
<!-- MODAL: Reset Cache -->
<!-- ============================================================================ -->
<div class="modal fade" id="resetCacheModal" tabindex="-1" aria-labelledby="resetCacheModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-warning text-dark">
        <h5 class="modal-title" id="resetCacheModalLabel">
          <i class="bi bi-trash"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('modal_reset_cache_title'); ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-warning">
          <i class="bi bi-exclamation-triangle"></i>
          <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('modal_reset_cache_warning_title'); ?></strong> <?php echo $CLICSHOPPING_ChatGpt->getDef('modal_reset_cache_warning_text'); ?>
        </div>

        <p><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('modal_reset_cache_select'); ?></strong></p>

        <div class="row">
          <div class="col-12">
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" id="cache_db" name="cache_types[]" value="db" checked>
              <label class="form-check-label" for="cache_db">
                <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_type_db'); ?></strong>
                <br><small class="text-muted"><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_type_db_desc'); ?></small>
              </label>
            </div>

            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" id="cache_disk" name="cache_types[]" value="disk" checked>
              <label class="form-check-label" for="cache_disk">
                <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_type_disk'); ?></strong>
                <br><small class="text-muted"><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_type_disk_desc'); ?></small>
              </label>
            </div>

            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" id="cache_logs" name="cache_types[]" value="logs">
              <label class="form-check-label" for="cache_logs">
                <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_type_logs'); ?></strong>
                <br><small class="text-muted"><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_type_logs_desc'); ?></small>
              </label>
            </div>
          </div>
        </div>

        <div id="cacheResetResult" class="mt-3" style="display: none;"></div>
      </div>
      <div class="modal-footer">
        <?php
          echo HTML::button($CLICSHOPPING_ChatGpt->getDef('modal_reset_cache_cancel'), 'bi bi-x', null, 'warning', ['type' => 'button', 'params' => 'data-bs-dismiss="modal"']);
          echo HTML::button($CLICSHOPPING_ChatGpt->getDef('modal_reset_cache_confirm'), 'bi bi-trash', null, 'danger', ['type' => 'button', 'params' => 'id="confirmResetCache"']);
        ?>
      </div>
    </div>
  </div>
</div>
<div class="py-4"></div>
<script>
window.ResetCacheConfig = {
  resetUrl: '<?php echo CLICSHOPPING::link('ajax/RAG/reset_cache.php'); ?>',
  labels: {
    selectOne: "<?php echo $CLICSHOPPING_ChatGpt->getDef('cache_reset_select_one'); ?>",
    resetting: "<?php echo $CLICSHOPPING_ChatGpt->getDef('cache_reset_resetting'); ?>",
    inProgress: "<?php echo $CLICSHOPPING_ChatGpt->getDef('cache_reset_in_progress'); ?>",
    success: "<?php echo $CLICSHOPPING_ChatGpt->getDef('cache_reset_success'); ?>",
    error: "<?php echo $CLICSHOPPING_ChatGpt->getDef('modal_reset_cache_error'); ?>",
    errorOccurred: "<?php echo $CLICSHOPPING_ChatGpt->getDef('cache_reset_error_occurred'); ?>",
    confirm: "<?php echo $CLICSHOPPING_ChatGpt->getDef('modal_reset_cache_confirm'); ?>",
    details: {
      db: "<?php echo $CLICSHOPPING_ChatGpt->getDef('cache_reset_db_entries_deleted'); ?>",
      disk: "<?php echo $CLICSHOPPING_ChatGpt->getDef('cache_reset_files_deleted'); ?>",
      logs: "<?php echo $CLICSHOPPING_ChatGpt->getDef('cache_reset_logs_deleted'); ?>"
    }
  }
};
</script>
<script defer src="<?php echo CLICSHOPPING::link('Shop/ext/javascript/clicshopping/ClicShoppingAdmin/Rag/reset_cache.js'); ?>"></script>
<?php endif; // End of $config['chatgpt_enabled'] check for reset cache modal ?>
