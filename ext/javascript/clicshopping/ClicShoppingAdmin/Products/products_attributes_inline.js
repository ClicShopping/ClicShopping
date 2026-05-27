/*
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

(function () {
  'use strict';

  var config = window.ProductsAttributesInline || {};
  var ajaxUrl = config.ajaxUrl || '';
  var labels = config.labels || {};
  var rowCounter = config.initialRows || 0;

  var $container = null;
  var $tbody = null;
  var $template = null;

  /**
   * Detect if a string looks like a hex color (#fff / #ffffff).
   */
  function isHexColor(s) {
    return typeof s === 'string' && /^#?[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/.test(s.trim());
  }

  /**
   * Read the option type from the currently selected option in the row,
   * propagate it to the values select as data-options-type, and refresh
   * the color swatch.
   */
  function syncOptionType($row) {
    var $opt = $row.find('select[data-role="options"]');
    var $valueSelect = $row.find('select[data-role="values"]');
    var type = $opt.find('option:selected').attr('data-type') || '';
    $valueSelect.attr('data-options-type', type);
    refreshSwatch($row);
  }

  /**
   * Fetch the values associated with the chosen option via the bridge
   * table and repopulate the values <select>. Used when the user changes
   * the option in an existing or new row.
   */
  function loadValues($row, optionsId) {
    var $valueSelect = $row.find('select[data-role="values"]');
    var placeholder = labels.selectValue || '';

    $valueSelect.empty();

    if (!optionsId) {
      $valueSelect.append($('<option>').val('').text(placeholder));
      $valueSelect.removeAttr('data-options-type');
      refreshSwatch($row);
      return;
    }

    if (!ajaxUrl) {
      $valueSelect.append($('<option>').val('').text(placeholder));
      return;
    }

    $valueSelect.append($('<option>').val('').text('...'));
    $valueSelect.prop('disabled', true);

    $.getJSON(ajaxUrl, { options_id: optionsId })
      .done(function (data) {
        var type = (data && data.type) || '';
        var values = (data && data.values) || [];

        $valueSelect.attr('data-options-type', type);
        $valueSelect.empty();
        $valueSelect.append($('<option>').val('').text(placeholder));

        $.each(values, function (_, item) {
          $valueSelect.append($('<option>').val(item.id).text(item.name));
        });

        refreshSwatch($row);
      })
      .fail(function (jqXHR) {
        console.error('ProductsAttributesInline: cascade AJAX failed', jqXHR.status, jqXHR.statusText, ajaxUrl);
        $valueSelect.empty();
        $valueSelect.append($('<option>').val('').text(placeholder));
      })
      .always(function () {
        $valueSelect.prop('disabled', false);
      });
  }

  /**
   * Render or hide the color swatch based on the current option type
   * and the text of the selected value (a hex string for color_picker).
   */
  function refreshSwatch($row) {
    var $valueSelect = $row.find('select[data-role="values"]');
    var $swatch = $row.find('[data-role="value-swatch"]');
    var type = $valueSelect.attr('data-options-type') || '';
    var selected = $valueSelect.find('option:selected').text();

    if (type === 'color_picker' && isHexColor(selected)) {
      var hex = '#' + selected.replace(/^#/, '');
      $swatch.css('background-color', hex).show();
    } else {
      $swatch.hide();
    }
  }

  /**
   * Mark a row as pending deletion. If the row is new (no id), remove it
   * outright; otherwise hide it and set the _delete hidden flag so the
   * server-side hook DELETEs it on the next product save.
   */
  function markRowDeleted($row) {
    var rowId = $row.find('input[data-role="id"]').val();

    if (!rowId || rowId === '0' || rowId === '') {
      $row.remove();
      return;
    }

    if (!window.confirm(labels.confirmRemove || '')) {
      return;
    }

    $row.find('input[data-role="delete"]').val('1');
    $row.addClass('pa-inline-deleted').css('opacity', '0.4');
    $row.find('input, select').not('input[data-role="delete"]').not('input[data-role="id"]').prop('disabled', true);
    $row.find('button[data-role="remove"]').hide();
    $row.find('button[data-role="restore"]').show();
  }

  /**
   * Undo a pending deletion on an existing row.
   */
  function restoreRow($row) {
    $row.find('input[data-role="delete"]').val('0');
    $row.removeClass('pa-inline-deleted').css('opacity', '');
    $row.find('input, select').prop('disabled', false);
    $row.find('button[data-role="remove"]').show();
    $row.find('button[data-role="restore"]').hide();
  }

  /**
   * Show a small filename preview next to the file input when a new image
   * is selected. The actual upload happens with the main product form POST.
   */
  function bindImagePreview($row) {
    $row.find('input[type="file"][data-role="image"]').on('change', function () {
      var file = this.files && this.files[0];
      var $preview = $(this).siblings('[data-role="image-preview"]');
      if (file) {
        $preview.text(file.name);
      } else {
        $preview.text('');
      }
    });
  }

  /**
   * Wire all event handlers for one row.
   */
  function bindRow($row) {
    $row.on('change', 'select[data-role="options"]', function () {
      syncOptionType($row);
      loadValues($row, $(this).val());
    });

    $row.on('change', 'select[data-role="values"]', function () {
      refreshSwatch($row);
    });

    $row.on('click', 'button[data-role="remove"]', function (e) {
      e.preventDefault();
      markRowDeleted($row);
    });

    $row.on('click', 'button[data-role="restore"]', function (e) {
      e.preventDefault();
      restoreRow($row);
    });

    bindImagePreview($row);

    syncOptionType($row);
  }

  /**
   * Parse the template HTML safely. Wrapping in <table><tbody> ensures the
   * browser keeps the <tr> structure intact during the in-memory parse.
   */
  function buildRow(index) {
    var html = $template.html().replace(/__INDEX__/g, index);
    return $('<table><tbody>' + html + '</tbody></table>').find('tr').first();
  }

  /**
   * Append a brand-new empty row at the end of the table.
   */
  function addRow() {
    var $row = buildRow(rowCounter);
    rowCounter++;
    $tbody.append($row);
    bindRow($row);
  }

  $(function () {
    $container = $('#section_ProductsAttributesInline_content');
    if ($container.length === 0) {
      return;
    }
    $tbody = $container.find('table[data-role="pa-inline-table"] tbody');
    $template = $container.find('[data-role="pa-inline-template"]');

    $container.find('tbody > tr[data-role="pa-inline-row"]').each(function () {
      bindRow($(this));
    });

    $container.on('click', 'button[data-role="add"]', function (e) {
      e.preventDefault();
      addRow();
    });
  });
})();
