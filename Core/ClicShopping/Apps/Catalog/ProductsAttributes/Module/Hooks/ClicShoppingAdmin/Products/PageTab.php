<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\ProductsAttributes\Module\Hooks\ClicShoppingAdmin\Products;

use ClicShopping\Apps\Catalog\ProductsAttributes\ProductsAttributes as ProductsAttributesApp;
use ClicShopping\Apps\Customers\Groups\Classes\ClicShoppingAdmin\GroupsB2BAdmin;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

/**
 * PageTab adds an inline Products Attributes management tab inside the
 * standard product edit form. Operators can add, update or delete variants
 * without leaving the product page; persistence is deferred to the product
 * save action via the companion Insert/Update hooks, which guarantees that
 * unsaved drafts never leak rows into the database.
 */
class PageTab implements HooksInterface
{
  public mixed $app;
  private mixed $db;
  private mixed $lang;
  private mixed $template;

  public function __construct()
  {
    if (!Registry::exists('ProductsAttributes')) {
      Registry::set('ProductsAttributes', new ProductsAttributesApp());
    }

    $this->app = Registry::get('ProductsAttributes');
    $this->db = $this->app->db;
    $this->lang = Registry::get('Language');
    $this->template = Registry::get('TemplateAdmin');
  }

  /**
   * Render the inline attributes tab. Returns false (and renders nothing)
   * when the ProductsAttributes app is disabled.
   */
  public function display(): string|false
  {
    if (!\defined('CLICSHOPPING_APP_PRODUCTS_ATTRIBUTES_PA_STATUS') || CLICSHOPPING_APP_PRODUCTS_ATTRIBUTES_PA_STATUS === 'False') {
      return false;
    }

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Products/page_tab');

    $products_id = isset($_GET['pID']) ? (int)HTML::sanitize($_GET['pID']) : 0;

    $options_html = $this->buildOptionsSelectHtml();
    $empty_values_html = $this->buildValuesSelectHtml(0);
    $price_prefix_options = $this->buildPricePrefixOptions();
    $status_options = $this->buildStatusOptions();
    $customers_group_options = $this->buildCustomersGroupOptions();

    $existing_rows = $this->loadExistingRows($products_id);
    $rows_html = '';
    $row_index = 0;

    foreach ($existing_rows as $row) {
      $row_options_id = (int)($row['options_id'] ?? 0);
      $row_values_html = $row_options_id > 0
        ? $this->buildValuesSelectHtml($row_options_id)
        : $empty_values_html;
      $rows_html .= $this->renderRow($row_index, $row, $options_html, $row_values_html, $price_prefix_options, $status_options, $customers_group_options);
      $row_index++;
    }

    $template_row_html = $this->renderRow('__INDEX__', null, $options_html, $empty_values_html, $price_prefix_options, $status_options, $customers_group_options);

    $tab_title = $this->app->getDef('tab_products_attributes_inline');
    $title = $this->app->getDef('text_products_attributes_inline');
    $help_title = $this->app->getDef('text_help_general');
    $help_text = $this->app->getDef('text_help_attributes_inline');
    $button_add = $this->app->getDef('button_add');
    $help_icon = HTML::image($this->template->getImageDirectory() . 'icons/help.gif', $help_title);
    $b2b_visible_class = (\defined('MODE_B2B_B2C') && MODE_B2B_B2C === 'True') ? '' : 'd-none';
    $headers = $this->renderHeaders();

    $ajax_url = CLICSHOPPING::link('ajax/Products/products_attributes_inline.php');
    $js_src = CLICSHOPPING::link('Shop/ext/javascript/clicshopping/ClicShoppingAdmin/Products/products_attributes_inline.js');

    $labels_json = json_encode([
      'selectValue' => $this->app->getDef('text_select_value'),
      'confirmRemove' => $this->app->getDef('js_confirm_remove'),
    ], JSON_UNESCAPED_UNICODE);

    $output = <<<EOD
<!-- ###################################### -->
<!-- Start ProductsAttributesInline tab     -->
<!-- ###################################### -->
<div class="tab-pane" id="section_ProductsAttributesInline_content">
  <div class="mainTitle">
    <span class="col-md-12">{$title}</span>
  </div>
  <div class="adminformTitle">
    <div class="mt-1"></div>
    <div class="table-responsive">
      <table class="table table-sm table-hover table-striped" data-role="pa-inline-table">
        <thead>
          <tr>{$headers}</tr>
        </thead>
        <tbody>
{$rows_html}
        </tbody>
        <tfoot>
          <tr>
            <td colspan="11" class="text-end">
              <button type="button" class="btn btn-primary btn-sm" data-role="add">
                <i class="bi bi-plus-circle-fill"></i> {$button_add}
              </button>
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
    <div class="mt-1"></div>
    <div class="alert alert-info" role="alert">
      <div>{$help_icon}&nbsp;{$help_title}</div>
      <div class="mt-1"></div>
      <div>{$help_text}</div>
    </div>
  </div>

  <script type="text/template" data-role="pa-inline-template">
{$template_row_html}
  </script>
</div>

<script>
\$('#section_ProductsAttributesInline_content').appendTo('#productsTabs .tab-content');
\$('#productsTabs .nav-tabs').append('    <li class="nav-item"><a data-bs-target="#section_ProductsAttributesInline_content" role="tab" data-bs-toggle="tab" class="nav-link">{$tab_title}</a></li>');

window.ProductsAttributesInline = {
  ajaxUrl: '{$ajax_url}',
  initialRows: {$row_index},
  labels: {$labels_json}
};

document.querySelectorAll('#section_ProductsAttributesInline_content .pa-inline-b2b').forEach(function (el) {
  if ('{$b2b_visible_class}' === 'd-none') { el.classList.add('d-none'); }
});
</script>
<script src="{$js_src}"></script>
<!-- ###################################### -->
<!-- End ProductsAttributesInline tab       -->
<!-- ###################################### -->
EOD;

    return $output;
  }

