<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Api = Registry::get('Api');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Hooks = Registry::get('Hooks');

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int) $_GET['page'] : 1;
$cId = $_GET['cID'] ?? '';
$cId = HTML::sanitize($cId);

// Optimized SQL query with proper formatting
$Qapi = $CLICSHOPPING_Api->db->prepare('SELECT api_id,
                                                 username,
                                                 api_key,
                                                 get_product_status,
                                                 update_product_status,
                                                 insert_product_status,
                                                 delete_product_status,
                                                 get_categories_status,
                                                 delete_categories_status,
                                                 insert_categories_status,
                                                 update_categories_status,
                                                 get_customer_status,
                                                 delete_customer_status,
                                                 insert_customer_status,
                                                 update_customer_status,
                                                 get_order_status,
                                                 delete_order_status,
                                                 insert_order_status,
                                                 update_order_status,
                                                 get_manufacturer_status,
                                                 delete_manufacturer_status,
                                                 insert_manufacturer_status,
                                                 update_manufacturer_status,
                                                 get_supplier_status,
                                                 delete_supplier_status,
                                                 insert_supplier_status,
                                                 update_supplier_status
                                          FROM :table_api
                                          WHERE api_id = :api_id
                                        ');
$Qapi->bindInt(':api_id', $cId);
$Qapi->execute();

$form_action = !empty($cId) ? 'Update' : 'Insert';
?>
<!-- body //-->
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/api.png', $CLICSHOPPING_Api->getDef('heading_title'), '40', '40'); ?></span>
          <span class="col-md-7 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Api->getDef('heading_title'); ?></span>
          <span class="col-md-4 text-end">
            <?php
            echo HTML::button($CLICSHOPPING_Api->getDef('button_configure'), null, $CLICSHOPPING_Api->link('Configure'), 'primary') . ' ';
            echo HTML::form('form_api', $CLICSHOPPING_Api->link('Api&' . $form_action . '&page=' . $page . '&' . (isset($_GET['cID']) ? '&cID=' . $_GET['cID'] : '')));
            echo HTML::button($CLICSHOPPING_Api->getDef('button_cancel'), null, $CLICSHOPPING_Api->link('Api&page=' . $page . '&cID=' . $Qapi->valueInt('api_id')), 'warning') . ' ';

            if (!empty($cId)) {
              echo HTML::button($CLICSHOPPING_Api->getDef('button_update'), null, null, 'success') . ' ';
            } else {
              echo HTML::button($CLICSHOPPING_Api->getDef('button_insert'), null, null, 'success') . ' ';
            }
            ?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>

  <div id="ApiTabs" style="overflow: auto;">
    <ul class="nav nav-tabs flex-column flex-sm-row" role="tablist" id="myTab">
      <li class="nav-item">
        <?php echo '<a href="#tab1" role="tab" data-bs-toggle="tab" class="nav-link active">' . $CLICSHOPPING_Api->getDef('tab_general') . '</a>'; ?>
      </li>
      <li class="nav-item">
        <?php echo '<a href="#tab2" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_Api->getDef('tab_ip_address') . '</a>'; ?>
      </li>
      <li class="nav-item">
        <?php echo '<a href="#tab3" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_Api->getDef('tab_session') . '</a>'; ?>
      </li>
    </ul>
    <div class="tabsClicShopping">
      <div class="tab-content">
        <?php
        // -------------------------------------------------------------------
        //          ONGLET General sur la description de la Apli
        // -------------------------------------------------------------------
        
        ?>
        <div class="tab-pane active" id="tab1">
          <div class="mt-1"></div>
          <div class="row" id="api_username">
            <div class="col-md-12">
              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_Api->getDef('text_api_username'); ?>"
                  class="col-3 col-form-label"><?php echo $CLICSHOPPING_Api->getDef('text_api_username'); ?></label>
                <div class="col-md-5">
                  <?php echo HTML::inputField('username', $Qapi->value('username'), 'required aria-required="true" '); ?>
                </div>
              </div>
            </div>
          </div>
          <div class="mt-1"></div>
          <div class="row" id="api_key">
            <div class="col-md-12">
              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_Api->getDef('text_api_key'); ?>"
                  class="col-3 col-form-label"><?php echo $CLICSHOPPING_Api->getDef('text_api_key'); ?></label>
                <div class="col-md-7">
                  <?php echo HTML::textAreaField('api_key', $Qapi->value('api_key'), 80, 4, 'id="input-key" class="form-control required aria-required="true" "'); ?>
                  <div class="mt-1"></div>
                  <button type="button" id="button-generate" class="btn btn-primary"><i
                      class="bi bi-arrow-clockwise"></i>
                    <?php echo $CLICSHOPPING_Api->getDef('text_api_generate_key'); ?>
                  </button>
                </div>
              </div>
            </div>
          </div>
          <div class="mt-1"></div>

          <!-- API Permissions Management -->
          <div class="card mt-3">
            <div class="card-header">
              <h6 class="mb-0"><?php echo $CLICSHOPPING_Api->getDef('text_api_permissions'); ?></h6>
            </div>
            <div class="card-body">
              <?php
              // Define permission categories for intelligent organization
              $permission_categories = [
                'product' => [
                  'title' => $CLICSHOPPING_Api->getDef('text_heading_product_management'),
                  'icon' => 'bi-box-seam',
                  'color' => 'primary'
                ],
                'categories' => [
                  'title' => $CLICSHOPPING_Api->getDef('text_heading_categories_management'),
                  'icon' => 'bi-folder',
                  'color' => 'success'
                ],
                'customer' => [
                  'title' => $CLICSHOPPING_Api->getDef('text_heading_customers_management'),
                  'icon' => 'bi-people',
                  'color' => 'info'
                ],
                'order' => [
                  'title' => $CLICSHOPPING_Api->getDef('text_heading_orders_management'),
                  'icon' => 'bi-cart-check',
                  'color' => 'warning'
                ],
                'manufacturer' => [
                  'title' => $CLICSHOPPING_Api->getDef('text_heading_manufacturers_management'),
                  'icon' => 'bi-building',
                  'color' => 'secondary'
                ],
                'supplier' => [
                  'title' => $CLICSHOPPING_Api->getDef('text_heading_suppliers_management'),
                  'icon' => 'bi-truck',
                  'color' => 'dark'
                ]
              ];

              $permission_types = [
                'get' => ['label' => $CLICSHOPPING_Api->getDef('text_get_status'), 'badge' => 'success'],
                'update' => ['label' => $CLICSHOPPING_Api->getDef('text_update_status'), 'badge' => 'warning'],
                'insert' => ['label' => $CLICSHOPPING_Api->getDef('text_insert_status'), 'badge' => 'info'],
                'delete' => ['label' => $CLICSHOPPING_Api->getDef('text_delete_status'), 'badge' => 'danger']
              ];
              ?>

              <div class="row">
                <?php foreach ($permission_categories as $category => $config): ?>
                  <div class="col-lg-6 col-md-12 mb-4">
                    <div class="card border-<?php echo $config['color']; ?>">
                      <div class="card-header bg-<?php echo $config['color']; ?> text-white">
                        <h6 class="mb-0">
                          <i class="<?php echo $config['icon']; ?> me-2"></i>
                          <?php echo $config['title']; ?>
                        </h6>
                      </div>
                      <div class="card-body">
                        <div class="row">
                          <?php foreach ($permission_types as $type => $type_config): ?>
                            <?php
                            $field_name = $type . '_' . $category . '_status';
                            $field_value = $Qapi->valueInt($field_name);
                            ?>
                            <div class="col-6 mb-3">
                              <div class="d-flex justify-content-between align-items-center">
                                <label class="form-label mb-0 small">
                                  <span class="badge bg-<?php echo $type_config['badge']; ?> me-1">
                                    <?php echo strtoupper($type); ?>
                                  </span>
                                  <?php echo $type_config['label']; ?>
                                </label>
                                <div class="form-check form-switch">
                                  <?php echo HTML::checkboxField($field_name, '1', $field_value, 'class="form-check-input" role="switch"'); ?>
                                </div>
                              </div>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <div class="mt-1"></div>
          <div class="mt-1"></div>
          <?php echo $CLICSHOPPING_Hooks->output('Api', 'ApiContentTab1', null, 'display'); ?>
          </form>
        </div>
        <?php
        // -------------------------------------------------------------------
        //          Ip address Tab
        // -------------------------------------------------------------------
        ?>
        <div class="tab-pane" id="tab2">
          <div class="mt-1"></div>
          <div class="row" id="text_alert">
            <div class="col-md-12">
              <?php
              if (!empty($cId)) {
                ?>
                <div class="alert alert-info" role="alert">
                  <?php echo $CLICSHOPPING_Api->getDef('text_info_message', ['ip_address' => HTTP::getIpAddress()]); ?>
                </div>
                <?php
              } else {
                ?>
                <div class="alert alert-warning" role="alert">
                  <?php echo $CLICSHOPPING_Api->getDef('text_info_warning'); ?></div>
                <?php
              }
              ?>
            </div>
          </div>
          <div class="mt-1"></div>
          <?php
          $Qip = $CLICSHOPPING_Api->db->prepare('select api_ip_id,
                                                         api_id,
                                                         ip,
                                                         comment
                                                  from :table_api_ip
                                                  where api_id = :api_id
                                                ');
          $Qip->bindInt(':api_id', $cId);
          $Qip->execute();

          $result = $Qip->fetchAll();

          if (!empty($cId)) {
            ?>
            <div class="col-md-12">
              <div class="row">
                <div class="text-end">
                  <div class="col-md-12">
                    <div class="form-group row">
                      <label for="<?php echo $CLICSHOPPING_Api->getDef('text_add_ip'); ?>"
                        class="col-11 col-form-label"></label>
                      <div class="col-md-1">
                        <?php echo HTML::button($CLICSHOPPING_Api->getDef('text_add_ip'), null, null, 'primary', ['params' => 'data-bs-toggle="modal" data-refresh="true" data-bs-target="#myModal"']); ?>
                        <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel"
                          aria-hidden="true">
                          <div class="modal-dialog">
                            <div class="modal-content">
                              <div class="modal-header">
                                <?php echo $CLICSHOPPING_Api->getDef('text_add_ip'); ?>
                              </div>
                              <div class="modal-body">
                                <?php echo HTML::form('add_api', $CLICSHOPPING_Api->link('Api&AddIp&cID=' . $Qapi->valueInt('api_id') . '&page=' . $page)); ?>
                                <div class="row" id="api_ip">
                                  <div class="col-md-12">
                                    <div class="form-group row">
                                      <label for="<?php echo $CLICSHOPPING_Api->getDef('text_ip'); ?>"
                                        class="col-3 col-form-label"><?php echo $CLICSHOPPING_Api->getDef('text_ip'); ?></label>
                                      <div class="col-md-7">
                                        <?php echo HTML::inputField('ip'); ?>
                                      </div>
                                    </div>
                                    <div class="form-group row">
                                      <label for="<?php echo $CLICSHOPPING_Api->getDef('text_comment'); ?>"
                                        class="col-3 col-form-label"><?php echo $CLICSHOPPING_Api->getDef('text_comment'); ?></label>
                                      <div class="col-md-7">
                                        <?php echo HTML::inputField('comment'); ?>
                                      </div>
                                    </div>

                                  </div>
                                  <div class="mt-1"></div>
                                  <div class="text-center">
                                    <?php echo HTML::button($CLICSHOPPING_Api->getDef('text_add'), null, null, 'success'); ?>
                                  </div>
                                </div>
                                </form>
                              </div> <!-- /.modal-content -->
                            </div><!-- /.modal-dialog -->
                          </div><!-- /.modal -->
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <?php
          }
          ?>
          <table class="table table-striped">
            <thead class="dataTableHeadingRow">
              <tr>
                <td><?php echo $CLICSHOPPING_Api->getDef('text_ip'); ?></td>
                <td><?php echo $CLICSHOPPING_Api->getDef('text_heading_comment'); ?></td>
                <td class="text-end"><?php echo $CLICSHOPPING_Api->getDef('text_action'); ?></td>
              </tr>
            </thead>
            <tbody>
              <?php
              foreach ($result as $value) {
                ?>
                <tr>
                  <td><?php echo $value['ip']; ?></td>
                  <td><?php echo $value['comment']; ?></td>
                  <td class="text-end">
                    <?php
                    echo HTML::link($CLICSHOPPING_Api->link('Api&DeleteIP&dID=' . $value['api_ip_id'] . '&cID=' . $cId), '<h4><i class="bi bi-trash2" title="' . $CLICSHOPPING_Api->getDef('icon_delete') . '"></i></h4>');
                    echo '&nbsp;';
                    ?>
                  </td>
                </tr>
                <?php
              }
              ?>
            </tbody>
          </table>
          <div class="mt-1"></div>
          <?php echo $CLICSHOPPING_Hooks->output('Api', 'ApiContentTab2', null, 'display'); ?>
        </div>
        <?php
        // -------------------------------------------------------------------
        //          Session tab 2
        // -------------------------------------------------------------------
        $Qsession = $CLICSHOPPING_Api->db->prepare('select api_session_id,
                                                           api_id,
                                                           session_id,
                                                           ip,
                                                           date_added,
                                                           date_modified
                                                    from :table_api_session
                                                    where api_id = :api_id
                                                  ');
        $Qsession->bindInt(':api_id', $cId);
        $Qsession->execute();

        $result = $Qsession->fetchAll();
        ?>
        <div class="tab-pane" id="tab3">
          <div class="mt-1"></div>
          <table class="table table-striped">
            <thead class="dataTableHeadingRow">
              <tr>
                <td><?php echo $CLICSHOPPING_Api->getDef('text_token'); ?></td>
                <td><?php echo $CLICSHOPPING_Api->getDef('text_ip'); ?></td>
                <td class="text-center"><?php echo $CLICSHOPPING_Api->getDef('text_date_added'); ?></td>
                <td class="text-center"><?php echo $CLICSHOPPING_Api->getDef('text_date_modified'); ?></td>
                <td class="text-end"><?php echo $CLICSHOPPING_Api->getDef('text_action'); ?></td>
              </tr>
            </thead>
            <tbody>
              <?php
              foreach ($result as $value) {
                ?>
                <tr>
                  <td><?php echo $value['session_id']; ?></td>
                  <td><?php echo $value['ip']; ?></td>
                  <td><?php echo $value['date_added']; ?></td>
                  <td><?php echo $value['date_modified']; ?></td>
                  <td class="text-end">
                    <?php
                    echo HTML::link($CLICSHOPPING_Api->link('Api&DeleteSessionIp&page=' . $page . '&cID=' . $value['api_session_id']), '<h4><i class="bi bi-trash2" title="' . $CLICSHOPPING_Api->getDef('icon_delete') . '"></i></h4>');
                    echo '&nbsp;';
                    ?>
                  </td>
                </tr>
                <?php
              }
              ?>
            </tbody>
          </table>
          <div class="mt-1"></div>
          <?php echo $CLICSHOPPING_Hooks->output('Api', 'ApiContentTab3', null, 'display'); ?>
        </div>
      </div>
    </div>
    </form>
  </div>
  <script
    src="<?php echo CLICSHOPPING::link('Shop/ext/javascript/clicshopping/ClicShoppingAdmin/generate_api.js'); ?>"></script>
