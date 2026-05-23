<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_TemplateEmail = Registry::get('TemplateEmail');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');

$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_Hooks = Registry::get('Hooks');
$CLICSHOPPING_Language = Registry::get('Language');

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

$QtemplateEmail = $CLICSHOPPING_TemplateEmail->db->prepare('select ted.language_id,
                                                                      ted.template_email_name,
                                                                      ted.template_email_short_description,
                                                                      te.template_email_variable,
                                                                      te.customers_group_id,
                                                                      te.template_email_type,
                                                                      te.template_email_id
                                                               from  :table_template_email te,
                                                                     :table_template_email_description ted
                                                               where  ted.language_id = :language_id
                                                               and te.template_email_id = ted.template_email_id
                                                              ');

$QtemplateEmail->bindInt(':language_id', $CLICSHOPPING_Language->getId());
$QtemplateEmail->execute();
?>
<!-- body //-->
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/mail.gif', $CLICSHOPPING_TemplateEmail->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-2 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_TemplateEmail->getDef('heading_title'); ?></span>
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
    data-sort-name="sort_order"
    data-sort-order="asc"
    data-toolbar="#toolbar"
    data-buttons-class="primary"
    data-show-toggle="true"
    data-show-columns="true"
    data-mobile-responsive="true"
    data-check-on-init="true">

    <thead class="dataTableHeadingRow">
    <tr>
      <th data-field="name"><?php echo $CLICSHOPPING_TemplateEmail->getDef('table_heading_template_email_name'); ?></th>
      <th
        data-field="email_type"><?php echo $CLICSHOPPING_TemplateEmail->getDef('table_heading_template_email_type'); ?></th>
      <th
        data-field="description"><?php echo $CLICSHOPPING_TemplateEmail->getDef('table_heading_template_email_description'); ?></th>
      <th data-field="customers_group"
          class="text-center"><?php echo $CLICSHOPPING_TemplateEmail->getDef('table_heading_template_customer_groups'); ?></th>
      <th data-field="action" data-switchable="false"
          class="text-end"><?php echo $CLICSHOPPING_TemplateEmail->getDef('table_heading_action'); ?>&nbsp;
      </th>
    </tr>
    </thead>
    <tbody>
    <?php
    while ($QtemplateEmail->fetch()) {
      if ($QtemplateEmail->valueInt('template_email_type') == 1) {
        $template_email_type = $CLICSHOPPING_TemplateEmail->getDef('text_template_email_catalog');

      } elseif ($QtemplateEmail->valueInt('template_email_type') == 0) {
        $template_email_type = $CLICSHOPPING_TemplateEmail->getDef('text_template_email_admin');
      } else {
        $template_email_type = $CLICSHOPPING_TemplateEmail->getDef('text_template_email_admin_catalog');
      }

      if ($QtemplateEmail->value('customers_group_id') == 0) {
        $template_email_customer_group = $CLICSHOPPING_TemplateEmail->getDef('text_template_email_b2c');
      } elseif ($QtemplateEmail->valueInt('template_email_type') == 1) {
        $template_email_customer_group = $CLICSHOPPING_TemplateEmail->getDef('text_template_email_b2c_b2b');
      }
      ?>
      <tr>
        <th scope="row"><?php echo $QtemplateEmail->value('template_email_name'); ?></th>
        <td><?php echo $template_email_type; ?></td>
        <td><?php echo $QtemplateEmail->value('template_email_short_description'); ?></td>
        <td class="text-center"><?php echo $template_email_customer_group; ?></td>
        <td
          class="text-end"><?php echo HTML::link($CLICSHOPPING_TemplateEmail->link('Edit&page=' . $page . '&tID=' . $QtemplateEmail->valueInt('template_email_id')), '<h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_TemplateEmail->getDef('icon_edit') . '"></i></h4>'); ?></td>
      </tr>
      <?php
    }
    ?>
    </tbody>
  </table>
</div>