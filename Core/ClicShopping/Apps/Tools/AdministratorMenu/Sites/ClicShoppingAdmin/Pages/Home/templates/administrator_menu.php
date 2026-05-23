<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\ObjectInfo;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Tools\AdministratorMenu\Classes\ClicShoppingAdmin\AdministratorMenu;

$CLICSHOPPING_AdministratorMenu = Registry::get('AdministratorMenu');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_Db = Registry::get('Db');
$CLICSHOPPING_Hooks = Registry::get('Hooks');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');

$Qaccess = $CLICSHOPPING_Db->prepare('select access,
                                              id
                                        from :table_administrators
                                        where id = :id
                                        and access = 1
                                        ');
$Qaccess->bindInt(':id', $_SESSION['admin']['id']);
$Qaccess->execute();


if (\is_null($Qaccess->valueInt('access'))) {
  $CLICSHOPPING_MessageStack->add($CLICSHOPPING_AdministratorMenu->getDef('error_no_access'), 'error');
  CLICSHOPPING::redirect();
}

if (isset($_POST['cPath'])) {
  $current_category_id = HTML::sanitize($_POST['cPath']);
} elseif (isset($_GET['cPath'])) {
  $current_category_id = HTML::sanitize($_GET['cPath']);
} else {
  $current_category_id = '';
}
?>
<!-- body //-->
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/menu.png', $CLICSHOPPING_AdministratorMenu->getDef($CLICSHOPPING_AdministratorMenu->getDef('heading_title')), '40', '40'); ?></span>
          <span
            class="col-md-2 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_AdministratorMenu->getDef('heading_title'); ?></span>
          <span class="col-md-3 text-end">
          <?php
            echo HTML::form('search', $CLICSHOPPING_AdministratorMenu->link('AdministratorMenu'), 'post', '', ['session_id' => true]);
            echo HTML::inputField('search', '', 'id="inputKeywords" placeholder="' . $CLICSHOPPING_AdministratorMenu->getDef('heading_title_search') . '"');
            echo '&nbsp;&nbsp;&nbsp;';
          ?>
           </form>
        </span>
          <span class="col-md-3">
           <div>
             <div>
                <?php
                  echo HTML::form('goto', $CLICSHOPPING_AdministratorMenu->link('AdministratorMenu'), 'post', '', ['session_id' => true]);
                  echo HTML::selectMenu('cPath', AdministratorMenu::getLabelTree(), $current_category_id, 'onchange="this.form.submit();"');
                  echo '</form>';
                ?>
               </form>
             </div>
           </div>
         </span>
          <span class="col-md-3 text-end">
<?php
$cPath_back = '';

$cPath_array = AdministratorMenu::getPathArray();

if (isset($cPath_array) && \count($cPath_array) > 0) {
  for ($i = 0, $n = \count($cPath_array) - 1; $i < $n; $i++) {
    if (empty($cPath_back)) {
      $cPath_back .= $cPath_array[$i];
    } else {
      $cPath_back .= '_' . $cPath_array[$i];
    }
  }
}

$cPath_back = (!\is_null($cPath_back)) ? 'cPath=' . $cPath_back . '&' : '';

if (isset($_GET['search']) || isset($_POST['cPath'])) {
  echo HTML::button($CLICSHOPPING_AdministratorMenu->getDef('button_reset'), null, $CLICSHOPPING_AdministratorMenu->link('AdministratorMenu&' . $cPath_back . 'cID=' . $current_category_id), 'warning') . '&nbsp;';
}

if (!isset($_GET['search'])) {
  echo HTML::button($CLICSHOPPING_AdministratorMenu->getDef('button_new_category'), null, $CLICSHOPPING_AdministratorMenu->link('Edit&cPath=' . $current_category_id), 'success') . '&nbsp;';
}
?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>

  <!-- //################################################################################################################ -->
  <!-- //                                             LISTING                                                            -->
  <!-- //################################################################################################################ -->

  <table
    id="table"
    data-toggle="table"
    data-icons-prefix="bi"
    data-icons="icons"
    data-sort-name="id"
    data-sort-order="asc"
    data-toolbar="#toolbar"
    data-buttons-class="primary"
    data-show-toggle="true"
    data-show-columns="true"
    data-mobile-responsive="true"
    data-check-on-init="true">

    <thead class="dataTableHeadingRow">
    <tr>
      <th data-switchable="false"></th>
      <th data-field="id"
          data-sortable="true" data-sortable="true"><?php echo $CLICSHOPPING_AdministratorMenu->getDef('table_heading_id'); ?></th>
      <th
        data-field="menu"><?php echo $CLICSHOPPING_AdministratorMenu->getDef('table_heading_categories_products'); ?></th>
      <th data-field="status" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_AdministratorMenu->getDef('table_heading_status'); ?></th>
      <th data-field="access"><?php echo $CLICSHOPPING_AdministratorMenu->getDef('table_heading_rights_access'); ?></th>
      <th data-field="app"
          class="text-center"><?php echo $CLICSHOPPING_AdministratorMenu->getDef('table_heading_app'); ?></th>
      <th data-field="sort_order" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_AdministratorMenu->getDef('table_heading_sort_order'); ?></th>
      <th data-field="action" data-switchable="false"
          class="text-end"><?php echo $CLICSHOPPING_AdministratorMenu->getDef('table_heading_action'); ?></th>
    </tr>
    </thead>
    </tbody>
    <?php
    $categories_count = 0;
    $rows = 0;

    if (isset($_POST['search'])) {
      $search = HTML::sanitize($_POST['search']);

      $Qcategories = $CLICSHOPPING_AdministratorMenu->db->prepare('select a.id,
                                                                            a.link,
                                                                            a.parent_id,
                                                                            a.access,
                                                                            a.sort_order,
                                                                            a.b2b_menu,
                                                                            a.app_code,
                                                                            a.status,
                                                                            amd.label
                                                                      from :table_administrator_menu a,
                                                                           :table_administrator_menu_description amd
                                                                      where a.id = amd.id
                                                                      and amd.language_id = :language_id
                                                                      and (amd.label like :search or a.id like :search  or a.app_code like :search)
                                                                      order by a.id asc,
                                                                               a.sort_order asc
                                                                      ');

      $Qcategories->bindValue(':search', '%' . $search . '%');
      $Qcategories->bindInt(':language_id', $CLICSHOPPING_Language->getId());
      $Qcategories->execute();
    } else {
      $Qcategories = $CLICSHOPPING_AdministratorMenu->db->prepare('select a.id,
                                                                     a.link,
                                                                    a.parent_id,
                                                                    a.access,
                                                                    a.sort_order,
                                                                    a.b2b_menu,
                                                                    a.app_code,
                                                                    a.status,
                                                                    amd.label
                                                              from :table_administrator_menu a,
                                                                   :table_administrator_menu_description amd
                                                              where a.id = amd.id
                                                              and a.parent_id = :parent_id
                                                              and amd.language_id = :language_id
                                                              order by a.id asc,
                                                                       a.sort_order asc
                                                              ');

      $Qcategories->bindInt(':parent_id', (int)$current_category_id);
      $Qcategories->bindInt(':language_id', (int)$CLICSHOPPING_Language->getId());
      $Qcategories->execute();
    }

    while ($Qcategories->fetch()) {
      $categories_count++;
      $rows++;

// Get parent_id for subcategories if search
      $cPath = $Qcategories->valueInt('parent_id');

      if ((!isset($_GET['cID']) && !isset($_GET['pID']) || (isset($_GET['cID']) && ((int)$_GET['cID'] === $Qcategories->valueInt('id')))) && !isset($cInfo)) {
        $category_childs = ['childs_count' => AdministratorMenu::getChildsInMenuCount($Qcategories->valueInt('id'))];

        $cInfo_array = array_merge($Qcategories->toArray(), $category_childs);
        $cInfo = new ObjectInfo($cInfo_array);
      }
      ?>
      <tr>
        <td><?php echo '<a href="' . $CLICSHOPPING_AdministratorMenu->link('AdministratorMenu&' . AdministratorMenu::getPath($Qcategories->valueInt('id'))) . '"'; ?>
          ><i class="bi bi-folder-fill text-primary"></i></td>
        <td><?php echo '<strong>' . $Qcategories->value('id') . '</strong>'; ?></td>
        <td><?php echo '<strong>' . $Qcategories->value('label') . '</strong>'; ?></td>
          <td>
            <?php
            if ($Qcategories->valueInt('status') == 1) {
              echo HTML::link($CLICSHOPPING_AdministratorMenu->link('AdministratorMenu&SetFlag&flag=0&cID=' . $Qcategories->valueInt('id') . '&cPath=' . $cPath), '<i class="bi-check text-success"></i>');
            } else {
              echo HTML::link($CLICSHOPPING_AdministratorMenu->link('AdministratorMenu&SetFlag&flag=1&cID=' . $Qcategories->valueInt('id') . '&cPath=' . $cPath), '<i class="bi bi-x text-danger"></i>');
            }
            ?>
          </td>

        <?php
        if ($Qcategories->valueInt('access') == 0) {
          echo '<td>' . $CLICSHOPPING_AdministratorMenu->getDef('text_all_right') . '</td>';
        } elseif ($Qcategories->valueInt('access') == 1) {
          echo '<td class="text-info">' . $CLICSHOPPING_AdministratorMenu->getDef('text_all_rights_admin') . '</td>';
        } elseif ($Qcategories->valueInt('access') == 2) {
          echo '<td class="text-warning">' . $CLICSHOPPING_AdministratorMenu->getDef('text_rights_employee') . '</td>';
        } elseif ($Qcategories->valueInt('access') == 3) {
          echo '<td class="text-danger">' . $CLICSHOPPING_AdministratorMenu->getDef('text_rights_visitor') . '</td>';
        }
        ?>
        <td><span class="badge text-bg-light"><?php echo $Qcategories->value('app_code'); ?></span></td>
        <td class="text-center"><?php echo $Qcategories->valueInt('sort_order'); ?></td>
        <td class="text-end">
          <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
            <?php
            echo '<a href="' . $CLICSHOPPING_AdministratorMenu->link('Edit&cPath=' . $cPath . '&cID=' . $Qcategories->valueInt('id')) . '"><h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_AdministratorMenu->getDef('image_edit') . '"></i></h4></a>';
            echo '&nbsp;';
            echo '<a href="' . $CLICSHOPPING_AdministratorMenu->link('Move&cPath=' . $cPath . '&cID=' . $Qcategories->valueInt('id')) . '"><h4><i class="bi bi-arrows-move" title="' . $CLICSHOPPING_AdministratorMenu->getDef('image_move') . '"></i></h4></a>';
            echo '&nbsp;';
            echo '<a href="' . $CLICSHOPPING_AdministratorMenu->link('Delete&cPath=' . $cPath . '&cID=' . $Qcategories->valueInt('id')) . '"><h4><i class="bi bi-trash2" title="' . $CLICSHOPPING_AdministratorMenu->getDef('image_delete') . '"></i></h4></a>';
            echo '&nbsp;';
            ?>
          </div>
        </td>
      </tr>
      <?php
    }
    ?>
    </tbody>
  </table>
  </form>
  <div><?php echo $CLICSHOPPING_AdministratorMenu->getDef('text_categories') . '&nbsp;' . $categories_count; ?></div>
</div>