<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Tools\DataBaseTables\Classes\Database;

$CLICSHOPPING_DataBaseTables = Registry::get('DataBaseTables');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');


Registry::set('Database', new Database());
$CLICSHOPPING_Database = Registry::get('Database');

$mysql_charsets = [
  [
    'id' => 'auto',
    'text' => $CLICSHOPPING_DataBaseTables->getDef('action_utf8_conversion_from_autodetect')
  ]
];

$Qcharsets = $CLICSHOPPING_DataBaseTables->db->query('SHOW CHARACTER SET');

while ($Qcharsets->fetch()) {
  $mysql_charsets[] = [
    'id' => $Qcharsets->value('Charset'),
    'text' => $CLICSHOPPING_DataBaseTables->getDef('action_utf8_conversion_from', ['char_set' => $Qcharsets->value('Charset')])
  ];
}

$action = null;
$actions = array([
  'id' => 'check',
  'text' => $CLICSHOPPING_DataBaseTables->getDef('action_check_tables')
],
  ['id' => 'analyze',
    'text' => $CLICSHOPPING_DataBaseTables->getDef('action_analyze_tables')
  ],
  ['id' => 'optimize',
    'text' => $CLICSHOPPING_DataBaseTables->getDef('action_optimize_tables')
  ],
  ['id' => 'repair',
    'text' => $CLICSHOPPING_DataBaseTables->getDef('action_repair_tables')
  ],
  ['id' => 'utf8',
    'text' => $CLICSHOPPING_DataBaseTables->getDef('action_utf8_conversion')
  ]);

if (isset($_POST['action'])) {
  if (\in_array($_POST['action'], array('check', 'analyze', 'optimize', 'repair', 'utf8'))) {
    if (isset($_POST['id']) && \is_array($_POST['id']) && !empty($_POST['id'])) {
      $tables = Database::getDtTables();

      foreach ($_POST['id'] as $key => $value) {
        if (!\in_array($value, $tables)) {
          unset($_POST['id'][$key]);
        }
      }

      if (!empty($_POST['id'])) {
        $action = $_POST['action'];
      }
    }
  }
}

switch ($action) {
  case 'check':
  case 'analyze':
  case 'optimize':
  case 'repair':
    set_time_limit(0);

    $table_headers = [
      $CLICSHOPPING_DataBaseTables->getDef('table_heading_table'),
      $CLICSHOPPING_DataBaseTables->getDef('table_heading_msg_type'),
      $CLICSHOPPING_DataBaseTables->getDef('table_heading_msg'),
      HTML::checkboxField('masterblaster')
    ];

    $table_data = [];

    foreach ($_POST['id'] as $table) {
      $current_table = null;

      $Qaction = $CLICSHOPPING_DataBaseTables->db->query($action . ' table ' . $table);

      while ($Qaction->fetch()) {
        $table_data[] = [
          ($table != $current_table) ? HTML::outputProtected($table) : '',
          $Qaction->valueProtected('Msg_type'),
          $Qaction->valueProtected('Msg_text'),
          ($table != $current_table) ? HTML::checkboxField('id[]', $table, isset($_POST['id']) && \in_array($table, $_POST['id'])) : ''
        ];

        $current_table = $table;
      }
    }

    break;

  case 'utf8':
    $charset_pass = false;

    if (isset($_POST['from_charset'])) {
      if ($_POST['from_charset'] == 'auto') {
        $charset_pass = true;
      } else {
        foreach ($mysql_charsets as $c) {
          if ($_POST['from_charset'] == $c['id']) {
            $charset_pass = true;
            break;
          }
        }
      }
    }

    if ($charset_pass === false) {
      $CLICSHOPPING_DataBaseTables->redirect('DataBaseTables');
    }

    set_time_limit(0);

    if (isset($_POST['dryrun'])) {
      $table_headers = array($CLICSHOPPING_DataBaseTables->getDef('table_heading_queries'));
    } else {
      $table_headers = array($CLICSHOPPING_DataBaseTables->getDef('table_heading_table'),
        $CLICSHOPPING_DataBaseTables->getDef('table_heading_msg'),
        HTML::checkboxField('masterblaster')
      );
    }

    $table_data = [];

    foreach ($_POST['id'] as $table) {
      $result = 'OK';

      $queries = [];

      $Qcols = $CLICSHOPPING_DataBaseTables->db->query('SHOW FULL COLUMNS FROM ' . $table);

      while ($Qcols->fetch()) {
        if ($Qcols->hasValue('Collation') && !\is_null($Qcols->value('Collation'))) {
          // Skip VECTOR columns - they are binary data and cannot be converted to UTF8
          $columnType = strtolower($Qcols->value('Type'));
          if (str_contains($columnType, 'vector')) {
            continue;
          }
          
          if ($_POST['from_charset'] == 'auto') {
            $old_charset = substr($Qcols->value('Collation'), 0, strpos($Qcols->value('Collation'), '_'));
          } else {
            $old_charset = $_POST['from_charset'];
          }

          $queries[] = 'update ' . $table . ' set ' . $Qcols->value('Field') . ' = convert(binary convert(' . $Qcols->value('Field') . ' using ' . $old_charset . ') using utf8mb4) where char_length(' . $Qcols->value('Field') . ') = length(convert(binary convert(' . $Qcols->value('Field') . ' using ' . $old_charset . ') using utf8mb4))';
        }
      }

      $query = 'alter table ' . $table . ' convert to character set utf8mb4 collate utf8mb4_unicode_ci';

      if (isset($_POST['dryrun'])) {
        $table_data[] = array($query);

        foreach ($queries as $q) {
          $table_data[] = array($q);
        }
      } else {
        if ($CLICSHOPPING_DataBaseTables->db->exec($query) !== false) {
          foreach ($queries as $q) {
            if ($CLICSHOPPING_DataBaseTables->db->exec($q) === false) {
              $result = implode(' - ', $CLICSHOPPING_DataBaseTables->db->errorInfo());
              break;
            }
          }
        } else {
          $result = implode(' - ', $CLICSHOPPING_DataBaseTables->db->errorInfo());
        }
      }

      if (!isset($_POST['dryrun'])) {
        $table_data[] = array(HTML::outputProtected($table),
          HTML::outputProtected($result),
          HTML::checkboxField('id[]', $table, true)
        );
      }
    }

    break;

  default:
    $table_headers = [
      $CLICSHOPPING_DataBaseTables->getDef('table_heading_table'),
      $CLICSHOPPING_DataBaseTables->getDef('table_heading_rows'),
      $CLICSHOPPING_DataBaseTables->getDef('table_heading_size'),
      $CLICSHOPPING_DataBaseTables->getDef('table_heading_engine'),
      $CLICSHOPPING_DataBaseTables->getDef('table_heading_collation'),
      HTML::checkboxField('masterblaster', null, null, 'onclick="check()"')
    ];

    $table_data = [];

    $Qstatus = $CLICSHOPPING_DataBaseTables->db->query('SHOW TABLE STATUS');

    while ($Qstatus->fetch()) {
      $table_data[] = [
        $Qstatus->valueProtected('Name'),
        $Qstatus->valueProtected('Rows'),
        round(($Qstatus->value('Data_length') + $Qstatus->value('Index_length')) / 1024 / 1024, 2) . 'M',
        $Qstatus->valueProtected('Engine'),
        $Qstatus->valueProtected('Collation'),
        HTML::checkboxField('id[]', $Qstatus->value('Name'))
      ];
    }
}

