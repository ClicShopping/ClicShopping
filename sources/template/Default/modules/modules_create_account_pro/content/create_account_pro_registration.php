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

$CLICSHOPPING_Address = Registry::get('Address');

echo $form;
?>
<div class="col-md-<?php echo $content_width; ?>">
  <div class="mt-1"></div>
  <div class="contentText">
    <?php
    if ($CLICSHOPPING_MessageStack->exists('main')) {
      echo $CLICSHOPPING_MessageStack->get('main');
    }

    // ----------------------
    // ------ Address   -----
    // ----------------------
    if ((ACCOUNT_COMPANY_PRO == 'true') || (ACCOUNT_SIRET_PRO == 'true') || (ACCOUNT_TVA_INTRACOM_PRO == 'true')) {
      ?>
      <div class="mt-1"></div>
      <div class="card">
        <div class="card-header">
          <span class="alert-warning float-end" role="alert"><?php echo CLICSHOPPING::getDef('form_required'); ?></span>
          <span
            class="modulesCreateAccountRegistrationPageHeader"><h3><?php echo CLICSHOPPING::getDef('category_company'); ?></h3></span>
        </div>
        <div class="card-block">
          <div class="mt-1"></div>
          <div class="card-text">
            <?php
            if (ACCOUNT_COMPANY_PRO == 'true') {
              ?>
              <div class="row">
                <div class="col-md-12">
                  <div class="form-group row">
                    <label for="InputCompany"
                           class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_company'); ?></label>
                    <div class="col-md-8">
                      <?php
                      echo HTML::inputField('company', null, 'required aria-required="true" id="InputCompany" autocomplete="company" aria-describedby="' . CLICSHOPPING::getDef('entry_company') . '" placeholder="' . CLICSHOPPING::getDef('entry_company') . '" minlength="' . ENTRY_COMPANY_PRO_MIN_LENGTH . '"');
                      if (ENTRY_COMPANY_PRO_MIN_LENGTH > 0) {
                        echo '&nbsp;' . (!\is_null(CLICSHOPPING::getDef('entry_company_text_pro')) ? '<span class="text-warning">' . CLICSHOPPING::getDef('entry_company_text_pro') . '</span>' : '');
                      }
                      ?>
                    </div>
                  </div>
                </div>
              </div>

              <div class="row">
                <div class="col-md-12">
                  <div class="form-group row">
                    <label for="InputCompanyWebsite"
                           class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_company_website'); ?></label>
                    <div class="col-md-8">
                      <?php
                      echo HTML::inputField('customer_website_company', null, 'id="InputCompanyWebsite" autocomplete="website" aria-describedby="' . CLICSHOPPING::getDef('entry_company_website') . '" placeholder="' . CLICSHOPPING::getDef('entry_company_website') . '" minlength="' . ENTRY_COMPANY_PRO_MIN_LENGTH . '"');
                      echo '&nbsp;' . (!\is_null(CLICSHOPPING::getDef('entry_company_text_pro')) ? '<span class="text-warning">' . CLICSHOPPING::getDef('entry_website_text_pro') . '</span>' : '');
                      ?>
                    </div>
                  </div>
                </div>
              </div>
              <?php
            }
            if (ACCOUNT_SIRET_PRO == 'true') {
              ?>

              <div class="row">
                <div class="col-md-12">
                  <div class="form-group row">
                    <label for="InputSiret"
                           class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_siret'); ?></label>
                    <div class="col-md-8">
                      <?php
                      echo HTML::inputField('siret', null, 'required aria-required="true" id="InputSiret" aria-describedby="' . CLICSHOPPING::getDef('entry_siret') . '" placeholder="' . CLICSHOPPING::getDef('entry_siret') . '" minlength="' . ENTRY_SIRET_MIN_LENGTH . '" maxlength="14"');
                      if (ENTRY_SIRET_MIN_LENGTH > 0) {
                        echo '&nbsp;' . (!\is_null(CLICSHOPPING::getDef('entry_siret_text')) ? '<span class="text-warning">' . CLICSHOPPING::getDef('entry_siret_text') . '</span>' : '');
                      }
                      echo '&nbsp;' . (!\is_null(CLICSHOPPING::getDef('entry_siret_exemple')) ? '<span class="text-warning">' . CLICSHOPPING::getDef('entry_siret_exemple') . '</span>' : '');
                      ?>
                    </div>
                  </div>
                </div>
              </div>
              <?php
            }
            if (ACCOUNT_APE_PRO == 'true') {
              ?>

              <div class="row">
                <div class="col-md-12">
                  <div class="form-group row">
                    <label for="InputCodeApe"
                           class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_code_ape'); ?></label>
                    <div class="col-md-8">
                      <?php
                      echo HTML::inputField('ape', null, 'required aria-required="true" id="InputCodeApe" aria-describedby="' . CLICSHOPPING::getDef('entry_code_ape') . '" placeholder="' . CLICSHOPPING::getDef('entry_code_ape') . '" minlength="' . (int)ENTRY_CODE_APE_MAX_LENGTH . '" maxlength="4"');

                      if (ENTRY_CODE_APE_MAX_LENGTH > 0) {
                        echo '&nbsp;' . (!\is_null(CLICSHOPPING::getDef('entry_code_ape_text')) ? '<span class="text-warning">' . CLICSHOPPING::getDef('entry_code_ape_text') . '</span>' : '');
                      }
                      echo '&nbsp;' . (!\is_null(CLICSHOPPING::getDef('entry_code_ape_exemple')) ? '<span class="text-warning">' . CLICSHOPPING::getDef('entry_code_ape_exemple') . '</span>' : '');
                      ?>
                    </div>
                  </div>
                </div>
              </div>

              <?php
            }
            if (ACCOUNT_TVA_INTRACOM_PRO == 'true') {
              ?>
              <div class="row">
                <div class="col-md-12">
                  <div class="form-group row">
                    <label for="InputCountry"
                           class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_country'); ?></label>
                    <div class="col-md-8">
                      <?php echo HTML::selectMenuIsoList('country', $default_country_pro, 'onchange="ISO_change();"') . '&nbsp;' . (!\is_null(CLICSHOPPING::getDef('entry_country_text')) ? '<span class="text-warning">' . CLICSHOPPING::getDef('entry_country_text') . '</span>' : ''); ?>
                    </div>
                  </div>
                </div>
              </div>

              <div class="row">
                <div class="col-md-12">
                  <div class="form-group row">
                    <label for="InputTvaIntracom"
                           class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_tva_intracom'); ?></label>
                    <div class="col-md-8">
                      <?php echo HTML::inputField('tva_intracom', null, 'id="InputTvaIntracom" aria-describedby="' . CLICSHOPPING::getDef('entry_tva_intracom') . '" placeholder="' . CLICSHOPPING::getDef('entry_tva_intracom') . '" minlength="' . (int)ENTRY_TVA_INTRACOM_MAX_LENGTH . '"  maxlength="14"'); ?>
                    </div>
                  </div>
                </div>
              </div>
              <?php
            }
            ?>

          </div>
        </div>
      </div>
      <div class="mt-1"></div>
      <?php
    }
    // ----------------------
    // ------ Address   -----
    // ----------------------
    ?>
    <div class="card">
      <div class="card-header">
        <span class="alert-warning float-end" role="alert"><?php echo CLICSHOPPING::getDef('form_required'); ?></span>
        <span
          class="modulesCreateAccountProRegistrationCategoryAddressProPageHeader"><h3><?php echo CLICSHOPPING::getDef('category_address_pro'); ?></h3></span>
      </div>
      <div class="card-block">
        <div class="mt-1"></div>
        <div class="card-text">

          <div class="row">
            <div class="col-md-12">
              <div class="form-group row">
                <label for="InputStreetAddress"
                       class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_street_address'); ?></label>
                <div class="col-md-8">
                  <?php
                  echo HTML::inputField('street_address', null, 'required aria-required="true" id="InputStreetAddress" aria-describedby="' . CLICSHOPPING::getDef('entry_street_address') . '" placeholder="' . CLICSHOPPING::getDef('entry_street_address') . '" minlength="' . ENTRY_STREET_ADDRESS_PRO_MIN_LENGTH . '"');
                  if (ENTRY_STREET_ADDRESS_PRO_MIN_LENGTH > 0) {
                    echo '&nbsp;' . (!\is_null(CLICSHOPPING::getDef('entry_street_address_text')) ? '<span class="text-warning">' . CLICSHOPPING::getDef('entry_street_address_text') . '</span>' : '');
                  }
                  ?>
                </div>
              </div>
            </div>
          </div>

          <?php
          if (ACCOUNT_SUBURB_PRO == 'true') {
            ?>
            <div class="row">
              <div class="col-md-12">
                <div class="form-group row">
                  <label for="InputSuburb"
                         class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_suburb'); ?></label>
                  <div class="col-md-8">
                    <?php echo HTML::inputField('suburb', null, 'id="InputSuburb" aria-describedby="' . CLICSHOPPING::getDef('entry_suburb') . '" placeholder="' . CLICSHOPPING::getDef('entry_suburb') . '"'); ?>
                  </div>
                </div>
              </div>
            </div>
            <?php
          }
          ?>
          <div class="row">
            <div class="col-md-12">
              <div class="form-group row">
                <label for="InputPostCode"
                       class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_post_code'); ?></label>
                <div class="col-md-8">
                  <?php echo HTML::inputField('postcode', null, 'required aria-required="true" id="InputPostCode" aria-describedby="' . CLICSHOPPING::getDef('entry_post_code') . '" placeholder="' . CLICSHOPPING::getDef('entry_post_code') . '"'); ?>
                </div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-12">
              <div class="form-group row">
                <label for="InputPostCode"
                       class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_city'); ?></label>
                <div class="col-md-8">
                  <?php echo HTML::inputField('city', null, 'required aria-required="true" id="InputCity" aria-describedby="' . CLICSHOPPING::getDef('entry_city') . '" placeholder="' . CLICSHOPPING::getDef('entry_city') . '"'); ?>
                </div>
              </div>
            </div>
          </div>
          <?php
          if (ACCOUNT_TVA_INTRACOM_PRO == 'false') {
            if (ACCOUNT_STATE_DROPDOWN == 'true') {
              ?>
              <div class="row">
                <div class="col-md-12">
                  <div class="form-group row">
                    <label for="InputCountry"
                           class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_country'); ?></label>
                    <div class="col-md-8">
                      <?php echo HTML::selectMenuCountryList('country', null, 'onchange="update_zone(this.form);" aria-required="true"'); ?>
                      <?php echo(!\is_null(CLICSHOPPING::getDef('entry_country_text')) ? '<span class="text-warning">' . CLICSHOPPING::getDef('entry_country_text') . '</span>' : ''); ?>
                    </div>
                  </div>
                </div>
              </div>
              <?php
            } else {
              ?>
              <div class="row">
                <div class="col-md-12">
                  <div class="form-group row">
                    <label for="InputCountry"
                           class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_country'); ?></label>
                    <div class="col-md-8">
                      <?php echo HTML::selectMenuIsoList('country', $default_country_pro, 'onchange="update_zone(this.form);" aria-required="true"') . '&nbsp;' . (!\is_null(CLICSHOPPING::getDef('entry_country_text')) ? '<span class="text-warning">' . CLICSHOPPING::getDef('entry_country_text') . '</span>' : ''); ?>
                    </div>
                  </div>
                </div>
              </div>
              <?php
            }
          }

          if (ACCOUNT_STATE_PRO == 'true') {
            if (ACCOUNT_STATE_DROPDOWN == 'true') {
              ?>
              <div class="row">
                <div class="col-md-12">
                  <div class="form-group row">
                    <label for="InputState"
                           class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_state'); ?></label>
                    <div class="col-md-8">
                      <?php echo HTML::selectField('state', $CLICSHOPPING_Address->getPrepareCountryZonesPullDown(), null, 'aria-required="true"'); ?>
                      <?php echo(!\is_null(CLICSHOPPING::getDef('entry_state_text')) ? '<span class="text-warning">' . CLICSHOPPING::getDef('entry_state_text') . '</span>' : ''); ?>
                    </div>
                  </div>
                </div>
              </div>
              <?php
            } else {
              if (isset($_POST['country']) && !empty($_POST['country'])) {
                $country = HTML::sanitize($_POST['country']);
              } else {
                $country = STORE_COUNTRY;
              }
              ?>
              <div class="row">
                <div class="col-md-12">
                  <div class="form-group row">
                    <label for="InputState"
                           class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_state'); ?></label>
                    <div class="col-md-8">
                      <?php
                      if ($process === true) {
                        if ($_SESSION['entry_state_has_zones'] === true) {
                          $zones_array = [];

                          $country_id = HTML::sanitize($_POST['country']);

                          if (!empty($country_id)) {
                            $Qcheck = $CLICSHOPPING_Db->prepare('select zone_name
                                               from :table_zones
                                               where zone_country_id = :zone_country_id
                                               and zone_status = 0
                                               order by zone_name
                                              ');
                            $Qcheck->bindInt(':zone_country_id', (int)$country_id);
                            $Qcheck->execute();


                            while ($Qcheck->fetch()) {
                              $zones_array[] = ['id' => $Qcheck->value('zone_name'),
                                'text' => $Qcheck->value('zone_name')
                              ];
                            }

                            echo HTML::selectMenu('state', $zones_array, 'id="inputState" aria-describedby="atState"', 'aria-required="true"');
                          } else {
                            echo HTML::inputField('state', '', 'id="inputState" placeholder="' . CLICSHOPPING::getDef('entry_state') . '" aria-describedby="atState"');
                          }
                        } else {
                          echo HTML::inputField('state', '', 'id="inputState" placeholder="' . CLICSHOPPING::getDef('entry_state') . '" aria-describedby="atState"');
                        }
                      } else {
                        if (isset($entry['country_id']) && $entry['country_id'] != 0) {
                          $country_id = $CLICSHOPPING_Address->getZoneName($entry['country_id'], $entry['zone_id'], $entry['state']);
                        } else {
                          $country_id = '';
                        }

                        echo HTML::inputField('state', $country_id, 'id="atState" placeholder="' . CLICSHOPPING::getDef('entry_state') . '" aria-required="true" aria-describedby="atState"');
                      }

                      if ((!\is_null(CLICSHOPPING::getDef('entry_state_text'))) && (ENTRY_STATE_PRO_MIN_LENGTH > 0)) echo '&nbsp;<span class="text-warning">' . CLICSHOPPING::getDef('entry_state_text') . '</span>';
                      ?>
                    </div>
                  </div>
                </div>
              </div>
              <?php
            }
          }
          ?>
        </div>
      </div>
    </div>
    <div class="mt-1"></div>
    <?php
    // -----------------
    // contact category
    // -----------------
    ?>
    <div class="card">
      <div class="card-header">
        <span class="alert-warning float-end" role="alert"><?php echo CLICSHOPPING::getDef('form_required'); ?></span>
        <span
          class="modulesCreateAccountProRegistrationContactPageHeader"><h3><?php echo CLICSHOPPING::getDef('category_contact'); ?></h3></span>
      </div>
      <div class="card-block">
        <div class="mt-1"></div>
        <div class="card-text">
          <div class="row">
            <div class="col-md-12">
              <div class="form-group row">
                <label for="InputTelephone"
                       class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_telephone_number'); ?></label>
                <div class="col-md-8">
                  <?php echo HTML::inputField('customers_telephone', null, 'rel="txtTooltipPhone" autocomplete="tel" title="' . CLICSHOPPING::getDef('entry_phone_dgrp') . '" data-bs-toggle="tooltip" data-placement="right"  required aria-required="true" id="InputTelephone" aria-describedby="' . CLICSHOPPING::getDef('entry_telephone_number') . '" placeholder="' . CLICSHOPPING::getDef('entry_telephone_number') . '"'); ?>
                </div>
              </div>
            </div>
          </div>

          <?php
          if (ACCOUNT_CELLULAR_PHONE_PRO == 'true') {
            ?>
            <div class="row">
              <div class="col-md-12">
                <div class="form-group row">
                  <label for="InputCellularPhone"
                         class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_cellular_phone_number'); ?></label>
                  <div class="col-md-8">
                    <?php echo HTML::inputField('customers_cellular_phone', null, 'rel="txtTooltipPhone" autocomplete="tel" title="' . CLICSHOPPING::getDef('entry_phone_dgrp') . '" data-bs-toggle="tooltip" data-placement="right" id="InputCellularPhone" aria-describedby="' . CLICSHOPPING::getDef('entry_cellular_phone_number') . '" placeholder="' . CLICSHOPPING::getDef('entry_cellular_phone_number') . '"'); ?>
                  </div>
                </div>
              </div>
            </div>
            <?php
          }
          ?>
        </div>
      </div>
    </div>
    <div class="mt-1"></div>
    <?php
    // ----------------------
    // Personnal Information
    // ----------------------
    ?>
    <div class="card">
      <div class="card-header">
        <span class="alert-warning float-end" role="alert"><?php echo CLICSHOPPING::getDef('form_required'); ?></span>
        <span
          class="modulesCreateAccountProRegistrationCategoryPersonnalPageHeader"><h3><?php echo CLICSHOPPING::getDef('category_personal_pro'); ?></h3></span>
      </div>
      <div class="card-block">
        <div class="mt-1"></div>
        <div class="card-text">

          <?php
          if (ACCOUNT_GENDER_PRO == 'true') {
            ?>
            <div class="row">
              <div class="col-md-12">
                <div class="form-group row">
                  <label for="gender"
                         class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_gender'); ?></label>
                  <div class="col-sm-6 col-md-6">
                    <div class="custom-control custom-radio custom-control-inline">
                      <?php echo HTML::radioField('gender', 'm', true, 'class="custom-control-input" id="male" name="male"'); ?>
                      <label class="custom-control-label" for="male"><?php echo CLICSHOPPING::getDef('male'); ?></label>
                    </div>
                    <div class="custom-control custom-radio custom-control-inline">
                      <?php echo HTML::radioField('gender', 'f', null, 'class="custom-control-input" id="female" name="female"'); ?>
                      <label class="custom-control-label"
                             for="female"><?php echo CLICSHOPPING::getDef('female'); ?></label>
                    </div>
                    <?php echo(!\is_null(CLICSHOPPING::getDef('entry_gender_text')) ? '<span class="text-warning">' . CLICSHOPPING::getDef('entry_gender_text') . '</span>' : ''); ?>
                  </div>
                </div>
              </div>
            </div>
            <?php
          }
          ?>
          <div class="row">
            <div class="col-md-12">
              <div class="form-group row">
                <label for="InputFirstName"
                       class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_first_name'); ?></label>
                <div class="col-sm-6 col-md-6">
                  <?php echo HTML::inputField('firstname', null, 'required aria-required="true" id="InputFirstName" autocomplete="name" aria-describedby="' . CLICSHOPPING::getDef('entry_first_name') . '" placeholder="' . CLICSHOPPING::getDef('entry_first_name') . '" minlength="' . ENTRY_FIRST_NAME_PRO_MIN_LENGTH . '"'); ?>
                </div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-12">
              <div class="form-group row">
                <label for="InputLastName"
                       class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_last_name'); ?></label>
                <div class="col-sm-6 col-md-6">
                  <?php echo HTML::inputField('lastname', null, 'required aria-required="true" id="InputLastName" autocomplete="name" aria-describedby="' . CLICSHOPPING::getDef('entry_last_name') . '" placeholder="' . CLICSHOPPING::getDef('entry_last_name') . '" minlength="' . ENTRY_LAST_NAME_PRO_MIN_LENGTH . '"'); ?>
                </div>
              </div>
            </div>
          </div>

          <?php
          if (ACCOUNT_DOB_PRO == 'true') {
            ?>
            <div class="row">
              <div class="col-md-12">
                <div class="form-group row">
                  <label for="dob"
                         class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_date_of_birth'); ?></label>
                  <div class="col-sm-6 col-md-6">
                    <?php echo HTML::inputField('dob', null, 'required aria-required="true" minlength="' . ENTRY_DOB_MIN_LENGTH . '"', 'date'); ?>
                  </div>
                </div>
              </div>
            </div>
            <?php
          }
          ?>
          <div class="row">
            <div class="col-md-12">
              <div class="form-group row">
                <label for="InputEmail"
                       class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_email_address_text'); ?></label>
                <div class="col-sm-6 col-md-6">
                  <?php echo HTML::inputField('email_address', null, 'rel="txtTooltipEmailAddress" required aria-required="true" autocomplete="email" title="' . CLICSHOPPING::getDef('text_create_account_dgrp') . '" data-bs-toggle="tooltip" data-placement="right" required aria-required="true" id="InputEmail" aria-describedby="' . CLICSHOPPING::getDef('entry_email_address') . '" placeholder="' . CLICSHOPPING::getDef('entry_email_address') . '"', 'email'); ?>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-12">
            <div class="form-group row">
              <label for="InputEmailConfirm"
                     class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_email_address_confirmation'); ?></label>
              <div class="col-sm-6 col-md-6">
                <?php echo HTML::inputField('email_address_confirm', null, 'required aria-required="true" id="InputEmailConfirm" autocomplete="email" aria-describedby="' . CLICSHOPPING::getDef('entry_email_address_confirmation') . '" placeholder="' . CLICSHOPPING::getDef('entry_email_address_confirmation') . '"', 'email'); ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <?php
  // ----------------------
  // Newsletter Information
  // ----------------------
  ?>
  <div class="card">
    <div class="card-header">
      <span
        class="modulesCreateAccountProRegistrationCategoryOptionsPageHeader"><h3><?php echo CLICSHOPPING::getDef('entry_newsletter'); ?></h3></span>
    </div>
    <div class="card-block">
      <div class="mt-1"></div>
      <div class="card-text">
        <div class="row">
          <div class="col-md-12">
            <div class="form-group row">
              <label for="InputNewsletter"
                     class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_newsletter'); ?></label>
              <div class="col-sm-6 col-md-6">
                <?php echo HTML::checkboxField('newsletter', 1, false, 'id="Inputnewsletter" aria-label="' . CLICSHOPPING::getDef('entry_newsletter') . '"'); ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <?php
  // ----------------------
  // Password
  // ----------------------
  if (MEMBER == 'false') {
    ?>
    <div class="card">
      <div class="card-header">
        <span class="alert-warning float-end" role="alert"><?php echo CLICSHOPPING::getDef('form_required'); ?></span>
        <span
          class="modulesCreateAccountProRegistrationPasswordPageHeader"><h3><?php echo CLICSHOPPING::getDef('category_password'); ?></h3></span>
      </div>
      <div class="card-block">
        <div class="mt-1"></div>
        <div class="card-text">
          <div class="row">
            <div class="col-md-12">
              <div class="form-group row">
                <label for="inputPassword"
                       class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_password'); ?></label>
                <div class="col-sm-6 col-md-6">
                  <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
                    <span><?php echo HTML::inputField('password', null, 'required aria-required="true" autocomplete="off" id="input-password" aria-describedby="' . CLICSHOPPING::getDef('entry_password') . '" placeholder="' . CLICSHOPPING::getDef('entry_password') . '" minlength="' . (int)ENTRY_PASSWORD_PRO_MIN_LENGTH . '"'); ?></span>
                    <span><button type="button" id="button-generate" class="btn btn-primary btn-sm"><i
                          class="bi bi-arrow-clockwise"></i></button></span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-12">
              <div class="form-group row">
                <label for="inputPasswordConfirmation"
                       class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_password_confirmation'); ?></label>
                <div class="col-sm-6 col-md-6">
                  <?php echo HTML::inputField('confirmation', null, 'required aria-required="true" id="inputPasswordConfirmation" aria-describedby="' . CLICSHOPPING::getDef('entry_password_confirmation') . '" placeholder="' . CLICSHOPPING::getDef('entry_password_confirmation') . '" minlength="' . (int)ENTRY_PASSWORD_PRO_MIN_LENGTH . '"', 'password'); ?>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="mt-1"></div>
    <?php
  }
  require_once(CLICSHOPPING::getConfig('dir_root', 'Shop') . 'ext/javascript/clicshopping/ClicShoppingAdmin/state_dropdown.php');
  ?>
</div>
