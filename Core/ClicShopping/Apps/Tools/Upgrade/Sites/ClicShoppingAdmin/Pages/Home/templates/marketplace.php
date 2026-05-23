<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Tools\Upgrade\Classes\ClicShoppingAdmin\Marketplace;

$CLICSHOPPING_Upgrade = Registry::get('Upgrade');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');

if ($CLICSHOPPING_MessageStack->exists('error')) {
  echo $CLICSHOPPING_MessageStack->get('error');
}

if (!Registry::exists('Marketplace')) {
  Registry::set('Marketplace', new Marketplace());
}

$CLICSHOPPING_Marketplace = Registry::get('Marketplace');

if (isset($_POST['cId'])) {
  $current_category_id = HTML::sanitize($_POST['cId']);
} else {
  $current_category_id = '';
}
?>
<div class="contentBody" xmlns="http://www.w3.org/1999/html">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/apps.png', $CLICSHOPPING_Upgrade->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-2 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Upgrade->getDef('heading_title'); ?></span>
          <span class="col-md-4 text-center">
          <?php
          echo HTML::form('display', $CLICSHOPPING_Upgrade->link('Marketplace'), 'post', '', ['session_id' => true]);
          echo HTML::selectMenu('cId', $CLICSHOPPING_Marketplace->getLabelTree(), $current_category_id, 'onchange="this.form.submit();"');
          echo '</form>';
          ?>
          </span>
          <span class="col-md-3 text-center">
          <?php
          echo HTML::form('search', $CLICSHOPPING_Upgrade->link('Marketplace'), 'post', '', ['session_id' => true]);
          echo HTML::inputField('search', null, 'id=search placeholder="' . $CLICSHOPPING_Upgrade->getDef('text_search') . '"');
          echo '</form>';
          ?>
          </span>
          <span class="col-md-2 text-end btn-group">
          <?php
          if (isset($_POST['search'])) {
            echo HTML::button($CLICSHOPPING_Upgrade->getDef('button_reset_search'), null, $CLICSHOPPING_Upgrade->link('Marketplace'), 'danger') . '&nbsp;';
          }

          if (!isset($_POST['search'])) {
            echo HTML::button($CLICSHOPPING_Upgrade->getDef('button_update'), null, $CLICSHOPPING_Upgrade->link('Marketplace&UpdateMarketplace'), 'warning') . '&nbsp;';
            if (empty(CLICSHOPPING_APP_UPGRADE_UP_USERNAME)) {
              echo HTML::button($CLICSHOPPING_Upgrade->getDef('button_configure'), null, $CLICSHOPPING_Upgrade->link('Configure'), 'primary');
            } else {
              if (MODE_DEMO == 'False') {
                echo HTML::button($CLICSHOPPING_Upgrade->getDef('button_install_app'), null, null, 'primary', ['params' => 'data-bs-toggle="modal" data-bs-target="#modulaInstall"']);
                ?>
                <div class="modal fade" id="modulaInstall" tabindex="-1" aria-labelledby="exampleModalLabel"
                     aria-hidden="true">
                    <div class="modal-dialog">
                      <div class="modal-content">
                        <div class="modal-header">
                          <h5 class="modal-title" id="exampleModalLabel"></h5>
                          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body text-center">
                          <?php
                          echo HTML::form('installApp', $CLICSHOPPING_Upgrade->link('Marketplace&InstallAppsMarketplace'), 'post', 'enctype="multipart/form-data" id="fileUpload"');
                          echo '<div>' . $CLICSHOPPING_Upgrade->getDef('text_info_upload') . '</div>';
                          echo '<p></p>';
                          echo HTML::fileField('uploadApp', 'id="uploadApp" accept=".zip"');
                          echo '<div class="mt-1"></div>';
                          echo HTML::button($CLICSHOPPING_Upgrade->getDef('button_update'), null, null, 'success');
                          echo '</form>';
                          ?>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php
              }
            }
          }
          ?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="col-md-12">
    <?php
    if (empty(CLICSHOPPING_APP_UPGRADE_UP_USERNAME)) {
      ?>
      <div style="height: 700px;">
        <div class="col-md-12">
          <div class="row">
            <div class="alert alert-warning text-center"
                 role="alert"><?php echo $CLICSHOPPING_Upgrade->getDef('text_marketplace_username'); ?></div>
          </div>
          <div class="text-center">
            <?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'marketplace.png', $CLICSHOPPING_Upgrade->getDef('heading_title')); ?>
          </div>
        </div>
      </div>
      <?php
    } else {
    /*
    * Categories
    */
    $check = $CLICSHOPPING_Upgrade->db->get('marketplace_categories', 'categories_id');

    $result = $CLICSHOPPING_Marketplace->getCategories();

    if ($check->rowCount() == 0) {
      $result = $CLICSHOPPING_Marketplace->getCategories();
      ?>
      <div style="height: 700px;">
        <div class="col-md-12">
          <div class="row">
            <div class="alert alert-warning text-center" role="alert">
              <?php
              echo $CLICSHOPPING_Upgrade->getDef('text_marketplace_update') . ' ';
              echo HTML::button($CLICSHOPPING_Upgrade->getDef('button_refresh'), null, $CLICSHOPPING_Upgrade->link('Marketplace'), 'success', null, 'sm');
              ?>
            </div>
          </div>
          <div class="text-center">
            <?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'marketplace.png', $CLICSHOPPING_Upgrade->getDef('heading_title')); ?>
          </div>
        </div>
      </div>
      <?php
    }

    if ((!isset($_POST['cId']) || $_POST['cId'] == '') && (!isset($_POST['search']) || $_POST['search'] == '') && $result === false) {
      ?>
      <div style="height: 700px;">
        <div class="col-md-12">
          <div class="row">
            <div class="alert alert-info text-center"
                 role="alert"><?php echo $CLICSHOPPING_Upgrade->getDef('text_marketplace_info'); ?></div>
          </div>
          <div class="text-center">
            <?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'marketplace.png', $CLICSHOPPING_Upgrade->getDef('heading_title')); ?>
          </div>
        </div>
      </div>
      <?php
    } else {
      $check = $CLICSHOPPING_Upgrade->db->get('marketplace_files', 'id');

      if ($check->rowCount() == 0) {
        $result = $CLICSHOPPING_Marketplace->getFiles();
      }
      /***********************************************************
       * Files
       */
      if (isset($_POST['cId'])) {
        $categories_id = HTML::sanitize($_POST['cId']);
        $sql = 'where file_categories_id = :categories_id';
      } else {
        $sql = '';
      }

      if (isset($_POST['search'])) {
        $search = " where file_name like '%" . HTML::sanitize($_POST['search']) . "%' ";
      } else {
        $search = '';
      }

      $Qfiles = $CLICSHOPPING_Upgrade->db->prepare('select file_id,
                                                            file_categories_id,
                                                            file_name,
                                                            file_url,
                                                            file_description,
                                                            file_author,
                                                            file_photo_url,
                                                            file_profil_url,
                                                            file_url
                                                      from :table_marketplace_files
                                                      ' . $sql . '
                                                      ' . $search . '
                                                      order by file_name desc
                                                      '
      );

      if (isset($_POST['cId'])) {
        $Qfiles->bindInt(':categories_id', $categories_id);
      }

      $Qfiles->execute();

      $result_files = $Qfiles->fetchAll();
      ?>
      <div class="col-md-12">
        <div class="row">
          <?php
          $i = 0;

          foreach ($result_files as $value) {
            $CLICSHOPPING_Marketplace->getFilesInformations($value['file_id']);

            $Qfilesinformation = $CLICSHOPPING_Upgrade->db->prepare('select file_id,
                                                                          file_name,
                                                                          file_version,
                                                                          file_downloads,
                                                                          file_rating,
                                                                          file_prices,
                                                                          file_date_added,
                                                                          file_url_screenshot,
                                                                          file_url_download,
                                                                          is_installed
                                                                  from :table_marketplace_file_informations
                                                                  where file_id = :file_id
                                                                  order by file_name desc
                                                                  ');
            $Qfilesinformation->bindInt('file_id', $value['file_id']);

            $Qfilesinformation->execute();
            ?>
            <div class="col-md-3 mb-4 d-flex align-items-stretch">
              <div class="card">
                <div class="card-header">
                  <div class="col-md-12">
                    <div class="row">
                      <span class="col-md-6"><h6><?php echo $Qfilesinformation->value('file_name'); ?></h6></span>
                      <span
                        class="col-md-3 text-end"><?php echo HTML::stars($Qfilesinformation->valueInt('file_rating')); ?></span>
                      <span class="col-md-3 text-end form-group">
                    <?php
                    echo '<h5>';
                    if ($Qfilesinformation->valueInt('is_installed') == 1) {
                      echo '<i class="bi bi-emoji-sunglasses-fill text-success" title="' . $CLICSHOPPING_Upgrade->getDef('text_app_installed') . '"></i> &nbsp;';
                    }

                    echo HTML::link($value['file_profil_url'], HTML::image($value['file_photo_url'], $value['file_author'], 25, 25, null, 'rounded-pill'), 'target="_blank"');
                    echo '</h5>';
                    ?>
                  </span>
                    </div>
                  </div>
                </div>
                <div class="card-body">
                  <div class="col-md-12 card-text">
                    <div class="row">
                  <span class="col-md-12">
                     <?php
                     $description = html_entity_decode($value['file_description']);

                     echo mb_strimwidth($description, 0, 80, '...');
                     echo '&nbsp;&nbsp;';
                     ?>
                    <i class="bi bi-box-arrow-up-right text-primary" data-bs-toggle="modal"
                       data-bs-target="#ModalLongDescription<?php echo $i; ?>"
                       title="<?php echo $CLICSHOPPING_Upgrade->getDef('button_more_infos'); ?>"></i>
                    <div class="modal fade" id="ModalLongDescription<?php echo $i; ?>" tabindex="-1"
                         aria-labelledby="ModalLongDescription" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-scrollable">
                      <div class="modal-content">
                        <div class="modal-header">
                          <h5 class="modal-title" id="exampleModalLabel"></h5>
                          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                          <?php
                          $description = html_entity_decode($value['file_description']);
                          $description = strip_tags($description, '<p>');

                          echo mb_strimwidth($description, 0, 800, '...');
                          ?>
                        </div>
                      </div>
                      </div>
                    </div>
                  </span>
                    </div>
                    <div class="mt-1"></div>
                    <div class="row">
                  <span class="col-md-12 text-center">
                    <?php
                    if (empty($Qfilesinformation->value('file_url_screenshot'))) {
                      echo HTML::image(CLICSHOPPING::getConfig('http_server') . CLICSHOPPING::getConfig('http_path', 'Shop') . 'images/nophoto.png', $Qfilesinformation->value('file_name'));
                    } else {
                      echo HTML::image($Qfilesinformation->value('file_url_screenshot'), $Qfilesinformation->value('file_name'), '75%', null, 'data-bs-toggle="modal" data-bs-target="#myModalImage' . $i . '"');
                    }
                    ?>
                    <div class="modal fade" id="myModalImage<?php echo $i; ?>" tabindex="-1"
                         aria-labelledby="myModalImage" aria-hidden="true">
                      <div class="modal-dialog">
                        <div class="modal-content">
                          <div class="modal-body">
                            <p id="myModal<?php echo $i; ?>">
                            <img src="<?php echo $Qfilesinformation->value('file_url_screenshot'); ?>" class="img-fluid"
                                 alt="<?php echo $Qfilesinformation->value('file_name'); ?>">
                            </p>
                          </div>
                        </div>
                      </div>
                    </div>
                  </span>
                    </div>
                  </div>
                </div>
                <div class="card-footer">
              <span class="col-md-12">
                <span class="row">
                  <span
                    class="col-md-4"><?php echo HTML::button($CLICSHOPPING_Upgrade->getDef('button_more_infos'), null, $value['file_url'], 'primary', ['params' => 'target="_blank"'], 'sm'); ?></span>
                  <span class="col-md-8 text-end">
                    <?php
                    if ($Qfilesinformation->valueDecimal('file_prices') == 0.00) {
                      echo '<span class="text-end text-white btn-group">' . HTML::button($CLICSHOPPING_Upgrade->getDef('button_free'), null, null, 'danger', null, 'sm') . '&nbsp;</span>';
                    } else {
                      echo '<span>' . HTML::button(round($Qfilesinformation->valueDecimal('file_prices'), 2) . 'EUR', null, null, 'success', null, 'sm') . '</span>';
                    }
                    ?>
                </span>
              </span>
                </div>
              </div>
            </div>
            <?php
            $i++;
          }
          ?>
        </div>
      </div>
      <?php
    }
    ?>
  </div>
  <?php
  }
  ?>
</div>
