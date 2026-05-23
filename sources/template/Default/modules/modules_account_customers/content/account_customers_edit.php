<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\DateTime;
use ClicShopping\OM\HTML;
use ClicShopping\Sites\Shop\AddressBook;

echo $form;

// ----------------------
// ------ Contact   -----
// ----------------------
?>
  <div class="col-md-<?php echo $content_width; ?>">
    <div class="mt-1"></div>
    <?php
    if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
      ?>
      <div class="row">
        <div class="col-md-12">
          <p class="text-warning text-end"><?php echo CLICSHOPPING::getDef('form_required'); ?></p>
          <h3><?php echo CLICSHOPPING::getDef('text_category_personal_pro'); ?></h3>
        </div>
      </div>
      <?php
    } else {
      ?>
      <div class="row">
        <div class="col-md-12">
          <p class="text-warning text-end"><?php echo CLICSHOPPING::getDef('form_required'); ?></p>
          <div class="page-title AccountCustomersEdit">
            <h1><?php echo CLICSHOPPING::getDef('module_account_customers_edit_title_account'); ?></h1></div>
        </div>
      </div>
      <?php
    }
    ?>
    <div class="hr"></div>
    <div class="mt-1"></div>
    <?php
    if (((\defined('ACCOUNT_GENDER') && ACCOUNT_GENDER == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) || ((\defined('ACCOUNT_GENDER_PRO') && ACCOUNT_GENDER_PRO == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0))) {
      if (isset($customers_gender)) {
        $male = ($customers_gender == 'm') ? true : false;
      } else {
        $male = ($customers_gender == 'm') ? true : false;
      }

      $female = !$male;
      ?>
      <div class="row">
        <div class="col-md-7">
          <div class="form-group row">
            <label for="gender" class="col-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_gender'); ?></label>
            <div class="col-md-5">
              <div class="custom-control custom-radio custom-control-inline">
                <?php echo HTML::radioField('gender', 'm', $male, 'class="custom-control-input" id="male" name="male"'); ?>
                <label class="custom-control-label" for="male"><?php echo CLICSHOPPING::getDef('male'); ?></label>
              </div>
              <div class="custom-control custom-radio custom-control-inline">
                <?php echo HTML::radioField('gender', 'f', $female, 'class="custom-control-input" id="female" name="female"'); ?>
                <label class="custom-control-label" for="female"><?php echo CLICSHOPPING::getDef('female'); ?></label>
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
      <div class="col-md-7">
        <div class="form-group row">
          <label for="InputFirstName"
                 class="col-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_first_name'); ?></label>
          <div class="col-md-5">
            <?php
            echo HTML::inputField('firstname', $customers_firstname, 'id="InputFirstName" required aria-required="true" placeholder="' . CLICSHOPPING::getDef('entry_first_name') . '"');
            if ((($CLICSHOPPING_Customer->getCustomersGroupID() == 0) && (ENTRY_FIRST_NAME_MIN_LENGTH > 0)) || (($CLICSHOPPING_Customer->getCustomersGroupID() != 0) && (ENTRY_FIRST_NAME_PRO_MIN_LENGTH > 0))) {
              echo '&nbsp;' . (!\is_null(CLICSHOPPING::getDef('entry_first_name_text')) ? '<span class="text">' . CLICSHOPPING::getDef('entry_first_name_text') . '</span>' : '');
            }
            ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-7">
        <div class="form-group row">
          <label for="InputLastName"
                 class="col-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_last_name'); ?></label>
          <div class="col-md-5">
            <?php
            echo HTML::inputField('lastname', $customers_lastname, 'id="InputLastName" required aria-required="true" placeholder="' . CLICSHOPPING::getDef('entry_last_name') . '"');
            if ((($CLICSHOPPING_Customer->getCustomersGroupID() == 0) && (ENTRY_LAST_NAME_MIN_LENGTH > 0)) || (($CLICSHOPPING_Customer->getCustomersGroupID() != 0) && (ENTRY_LAST_NAME_MIN_LENGTH > 0))) {
              echo '&nbsp;' . (!\is_null(CLICSHOPPING::getDef('entry_last_name_text')) ? '<span class="text">' . CLICSHOPPING::getDef('entry_last_name_text') . '</span>' : '');
            }
            ?>
          </div>
        </div>
      </div>
    </div>
    <?php
    if ((ACCOUNT_DOB == 'true' && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) || (ACCOUNT_DOB_PRO == 'true' && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0))) {
      if (!empty($customers_dob)) {
        $customers_dob = DateTime::toShort($customers_dob);
        $dateObj = new \DateTimeImmutable($customers_dob);
        $customers_dob = $dateObj->format('Y-m-d');
      } else {
        $customers_dob = null;
      }
      ?>
      <div class="row">
        <div class="col-md-7">
          <div class="form-group row">
            <label for="dob"
                   class="col-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_date_of_birth'); ?></label>
            <div class="col-md-8 date">
              <?php echo HTML::inputField('dob', $customers_dob, 'required aria-required="true"', 'date'); ?>
            </div>
          </div>
        </div>
      </div>
      <?php
    }
    ?>
    <div class="row">
      <div class="col-md-7">
        <div class="form-group row">
          <label for="inputEmail"
                 class="col-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_email_address'); ?></label>
          <div class="col-md-8">
            <?php echo HTML::inputField('email_address',  $customers_email_address, 'id="inputEmail" required aria-required="true" placeholder="' . CLICSHOPPING::getDef('entry_email_address') . '"', 'email') . (!\is_null(CLICSHOPPING::getDef('entry_email_address_text')) ? '&nbsp;<span class="text-warning">' . CLICSHOPPING::getDef('entry_email_address_text') . '</span>' : ''); ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-7">
        <div class="form-group row">
          <label for="inputTelephone"
                 class="col-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_telephone_number'); ?></label>
          <div class="col-md-8">
            <?php
            echo HTML::inputField('customers_telephone', $customers_telephone, 'id="inputTelephone" required aria-required="true" id="inputTelephone" placeholder="' . CLICSHOPPING::getDef('entry_telephone_number') . '"', 'tel');
            if ((($CLICSHOPPING_Customer->getCustomersGroupID() == 0) && (ENTRY_TELEPHONE_MIN_LENGTH > 0)) || (($CLICSHOPPING_Customer->getCustomersGroupID() != 0) && (ENTRY_TELEPHONE_PRO_MIN_LENGTH > 0))) {
              echo '&nbsp;' . (!\is_null(CLICSHOPPING::getDef('entry_telephone_number_text')) ? '<span class="text-warning">' . CLICSHOPPING::getDef('entry_telephone_number_text') . '</span>' : '');
            }
            ?>
          </div>
        </div>
      </div>
    </div>
    <?php
    if ((($CLICSHOPPING_Customer->getCustomersGroupID() == 0) && (ACCOUNT_CELLULAR_PHONE == 'true')) || (($CLICSHOPPING_Customer->getCustomersGroupID() != 0) && (ACCOUNT_CELLULAR_PHONE_PRO == 'true'))) {
      ?>
      <div class="row">
        <div class="col-md-7">
          <div class="form-group row">
            <label for="inputCellularPhone"
                   class="col-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_cellular_phone_number'); ?></label>
            <div class="col-md-8">
              <?php echo HTML::inputField('cellular_phone', $customers_cellular_phone, 'id="inputCellularPhone" placeholder="' . CLICSHOPPING::getDef('entry_cellular_phone_number') . '"', 'tel') . '&nbsp;' . (!\is_null(CLICSHOPPING::getDef('entry_cellular_phone_number_text')) ? '<span class="text-warning">' . CLICSHOPPING::getDef('entry_cellular_phone_number_text') . '</span>' : ''); ?>
            </div>
          </div>
        </div>
      </div>

      <?php
    }
    // ----------------------
    // ----- Company   -----
    // ----------------------
    if ($CLICSHOPPING_MessageStack->exists('main')) {
      echo $CLICSHOPPING_MessageStack->get('main');
    }

    if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
      ?>
      <h2><?php echo CLICSHOPPING::getDef('module_account_customers_edit_title_company'); ?></h2>

      <?php
      if (ACCOUNT_COMPANY_PRO == 'true') {
        if (AddressBook::countCustomersModifyCompany() == 1) {
          $input_field_option = 'maxlength="' . ENTRY_COMPANY_PRO_MAX_LENGTH . '" placeholder="' . CLICSHOPPING::getDef('entry_company') . '" id="entry_company"';
        } else {
          $input_field_option = 'readonly="readonly"';
        }
        ?>
        <div class="row">
          <div class="col-md-7">
            <div class="form-group row">
              <label for="inputCompany"
                     class="col-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_company'); ?></label>
              <div class="col-md-8">
                <?php
                echo HTML::inputField('company', $customers_company, $input_field_option);
                if ((AddressBook::countCustomersModifyCompany() == 1) && (ENTRY_COMPANY_PRO_MIN_LENGTH > 0)) {
                  echo '&nbsp;' . (!\is_null(CLICSHOPPING::getDef('entry_company_text_pro')) ? '<span class="text-warning">' . CLICSHOPPING::getDef('entry_company_text_pro') . '</span>' : '');
                }
                ?>
              </div>
            </div>
          </div>
        </div>

        <?php
      }
      if (ACCOUNT_SIRET_PRO == 'true') {
        if (AddressBook::countCustomersModifyCompany() == 1) {
          $input_field_option = 'maxlength="' . ENTRY_SIRET_MAX_LENGTH . '" placeholder="' . CLICSHOPPING::getDef('entry_siret') . '" id="entry_siret"';
        } else {
          $input_field_option = 'readonly="readonly"';
        }
        ?>
        <div class="row">
          <div class="col-md-7">
            <div class="form-group row">
              <label for="inputSiret"
                     class="col-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_siret'); ?></label>
              <div class="col-md-8">
                <?php
                echo HTML::inputField('siret', $customers_siret, $input_field_option);
                if ((AddressBook::countCustomersModifyCompany() == 1) && (ENTRY_SIRET_MIN_LENGTH > 0)) {
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
        if (AddressBook::countCustomersModifyCompany() == 1) {
          $input_field_option = 'maxlength="' . ENTRY_CODE_APE_MAX_LENGTH . '" placeholder="' . CLICSHOPPING::getDef('entry_code_ape') . '" id="entry_code_ape"';
        } else {
          $input_field_option = 'readonly="readonly"';
        }
        ?>
        <div class="row">
          <div class="col-md-7">
            <div class="form-group row">
              <label for="inputCodeApe"
                     class="col-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_code_ape'); ?></label>
              <div class="col-md-8">
                <?php
                echo HTML::inputField('ape', $customers_ape, $input_field_option);
                if ((AddressBook::countCustomersModifyCompany() == 1) && (ENTRY_CODE_APE_MIN_LENGTH > 0)) {
                  echo '&nbsp;' . (!\is_null(CLICSHOPPING::getDef('entry_code_ape_text')) ? '<span class="text-warning">' . CLICSHOPPING::getDef('entry_code_ape_text') . '</span>' : '');
                }
                echo '&nbsp;' . (!\is_null(CLICSHOPPING::getDef('entry_code_exemple')) ? '<span class="text-warning">' . CLICSHOPPING::getDef('entry_code_exemple') . '</span>' : '');
                ?>
              </div>
            </div>
          </div>
        </div>
        <?php
      }

      if (ACCOUNT_TVA_INTRACOM_PRO == 'true') {
        if (AddressBook::countCustomersModifyCompany() == 1) {
          $input_field_option = 'maxlength="' . ENTRY_TVA_INTRACOM_MAX_LENGTH . '" placeholder="' . CLICSHOPPING::getDef('entry_tva_intracom') . '" id="entry_tva_intracom"';
        } else {
          $input_field_option = 'readonly="readonly"';
        }

        if (AddressBook::countCustomersModifyCompany() == 1) {
          ?>
          <div class="row">
            <div class="col-md-7">
              <div class="form-group row">
                <label for="inputTvaIntracom"
                       class="col-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_country'); ?></label>
                <div class="col-md-8">
                  <?php echo HTML::selectMenuIsoList('country', $customers_tva_intracom_code_iso, 'onchange="ISO_account_edit();"') . '&nbsp;' . (!\is_null(CLICSHOPPING::getDef('entry_country_text')) ? '<span class="text-warning">' . CLICSHOPPING::getDef('entry_country_text') . '</span>' : ''); ?>
                </div>
              </div>
            </div>
          </div>
          <?php
        }
        ?>
        <div class="row">
          <div class="col-md-7">
            <div class="form-group row">
              <label for="entry_tva_intracom"
                     class="col-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_tva_intracom'); ?></label>
              <div class="col-md-8">
                <input type="text" size="2" maxlength="2" name="ISO" readonly="readonly"
                       onFocus="setTimeout('document.country.ISO.blur()',1);"
                       value="<?php echo $customers_tva_intracom_code_iso; ?>"
                       style="bottom:auto; background-color:#fff; border: #fff;">&nbsp;
                <?php
                echo HTML::inputField('tva_intracom', $customers_tva_intracom, $input_field_option);
                if (AddressBook::countCustomersModifyCompany() == 1) {
                  echo '&nbsp;' . (!\is_null(CLICSHOPPING::getDef('entry_tva_intracom_text')) ? '<span class="text-warning">' . CLICSHOPPING::getDef('entry_tva_intracom_text') . '</span>' : '');
                }
                ?>
              </div>
            </div>
          </div>
        </div>

        <?php
      }
    }
    ?>

    <div class="col-md-12">
      <div class="control-group">
        <div>
          <div class="buttonSet">
            <span
              class="col-md-2"><?php echo HTML::button(CLICSHOPPING::getDef('button_back'), null, CLICSHOPPING::link(null, 'Account&Main'), 'primary'); ?></span>
            <span
              class="col-md-2 float-end text-end"><?php echo HTML::button(CLICSHOPPING::getDef('button_continue'), null, null, 'success'); ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php
echo $endform;