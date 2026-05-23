<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\Sites\ClicShoppingAdmin\HTMLOverrideAdmin;

?>
<script>
  function resetZoneSelected(theForm) {
    if (theForm.state.value != '') {
      theForm.state.selectedIndex = '0';
      if (theForm.state.options.length > 0) {
        theForm.state.value = '<?php echo CLICSHOPPING::getDef('js_state_select'); ?>';
      }
    }
  }

  function update_zone(theForm) {
    let NumState = theForm.state.options.length;
    let SelectedCountry = "";

    while (NumState > 0) {
      NumState--;
      theForm.state.options[NumState] = null;
    }

    SelectedCountry = theForm.country.options[theForm.country.selectedIndex].value;

    <?php echo HTMLOverrideAdmin::getJsZoneList('SelectedCountry', 'theForm', 'state'); ?>
  }
</script>