  /**
   * Render the table header row.
   */
  private function renderHeaders(): string
  {
    $cells = [
      $this->app->getDef('table_heading_id'),
      $this->app->getDef('table_heading_image'),
      $this->app->getDef('table_heading_reference'),
      $this->app->getDef('table_heading_option'),
      $this->app->getDef('table_heading_value'),
      $this->app->getDef('table_heading_customers_group'),
      $this->app->getDef('table_heading_price'),
      $this->app->getDef('table_heading_price_prefix'),
      $this->app->getDef('table_heading_status'),
      $this->app->getDef('table_heading_sort_order'),
      $this->app->getDef('table_heading_action'),
    ];

    $html = '';
    $cg_idx = 5;

    foreach ($cells as $i => $label) {
      $extra = ($i === $cg_idx) ? ' class="pa-inline-b2b"' : '';
      $html .= '<th' . $extra . '>' . $label . '</th>';
    }

    return $html;
  }

  /**
   * Render one editable row. $row is null for the JS template row.
   *
   * @param int|string $index Row index (or '__INDEX__' for the template).
   * @param array|null $row Existing DB row, or null for a blank line.
   */
  private function renderRow(int|string $index, ?array $row, string $options_html, string $values_html, string $prefix_options, string $status_options, string $customers_group_options): string
  {
    $name_prefix = 'products_attributes_inline[' . $index . ']';
    $file_name = 'products_attributes_inline_image_' . $index;

    $id = $row['products_attributes_id'] ?? '';
    $image = $row['products_attributes_image'] ?? '';
    $reference = $row['products_attributes_reference'] ?? '';
    $option_selected = $row['options_id'] ?? '';
    $value_selected = $row['options_values_id'] ?? '';
    $customers_group_id = (string)($row['customers_group_id'] ?? '0');
    $price = $row['options_values_price'] ?? '';
    $prefix = $row['price_prefix'] ?? '+';
    $status = (string)($row['status'] ?? '1');
    $sort_order = $row['products_options_sort_order'] ?? '1';

    $options_html_for_row = $this->markSelected($options_html, (string)$option_selected);
    $values_html_for_row = $this->markSelected($values_html, (string)$value_selected);
    $prefix_html = $this->markSelected($prefix_options, $prefix);
    $status_html = $this->markSelected($status_options, $status);
    $customers_group_html = $this->markSelected($customers_group_options, $customers_group_id);

    $image_preview = '';
    if ($image !== '' && $image !== null) {
      $CLICSHOPPING_ProductsAdmin = Registry::get('ProductsAdmin');
      $image_preview = '<div>' . $CLICSHOPPING_ProductsAdmin->getInfoImage($image, '', '50', '50') . '</div>';
    }

    return '
<tr data-role="pa-inline-row">
  <td>
    ' . ($id !== '' ? HTML::outputProtected((string)$id) : '') . '
    <input type="hidden" name="' . $name_prefix . '[id]" data-role="id" value="' . HTML::outputProtected((string)$id) . '" />
    <input type="hidden" name="' . $name_prefix . '[_delete]" data-role="delete" value="0" />
    <input type="hidden" name="' . $name_prefix . '[image_existing]" value="' . HTML::outputProtected((string)$image) . '" />
  </td>
  <td>
    ' . $image_preview . '
    <input type="file" name="' . $file_name . '" data-role="image" class="form-control form-control-sm" accept="image/*" />
    <small data-role="image-preview" class="text-muted"></small>
  </td>
  <td>
    <input type="text" name="' . $name_prefix . '[reference]" value="' . HTML::outputProtected((string)$reference) . '" class="form-control form-control-sm" />
  </td>
  <td>
    <select name="' . $name_prefix . '[options_id]" data-role="options" class="form-control form-control-sm">' . $options_html_for_row . '</select>
  </td>
  <td>
    <div style="display:flex;align-items:center;gap:6px;">
      <span data-role="value-swatch" style="display:none;width:18px;height:18px;border:1px solid #ccc;border-radius:3px;flex-shrink:0;"></span>
      <select name="' . $name_prefix . '[options_values_id]" data-role="values" class="form-control form-control-sm">' . $values_html_for_row . '</select>
    </div>
  </td>
  <td class="pa-inline-b2b">
    <select name="' . $name_prefix . '[customers_group_id]" class="form-control form-control-sm">' . $customers_group_html . '</select>
  </td>
  <td>
    <input type="text" name="' . $name_prefix . '[value_price]" value="' . HTML::outputProtected((string)$price) . '" class="form-control form-control-sm" style="width:90px;" />
  </td>
  <td>
    <select name="' . $name_prefix . '[price_prefix]" class="form-control form-control-sm" style="width:60px;">' . $prefix_html . '</select>
  </td>
  <td>
    <select name="' . $name_prefix . '[status]" class="form-control form-control-sm">' . $status_html . '</select>
  </td>
  <td>
    <input type="text" name="' . $name_prefix . '[sort_order]" value="' . HTML::outputProtected((string)$sort_order) . '" class="form-control form-control-sm" style="width:70px;" />
  </td>
  <td class="text-end">
    <button type="button" class="btn btn-danger btn-sm" data-role="remove" title="' . $this->app->getDef('button_remove') . '"><i class="bi bi-dash-circle-fill"></i></button>
    <button type="button" class="btn btn-secondary btn-sm" data-role="restore" title="' . $this->app->getDef('button_restore') . '" style="display:none;"><i class="bi bi-arrow-counterclockwise"></i></button>
  </td>
</tr>';
  }