echo HTML::form('sql', $CLICSHOPPING_DataBaseTables->link('DataBaseTables'));
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/database_analyse.gif', $CLICSHOPPING_DataBaseTables->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-5 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_DataBaseTables->getDef('heading_title'); ?></span>
          <?php
          if (isset($_GET['action'])) {
            $actions = HTML::sanitize($_GET['action']);
          }

          if (!isset($POST['dryrun'])) {
            if (isset($action)) {
              ?>
              <span
                class="col-md-6 text-end"><?php echo HTML::button($CLICSHOPPING_DataBaseTables->getDef('button_back'), null, $CLICSHOPPING_DataBaseTables->link('DataBaseTables'), 'primary'); ?></span>
              <?php
            } else {
              ?>
              <span
                class="col-md-2 text-end runUtf8"><?php echo HTML::selectMenu('action', $actions, '', 'id="sqlActionsMenu"') . '<span class="runUtf8" style="display: none;">&nbsp;' . HTML::selectMenu('from_charset', $mysql_charsets) . '</span>'; ?></span>
              <span
                class="col-md-4 text-end"><?php echo HTML::button($CLICSHOPPING_DataBaseTables->getDef('button_update'), null, null, 'success'); ?></span>
              <?php
            }
          }
          ?>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <table border="0" width="100%" cellspacing="0" cellpadding="2">
    <td>
      <table class="table table-sm table-hover table-striped">
        <thead>
        <tr class="dataTableHeadingRow">
          <?php
          foreach ($table_headers as $th) {
            echo '    <td>' . $th . '</td>' . "\n";
          }
          ?>
        </tr>
        <?php
        foreach ($table_data as $td) {
          echo '  <tr>' . "\n";

          foreach ($td as $data) {
            echo '    <td>' . $data . '</td>' . "\n";
          }

          echo '  </tr>' . "\n";
        }
        ?>
        </thead>
      </table>
    </td>
  </table>
  </form>
</div>

<script>
  function check() {
    var inputs = document.querySelectorAll('form[name="sql"] input[type="checkbox"]');
    if (document.querySelectorAll('form[name="sql"] input[type="checkbox"]').length > 0) {
      for (var i = 0; i < inputs.length; i++) {
        inputs[i].checked = true;
      }
    }
  }
</script>
