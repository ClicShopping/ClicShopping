<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
?>
<div class="col-md-12 text-center"><?php echo $menu_footer; ?></div>
<div class="d-flex flex-wrap footerTemplate text-start">
  <div class="col-md-<?php echo $content_width; ?>">
    <div class="footerTemplateBox footerTemplateAccount">
      <h4><?php echo CLICSHOPPING::getDef('module_footer_multi_template_account_heading_title'); ?></h4>
      <ul class="list-unstyled">
        <?php
        if ($CLICSHOPPING_Customer->isLoggedOn()) {
          ?>
          <li><?php echo HTML::link(CLICSHOPPING::link(null, 'Account&Main'), CLICSHOPPING::getDef('module_footer_multi_template_account_box_account')); ?></li>
          <li><?php echo HTML::link(CLICSHOPPING::link(null, 'Account&AddressBook'), CLICSHOPPING::getDef('module_footer_multi_template_account_box_address_book')); ?></li>
          <li><?php echo HTML::link(CLICSHOPPING::link(null, 'Account&History'), CLICSHOPPING::getDef('module_footer_multi_template_account_box_order_history')); ?></li>
          <br/>
          <label for="buttonLogOff"><a class="btn btn-danger btn-sm btn-block" role="button"
                                       href="<?php echo CLICSHOPPING::link(null, 'Account&Logoff') ?>"><i
                class="bi bi-box-arrow-right"></i><?php echo CLICSHOPPING::getDef('module_footer_multi_template_account_box_logoff') ?>
            </a></label>
          <?php
        } else {
          ?>
          <li><?php echo HTML::link(CLICSHOPPING::link(null, 'Account&Create'), CLICSHOPPING::getDef('module_footer_multi_template_account_box_create_account')); ?></li>
          <li class="col-md-12"><br/><label for="buttonLogIn"><a class="btn btn-success btn-sm btn-block" role="button"
                                                                 href="<?php echo CLICSHOPPING::link(null, 'Account&LogIn'); ?>"><i
                  class="bi bi-box-arrow-in-right"></i> <?php echo CLICSHOPPING::getDef('module_footer_multi_template_account_box_login') ?>
              </a></label></li>
          <?php
        }
        ?>
      </ul>
    </div>
  </div>

  <div class="col-md-<?php echo $content_width; ?>">
    <div class="footerTemplateBox">
      <h4><?php echo CLICSHOPPING::getDef('module_footer_multi_template_text_heading_title'); ?></h4>
      <?php echo CLICSHOPPING::getDef('module_footer_multi_template_text_text'); ?>
    </div>
  </div>

  <div class="col-md-<?php echo $content_width; ?>">
    <div class="footerTemplateBox footerTemplateInformation">
      <h4><?php echo CLICSHOPPING::getDef('module_footer_multi_template_information_heading_title'); ?></h4>
      <span itemscope itemtype="https://schema.org/Organization">
          <link itemprop="url" href="<?php echo CLICSHOPPING::getConfig('http_server', 'Shop'); ?>">
            <ul class="footerTemplateSocial">
<?php
if (!empty($facebook_url)) {
  ?>
  <li><a aria-label="Facebook" href="<?php echo $facebook_url; ?>" target="_blank" rel="noopener"><i
        class="bi bi-facebook"></i></a></li>
  <?php
}
if (!empty($instagram_url)) {
  ?>
  <li><a aria-label="Instagram" href="<?php echo $instagram_url; ?>" target="_blank" rel="noopener"><i
        class="bi bi-instagram"></i></a></li>
  <?php
}
if (!empty($whatsapp_url)) {
  ?>
  <li><a aria-label="WhatsApp" href="<?php echo $whatsapp_url; ?>" target="_blank" rel="noopener"><i
        class="bi bi-whatsapp"></i></a></li>
  <?php
}

  if (!empty($pinterest_url)) {
    ?>
    <li><a aria-label="Pinterest" href="<?php echo $pinterest_url; ?>" target="_blank" rel="noopener"><i
          class="bi bi-pinterest"></i></a></li>
    <?php
  }
?>
          </ul>
        </span>
    </div>
    <div class="mt-1"></div>
    <div class="mt-1"></div>
  </div>


  <div class="col-md-<?php echo $content_width; ?>">
    <div class="footerTemplateBox footerTemplateContact">
      <h4><?php echo CLICSHOPPING::getDef('module_footer_multi_template_contact_us_email_link'); ?></h4>
      <address>
        <strong><?php echo HTML::outputProtected(STORE_NAME); ?></strong><br/>
        <?php echo nl2br(STORE_NAME_ADDRESS); ?><br/>
      </address>
      <ul class="list-unstyled">
        <li><label
            for="buttonFooterContactUs"><?php echo HTML::button(CLICSHOPPING::getDef('module_footer_multi_template_contact_us_email_link'), 'bi bi-person-lines-fill', 'index.php?Info&Contact', 'info'); ?></label>
        </li>
      </ul>
    </div>
  </div>
</div>