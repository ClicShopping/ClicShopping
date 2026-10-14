(function () {

  // jscolor v1.4.1 — instance stored at element.color (not element.jscolor).
  // Listeners (focus, blur, keyup, input) are registered via addEventListener
  // inside the constructor and cannot be removed.  The only clean way to
  // "unbind" is to replace the element with a fresh one.

  function bindJscolor(input) {
    if (!input.color && typeof jscolor !== 'undefined') {
      input.classList.add('color');
      input.color = new jscolor.color(input);
    }
  }

  // Replace input with a clean element (removes all jscolor listeners).
  // Returns the new element, or the original if no replacement was needed.
  function unbindJscolor(input) {
    if (!input.color && !input.classList.contains('color')) {
      return input; // already clean
    }
    if (input.color) {
      input.color.pickerOnfocus = false; // stop the shared picker from reacting
      input.color.hidePicker();
      delete input.color;
    }
    var fresh = document.createElement('input');
    fresh.type = 'text';
    fresh.name = input.name;
    if (input.id) fresh.id = input.id;
    fresh.className = input.className.replace(/\bcolor\b/g, '').trim();
    if (input.required) fresh.required = true;
    var ariaReq = input.getAttribute('aria-required');
    if (ariaReq) fresh.setAttribute('aria-required', ariaReq);
    fresh.value = input.value; // preserve value (caller clears it if needed)
    if (input.parentNode) input.parentNode.replaceChild(fresh, input);
    return fresh;
  }

  // -----------------------------------------------------------------------
  // go_option : sort/order redirect (URL passed via data-go-option-url on
  // #categoriesTabs)
  // -----------------------------------------------------------------------
  window.go_option = function () {
    var container = document.getElementById('categoriesTabs');
    var baseUrl = container ? container.getAttribute('data-go-option-url') : '';
    if (!baseUrl) return;
    var form = document.option_order_by;
    if (!form) return;
    var val = form.elements['selected'].value;
    if (val !== 'none') {
      location = baseUrl + '&option_order_by=' + encodeURIComponent(val);
    }
  };

  // -----------------------------------------------------------------------
  // Tab 2 — UPDATE form : toggle jscolor on value_name inputs when the
  // selected option type changes.
  // Excludes #tab2InsertOptionId which has its own dedicated handler below.
  // -----------------------------------------------------------------------
  document.querySelectorAll('select[name="option_id"]:not(#tab2InsertOptionId)').forEach(function (select) {
    function toggleJscolor() {
      var opt = select.options[select.selectedIndex];
      var type = opt ? opt.getAttribute('data-type') : '';
      var form = select.closest('form');
      if (!form) return;
      form.querySelectorAll('input[name^="value_name"]').forEach(function (input) {
        if (type === 'color_picker') {
          bindJscolor(input);
        } else {
          unbindJscolor(input); // value preserved for the update form
        }
      });
    }
    select.addEventListener('change', toggleJscolor);
    toggleJscolor();
  });

  // -----------------------------------------------------------------------
  // Tab 2 — INSERT form : jscolor on value_name inputs
  // (select#tab2InsertOptionId, inputs id^="tab2InsertValueName_")
  // -----------------------------------------------------------------------
  function initTab2InsertColorPicker() {
    var sel = document.getElementById('tab2InsertOptionId');
    if (!sel) return;

    function updateColorPicker() {
      var selectedOption = sel.options[sel.selectedIndex];
      var isColor = selectedOption && selectedOption.getAttribute('data-type') === 'color_picker';

      // Query fresh each time (elements may have been replaced)
      document.querySelectorAll('[id^="tab2InsertValueName_"]').forEach(function (inp) {
        if (isColor) {
          bindJscolor(inp);
        } else {
          var fresh = unbindJscolor(inp);
          fresh.value = ''; // clear hex value when switching away from color_picker
        }
      });
    }

    sel.addEventListener('change', updateColorPicker);
    updateColorPicker();
  }

  // -----------------------------------------------------------------------
  // Tab 3 — swatch preview next to values_id select (generic)
  // -----------------------------------------------------------------------
  document.querySelectorAll('select[name="options_id"]').forEach(function (optSelect) {
    var row = optSelect.closest('tr');
    if (!row) return;
    var valSelect = row.querySelector('select[name="values_id"]');
    if (!valSelect) return;

    function updateSwatch() {
      var opt = optSelect.options[optSelect.selectedIndex];
      var type = opt ? opt.getAttribute('data-type') : '';
      var swatch = valSelect.parentNode.querySelector('.attr-color-swatch');

      if (type === 'color_picker') {
        if (!swatch) {
          swatch = document.createElement('span');
          swatch.className = 'attr-color-swatch';
          swatch.style.cssText = 'display:inline-block;width:22px;height:22px;border:1px solid #999;border-radius:3px;vertical-align:middle;margin-left:6px;';
          valSelect.after(swatch);
        }
        function refreshSwatch() {
          var hex = '#' + valSelect.options[valSelect.selectedIndex].text.replace(/^#/, '');
          swatch.style.backgroundColor = hex;
        }
        valSelect.addEventListener('change', refreshSwatch);
        refreshSwatch();
      } else {
        if (swatch) swatch.remove();
      }
    }
    optSelect.addEventListener('change', updateSwatch);
    updateSwatch();
  });

  // -----------------------------------------------------------------------
  // Tab 3 — edit row custom dropdown
  // Data passed via data-* attributes on #tab3EditValuesDropdown:
  //   data-values        JSON array [{id, name}, ...]
  //   data-options-type  string  (e.g. "color_picker")
  //   data-selected-name string
  //   data-selected-id   int
  // -----------------------------------------------------------------------
  function initTab3EditValues() {
    var dropdown = document.getElementById('tab3EditValuesDropdown');
    if (!dropdown) return;

    var ajaxUrl = dropdown.getAttribute('data-ajax-url') || '';
    var valuesData = JSON.parse(dropdown.getAttribute('data-values') || '[]');
    var currentOptionsType = dropdown.getAttribute('data-options-type') || '';
    var selectedName = dropdown.getAttribute('data-selected-name') || '';
    var selectedId = parseInt(dropdown.getAttribute('data-selected-id') || '0', 10);

    var optionsSelect = document.getElementById('tab3EditOptionsId');
    var nativeSelect = document.getElementById('tab3EditValuesSelect');
    var display = document.getElementById('tab3EditValuesDisplay');
    var swatch = document.getElementById('tab3EditValuesSwatch');
    var label = document.getElementById('tab3EditValuesLabel');
    var list = document.getElementById('tab3EditValuesList');
    var isOpen = false;

    function isColorPicker() { return currentOptionsType === 'color_picker'; }
    function isHex(str) { return /^#?[0-9a-fA-F]{3,6}$/.test(str); }

    function renderSwatch(name, swEl) {
      if (isColorPicker() && isHex(name)) {
        swEl.style.background = name.startsWith('#') ? name : '#' + name;
        swEl.style.display = 'inline-block';
      } else {
        swEl.style.background = 'transparent';
        swEl.style.display = 'none';
      }
    }

    function buildList() {
      list.innerHTML = '';
      valuesData.forEach(function (v) {
        var item = document.createElement('div');
        item.style.cssText = 'display:flex;align-items:center;gap:6px;padding:5px 8px;cursor:pointer;';
        item.addEventListener('mouseover', function () { this.style.background = '#f0f0f0'; });
        item.addEventListener('mouseout', function () { this.style.background = ''; });
        var sw = document.createElement('span');
        sw.style.cssText = 'display:inline-block;width:18px;height:18px;border:1px solid #ccc;border-radius:3px;flex-shrink:0;';
        renderSwatch(v.name, sw);
        var txt = document.createElement('span');
        txt.textContent = v.name;
        item.appendChild(sw);
        item.appendChild(txt);
        item.addEventListener('click', function () {
          nativeSelect.value = v.id;
          label.textContent = v.name;
          renderSwatch(v.name, swatch);
          closeList();
        });
        list.appendChild(item);
      });
    }

    function rebuildNativeSelect() {
      nativeSelect.innerHTML = '';
      valuesData.forEach(function (v) {
        var opt = document.createElement('option');
        opt.value = v.id;
        opt.textContent = v.name;
        nativeSelect.appendChild(opt);
      });
    }

    function applyFirstValue() {
      if (valuesData.length > 0) {
        nativeSelect.value = valuesData[0].id;
        label.textContent = valuesData[0].name;
        renderSwatch(valuesData[0].name, swatch);
      } else {
        nativeSelect.value = '';
        label.textContent = '';
        renderSwatch('', swatch);
      }
    }

    // Fetch values associated with the chosen option via the bridge table
    // and reset selection (the previous selected value belonged to the old
    // option family, no longer relevant).
    function fetchValuesForOption(optionsId) {
      if (!ajaxUrl || !optionsId) {
        valuesData = [];
        rebuildNativeSelect();
        applyFirstValue();
        if (isOpen) buildList();
        return;
      }

      var url = ajaxUrl + (ajaxUrl.indexOf('?') >= 0 ? '&' : '?') + 'options_id=' + encodeURIComponent(optionsId);
      var xhr = new XMLHttpRequest();
      xhr.open('GET', url, true);
      xhr.onload = function () {
        if (xhr.status !== 200) {
          console.error('tab3EditValues: cascade AJAX failed', xhr.status, url);
          return;
        }
        try {
          var data = JSON.parse(xhr.responseText);
          valuesData = (data && data.values) || [];
          currentOptionsType = (data && data.type) || '';
          dropdown.setAttribute('data-options-type', currentOptionsType);
        } catch (e) {
          console.error('tab3EditValues: invalid JSON response', e);
          valuesData = [];
        }
        rebuildNativeSelect();
        applyFirstValue();
        if (isOpen) buildList();
      };
      xhr.send();
    }

    function openList() { buildList(); list.style.display = 'block'; isOpen = true; }
    function closeList() { list.style.display = 'none'; isOpen = false; }

    display.addEventListener('click', function (e) { e.stopPropagation(); isOpen ? closeList() : openList(); });
    document.addEventListener('click', closeList);

    // Initial state: keep the server-rendered selection (admin opens edit
    // row, sees the current value/option pair)
    nativeSelect.value = selectedId;
    label.textContent = selectedName;
    renderSwatch(selectedName, swatch);

    // On option change, refresh the values list via AJAX
    if (optionsSelect) {
      optionsSelect.addEventListener('change', function () {
        fetchValuesForOption(this.value);
      });
    }
  }

  // -----------------------------------------------------------------------
  // Tab 3 — insert row custom dropdown
  // Data passed via data-values (JSON) on #tab3ValuesDropdown
  // -----------------------------------------------------------------------
  function initTab3InsertValues() {
    var dropdown = document.getElementById('tab3ValuesDropdown');
    if (!dropdown) return;

    var ajaxUrl = dropdown.getAttribute('data-ajax-url') || '';
    var valuesData = JSON.parse(dropdown.getAttribute('data-values') || '[]');
    var optionsSelect = document.getElementById('tab3InsertOptionsId');
    var nativeSelect = document.getElementById('tab3ValuesSelect');
    var display = document.getElementById('tab3ValuesDisplay');
    var swatch = document.getElementById('tab3ValuesSwatch');
    var label = document.getElementById('tab3ValuesLabel');
    var list = document.getElementById('tab3ValuesList');
    var isOpen = false;

    function isColorPicker() {
      var opt = optionsSelect ? optionsSelect.options[optionsSelect.selectedIndex] : null;
      return opt && opt.getAttribute('data-type') === 'color_picker';
    }

    function isHex(str) { return /^#?[0-9a-fA-F]{3,6}$/.test(str); }

    function renderSwatch(name, swatchEl) {
      if (isColorPicker() && isHex(name)) {
        var hex = name.startsWith('#') ? name : '#' + name;
        swatchEl.style.background = hex;
        swatchEl.style.display = 'inline-block';
      } else {
        swatchEl.style.background = 'transparent';
        swatchEl.style.display = 'none';
      }
    }

    function buildList() {
      list.innerHTML = '';
      valuesData.forEach(function (v) {
        var item = document.createElement('div');
        item.style.cssText = 'display:flex;align-items:center;gap:6px;padding:5px 8px;cursor:pointer;';
        item.addEventListener('mouseover', function () { this.style.background = '#f0f0f0'; });
        item.addEventListener('mouseout', function () { this.style.background = ''; });
        var sw = document.createElement('span');
        sw.style.cssText = 'display:inline-block;width:18px;height:18px;border:1px solid #ccc;border-radius:3px;flex-shrink:0;';
        renderSwatch(v.name, sw);
        var txt = document.createElement('span');
        txt.textContent = v.name;
        item.appendChild(sw);
        item.appendChild(txt);
        item.addEventListener('click', function () {
          nativeSelect.value = v.id;
          label.textContent = v.name;
          renderSwatch(v.name, swatch);
          closeList();
        });
        list.appendChild(item);
      });
    }

    function rebuildNativeSelect() {
      nativeSelect.innerHTML = '';
      valuesData.forEach(function (v) {
        var opt = document.createElement('option');
        opt.value = v.id;
        opt.textContent = v.name;
        nativeSelect.appendChild(opt);
      });
    }

    function applyFirstValue() {
      if (valuesData.length > 0) {
        nativeSelect.value = valuesData[0].id;
        label.textContent = valuesData[0].name;
        renderSwatch(valuesData[0].name, swatch);
      } else {
        nativeSelect.value = '';
        label.textContent = '';
        renderSwatch('', swatch);
      }
    }

    function fetchValuesForOption(optionsId) {
      if (!ajaxUrl || !optionsId) {
        valuesData = [];
        rebuildNativeSelect();
        applyFirstValue();
        if (isOpen) buildList();
        return;
      }

      var url = ajaxUrl + (ajaxUrl.indexOf('?') >= 0 ? '&' : '?') + 'options_id=' + encodeURIComponent(optionsId);
      var xhr = new XMLHttpRequest();
      xhr.open('GET', url, true);
      xhr.onload = function () {
        if (xhr.status !== 200) {
          console.error('tab3InsertValues: cascade AJAX failed', xhr.status, url);
          return;
        }
        try {
          var data = JSON.parse(xhr.responseText);
          valuesData = (data && data.values) || [];
        } catch (e) {
          console.error('tab3InsertValues: invalid JSON response', e);
          valuesData = [];
        }
        rebuildNativeSelect();
        applyFirstValue();
        if (isOpen) buildList();
      };
      xhr.send();
    }

    function openList() { buildList(); list.style.display = 'block'; isOpen = true; }
    function closeList() { list.style.display = 'none'; isOpen = false; }

    display.addEventListener('click', function (e) {
      e.stopPropagation();
      isOpen ? closeList() : openList();
    });
    document.addEventListener('click', closeList);

    if (optionsSelect) {
      optionsSelect.addEventListener('change', function () {
        fetchValuesForOption(this.value);
      });

      // Initial sync with the default-selected option
      fetchValuesForOption(optionsSelect.value);
    } else {
      applyFirstValue();
    }
  }

  // -----------------------------------------------------------------------
  // Boot
  // -----------------------------------------------------------------------
  initTab2InsertColorPicker();
  initTab3EditValues();
  initTab3InsertValues();

}());