  /**
   * Pre-render the <option> list for the options select, used as-is in
   * every row and in the JS template. Each option carries data-type so
   * the client can render color swatches for color_picker without an
   * extra round-trip.
   */
  private function buildOptionsSelectHtml(): string
  {
    $Qoptions = $this->db->prepare('select products_options_id, products_options_name, products_options_type
                                      from :table_products_options
                                      where language_id = :language_id
                                      order by products_options_name
                                    ');
    $Qoptions->bindInt(':language_id', (int)$this->lang->getId());
    $Qoptions->execute();

    $html = '<option value="">' . $this->app->getDef('text_select_option') . '</option>';

    while ($Qoptions->fetch()) {
      $html .= '<option value="' . $Qoptions->valueInt('products_options_id') . '" data-type="' . HTML::outputProtected($Qoptions->value('products_options_type')) . '">' . HTML::outputProtected($Qoptions->value('products_options_name')) . '</option>';
    }

    return $html;
  }

  /**
   * Pre-render the <option> list for the values select, filtered by the
   * bridge table so that only values associated with the given option
   * are displayed (e.g. only colors for the Color option). When called
   * with $options_id === 0, only the placeholder is returned — useful
   * for new template rows where no option has been picked yet.
   */
  private function buildValuesSelectHtml(int $options_id): string
  {
    $html = '<option value="">' . $this->app->getDef('text_select_value') . '</option>';

    if ($options_id <= 0) {
      return $html;
    }

    $Qvalues = $this->db->prepare('select distinct pov.products_options_values_id, pov.products_options_values_name
                                     from :table_products_options_values pov
                                     inner join :table_products_options_values_to_products_options pov2po
                                            on pov.products_options_values_id = pov2po.products_options_values_id
                                    where pov.language_id = :language_id
                                      and pov2po.products_options_id = :options_id
                                    order by pov.products_options_values_name
                                  ');
    $Qvalues->bindInt(':language_id', (int)$this->lang->getId());
    $Qvalues->bindInt(':options_id', $options_id);
    $Qvalues->execute();

    while ($Qvalues->fetch()) {
      $html .= '<option value="' . $Qvalues->valueInt('products_options_values_id') . '">' . HTML::outputProtected($Qvalues->value('products_options_values_name')) . '</option>';
    }

    return $html;
  }

  /**
   * Render the price prefix select options (+ / -).
   */
  private function buildPricePrefixOptions(): string
  {
    return '<option value="+">+</option><option value="-">-</option>';
  }

  /**
   * Render the per-row status select options.
   */
  private function buildStatusOptions(): string
  {
    return '<option value="1">' . $this->app->getDef('text_status_active') . '</option>'
      . '<option value="0">' . $this->app->getDef('text_status_inactive') . '</option>';
  }

  /**
   * Render the customers group select options. Always rendered: the cell
   * itself is hidden via CSS when MODE_B2B_B2C is False.
   */
  private function buildCustomersGroupOptions(): string
  {
    $html = '';
    $groups = GroupsB2BAdmin::getAllGroups();

    foreach ($groups as $group) {
      $html .= '<option value="' . HTML::outputProtected((string)$group['id']) . '">' . HTML::outputProtected($group['text']) . '</option>';
    }

    return $html;
  }

  /**
   * Add a "selected" attribute to the matching <option> in a pre-rendered
   * options list. Used to bind a current value without re-querying the DB.
   */
  private function markSelected(string $options_html, string $value): string
  {
    if ($value === '') {
      return $options_html;
    }

    $needle = 'value="' . $value . '"';
    $pos = strpos($options_html, $needle);

    if ($pos === false) {
      return $options_html;
    }

    return substr_replace($options_html, $needle . ' selected', $pos, strlen($needle));
  }

  /**
   * Load existing variant rows for a product. Uses select * so the query
   * tolerates schemas where the optional status column has not yet been
   * added by ProductsAttributes/Sql/MariaDb/MariaDb::installDb(). The
   * value name lookup is deferred to a second pass to keep this query
   * resilient against language join misses.
   *
   * @return array<int, array<string, mixed>>
   */
  private function loadExistingRows(int $products_id): array
  {
    if ($products_id <= 0) {
      return [];
    }

    $Qrows = $this->db->prepare('select *
                                   from :table_products_attributes
                                  where products_id = :products_id
                                  order by products_options_sort_order, products_attributes_id
                                 ');
    $Qrows->bindInt(':products_id', $products_id);
    $Qrows->execute();

    $rows = $Qrows->fetchAll();

    if (empty($rows)) {
      return [];
    }

    $value_names = $this->fetchValueNames(array_column($rows, 'options_values_id'));

    foreach ($rows as $key => $row) {
      $vid = (int)($row['options_values_id'] ?? 0);
      $rows[$key]['options_values_name'] = $value_names[$vid] ?? '';
    }

    return $rows;
  }

  /**
   * Bulk-fetch option value names for the current language.
   *
   * @param array<int|string> $ids
   * @return array<int, string>
   */
  private function fetchValueNames(array $ids): array
  {
    $ids = array_unique(array_filter(array_map('intval', $ids)));

    if (empty($ids)) {
      return [];
    }

    $placeholders = implode(',', $ids);

    $Qnames = $this->db->prepare('select products_options_values_id, products_options_values_name
                                    from :table_products_options_values
                                   where language_id = :language_id
                                     and products_options_values_id in (' . $placeholders . ')
                                 ');
    $Qnames->bindInt(':language_id', (int)$this->lang->getId());
    $Qnames->execute();

    $map = [];

    while ($Qnames->fetch()) {
      $map[$Qnames->valueInt('products_options_values_id')] = $Qnames->value('products_options_values_name');
    }

    return $map;
  }
}
