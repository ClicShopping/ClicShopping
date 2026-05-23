<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_DefineLanguage = Registry::get('DefineLanguage');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');

$CLICSHOPPING_Page = Registry::get('Site')->getPage();

if (isset($_GET['search']) && !empty($_GET['search'])) {
  $search = HTML::sanitize($_GET['search']);
}

$languages = $CLICSHOPPING_Language->getLanguages();

if ($CLICSHOPPING_MessageStack->exists('main')) {
  echo $CLICSHOPPING_MessageStack->get('main');
}
$content_group = HTML::sanitize($_GET['ContentGroup']);

echo HTML::form('define_language', $CLICSHOPPING_DefineLanguage->link('DefineLanguage&Save&ContentGroup=' . $content_group, 'post', 'enctype="multipart/form-data"'));
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/define_language.gif', $CLICSHOPPING_DefineLanguage->getDef('heading_title_2'), '40', '40'); ?></span>
          <span
            class="col-md-6 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_DefineLanguage->getDef('heading_title'); ?></span>
          <span class="col-md-5 text-end">
            <?php
            echo '&nbsp;';
            echo HTML::button($CLICSHOPPING_DefineLanguage->getDef('button_back'), null, $CLICSHOPPING_DefineLanguage->link('DefineLanguage'), 'primary') . ' ' . HTML::button($CLICSHOPPING_DefineLanguage->getDef('button_save'), null, null, 'success') . ' ';
            ?>
           </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="alert alert-info" role="alert" id="alert">
    <?php echo $CLICSHOPPING_DefineLanguage->getDef('alert_info_language'); ?>
  </div>
  <ul class="nav nav-tabs flex-column flex-sm-row" role="tablist" id="myTab">
    <?php
    for ($i = 0, $n = \count($languages); $i < $n; $i++) {
      echo '<li class="nav-item " ' . ($i === 0 ? 'active"' : '') . '><a href="#tab' . $i . '" data-bs-target="#section_general_content_' . $languages[$i]['directory'] . '" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_Language->getImage($languages[$i]['code']) . '&nbsp;' . $languages[$i]['name'] . '</a></li>';
    }

    echo '<li class="nav-item"><a data-bs-target="#section_general_content_translation_tab" role="tab" data-bs-toggle="tab" class="nav-link">' . HTML::button($CLICSHOPPING_DefineLanguage->getDef('section_heading_translations'), null, null, 'primary', null, 'sm') . '</a></li>';
    ?>
  </ul>

  <div class="tabsClicShopping">
    <div class="tab-content">
      <?php
      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        ?>
        <div class="adminformTitle tab-pane <?php echo($i === 0 ? 'active' : ''); ?>"
             id="section_general_content_<?php echo $languages[$i]['directory']; ?>">
          <table class="table table-hover">
            <thead>
            <tr class="dataTableHeadingRow">
              <th width="50%"><?php echo $CLICSHOPPING_DefineLanguage->getDef('table_heading_definition_key'); ?></th>
              <th><?php echo $CLICSHOPPING_DefineLanguage->getDef('table_heading_definition_value'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php
            if (isset($search)) {
              $Qdefinitions = $CLICSHOPPING_DefineLanguage->db->prepare("select definition_key,
                                                                                    definition_value
                                                                            from :table_languages_definitions
                                                                            where content_group = :content_group
                                                                            and (definition_key like  " . "'%" . $search . "%'" . " or definition_value like  " . "'%" . $search . "%'" . ")
                                                                            and languages_id = :languages_id
                                                                            order by definition_key
                                                                          ");
            } else {
              $Qdefinitions = $CLICSHOPPING_DefineLanguage->db->prepare('select  id,
                                                                                     definition_key,
                                                                                     definition_value
                                                                             from :table_languages_definitions
                                                                             where content_group = :content_group
                                                                             and languages_id = :languages_id
                                                                             order by definition_key
                                                                            ');
            }

            $Qdefinitions->bindValue(':content_group', $content_group);
            $Qdefinitions->bindInt(':languages_id', $languages[$i]['id']);
            $Qdefinitions->execute();

            while ($Qdefinitions->fetch()) {
              ?>
              <tr>
                <td style="word-break: break-all;"><?php echo $Qdefinitions->value('definition_key'); ?></td>
                <td><?php echo htmlentities($Qdefinitions->value('definition_value'), ENT_QUOTES | ENT_HTML5); ?></td>
              </tr>
              <?php
            }
            ?>
            </tbody>
          </table>
        </div>
        <?php
      }
      ?>
      <style>
        .table-hover > tbody > tr.new_definition_row:hover > td,
        .new_definition_row > td {
          background-color: #bddef9;
        }
      </style>
      <div id="section_general_content_translation_tab" class="tab-pane">
        <table class="table table-hover">
          <thead>
          <tr class="dataTableHeadingRow">
            <th width="50%"><?php echo $CLICSHOPPING_DefineLanguage->getDef('table_heading_definition_key'); ?></th>
            <th><?php echo $CLICSHOPPING_DefineLanguage->getDef('table_heading_definition_value'); ?></th>
          </tr>
          </thead>

          <tbody>
          <?php
          if (isset($search)) {
            $Qdefinitions = $CLICSHOPPING_DefineLanguage->db->prepare('select distinct definition_key
                                                                          from :table_languages_definitions
                                                                          where content_group = :content_group
                                                                          and (definition_key like :search or definition_value like :search)
                                                                          order by definition_key
                                                                         ');
            $Qdefinitions->bindValue(':search', '%"' . $search . '"%');
          } else {
            $Qdefinitions = $CLICSHOPPING_DefineLanguage->db->prepare('select distinct definition_key
                                                                          from :table_languages_definitions
                                                                          where content_group = :content_group
                                                                          order by definition_key
                                                                         ');
          }

          $Qdefinitions->bindValue(':content_group', $content_group);
          $Qdefinitions->execute();

          while ($Qdefinitions->fetch()) {
            ?>
            <tr class="<?php echo $Qdefinitions->value('definition_key'); ?>">
              <td style="word-break: break-all;"><?php echo $Qdefinitions->value('definition_key'); ?><br/><span
                  style="cursor:pointer"
                  onclick="DeleteDef('<?php echo $Qdefinitions->value('definition_key'); ?>')"><?php echo '<h4><i class="bi bi-trash2" title="' . $CLICSHOPPING_DefineLanguage->getDef('icon_delete') . '"></i></h4>'; ?></span>
              </td>
              <td>
                <?php
                for ($i = 0, $n = \count($languages); $i < $n; $i++) {
                  $Tdefinitions = $CLICSHOPPING_DefineLanguage->db->prepare('select definition_key,
                                                                                        definition_value
                                                                                from :table_languages_definitions
                                                                                where content_group = :content_group
                                                                                and languages_id = :languages_id
                                                                                and definition_key = :definition_key
                                                                                order by languages_id
                                                                              ');
                  $Tdefinitions->bindValue(':content_group', $content_group);
                  $Tdefinitions->bindValue(':definition_key', $Qdefinitions->value('definition_key'));
                  $Tdefinitions->bindInt(':languages_id', $languages[$i]['id']);
                  $Tdefinitions->execute();
                  ?>
                  <br/><?php echo '<p class="text-info"><i><small>' . $languages[$i]['name'] . '</small></i></p>';
                  if ($Tdefinitions->fetch() !== false) {
                    do {
                      ?>
                      <textarea class="form-control"
                                name="definition_value[<?php echo $Tdefinitions->value('definition_key'); ?>][<?php echo $languages[$i]['id']; ?>]"><?php echo htmlentities($Tdefinitions->value('definition_value'), ENT_QUOTES | ENT_HTML5); ?></textarea>
                      <?php
                    } while ($Tdefinitions->fetch());
                  } else {
                    ?>
                    <span style="cursor:pointer"
                          onclick="NewDef('definition_value[<?php echo $Qdefinitions->value('definition_key') . '][' . $languages[$i]['id'] . ']\', this)'; ?>"><i
                        class="bi bi-plus-circle-fill"
                        title="<?php echo $CLICSHOPPING_DefineLanguage->getDef('icon_add_new'); ?>"></i></span>
                    <?php
                  }
                }
                ?>
              </td>
            </tr>
            <?php
          }
          ?>
          <tr class="new_definition_row">
            <td>
              <div>
                <div class="input-group">
                  <div class="input-group-addon"><i class="bi bi-plus-circle-fill" aria-hidden="true"></i>&nbsp;&nbsp;
                  </div>
                  <?php echo HTML::inputField('new_definition_key', '', 'size="50" placeholder="' . $CLICSHOPPING_DefineLanguage->getDef('placeholder_new_definition_value') . '" maxlength="255" pattern="^[a-z0-9_]{1,255}$"'); ?>
                </div>
              </div>
              <?php echo $CLICSHOPPING_DefineLanguage->getDef('text_pattern'); ?> [a-z0-9_]
            </td>
            <td>
              <?php
              for ($i = 0, $n = \count($languages); $i < $n; $i++) {
                ?>
                <br/>
                <p class="text-info"><strong><i>
                      <small><?php echo $languages[$i]['name']; ?></small>
                    </i></strong></p>
                <textarea class="form-control"
                          name="new_definition_value[<?php echo $languages[$i]['id']; ?>]"></textarea>
                <?php
              }
              ?>
            </td>
          </tr>
          </tbody>
        </table>
      </div>

      <div class="modal fade" id="rowDelConfirm" tabindex="-1" role="dialog" aria-labelledby="myModalLabel"
           aria-hidden="true">
        <div class="modal-dialog" role="document">
          <div class="modal-content"> <!-- Modal Content -->
            <div class="modal-header"> <!-- Modal Header -->
              <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">×</span>
                <span class="visually-hidden"><?php echo $CLICSHOPPING_DefineLanguage->getDef('text_close'); ?></span>
              </button>
              <h4
                class="modal-title"><?php echo $CLICSHOPPING_DefineLanguage->getDef('text_language_definition_delete_title'); ?></h4>
            </div>

            <div class="modal-body"> <!-- Modal Body -->
              <p><?php echo $CLICSHOPPING_DefineLanguage->getDef('text_language_definition_confirm_delete'); ?></p>
              <p id="modalDefinitionKey"></p>
            </div>

            <div class="modal-footer"> <!-- Modal Footer -->
              <button type="button" class="btn btn-danger"
                      id="rowDelConfirmButtonDelete"><?php echo $CLICSHOPPING_DefineLanguage->getDef('button_delete'); ?></button>
              <button type="button" class="btn btn-link"
                      data-bs-dismiss="modal"><?php echo $CLICSHOPPING_DefineLanguage->getDef('button_cancel'); ?></button>
            </div>
          </div><!-- /.modal-content -->
        </div><!-- /.modal-dialog -->
      </div><!-- /.modal -->
    </div>
  </div>
  <script>
    var definition_key = '';

    $('#rowDelConfirm').on('hidden.bs.modal', function (e) {
    });

    function NewDef(defVar, place) {
      $(place).replaceWith('<textarea class="form-control" name="' + defVar + '"></textarea>');
    }

    function DeleteDef(defVar) {
      definition_key = defVar;
      $('#modalDefinitionKey').html(definition_key);
      $('#rowDelConfirm').modal('show');
    }

    $(function () {
      $('#rowDelConfirmButtonDelete').on('click', function () {
        $('#rowDelConfirm').modal('hide');
        $("." + definition_key).remove();
        $('form[name="define_language"]').append('<input type="hidden" name="delete[' + definition_key + ']" value="">');
      });
    });
  </script>
</div>
</form>