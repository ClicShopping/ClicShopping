<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\DateTime;
use ClicShopping\OM\Hash;
use ClicShopping\OM\HTML;
use ClicShopping\OM\ObjectInfo;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Customers = Registry::get('Customers');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Hooks = Registry::get('Hooks');
$CLICSHOPPING_Db = Registry::get('Db');

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

if (isset($_GET['search'])) {
  $_POST['search'] = HTML::sanitize($_GET['search']);
}
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <div
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/client.gif', $CLICSHOPPING_Customers->getDef('heading_title'), '40', '40'); ?></div>
          <div
            class="col-md-3 pageHeading float-start"><?php echo '&nbsp;' . $CLICSHOPPING_Customers->getDef('heading_title'); ?></div>
          <div class="col-md-3">
            <div>
              <div>
                <?php
                echo HTML::form('search', $CLICSHOPPING_Customers->link('Customers'), 'post', 'role="form" ', ['session_id' => true]);
                echo HTML::inputField('search', '', 'id="inputKeywords" placeholder="' . $CLICSHOPPING_Customers->getDef('heading_title_search') . '"');
                ?>
                </form>
              </div>
            </div>
          </div>
          <div class="col-md-2 text-end">
            <?php
            if (isset($_POST['search']) && !\is_null($_POST['search'])) {
              echo HTML::button($CLICSHOPPING_Customers->getDef('button_reset'), null, $CLICSHOPPING_Customers->link('Customers&page=' . $page), 'warning');
            }
            ?>
          </div>
          </form>

          <div class="col-md-3 text-end">
            <?php
            if ((MODE_B2B_B2C == 'True')) {
              echo HTML::button($CLICSHOPPING_Customers->getDef('button_create_account'), null, $CLICSHOPPING_Customers->link('Create'), 'success');
            }
            ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <?php echo $CLICSHOPPING_Hooks->output('Stats', 'StatsCustomersAgeBySex', null, 'display'); ?>
        </div>
      </div>
    </div>
  </div>

  <div class="mt-1"></div>

  <?php
  echo HTML::form('delete_all', $CLICSHOPPING_Customers->link('Customers&DeleteAll&page=' . $page));
  ?>

  <div id="toolbar" class="float-end">
    <button id="button" class="btn btn-danger"><?php echo $CLICSHOPPING_Customers->getDef('button_delete'); ?></button>
  </div>

  <table
    id="table"
    data-toggle="table"
    data-icons-prefix="bi"
    data-icons="icons"
    data-id-field="selected"
    data-select-item-name="selected[]"
    data-click-to-select="true"
    data-sort-order="asc"
    data-sort-name="selected"
    data-toolbar="#toolbar"
    data-buttons-class="primary"
    data-show-toggle="true"
    data-show-columns="true"
    data-mobile-responsive="true"
    data-check-on-init="true">

    <thead class="dataTableHeadingRow">
    <tr>
      <th data-checkbox="true" data-field="state"></th>
      <th data-field="selected" data-sortable="true" data-visible="false"
          data-switchable="false"><?php echo $CLICSHOPPING_Customers->getDef('id'); ?></th>
      <th data-sortable="true"><?php echo $CLICSHOPPING_Customers->getDef('table_heading_customers_id'); ?></th>
      <th data-field="lastname"
          data-sortable="true"><?php echo $CLICSHOPPING_Customers->getDef('table_heading_lastname'); ?></th>
      <th data-field="company"><?php echo $CLICSHOPPING_Customers->getDef('table_heading_entry_company'); ?></th>
      <?php
      // Permettre le changement de groupe en mode B2B
      if ((MODE_B2B_B2C == 'True')) {
        ?>
        <th
          data-field="company_b2b"><?php echo $CLICSHOPPING_Customers->getDef('table_heading_entry_company_b2b'); ?></th>
        <th data-field="group_name"
            data-sortable="true"><?php echo $CLICSHOPPING_Customers->getDef('table_entry_groups_name'); ?></th>
        <th data-field="entry_validate"
            class="text-center"><?php echo $CLICSHOPPING_Customers->getDef('table_entry_validate'); ?></th>
        <?php
      }
      ?>
      <th data-field="email_validation"
          class="text-center"><?php echo $CLICSHOPPING_Customers->getDef('table_heading_entry_email_validation'); ?></th>
      <th data-field="country"
          class="text-center"><?php echo $CLICSHOPPING_Customers->getDef('table_heading_country'); ?></th>
      <th data-field="number_review"
          class="text-center"><?php echo $CLICSHOPPING_Customers->getDef('table_heading_number_of_reviews'); ?></th>
      <th data-field="account_created" data-sortable="true"
          class="text-end"><?php echo $CLICSHOPPING_Customers->getDef('table_heading_account_created'); ?></th>
      <th data-field="action" data-switchable="false"
          class="text-end"><?php echo $CLICSHOPPING_Customers->getDef('table_heading_action'); ?>&nbsp;
      </th>
    </tr>
    </thead>
    <tbody>
    <?php
    // Recherche
    $search = '';

    if (isset($_POST['search']) && !\is_null($_POST['search'])) {

      $keywords = HTML::sanitize($_POST['search']);
      $search = " (c.customers_id like '" . $keywords . "'
             or c.customers_lastname like '%" . $keywords . "%'
             or c.customers_firstname like '%" . $keywords . "%'
             or c.customers_email_address like '%" . $keywords . "%'
             or a.entry_company like '%" . $keywords . "%' 
            )
         ";

      $Qcustomers = $CLICSHOPPING_Customers->db->prepare('select  SQL_CALC_FOUND_ROWS c.customers_id,
                                                                                        c.customers_company,
                                                                                        c.customers_lastname,
                                                                                        c.customers_firstname,
                                                                                        c.customers_group_id,
                                                                                        a.entry_company,
                                                                                        c.customers_email_address,
                                                                                        a.entry_country_id,
                                                                                        c.member_level,
                                                                                        c.customers_email_validation
                                                            from :table_customers c left join :table_address_book a on c.customers_id = a.customers_id
                                                            where ' . $search . '
                                                            and c.customers_default_address_id = a.address_book_id
                                                            and c.customer_guest_account = 0
                                                            order by c.customers_id ASC
                                                            limit :page_set_offset, :page_set_max_results
                                                            ');

      $Qcustomers->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
      $Qcustomers->execute();
    } else {
      $Qcustomers = $CLICSHOPPING_Customers->db->prepare('select SQL_CALC_FOUND_ROWS c.customers_id,
                                                                                        c.customers_company,
                                                                                        c.customers_lastname,
                                                                                        c.customers_firstname,
                                                                                        c.customers_group_id,
                                                                                        a.entry_company,
                                                                                        c.customers_email_address,
                                                                                        a.entry_country_id,
                                                                                        c.member_level,
                                                                                        c.customers_email_validation
                                                              from :table_customers c left join :table_address_book a on c.customers_id = a.customers_id
                                                              where c.customers_default_address_id = a.address_book_id
                                                              and c.customer_guest_account = 0
                                                              order by c.customers_id ASC
                                                              limit :page_set_offset, :page_set_max_results
                                                              ');

      $Qcustomers->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
      $Qcustomers->execute();
    }

    $listingTotalRow = $Qcustomers->getPageSetTotalRows();

    if ($listingTotalRow > 0) {
      while ($Qcustomers->fetch()) {
// suppression du membre non approuvé
        $Qinfo = $CLICSHOPPING_Customers->db->prepare('select customers_info_date_account_created as date_account_created,
                                                                 customers_info_date_account_last_modified as date_account_last_modified,
                                                                 customers_info_date_of_last_logon as date_last_logon,
                                                                 customers_info_number_of_logons as number_of_logons
                                                           from :table_customers_info
                                                           where customers_info_id = :customers_id
                                                          ');
        $Qinfo->bindInt(':customers_id', $Qcustomers->valueInt('customers_id'));
        $Qinfo->execute();

        $info = $Qinfo->fetch();

        $QcustColl = $CLICSHOPPING_Customers->db->prepare('select customers_group_id,
                                                                     customers_group_name
                                                             from :table_customers_groups
                                                             where customers_group_id = :customers_group_id
                                                            ');
        $QcustColl->bindInt(':customers_group_id', $Qcustomers->valueInt('customers_group_id'));
        $QcustColl->execute();

        $cust_ret = $QcustColl->fetch();

        if ($QcustColl->valueInt('customers_group_id') == 0) {
          $cust_ret['customers_group_name'] = $CLICSHOPPING_Customers->getDef('visitor_name');
        }

        if ((!isset($_GET['cID']) || (isset($_GET['cID']) && ((int)$_GET['cID'] === $Qcustomers->valueInt('customers_id')))) && !isset($cInfo)) {

          $Qcountry = $CLICSHOPPING_Customers->db->prepare('select countries_name
                                                             from :table_countries
                                                             where countries_id = :countries_id
                                                            ');

          $Qcountry->bindInt(':countries_id', $Qcustomers->valueInt('entry_country_id'));
          $Qcountry->execute();
          $country = $Qcountry->fetch();

          $Qreviews = $CLICSHOPPING_Customers->db->prepare('select count(*) as number_of_reviews
                                                               from :table_reviews
                                                               where customers_id = :customers_id
                                                              ');

          $Qreviews->bindInt(':customers_id', $Qcustomers->valueInt('customers_id'));
          $Qreviews->execute();
          $reviews = $Qreviews->fetch();

          // recover from bad records
          if (!\is_array($Qcountry->fetch())) {
            $country = array('Country is NULL');
          }

          if (!\is_array($Qinfo->fetch())) {
            $info = ['Info is NULL'];
          }

          if (!\is_array($Qreviews->fetch())) {
            $reviews = ['Customers is NULL'];
          }

          $Qorders = $CLICSHOPPING_Customers->db->prepare('select count(*) as number_of_orders
                                                            from :table_orders
                                                            where customers_id = :customers_id
                                                           ');

          $Qorders->bindInt(':customers_id', $Qcustomers->valueInt('customers_id'));
          $Qorders->execute();

          $customer_info = array_merge(array($country), array($info), array($reviews), $Qorders->toArray());
          $cInfo_array = array_merge($Qcustomers->toArray(), (array)$customer_info, (array)$cust_ret);

          $cInfo = new ObjectInfo($cInfo_array);
        }
        ?>
        <td></td>
        <td><?php echo $Qcustomers->valueInt('customers_id'); ?></td>

        <th scope="row"><?php echo $Qcustomers->valueInt('customers_id'); ?></th>
        <td><?php echo Hash::displayDecryptedDataText($Qcustomers->value('customers_lastname')) . ' ' . Hash::displayDecryptedDataText($Qcustomers->value('customers_firstname')); ?></td>
        <td><?php echo Hash::displayDecryptedDataText($Qcustomers->value('entry_company')); ?></td>
        <?php
        if ((MODE_B2B_B2C == 'True')) {
          ?>
          <td><?php echo Hash::displayDecryptedDataText($Qcustomers->value('customers_company')); ?></td>
          <td><?php echo $cust_ret['customers_group_name']; ?></td>
          <td class="text-center">
            <?php
            if ($Qcustomers->valueInt('member_level') == 0) {
              echo HTML::link(CLICSHOPPING::link('index.php?A&Customers\Members&Members'), '<h4><i class="bi bi-lock" title="' . $CLICSHOPPING_Customers->getDef('approved_client') . '"></i></h4>');
            }
            ?>
          </td>
          <?php
        }

        if ($Qcustomers->valueInt('customers_email_validation') == 0) {
          $email_validation = '<i class="bi-check text-success"></i>';
        } else {
          $email_validation = '<i class="bi bi-x text-danger"></i>';
        }
        ?>
        <td class="text-center"><?php echo $email_validation; ?></td>
        <?php
        $QcustomersCountry = $CLICSHOPPING_Customers->db->prepare('select a.entry_country_id,
                                                                          c.countries_id,
                                                                          c.countries_name
                                                                   from :table_address_book a,
                                                                        :table_countries c
                                                                   where customers_id = :customers_id
                                                                   and a.entry_country_id = c.countries_id
                                                                  ');
        $QcustomersCountry->bindInt(':customers_id', $Qcustomers->valueInt('customers_id'));

        $QcustomersCountry->execute();
        ?>
        <td class="dataTableContent">
          <?php
          if (!empty($QcustomersCountry->value('countries_name'))) {
            echo $QcustomersCountry->value('countries_name');
          } else {
            echo '<span class="text-warning">' . $CLICSHOPPING_Customers->getDef('text_customer_partial_registred') . '</span>';
          }
          ?>
        </td>
        <?php
        $Qreviews = $CLICSHOPPING_Customers->db->prepare('select count(*) as number_of_reviews
                                                           from :table_reviews
                                                           where customers_id = :customers_id
                                                          ');
        $Qreviews->bindInt(':customers_id', $Qcustomers->valueInt('customers_id'));
        $Qreviews->execute();
        ?>
        <td class="text-center"><?php echo $Qreviews->valueInt('number_of_reviews'); ?></td>
        <?php
        if (!\is_null($Qinfo->value('date_account_created'))) {
          echo '<td class="text-end">' . DateTime::toShort($Qinfo->value('date_account_created')) . '</td>';
        } else {
          echo '<td class="text-end"></td>';
        }
        ?>
        <td class="text-end">
          <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
            <?php
            if ($QcustColl->valueInt('customers_group_id') > 0) {
              echo HTML::link(CLICSHOPPING::link(null, 'A&Customers\Groups&Edit&cID=' . $QcustColl->valueInt('customers_group_id') . '&action=edit'), '<h4><i class="bi bi-edit" title="' . $CLICSHOPPING_Customers->getDef('icon_edit_customers_group') . '"></i></h4>');
              echo '&nbsp;';
            }

            echo HTML::link($CLICSHOPPING_Customers->link('Edit&cID=' . $Qcustomers->valueInt('customers_id')), '<h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_Customers->getDef('icon_edit_customer') . '"></i></h4>');
            echo '&nbsp;';
            echo HTML::link(CLICSHOPPING::link(null, 'A&Communication\EMail&EMail&customer=' . $Qcustomers->value('customers_email_address')), '<h4><i class="bi bi-send" title="' . $CLICSHOPPING_Customers->getDef('icon_email') . '"></i></h4>');
            echo '&nbsp;';

            echo HTML::link(CLICSHOPPING::link(null, 'A&Orders\Orders&Orders'), '<h4><i class="bi bi-cart3" title="' . $CLICSHOPPING_Customers->getDef('icon_edit_orders') . '"></i></h4>');
            echo '&nbsp;';
            echo HTML::link($CLICSHOPPING_Customers->link('Customers&Customers&PasswordForgotten&cID=' . $Qcustomers->valueInt('customers_id')), '<h4><i class="bi bi-pass" title="' . $CLICSHOPPING_Customers->getDef('icon_edit_new_password') . '"></i></h4>');
            echo '&nbsp;';
            ?>
          </div>
        </td>
        </tr>
        <?php
      } // end while
    }
    ?>
    </tbody>
  </table>
  </form><!-- end form delete all -->
  <?php
  if ($listingTotalRow > 0) {
    ?>
    <div class="row">
      <div class="col-md-12">
        <div
          class="col-md-6 float-start pagenumber hidden-xs TextDisplayNumberOfLink"><?php echo $Qcustomers->getPageSetLabel($CLICSHOPPING_Customers->getDef('text_display_number_of_link')); ?></div>
        <div
          class="float-end text-end"> <?php echo $Qcustomers->getPageSetLinks(CLICSHOPPING::getAllGET(array('page', 'info', 'x', 'y'))); ?></div>
      </div>
    </div>
    <?php
//------------------------------------------------
//       Extra Button
//------------------------------------------------
    ?>
    <div class="col-md-12">
      <div class="card card-block footerCard">
        <div class="row">
          <?php
          echo $CLICSHOPPING_Hooks->output('MailChimp', 'MailChimpBatch');
          echo '&nbsp;';
          ?>
        </div>
      </div>
    </div>
    <?php
  } // end $listingTotalRow
  ?>
</div>
