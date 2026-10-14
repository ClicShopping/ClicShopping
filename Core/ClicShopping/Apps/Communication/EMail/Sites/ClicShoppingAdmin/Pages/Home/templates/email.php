<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\Hash;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Mail = Registry::get('Mail');
$CLICSHOPPING_EMail = Registry::get('EMail');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');
$CLICSHOPPING_Wysiwyg = Registry::get('Wysiwyg');

// dropdown
$customers = [];
$customers[] = ['id' => '', 'text' => $CLICSHOPPING_EMail->getDef('text_select_customer')];
$customers[] = ['id' => '***', 'text' => $CLICSHOPPING_EMail->getDef('text_all_customers')];
$customers[] = ['id' => '**D', 'text' => $CLICSHOPPING_EMail->getDef('text_newsletter_customers')];

$QmailCustomers = $CLICSHOPPING_EMail->db->prepare('select customers_email_address,
                                                             customers_firstname,
                                                             customers_lastname
                                                      from :table_customers
                                                      where customers_email_validation = 0
                                                      order by customers_lastname
                                                      ');

$QmailCustomers->execute();

while ($QmailCustomers->fetch()) {
  $customers[] = [
    'id' => $QmailCustomers->value('customers_email_address'),
    'text' => Hash::displayDecryptedDataText($QmailCustomers->value('customers_lastname')) . ', ' . Hash::displayDecryptedDataText($QmailCustomers->value('customers_firstname')) . ' (' . $QmailCustomers->value('customers_email_address') . ')'
  ];
}

if ($CLICSHOPPING_MessageStack->exists('main')) {
  echo $CLICSHOPPING_MessageStack->get('main');
}

if (isset($_GET['messageInfo'])) {
  $replace = str_replace('_b', '<b', $_GET['messageInfo']);
  $replace = str_replace('r_', 'r />', $replace);

  $message = htmlentities($replace, ENT_QUOTES | ENT_HTML5);
} else {
  $message = $CLICSHOPPING_EMail->getDef('text_message_customer');
}

echo $CLICSHOPPING_Wysiwyg::getWysiwyg();
?>
<!-- body //-->
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <div
            class="col-md-1"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/mail.gif', $CLICSHOPPING_EMail->getDef('heading_title'), '40', '40'); ?></div>
          <div class="col-md-5 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_EMail->getDef('heading_title'); ?></div>
          <?php
          if (SEND_EMAILS == 'true') {
            ?>
            <div class="col-md-6 text-end">
              <?php
              echo HTML::form('mail', $CLICSHOPPING_EMail->link('SendEmailToUser&Process'));
              echo HTML::button($CLICSHOPPING_EMail->getDef('button_send'), null, null, 'success');
              ?>
            </div>
            <?php
          }
          ?>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div id="emailTab">
    <ul class="nav nav-tabs flex-column flex-sm-row" role="tablist" id="myTab">
      <li
        class="nav-item"><?php echo '<a href="#tab1" role="tab" data-bs-toggle="tab" class="nav-link active">' . $CLICSHOPPING_EMail->getDef('tab_general') . '</a>'; ?></li>
    </ul>
    <div class="tabsClicShopping">
      <div class="tab-content">
        <div class="col-md-12 mainTitle"><?php echo $CLICSHOPPING_EMail->getDef('text_email'); ?></div>
        <div class="adminformTitle">
          <div class="row">
            <div class="col-md-5">
              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_EMail->getDef('text_customer'); ?>"
                       class="col-5 col-form-label"><?php echo $CLICSHOPPING_EMail->getDef('text_customer'); ?></label>
                <div class="col-md-5">
                  <?php echo HTML::selectMenu('customers_email_address', $customers, isset($_GET['customer']) ?? ''); ?>
                </div>
              </div>
            </div>
          </div>
          <div class="mt-1"></div>
          <div class="row">
            <div class="col-md-5">
              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_EMail->getDef('text_from'); ?>"
                       class="col-5 col-form-label"><?php echo $CLICSHOPPING_EMail->getDef('text_from'); ?></label>
                <div class="col-md-5">
                  <?php echo HTML::inputField('from', STORE_OWNER_EMAIL_ADDRESS, 'required aria-required="true" id="textFrom" placeholder="' . $CLICSHOPPING_EMail->getDef('email_text_from') . '"', 'email'); ?>
                </div>
              </div>
            </div>
          </div>
          <div class="mt-1"></div>
          <div class="row">
            <div class="col-md-5">
              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_EMail->getDef('text_subject'); ?>"
                       class="col-5 col-form-label"><?php echo $CLICSHOPPING_EMail->getDef('text_subject'); ?></label>
                <div class="col-md-5">
                  <?php echo HTML::inputField('subject', '', 'required aria-required="true" id="subject" placeholder="' . $CLICSHOPPING_EMail->getDef('subject') . '"'); ?>
                </div>
              </div>
            </div>
          </div>

          <script>
            var options = {
              'defaultView': 'list',
              'onlyMimes': ["image"], // display all images
              lang: 'fr'
            }
            $('#elfinder').elfinder(options);
          </script>
          <div class="mt-1"></div>
          <div class="row">
            <div class="col-md-12">
              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_EMail->getDef('text_message'); ?>"
                       class="col-1 col-form-label"><?php echo $CLICSHOPPING_EMail->getDef('text_message'); ?></label>
                <div class="col-md-11">
                  <?php
                  $name = 'message';
                  $ckeditor_id = $CLICSHOPPING_Wysiwyg::getWysiwygId($name);

                  echo $CLICSHOPPING_Wysiwyg::textAreaCkeditor($name, 'soft', '750', '300', $message, 'id="' . $ckeditor_id . '"');
                  ?>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  </form>
  <div class="mt-1"></div>
</div>