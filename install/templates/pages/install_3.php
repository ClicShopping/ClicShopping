<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\DateTime;
use ClicShopping\OM\HTML;

$dir_fs_document_root = $_POST['DIR_FS_DOCUMENT_ROOT'];

if ((substr($dir_fs_document_root, -1) != '\\') && (substr($dir_fs_document_root, -1) != '/')) {
  if (strrpos($dir_fs_document_root, '\\') !== false) {
    $dir_fs_document_root .= '\\';
  } else {
    $dir_fs_document_root .= '/';
  }
}
?>
<form name="install" id="installForm" action="install.php?step=4" method="post">
  <div id="content">
    <div class="page-header">
      <div class="container">
        <div></div>
      </div>
    </div>
    <div class="container">
      <div class="card">
        <div class="card-header"><i class="bi bi-sliders"></i><?php echo TEXT_END_CONFIGURATION; ?></div>
        <div class="card-body">
          <div id="mBox"></div>
          <fieldset>
            <div class="row">
              <div class="col-md-4  order-md-2">
                <div class="card">
                  <div class="card-header">
                    <p>Step 3/4</p>
                    <ol>
                      <li>Database Server</li>
                      <li>Web Server</li>
                      <li><strong>&gt; Online Store Settings</strong></li>
                      <li>Last effort and it's Finished!</li>
                    </ol>
                  </div>
                </div>
                <br/>
                <div class="progress">
                  <div class="progress-bar progress-bar-primary progress-bar-striped" role="progressbar"
                       aria-valuenow="75" aria-valuemin="0" aria-valuemax="100" style="width: 75%">75%
                  </div>
                </div>
                <br/>
                <div class="card">
                  <div class="card-header">
                    <div class="card-title">
                      Step 3: Online Store Settings
                    </div>
                  </div>
                  <div class="card-body">
                    <p>Here you can define the name of your online store and the contact information for the store
                      owner.</p>
                    <p>The administrator username and password are used to log into the protected administration tool
                      section.</p>
                  </div>
                </div>
              </div>

              <div class="col-md-8 order-md-1">
                <p><h5><?php echo TEXT_TITLE_CONFIGURATION; ?></h5></p>
                <div class="form-row">
                  <div class="form-group col required">
                    <label for="storeName" class="col-md-6"><strong><?php echo TEXT_STORE_NAME; ?></strong></label>
                    <span
                      class="col-md-6"><?php echo HTML::inputField('CFG_STORE_NAME', NULL, 'required aria-required="true" id="storeName" placeholder="' . TEXT_STORE_NAME . '"'); ?></span>
                    <span class="text-primary"><i class="bi bi-asterisk"
                                                  aria-hidden="true"></i> <small><?php echo TEXT_STORE_HELP; ?></small></span>
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-group col required">
                    <label for="StoreOwnerEmail"
                           class=col-md-6"><strong><?php echo TEXT_STORE_OWNER; ?></strong></label>
                    <span
                      class="col-md-6"><?php echo HTML::inputField('CFG_STORE_OWNER_NAME', null, 'required aria-required="true" id="StoreOwnerEmail" placeholder="' . TEXT_STORE_OWNER . '"'); ?></span>
                    <span class="text-danger"><i class="bi bi-asterisk"
                                                 aria-hidden="true"></i><small><?php echo TEXT_STORE_OWNER_HELP; ?></small></span>
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-group col required">
                    <label for="ownerEmail"
                           class="col-md-6"><strong><?php echo TEXT_STORE_OWNER_EMAIL; ?></strong></label>
                    <span
                      class="col-md-6"><?php echo HTML::inputField('CFG_STORE_OWNER_EMAIL_ADDRESS', null, 'required aria-required="true" id="ownerEmail" placeholder="' . TEXT_STORE_OWNER_EMAIL . '"', 'email'); ?></span>
                    <span class="text-danger"><i class="bi bi-asterisk"
                                                 aria-hidden="true"></i><small><?php echo TEXT_STORE_OWNER_EMAIL_HELP; ?></small></span>
                  </div>
                </div>
                <hr>
                <br>
                <div class="h5"><?php echo TEXT_TITLE_ACCESS; ?></h5></div>
                <div class="form-row">
                  <div class="form-group col required">
                    <label for="StoreNameAdmin"
                           class="col-md-6"><strong><?php echo TEXT_STORE_NAME_ADMIN; ?></strong></label>
                    <span
                      class="col-md-6"><?php echo HTML::inputField('CFG_ADMINISTRATOR_NAME', null, 'required aria-required="true" id="StoreNameAdmin" placeholder="' . TEXT_STORE_NAME_ADMIN . '"'); ?></span>
                    <span class="text-primary"><i class="bi bi-asterisk"
                                                  aria-hidden="true"></i><small><?php echo TEXT_STORE_NAME_ADMIN_HELP; ?></small></span>
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-group col required">
                    <label for="adminFirstName"
                           class="col-md-6"><strong><?php echo TEXT_STORE_FIRST_NAME; ?></strong></label>
                    <span
                      class="col-md-6"><?php echo HTML::inputField('CFG_ADMINISTRATOR_FIRSTNAME', null, 'required aria-required="true" id="adminFirstName" placeholder="' . TEXT_STORE_FIRST_NAME . '"'); ?></span>
                    <span class="text-primary"><i class="bi bi-asterisk"
                                                  aria-hidden="true"></i><small><?php echo TEXT_STORE_FIRST_NAME_HELP; ?></small></span></span>
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-group col required">
                    <label for="adminUsername"
                           class="col-md-6"><strong><?php echo TEXT_STORE_EMAIL_ADMIN; ?></strong></label>
                    <span
                      class="col-md-6"><?php echo HTML::inputField('CFG_ADMINISTRATOR_USERNAME', NULL, 'required aria-required="true" id="adminUsername" placeholder="' . TEXT_STORE_EMAIL_ADMIN . '"', 'email'); ?></span>
                    <span class="text-danger"><i class="bi bi-asterisk"
                                                 aria-hidden="true"></i><small><?php echo TEXT_STORE_EMAIL_ADMIN_HELP; ?></small></span>
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-group col required">
                    <label for="adminPassword"
                           class="col-md-6"><strong><?php echo TEXT_STORE_PASSWORD; ?></strong></label>
                    <span
                      class="col-md-6"><?php echo HTML::inputField('CFG_ADMINISTRATOR_PASSWORD', NULL, 'required aria-required="true" id="adminPassword" placeholder="' . TEXT_STORE_PASSWORD . '"'); ?></span>
                    <span class="text-danger"><i class="bi bi-asterisk"
                                                 aria-hidden="true"></i><small><?php echo TEXT_STORE_PASSWORD_HELP; ?></small></span>
                  </div>
                </div>
                <hr>
                <br/>

                <div class="h4"><?php echo TEXT_TITLE_STMP; ?></div>
                <?php echo TEXT_INTRO_SMTP; ?>

                <div class="form-row">
                  <div class="form-group">
                    <label for="smtp_host" class="col-md-6"><strong><?php echo TEXT_SMTP_HOST; ?></strong></label>
                    <span
                      class="col-md-6"><?php echo HTML::inputField('CFG_SMTP_HOST', NULL, 'id="smtp_host" placeholder="ex: smtp.gmail.com"'); ?></span>
                    <span class="text-primary"><i class="bi bi-asterisk"
                                                  aria-hidden="true"></i><small><?php echo TEXT_SMTP_HOST; ?></small></span>
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-group col required">
                    <div><strong><?php echo TEXT_SMTP_EMAIL_TRANSORT; ?></strong></div>

                    <?php
                    $smtp_transport_array = array(
                      ['id' => 'smtp', 'text' => 'smtp'],
                      ['id' => 'gmail', 'text' => 'gmail']
                    );

                    echo HTML::selectField('CFG_SMTP_EMAIL_TRANSORT', $smtp_transport_array);
                    ?>
                    <div class="col-md-12 text-primary"><i class="bi bi-asterisk"
                                                           aria-hidden="true"></i><small><?php echo TEXT_SMTP_PORT_INFO; ?></small>
                    </div>
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-group">
                    <div><strong><?php echo TEXT_SMTP_PORT; ?></strong></div>
                    <?php
                    $smtp_port_array = array(
                      ['id' => '25', 'text' => '25'],
                      ['id' => '465', 'text' => '465'],
                      ['id' => '587', 'text' => '587'],
                    );

                    echo HTML::selectField('CFG_SMTP_PORT', $smtp_port_array);
                    ?>
                    <div class="col-md-12 text-primary"><i class="bi bi-asterisk"
                                                           aria-hidden="true"></i><small><?php echo TEXT_SMTP_PORT_INFO; ?></small>
                    </div>
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-group">
                    <label for="smtp_host"
                           class="control-label col-md-6"><strong><?php echo TEXT_SMTP_USERNAME; ?></strong></label>
                    <span
                      class="col-md-6"><?php echo HTML::inputField('CFG_SMTP_USER_NAME', NULL, 'id="smtp_host" placeholder="' . TEXT_SMTP_USERNAME . '"'); ?></span>
                    <span class="text-primary"><i class="bi bi-asterisk"
                                                  aria-hidden="true"></i><small><?php echo 'Veuillez indiquer votre user name concernant votre email. Celuyi-ci n\'est pas forcément en relation avec votre compte d\'administration'; ?></small></span><br/><br/>
                  </div>
                </div>
                <div class="form-row">
                  <label for="smtp_password"
                         class="control-label col-md-6"><strong><?php echo TEXT_SMTP_PASSWORD; ?></strong></label>
                  <span
                    class="col-md-6"><?php echo HTML::inputField('CFG_SMTP_PASSWORD', NULL, 'id="smtp_password" placeholder="' . TEXT_SMTP_PASSWORD . '"'); ?></span>
                  <span class="text-primary"><i class="bi bi-asterisk"
                                                aria-hidden="true"></i><small><?php echo TEXT_SMTP_PASSWORD_INFO; ?></small></span><br/>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-8 order-md-1">
                <br/>
                <hr>
              </div>
            </div>
            <div class="row">
              <div class="col-md-8 order-md-1">
                <div class="h4"><?php echo TEXT_STORE_TIME_ZONE; ?></div>
                <div class="form-row">
                  <div class="form-group">
                    <label for="Zulu" class="control-label col-md-6"></label>
                    <span
                      class="col-md-6"><?php echo HTML::selectMenu('TIME_ZONE', DateTime::getTimeZones(), date_default_timezone_get(), 'id="Zulu"'); ?></span>
                    <span class="text-primary"><i class="bi bi-asterisk"
                                                  aria-hidden="true"></i><small><?php echo TEXT_STORE_TIME_ZONE_HELP; ?></small></span>
                  </div>
                </div>
                <br/><br/>
                <div class="text-end">
                  <p><?php echo HTML::button('Continue to Step 4', 'bi bi-caret-right', null, 'success'); ?></p>
                </div>
              </div>
            </div>
          </fieldset>
          <?php
          foreach ($_POST as $key => $value) {
            if (($key != 'x') && ($key != 'y')) {
              echo HTML::hiddenField($key, $value);
            }
          }
          ?>
        </div>
      </div>
    </div>
  </div>
</form>
