<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\DateTime;
use ClicShopping\OM\Hash;
use ClicShopping\OM\HTML;
use ClicShopping\OM\ObjectInfo;
use ClicShopping\OM\Registry;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\Apps\Customers\Groups\Classes\ClicShoppingAdmin\VatNumber;
use ClicShopping\Sites\ClicShoppingAdmin\AddressAdmin;

$CLICSHOPPING_Customers = Registry::get('Customers');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Hooks = Registry::get('Hooks');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Address = Registry::get('Address');
$CLICSHOPPING_Wysiwyg = Registry::get('Wysiwyg');

if ($CLICSHOPPING_MessageStack->exists('header')) {
  echo $CLICSHOPPING_MessageStack->get('header');
}

$error = false;
$processed = false;

$newsletter_array = [
  array('id' => '1', 'text' => $CLICSHOPPING_Customers->getDef('entry_newsletter_yes')),
  array('id' => '0', 'text' => $CLICSHOPPING_Customers->getDef('entry_newsletter_no'))
];

$customers_email_array = [
  array('id' => '1', 'text' => $CLICSHOPPING_Customers->getDef('entry_customers_yes')),
  array('id' => '0', 'text' => $CLICSHOPPING_Customers->getDef('entry_customers_no'))
];


$languages = $CLICSHOPPING_Language->getLanguages();

for ($i = 0, $n = \count($languages); $i < $n; $i++) {
  $values_languages_id[$i] = [
    'id' => $languages[$i]['id'],
    'text' => $languages[$i]['name']
  ];
}

$Qcustomers = $CLICSHOPPING_Customers->db->prepare('select c.*,
                                                             a.*
                                                      from :table_customers c left join :table_address_book a on c.customers_default_address_id = a.address_book_id
                                                      where a.customers_id = c.customers_id
                                                      and c.customers_id = :customers_id
                                                    ');
$Qcustomers->bindInt(':customers_id', $_GET['cID']);
$Qcustomers->execute();

$cInfo = new ObjectInfo($Qcustomers->toArray());

// Lecture sur la base de données des informations facturations et livraison du groupe client
if ($cInfo->customers_group_id != 0) {
  $QcustomersGroup = $CLICSHOPPING_Customers->db->prepare('select customers_group_name,
                                                                    group_order_taxe,
                                                                    group_payment_unallowed,
                                                                    group_shipping_unallowed
                                                             from :table_customers_groups
                                                             where customers_group_id = :customers_group_id
                                                            ');
  $QcustomersGroup->bindInt(':customers_group_id', $cInfo->customers_group_id);
  $QcustomersGroup->execute();

  $cInfo_group = new ObjectInfo($QcustomersGroup->toArray());
}

if ($cInfo->entry_country_id == 0) {
  echo '<div class="row alert alert-warning ">';
  echo '<span class="col-md-10">' . $CLICSHOPPING_Customers->getDef('text_customer_no_registred_completly') . '</span>';
  echo '<span class="col-md-2 text-end">' . HTML::button($CLICSHOPPING_Customers->getDef('button_back'), null, $CLICSHOPPING_Customers->link('Customers'), 'success', null, 'sm') . '</span>';
  echo '</div>';
  exit;
}
?>

<script>
  function check_form() {
    var error = 0;
    var error_message = <?= json_encode(CLICSHOPPING::getDef('js_error') . "\n\n"); ?>;

    var customers_firstname = document.customers.customers_firstname.value;
    var customers_lastname = document.customers.customers_lastname.value;
    <?php if (ACCOUNT_COMPANY == 'true') echo 'var entry_company = document.customers.entry_company.value;' . "\n"; ?>
    <?php if (ACCOUNT_DOB == 'true') echo 'var customers_dob = document.customers.customers_dob.value;' . "\n"; ?>
    var customers_email_address = document.customers.customers_email_address.value;
    var entry_street_address = document.customers.entry_street_address.value;
    var entry_postcode = document.customers.entry_postcode.value;
    var entry_city = document.customers.entry_city.value;
    var customers_telephone = document.customers.customers_telephone.value;
    <?php
    if (ACCOUNT_GENDER == 'true') {
    ?>
    if (document.customers.customers_gender[0].checked || document.customers.customers_gender[1].checked) {
    } else {
      error_message = error_message + <?= json_encode(CLICSHOPPING::getDef('js_gender') . "\n"); ?>;
      error = 1;
    }
    <?php
    }
    ?>
    if (customers_firstname.length < <?php echo ENTRY_FIRST_NAME_MIN_LENGTH; ?>) {
      error_message = error_message + <?= json_encode(CLICSHOPPING::getDef('js_first_name', ['min_length' => ENTRY_FIRST_NAME_MIN_LENGTH]) . "\n"); ?>;
      error = 1;
    }

    if (customers_lastname.length < <?php echo ENTRY_LAST_NAME_MIN_LENGTH; ?>) {
      error_message = error_message + <?= json_encode(CLICSHOPPING::getDef('js_last_name', ['min_length' => ENTRY_LAST_NAME_MIN_LENGTH]) . "\n"); ?>;
      error = 1;
    }

    if (customers_dob.length < <?php echo ENTRY_DOB_MIN_LENGTH; ?>) {
      error_message = error_message + <?= json_encode(CLICSHOPPING::getDef('js_dob') . "\n"); ?>;
      error = 1;
    }

    if (entry_street_address.length < <?php echo ENTRY_STREET_ADDRESS_MIN_LENGTH; ?>) {
      error_message = error_message + <?= json_encode(CLICSHOPPING::getDef('js_address', ['min_length' => ENTRY_STREET_ADDRESS_MIN_LENGTH]) . "\n"); ?>;
      error = 1;
    }

    if (entry_postcode.length < <?php echo ENTRY_POSTCODE_MIN_LENGTH; ?>) {
      error_message = error_message + <?= json_encode(CLICSHOPPING::getDef('js_post_code', ['min_length' => ENTRY_POSTCODE_MIN_LENGTH]) . "\n"); ?>;
      error = 1;
    }

    if (entry_city.length < <?php echo ENTRY_CITY_MIN_LENGTH; ?>) {
      error_message = error_message + <?= json_encode(CLICSHOPPING::getDef('js_city', ['min_length' => ENTRY_CITY_MIN_LENGTH]) . "\n"); ?>;
      error = 1;
    }

    <?php
    if (ACCOUNT_STATE == 'true' ) {
    $Qzones = $CLICSHOPPING_Customers->db->get('zones', 'zone_name', ['zone_country_id' => $cInfo->entry_country_id], 'zone_name');
    $check = $Qzones->fetchAll();

      if (\count($check) > 0) {
    ?>
        if (document.customers.elements['entry_state'].type != "hidden") {
          if (document.customers.entry_state.value.length < <?php echo ENTRY_STATE_MIN_LENGTH; ?>) {
            error_message = error_message + <?= json_encode(CLICSHOPPING::getDef('js_state') . "\n"); ?>;
            error = 1;
          }
        }
    <?php
      }
    }
    ?>

    if (document.customers.elements['entry_country_id'].type != "hidden") {
      if (document.customers.entry_country_id.value == 0) {
        error_message = error_message + <?= json_encode(CLICSHOPPING::getDef('js_country') . "\n"); ?>;
        error = 1;
      }
    }

    if (customers_telephone.length < <?php echo ENTRY_TELEPHONE_MIN_LENGTH; ?>) {
      error_message = error_message + <?= json_encode(CLICSHOPPING::getDef('js_telephone', ['min_length' => ENTRY_TELEPHONE_MIN_LENGTH]) . "\n"); ?>;
      error = 1;
    }

    if (error == 1) {
      alert(error_message);
      return false;
    } else {
      return true;
    }
  }

</script>
<?php
echo HTML::form('customers', $CLICSHOPPING_Customers->link('Customers&Update'), 'post', 'onSubmit="return check_form();"') . HTML::hiddenField('default_address_id', $cInfo->customers_default_address_id);
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/client_editer.gif', $CLICSHOPPING_Customers->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-5 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Customers->getDef('heading_title_edit') . (int)$_GET['cID'] . '&nbsp;-&nbsp;' . Hash::displayDecryptedDataText($cInfo->customers_lastname) . '&nbsp;' . Hash::displayDecryptedDataText($cInfo->customers_firstname); ?></span>
          <span class="col-md-6 text-end">
            <?php
            echo HTML::button($CLICSHOPPING_Customers->getDef('button_export_customer_info'), null, $CLICSHOPPING_Customers->link('Customers&ExportCustomerInfo&customers_id=' . (int)$_GET['cID']), 'info') . ' ';
            echo HTML::hiddenField('customers_id', (int)$_GET['cID']);
            echo HTML::button($CLICSHOPPING_Customers->getDef('button_cancel'), null, $CLICSHOPPING_Customers->link('Customers'), 'warning');
            echo '&nbsp;';
            echo HTML::button($CLICSHOPPING_Customers->getDef('button_update'), null, null, 'success');
            ?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <?php
  if ($error === true) {
    ?>
    <div class="alert alert-warning">
      <?php
      echo '<h4><i class="bi bi-exclamation-diamond" title="' . $CLICSHOPPING_Customers->getDef('icon_warning') . '"></i></h4> ';
      echo $CLICSHOPPING_Customers->getDef('warning_edit_customers');
      ?>
    </div>
    <div class="row">&nbsp;</div>
    <?php
  }
  ?>
  <!-- //################################################################################################################ -->
  <!--          ONGLET NOM & ADRESSE          //-->
  <!-- //################################################################################################################ -->
  <div id="customersTabs" style="overflow: auto;">
    <ul class="nav nav-tabs flex-column flex-sm-row" role="tablist" id="myTab">
      <li
        class="nav-item"><?php echo '<a href="#tab1" role="tab" data-bs-toggle="tab" class="nav-link active">' . $CLICSHOPPING_Customers->getDef('tab_general'); ?></a></li>
      <li
        class="nav-item"><?php echo '<a href="#tab2" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_Customers->getDef('tab_societe'); ?></a></li>
      <li
        class="nav-item"><?php echo '<a href="#tab3" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_Customers->getDef('tab_adresse_book'); ?></a></li>
      <li
        class="nav-item"><?php echo '<a href="#tab6" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_Customers->getDef('tab_notes'); ?></a></li>
    </ul>
    <div class="tabsClicShopping">
      <div class="tab-content">
        <!-- //################################################################################################################ -->
        <!--          ONGLET NOM & ADRESSE          //-->
        <!-- //################################################################################################################ -->
        <div class="tab-pane active" id="tab1">
          <div class="mainTitle"><?php echo $CLICSHOPPING_Customers->getDef('category_personal'); ?></div>
          <div class="adminformTitle">
            <?php
            if (ACCOUNT_GENDER == 'true') {
            ?>
            <div class="row">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_Customers->getDef('entry_gender'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_Customers->getDef('entry_gender'); ?></label>
                  <div class="col-md-5 class=" form-check
                  "">
                  <?php
                  if ($error === true) {
                    if ($entry_gender_error === true) {
                      echo HTML::radioField('customers_gender', 'm', $cInfo->customers_gender, 'id="gender_male"') . '&nbsp;&nbsp;' . $CLICSHOPPING_Customers->getDef('male') . '&nbsp;&nbsp;' . HTML::radioField('customers_gender', 'f', $cInfo->customers_gender, $cInfo->customers_gender) . '&nbsp;&nbsp;' . $CLICSHOPPING_Customers->getDef('female') . '&nbsp;' . $CLICSHOPPING_Customers->getDef('entry_gender_error');
                    } else {
                      echo ($cInfo->customers_gender == 'm') ? $CLICSHOPPING_Customers->getDef('male') : $CLICSHOPPING_Customers->getDef('female');
                      echo HTML::hiddenField('customers_gender');
                    }
                  } else {
                    echo HTML::radioField('customers_gender', 'm', $cInfo->customers_gender, 'id="gender_female"') . '&nbsp;&nbsp;' . $CLICSHOPPING_Customers->getDef('male') . '&nbsp;&nbsp;';
                    echo HTML::radioField('customers_gender', 'f', $cInfo->customers_gender, $cInfo->customers_gender) . '&nbsp;&nbsp;' . $CLICSHOPPING_Customers->getDef('female');
                  }
                  ?>
                </div>
              </div>
            </div>
          </div>
          <?php
          }
          ?>
          <div class="row">
            <div class="col-md-5">
              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_Customers->getDef('entry_first_name'); ?>"
                       class="col-5 col-form-label"><?php echo $CLICSHOPPING_Customers->getDef('entry_first_name'); ?></label>
                <div class="col-md-5">
                  <?php
                  if ($error === true) {
                    if ($entry_firstname_error === true) {
                      echo HTML::inputField('customers_firstname', Hash::displayDecryptedDataText($cInfo->customers_firstname), 'maxlength="32" style="border: 2px solid #FF0000"') . '&nbsp;' . $CLICSHOPPING_Customers->getDef('entry_first_name_error', ['min_length' => ENTRY_FIRST_NAME_MIN_LENGTH]);
                    } else {
                      echo Hash::displayDecryptedDataText($cInfo->customers_firstname) . HTML::hiddenField('customers_firstname');
                    }
                  } else {
                    echo HTML::inputField('customers_firstname', Hash::displayDecryptedDataText($cInfo->customers_firstname), 'maxlength="32" required aria-required="true" placeholder="' . $CLICSHOPPING_Customers->getDef('entry_first_name') . '"', true);
                  }
                  ?>
                </div>
              </div>
            </div>
            <div class="col-md-5">
              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_Customers->getDef('entry_last_name'); ?>"
                       class="col-5 col-form-label"><?php echo $CLICSHOPPING_Customers->getDef('entry_last_name'); ?></label>
                <div class="col-md-5">
                  <?php
                  if ($error === true) {
                    if ($entry_lastname_error === true) {
                      echo HTML::inputField('customers_lastname', Hash::displayDecryptedDataText($cInfo->customers_lastname), 'maxlength="32" style="border: 2px solid #FF0000"') . '&nbsp;' . $CLICSHOPPING_Customers->getDef('entry_last_name_error', ['min_length' => ENTRY_LAST_NAME_MIN_LENGTH]);
                    } else {
                      echo Hash::displayDecryptedDataText($cInfo->customers_lastname) . HTML::hiddenField('customers_lastname');
                    }
                  } else {
                    echo HTML::inputField('customers_lastname', Hash::displayDecryptedDataText($cInfo->customers_lastname), 'maxlength="32" required aria-required="true" placeholder="' . $CLICSHOPPING_Customers->getDef('entry_last_name') . '"', true);
                  }
                  ?>
                </div>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-5">
              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_Customers->getDef('entry_date_of_birth'); ?>"
                       class="col-5 col-form-label"><?php echo $CLICSHOPPING_Customers->getDef('entry_date_of_birth'); ?></label>
                <div class="col-md-5">
                  <?php
                  if (!empty($cInfo->customers_dob)) {
                    $date_dob = DateTime::toShortWithoutFormat($cInfo->customers_dob);
                  } else {
                    $date_dob = $cInfo->customers_dob;
                  }

                  if ($error === true) {
                    if ($entry_date_of_birth_error === true) {
                      if (!empty($date_dob)) {
                        echo HTML::inputField('customers_dob', $date_dob, 'maxlength="10" style="border: 2px solid #FF0000" required aria-required="true"', 'date') . '&nbsp;' . $CLICSHOPPING_Customers->getDef('entry_date_of_birth_error');
                      }
                    } else {
                      echo $cInfo->customers_dob . HTML::hiddenField('customers_dob');
                    }
                  } else {
                    echo HTML::inputField('customers_dob', $date_dob, null, 'date');
                  }
                  ?>
                </div>
              </div>
            </div>

            <div class="col-md-5">
              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_Customers->getDef('entry_email_address'); ?>"
                       class="col-5 col-form-label"><?php echo $CLICSHOPPING_Customers->getDef('entry_email_address'); ?></label>
                <div class="col-md-5">
                  <?php
                  if ($error === true) {
                    if ($entry_email_address_error === true) {
                      echo HTML::inputField('customers_email_address', $cInfo->customers_email_address, 'maxlength="96" style="border: 2px solid #FF0000"') . '&nbsp;' . $CLICSHOPPING_Customers->getDef('entry_email_address_error', ['min_length' => ENTRY_EMAIL_ADDRESS_MIN_LENGTH]);
                    } elseif ($entry_email_address_check_error === true) {
                      echo HTML::inputField('customers_email_address', $cInfo->customers_email_address, 'maxlength="96" style="border: 2px solid #FF0000"') . '&nbsp;' . $CLICSHOPPING_Customers->getDef('entry_email_address_check_error');
                    } elseif ($entry_email_address_exists === true) {
                      echo HTML::inputField('customers_email_address', $cInfo->customers_email_address, 'maxlength="96" style="border: 2px solid #FF0000"') . '&nbsp;' . $CLICSHOPPING_Customers->getDef('entry_email_address_error_exists');
                    } else {
                      echo $customers_email_address . HTML::hiddenField('customers_email_address');
                    }
                  } else {
                    echo HTML::inputField('customers_email_address', $cInfo->customers_email_address, 'maxlength="96" required aria-required="true" placeholder="' . $CLICSHOPPING_Customers->getDef('entry_email_address') . '"', true);
                  }
                  ?>
                </div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-5">
              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_Customers->getDef('entry_telephone_number'); ?>"
                       class="col-5 col-form-label"><?php echo $CLICSHOPPING_Customers->getDef('entry_telephone_number'); ?></label>
                <div class="col-md-5">
                  <?php
                  if ($error === true) {
                    if ($entry_telephone_error === true) {
                      echo HTML::inputField('customers_telephone', Hash::displayDecryptedDataText($cInfo->customers_telephone), 'maxlength="32" style="border: 2px solid #FF0000"') . '&nbsp;' . $CLICSHOPPING_Customers->getDef('entry_telephone_number_error', ['min_length' => ENTRY_TELEPHONE_MIN_LENGTH]);
                    } else {
                      echo Hash::displayDecryptedDataText($cInfo->customers_telephone) . HTML::hiddenField('customers_telephone');
                    }
                  } else {
                    echo HTML::inputField('customers_telephone', Hash::displayDecryptedDataText($cInfo->customers_telephone), 'maxlength="32" required aria-required="true" placeholder="' . $CLICSHOPPING_Customers->getDef('entry_telephone_number') . '"', true);
                  }
                  ?>
                </div>
              </div>
            </div>

            <div class="col-md-5">
              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_Customers->getDef('entry_cellular_phone_number'); ?>"
                       class="col-5 col-form-label"><?php echo $CLICSHOPPING_Customers->getDef('entry_cellular_phone_number'); ?></label>
                <div class="col-md-5">
                  <?php
                  if ($processed === true) {
                    echo Hash::displayDecryptedDataText($cInfo->customers_cellular_phone) . HTML::hiddenField('customers_cellular_phone');
                  } else {
                    echo HTML::inputField('customers_cellular_phone', Hash::displayDecryptedDataText($cInfo->customers_cellular_phone), 'maxlength="32"', true);
                  }
                  ?>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="mt-1"></div>
        <div class="mainTitle"><?php echo $CLICSHOPPING_Customers->getDef('entry_street_address'); ?></div>
        <div class="adminformTitle">

          <div class="row">
            <div class="col-md-5">
              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_Customers->getDef('entry_street_address'); ?>"
                       class="col-5 col-form-label"><?php echo $CLICSHOPPING_Customers->getDef('entry_street_address'); ?></label>
                <div class="col-md-5">
                  <?php
                  if ($error === true) {
                    if ($entry_street_address_error === true) {
                      echo HTML::inputField('entry_street_address', Hash::displayDecryptedDataText($cInfo->entry_street_address), 'maxlength="64" style="border: 2px solid #FF0000"') . '&nbsp;' . $CLICSHOPPING_Customers->getDef('entry_street_address_error', ['min_length' => ENTRY_STREET_ADDRESS_MIN_LENGTH]);
                    } else {
                      echo Hash::displayDecryptedDataText($cInfo->entry_street_address) . HTML::hiddenField('entry_street_address');
                    }
                  } else {
                    echo HTML::inputField('entry_street_address', Hash::displayDecryptedDataText($cInfo->entry_street_address), 'maxlength="64" required aria-required="true" placeholder="' . $CLICSHOPPING_Customers->getDef('entry_street_address') . '"');
                  }
                  ?>
                </div>
              </div>
            </div>

            <?php
            if (ACCOUNT_SUBURB == 'true') {
              ?>
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_Customers->getDef('entry_suburb'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_Customers->getDef('entry_suburb'); ?></label>
                  <div class="col-md-5">
                    <?php
                    if ($error === true) {
                      if ($entry_suburb_error === true) {
                        echo HTML::inputField('suburb', Hash::displayDecryptedDataText($cInfo->entry_suburb), 'maxlength="32"') . '&nbsp;' . $CLICSHOPPING_Customers->getDef('entry_suburb_error');
                      } else {
                        echo Hash::displayDecryptedDataText($cInfo->entry_suburb) . HTML::hiddenField('entry_suburb');
                      }
                    } else {
                      echo HTML::inputField('entry_suburb', Hash::displayDecryptedDataText($cInfo->entry_suburb), 'maxlength="32"');
                    }
                    ?>
                  </div>
                </div>
              </div>
              <?php
            }
            ?>
          </div>

          <div class="row">
            <div class="col-md-5">
              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_Customers->getDef('entry_post_code'); ?>"
                       class="col-5 col-form-label"><?php echo $CLICSHOPPING_Customers->getDef('entry_post_code'); ?></label>
                <div class="col-md-5">
                  <?php
                  if ($error === true) {
                    if ($entry_post_code_error === true) {
                      echo HTML::inputField('entry_postcode', Hash::displayDecryptedDataText($cInfo->entry_postcode), 'maxlength="8" style="border: 2px solid #FF0000"') . '&nbsp;' . $CLICSHOPPING_Customers->getDef('entry_post_code_error', ['min_length' => ENTRY_POSTCODE_MIN_LENGTH]);
                    } else {
                      echo Hash::displayDecryptedDataText($cInfo->entry_postcode) . HTML::hiddenField('entry_postcode');
                    }
                  } else {
                    echo HTML::inputField('entry_postcode', Hash::displayDecryptedDataText($cInfo->entry_postcode), 'maxlength="8" required aria-required="true" placeholder="' . $CLICSHOPPING_Customers->getDef('entry_post_code') . '"');
                  }
                  ?>
                </div>
              </div>
            </div>
            <div class="col-md-5">
              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_Customers->getDef('entry_city'); ?>"
                       class="col-5 col-form-label"><?php echo $CLICSHOPPING_Customers->getDef('entry_city'); ?></label>
                <div class="col-md-5">
                  <?php
                  if ($error === true) {
                    if ($entry_city_error === true) {
                      echo HTML::inputField('entry_city', Hash::displayDecryptedDataText($cInfo->entry_city), 'maxlength="32" style="border: 2px solid #FF0000"') . '&nbsp;' . $CLICSHOPPING_Customers->getDef('entry_city_error', ['min_length' => ENTRY_CITY_MIN_LENGTH]);
                    } else {
                      echo Hash::displayDecryptedDataText($cInfo->entry_city) . HTML::hiddenField('entry_city');
                    }
                  } else {
                    echo HTML::inputField('entry_city', Hash::displayDecryptedDataText($cInfo->entry_city), 'maxlength="32" required aria-required="true" placeholder="' . $CLICSHOPPING_Customers->getDef('entry_city') . '"');
                  }
                  ?>
                </div>
              </div>
            </div>
          </div>
          <?php
          if (ACCOUNT_STATE == 'true') {
            ?>
            <div class="row">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_Customers->getDef('entry_state'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_Customers->getDef('entry_state'); ?></label>
                  <div class="col-md-5">
                    <?php
                    $entry_state = $CLICSHOPPING_Address->getZoneName($cInfo->entry_country_id, $cInfo->entry_zone_id, $cInfo->entry_state);

                    if ($error === true) {
                      if ($_SESSION['entry_state_error'] === true) {
                        if ($_SESSION['entry_state_has_zones'] === true) {
                          $zones_array = [];

                          $Qzones = $CLICSHOPPING_Customers->db->get('zones', 'zone_name', ['zone_country_id' => $cInfo->entry_country_id], 'zone_name');

                          while ($Qzones->fetch()) {
                            $zones_array[] = [
                              'id' => $Qzones->value('zone_name'),
                              'text' => $Qzones->value('zone_name')
                            ];
                          }

                          echo HTML::selectMenu('entry_state', $zones_array) . '&nbsp;' . $CLICSHOPPING_Customers->getDef('entry_state_error', ['min_length' => ENTRY_STATE_MIN_LENGTH]);
                        } else {
                          echo HTML::inputField('entry_state', $CLICSHOPPING_Address->getZoneName($cInfo->entry_country_id, $cInfo->entry_zone_id, $cInfo->entry_state)) . '&nbsp;' . $CLICSHOPPING_Customers->getDef('entry_state_error', ['min_length' => ENTRY_STATE_MIN_LENGTH]);
                        }
                      } else {
                        echo $CLICSHOPPING_Address->getZoneName($cInfo->entry_country_id, $cInfo->entry_zone_id, $cInfo->entry_state) . HTML::hiddenField('entry_zone_id') . HTML::hiddenField('entry_state');
                      }
                    } else {
                      echo HTML::inputField('entry_state', $CLICSHOPPING_Address->getZoneName($cInfo->entry_country_id, $cInfo->entry_zone_id, $cInfo->entry_state));
                    }
                    ?>
                  </div>
                </div>
              </div>
            </div>
            <?php
          }
          ?>
          <div class="row">
            <div class="col-md-5">
              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_Customers->getDef('entry_country'); ?>"
                       class="col-5 col-form-label"><?php echo $CLICSHOPPING_Customers->getDef('entry_country'); ?></label>
                <div class="col-md-5">
                  <?php
                  if ($error === true) {
                    if ($entry_country_error === true) {
                      echo HTML::selectMenuCountryList('entry_country_id', $cInfo->entry_country_id) . '&nbsp;' . $CLICSHOPPING_Customers->getDef('entry_country_error');

                    } else {
                      echo $CLICSHOPPING_Address->getCountryName($cInfo->entry_country_id) . HTML::hiddenField('entry_country_id');
                    }
                  } else {
                    echo HTML::selectMenuCountryList('entry_country_id', $cInfo->entry_country_id);
                  }
                  ?>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="mt-1"></div>
        <div class="mainTitle"><?php echo $CLICSHOPPING_Customers->getDef('text_newsletter'); ?></div>
        <div class="adminformTitle">
          <div class="row">
            <div class="col-md-5">
              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_Customers->getDef('entry_newsletter'); ?>"
                       class="col-5 col-form-label"><?php echo $CLICSHOPPING_Customers->getDef('entry_newsletter'); ?></label>
                <div class="col-md-5">
                  <?php
                  if ($processed === true) {
                    if ($cInfo->customers_newsletter == 1) {
                      echo $CLICSHOPPING_Customers->getDef('entry_newsletter_yes');
                    } else {
                      echo $CLICSHOPPING_Customers->getDef('entry_newsletter_no');
                    }

                    echo HTML::hiddenField('customers_newsletter');
                  } else {
                    echo HTML::selectMenu('customers_newsletter', $newsletter_array, (($cInfo->customers_newsletter == 1) ? 1 : 0));
                  }
                  ?>
                </div>
              </div>
            </div>
            <div class="col-md-5">
              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_Customers->getDef('entry_newsletter_language'); ?>"
                       class="col-5 col-form-label"><?php echo $CLICSHOPPING_Customers->getDef('entry_newsletter_language'); ?></label>
                <div class="col-md-5">
                  <?php
                  if (\is_null($values_languages_id)) {
                    $values_languages_id = DEFAULT_LANGUAGES;
                  }

                  echo HTML::selectMenu('languages_id', $values_languages_id, $cInfo->languages_id);
                  ?>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="mt-1"></div>

        <div class="mainTitle"><?php echo $CLICSHOPPING_Customers->getDef('text_customer_info'); ?></div>
        <div class="adminformTitle">
          <div class="row">
            <div class="col-md-5">
              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_Customers->getDef('text_customer_ip'); ?>"
                       class="col-5 col-form-label"><?php echo $CLICSHOPPING_Customers->getDef('text_customer_ip'); ?></label>
                <div class="col-md-5">
                  <?php echo $cInfo->client_computer_ip; ?>
                </div>
              </div>
            </div>
            <div class="col-md-5">
              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_Customers->getDef('text_customer_provider'); ?>"
                       class="col-5 col-form-label"><?php echo $CLICSHOPPING_Customers->getDef('text_customer_provider'); ?></label>
                <div class="col-md-5">
                  <?php echo $cInfo->provider_name_client; ?>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div id="tab1Content"></div>
      </div>

      <!-- //################################################################################################################ -->
      <!--          ONGLET Infos Société          //-->
      <!-- //################################################################################################################ -->
      <?php
      // Insertion du numéro de Siret, code APE et TVA Intracom
      if ((ACCOUNT_COMPANY == 'true') || (ACCOUNT_COMPANY_PRO == 'true') || (ACCOUNT_SIRET_PRO == 'true') || (ACCOUNT_APE_PRO == 'true') || (ACCOUNT_TVA_INTRACOM_PRO == 'true')) {
        ?>
        <div class="tab-pane" id="tab2">
          <div class="mainTitle">
            <span class="col-md-2"><?php echo $CLICSHOPPING_Customers->getDef('category_company'); ?></span>
            <span class="float-end col-md-10">
                <span
                  class="col-md-11 text-end"><?php echo $CLICSHOPPING_Customers->getDef('entry_customers_modify_company'); ?></span>
<?php
if ($error === true) {
  ?>
  <span class="col-md-5 text-end">
<?php
if ($cInfo->customers_modify_company != '1') echo ':&nbsp;' . $CLICSHOPPING_Customers->getDef('error_entry_customers_modify_no');
if ($cInfo->customers_modify_company == '1') echo ':&nbsp;' . $CLICSHOPPING_Customers->getDef('error_entry_customers_modifiy_yes');
echo HTML::hiddenField('customers_modify_company');
?>
                </span>
  <?php
} else {
  ?>
  <span
    class="col-md-1"><?php echo HTML::checkboxField('customers_modify_company', '1', $cInfo->customers_modify_company); ?></span>
  <?php
}
?>
              </span>
          </div>

          <div class="adminformTitle">
            <?php
            // Insertion du numéro de Siret, code APE et TVA Intracom
            if (ACCOUNT_COMPANY_PRO == 'true') {
              ?>
              <div class="row">
                <div class="col-md-5">
                  <div class="form-group row">
                    <label for="<?php echo $CLICSHOPPING_Customers->getDef('entry_company'); ?>"
                           class="col-5 col-form-label"><?php echo $CLICSHOPPING_Customers->getDef('entry_company'); ?></label>
                    <div class="col-md-5">
                      <?php
                      if ($error === true) {
                        if ($entry_company_error === true) {
                          echo HTML::inputField('customers_company', Hash::displayDecryptedDataText($cInfo->customers_company), 'maxlength="32" style="border: 2px solid #FF0000"') . '&nbsp;' . $CLICSHOPPING_Customers->getDef('entry_company_error', ['min_length' => ENTRY_COMPANY_MIN_LENGTH]);
                        } else {
                          echo Hash::displayDecryptedDataText($cInfo->customers_company) . HTML::hiddenField('customers_company');
                        }
                      } else {
                        echo HTML::inputField('customers_company', Hash::displayDecryptedDataText($cInfo->customers_company), 'maxlength="32"') . $CLICSHOPPING_Customers->getDef('text_field_required');
                      }
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
                <div class="col-md-5">
                  <div class="form-group row">
                    <label for="<?php echo $CLICSHOPPING_Customers->getDef('entry_siret'); ?>"
                           class="col-5 col-form-label"><?php echo $CLICSHOPPING_Customers->getDef('entry_siret'); ?></label>
                    <div class="col-md-5">
                      <?php
                      if ($error === true) {
                        echo $cInfo->customers_siret . HTML::hiddenField('customers_siret');
                      } else {
                        echo HTML::inputField('customers_siret', $cInfo->customers_siret, 'maxlength="14"');
                      }
                      echo '&nbsp;<span class="fieldRequired">' . $CLICSHOPPING_Customers->getDef('entry_siret_exemple') . '</span>';
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
                <div class="col-md-5">
                  <div class="form-group row">
                    <label for="<?php echo $CLICSHOPPING_Customers->getDef('entry_ape'); ?>"
                           class="col-5 col-form-label"><?php echo $CLICSHOPPING_Customers->getDef('entry_ape'); ?></label>
                    <div class="col-md-5">
                      <?php
                      if ($error === true) {
                        echo $cInfo->customers_ape . HTML::hiddenField('customers_ape');
                      } else {
                        echo HTML::inputField('customers_ape', $cInfo->customers_ape, 'maxlength="4"');
                      }
                      echo '&nbsp;<span class="fieldRequired">' . $CLICSHOPPING_Customers->getDef('entry_ape_exemple') . '</span>';
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
                <div class="col-md-5">
                  <div class="form-group row">
                    <label for="<?php echo $CLICSHOPPING_Customers->getDef('entry_tva'); ?>"
                           class="col-5 col-form-label"><?php echo $CLICSHOPPING_Customers->getDef('entry_tva'); ?></label>
                    <div class="col-md-5">
                      <?php
                      if ($error === true) {
                        if ($customers_tva_intracom_code_iso_error === true) {
                          echo $CLICSHOPPING_Customers->getDef('entry_company') . HTML::inputField('customers_tva_intracom_code_iso', $cInfo->customers_tva_intracom_code_iso, 'maxlength="2" size="2" style="border: 2px solid #FF0000"') . '&nbsp;' . $cInfo->customers_tva_intracom . HTML::hiddenField('customers_tva_intracom') . '&nbsp;' . $CLICSHOPPING_Customers->getDef('entry_tva_iso_error');
                        } else {
                          echo $CLICSHOPPING_Customers->getDef('entry_tva') . ' ' . $cInfo->customers_tva_intracom . HTML::hiddenField('customers_tva_intracom_code_iso') . HTML::hiddenField('customers_tva_intracom');
                        }
                      } else {
                        echo $CLICSHOPPING_Customers->getDef('entry_company') . HTML::inputField('customers_tva_intracom_code_iso', $cInfo->customers_tva_intracom_code_iso, 'maxlength="2" size="2"');
                        echo '<br />' . $CLICSHOPPING_Customers->getDef('entry_tva') . ' ' . HTML::inputField('customers_tva_intracom', $cInfo->customers_tva_intracom, 'maxlength="14"');
                      }
                      ?>
                      <!-- lien pointant sur le site de vérification -->
                      <a
                        href="<?php echo 'https://ec.europa.eu/taxation_customs/vies/#/vat-validation?memberStateCode=' . $cInfo->customers_tva_intracom_code_iso . '&number=' . preg_replace('/^' . $cInfo->customers_tva_intracom_code_iso . '/', '', $cInfo->customers_tva_intracom); ?>"
                        target="_blank"
                        rel="noreferrer"><?php echo $CLICSHOPPING_Customers->getDef('tva_intracom_verify'); ?></a>
                    </div>
                  </div>
                </div>
              </div>
              <?php
            }

              if (defined('PAPPERS_API_TOKEN') && !empty(PAPPERS_API_TOKEN)) {
            ?>
            <!-- Pappers company lookup -->
            <div class="adminformTitle">
              <div class="row">
                <div class="col-md-12">
                  <div class="form-group row">
                    <label class="col-5 col-form-label">
                      <?php echo $CLICSHOPPING_Customers->getDef('entry_pappers_lookup'); ?>
                    </label>
                    <div class="col-md-5">
                      <?php
                        // Build SIREN from SIRET (first 9 digits) if available
                        $siren = !empty($cInfo->customers_siret) ? substr(preg_replace('/\s/', '', $cInfo->customers_siret), 0, 9) : null;
                        $companyName = Hash::displayDecryptedDataText($cInfo->customers_company);

                        $vatNumber = new VatNumber();
                        $companyInfo = $vatNumber->checkCompanyInfo($companyName, $siren);
                      ?>

                      <?php if ($companyInfo === false): ?>
                        <!-- API token not configured — button hidden -->

                      <?php elseif ($companyInfo !== null): ?>
                        <!-- Display results + fill button -->
                        <div id="pappersInfoBox" class="border rounded p-2 mb-2 bg-light small">
                          <strong><?php echo $companyInfo['name']; ?></strong><br>
                          <?php if ($companyInfo['legal_form']): ?><span><?php echo $companyInfo['legal_form']; ?></span><br><?php endif; ?>
                          <?php if ($companyInfo['address']): ?><span><?php echo $companyInfo['address'] . ', ' . $companyInfo['zip_code'] . ' ' . $companyInfo['city']; ?></span><br><?php endif; ?>
                          <?php if ($companyInfo['vat_number']): ?><span>TVA : <?php echo $companyInfo['vat_number']; ?></span><br><?php endif; ?>
                          <?php if ($companyInfo['naf_label']): ?><span>NAF : <?php echo $companyInfo['naf_code'] . ' – ' . $companyInfo['naf_label']; ?></span><br><?php endif; ?>
                          <?php if ($companyInfo['manager']): ?><span><?php echo $CLICSHOPPING_Customers->getDef('entry_pappers_manager'); ?> : <?php echo $companyInfo['manager']; ?></span><br><?php endif; ?>
                          <?php if ($companyInfo['status'] === 'C'): ?>
                            <span class="text-danger"><?php echo $CLICSHOPPING_Customers->getDef('entry_pappers_company_closed'); ?></span><br>
                          <?php endif; ?>
                          <a href="<?php echo $companyInfo['pappers_url']; ?>" target="_blank" rel="noreferrer" class="small">
                            <?php echo $CLICSHOPPING_Customers->getDef('entry_pappers_view'); ?>
                          </a>
                        </div>

                        <?php
                          // Build JSON for JS fill
                          $pappersJson = json_encode([
                            'customers_company'    => $companyInfo['name']       ?? '',
                            'customers_siret'      => $companyInfo['siret']      ?? '',
                            'customers_ape'        => $companyInfo['naf_code']   ?? '',
                            'customers_tva_intracom' => $companyInfo['vat_number'] ?? '',
                            'customer_company_information' => implode("\n", array_filter([
                              $companyInfo['name'],
                              $companyInfo['legal_form'],
                              $companyInfo['address'],
                              ($companyInfo['zip_code'] ?? '') . ' ' . ($companyInfo['city'] ?? ''),
                              $companyInfo['country'],
                              'SIREN: ' . ($companyInfo['siren'] ?? ''),
                              'TVA: '  . ($companyInfo['vat_number'] ?? ''),
                              'NAF: '  . ($companyInfo['naf_code'] ?? '') . ' ' . ($companyInfo['naf_label'] ?? ''),
                            ])),
                          ]);
                        ?>
                        <button type="button"
                                class="btn btn-sm btn-primary mt-1"
                                onclick="pappersFillFields(<?php echo htmlspecialchars($pappersJson, ENT_QUOTES); ?>)">
                          <i class="bi bi-arrow-down-circle"></i>
                          <?php echo $CLICSHOPPING_Customers->getDef('entry_pappers_fill'); ?>
                        </button>

                      <?php else: ?>
                        <!-- Company not found -->
                        <span class="text-muted small"><?php echo $CLICSHOPPING_Customers->getDef('entry_pappers_not_found'); ?></span>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <?php
              }
            ?>
            <div id="tab2Content"></div>
            <div class="mt-1"></div>
            <div class="alert alert-info">
              <div><?php echo '<h4><i class="bi bi-question-circle" title="' . $CLICSHOPPING_Customers->getDef('title_help_customers_tva') . '"></i></h4> ' . $CLICSHOPPING_Customers->getDef('title_help_customers_tva') ?></div>
              <div class="mt-1"></div>
              <div><?php echo $CLICSHOPPING_Customers->getDef('title_help_tva_customers'); ?></div>
            </div>
          </div>
          <div id="tab2Content"></div>
          <div class="mt-1"></div>
        </div>
        <?php
      } else {
        ?>
        <div class="alert alert-info">
          <div><?php echo '<h4><i class="bi bi-warning" title="' . $CLICSHOPPING_Customers->getDef('title_help_company_warning') . '"></i></h4> ' . $CLICSHOPPING_Customers->getDef('title_help_company_warning') ?></div>
          <div class="mt-1"></div>
          <div><?php echo $CLICSHOPPING_Customers->getDef('text_help_compaby_warning'); ?></div>
        </div>
        <?php
      }
      ?>
      <!-- //################################################################################################################ -->
      <!--          ONGLET Carnet d'adresses          //-->
      <!-- //################################################################################################################ -->
      <div class="tab-pane" id="tab3">
        <div class="mainTitle"><span class="col-md-3">&nbsp;</span></div>
        <div class="adminformTitle">
          <div class="row">
            <div class="col-md-7">
              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_Customers->getDef('text_allow_customer_add_address'); ?>"
                       class="col-5 col-form-label"><?php echo $CLICSHOPPING_Customers->getDef('text_allow_customer_add_address'); ?></label>
                <div class="col-md-5">
                  <ul class="list-group-slider list-group-flush">
                    <li class="list-group-item-slider">
                      <label class="switch">
                        <?php echo HTML::checkboxField('customers_add_address', '1', $cInfo->customers_add_address, 'class="success"'); ?>
                        <span class="slider"></span>
                      </label>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="mt-1"></div>
        <?php
        $number_address = '1';

        $QaddressesBook = AddressAdmin::getListingAdmin($_GET['cID']);

        // only display addresses if more than 1
        if ($QaddressesBook->rowCount() >= 1) {
          while ($QaddressesBook->fetch()) {

            $QcountryAddressesBook = $CLICSHOPPING_Customers->db->prepare('select countries_name
                                                                             from :table_countries
                                                                             where countries_id = :countries_id
                                                                            ');
            $QcountryAddressesBook->bindInt(':countries_id', (int)$QaddressesBook->valueInt('country_id'));
            $QcountryAddressesBook->execute();
            ?>
            <div class="mainTitle">
              <?php
              if ($QaddressesBook->valueInt('address_book_id') == $cInfo->customers_default_address_id) {
              ?>
              <span
                class="col-md-3"><?php echo $CLICSHOPPING_Customers->getDef('entry_address_number') . $number_address . '&nbsp;<i>' . $CLICSHOPPING_Customers->getDef('entry_address_default') . '</i>'; ?></span>
              <?php
              if ($error === true) {
              ?>
              <span class="float-end col-md-9">
                  <span class="col-md-11 text-end">
                    <?php
                    echo '&nbsp;' . $CLICSHOPPING_Customers->getDef('category_company') . ' ' . $CLICSHOPPING_Customers->getDef('entry_customers_modify_address_default') . '&nbsp;:&nbsp;';
                    if ($cInfo->customers_modify_address_default != '1') echo $CLICSHOPPING_Customers->getDef('error_entry_customers_modify_no');
                    if ($cInfo->customers_modify_address_default == '1') echo $CLICSHOPPING_Customers->getDef('error_entry_customers_modify_yes');
                    echo HTML::hiddenField('customers_modify_address_default');
                    ?>
                  </span>
                    <?php
                    echo HTML::hiddenField('customers_modify_address_default');
                    } else {
                    ?>
                  <span class="float-end col-md-9">
                  <span class="col-md-11 text-end">
                    <?php
                    echo '&nbsp;' . $CLICSHOPPING_Customers->getDef('entry_customers_modify_address_default') . '&nbsp;:&nbsp;';
                    echo HTML::checkboxField('customers_modify_address_default', '1', $cInfo->customers_modify_address_default);
                    ?>
                </span>
                    <?php
                    }
                } else {
                  ?>
                  <span
                    class="mainTitle"><?php echo $CLICSHOPPING_Customers->getDef('entry_address_number') . $number_address; ?></span>
                  <?php
                }
                ?>
            </div>
            <div class="adminformTitle">
              <?php
              if ((\strlen($QaddressesBook->value('company')) > '0')) {
                ?>

                <div class="row">
                  <div class="col-md-5">
                    <div class="form-group row">
                      <label for="<?php echo $CLICSHOPPING_Customers->getDef('entry_company'); ?>"
                             class="col-5 col-form-label"><?php echo $CLICSHOPPING_Customers->getDef('entry_company'); ?></label>
                      <div class="col-md-5">
                        <?php echo HASH::displayDecryptedDataText($QaddressesBook->value('company')); ?>
                      </div>
                    </div>
                  </div>
                </div>
                <?php
              }
              ?>
              <div class="row">
                <div class="col-md-5">
                  <div class="form-group row">
                    <label for="<?php echo $CLICSHOPPING_Customers->getDef('entry_first_name'); ?>"
                           class="col-5 col-form-label"><?php echo $CLICSHOPPING_Customers->getDef('entry_first_name'); ?></label>
                    <div class="col-md-5">
                      <?php echo Hash::displayDecryptedDataText($QaddressesBook->value('firstname')); ?>
                    </div>
                  </div>
                </div>
                <div class="col-md-5">
                  <div class="form-group row">
                    <label for="<?php echo $CLICSHOPPING_Customers->getDef('entry_lastname'); ?>"
                           class="col-5 col-form-label"><?php echo $CLICSHOPPING_Customers->getDef('entry_last_name'); ?></label>
                    <div class="col-md-5">
                      <?php echo Hash::displayDecryptedDataText($QaddressesBook->value('lastname')); ?>
                    </div>
                  </div>
                </div>
              </div>

              <div class="row">
                <div class="col-md-5">
                  <div class="form-group row">
                    <label for="<?php echo $CLICSHOPPING_Customers->getDef('entry_telephone'); ?>"
                           class="col-5 col-form-label"><?php echo $CLICSHOPPING_Customers->getDef('entry_telephone'); ?></label>
                    <div class="col-md-5">
                      <?php echo Hash::displayDecryptedDataText($QaddressesBook->value('telephone')); ?>
                    </div>
                  </div>
                </div>
              </div>

              <div class="row">
                <div class="col-md-5">
                  <div class="form-group row">
                    <label for="<?php echo $CLICSHOPPING_Customers->getDef('entry_street_address'); ?>"
                           class="col-5 col-form-label"><?php echo $CLICSHOPPING_Customers->getDef('entry_street_address'); ?></label>
                    <div class="col-md-5">
                      <?php echo Hash::displayDecryptedDataText($QaddressesBook->value('street_address')); ?>
                    </div>
                  </div>
                </div>
                <div class="col-md-7">
                  <div class="form-group row">
                    <div class="col-md-12">
                        <span>
                          <a
                            href="https://maps.google.com/maps?q=<?php echo Hash::displayDecryptedDataText($QaddressesBook->value('street_address')) . ',' . Hash::displayDecryptedDataText($QaddressesBook->value('suburb')) . ',' . Hash::displayDecryptedDataText($QaddressesBook->value('postcode')) . ',' . Hash::displayDecryptedDataText($QaddressesBook->value('city')); ?>&hl=fr&um=1&ie=UTF-8&sa=N&tab=wl"
                            target="_blank"
                            rel="noreferrer"><?php echo $CLICSHOPPING_Customers->getDef('entry_customer_location') . ' <h4><i class="bi bi-question-circle" title="' . $CLICSHOPPING_Customers->getDef('entry_customer_location') . '"></i></h4></a>'; ?>
                       </span>
                    </div>
                  </div>
                </div>
              </div>

              <div class="row">
                <div class="col-md-5">
                  <div class="form-group row">
                    <label for="<?php echo $CLICSHOPPING_Customers->getDef('entry_suburb'); ?>"
                           class="col-5 col-form-label"><?php echo $CLICSHOPPING_Customers->getDef('entry_suburb'); ?></label>
                    <div class="col-md-5">
                      <?php echo Hash::displayDecryptedDataText($QaddressesBook->value('suburb')); ?>
                    </div>
                  </div>
                </div>
              </div>

              <div class="row">
                <div class="col-md-5">
                  <div class="form-group row">
                    <label for="<?php echo $CLICSHOPPING_Customers->getDef('entry_post_code'); ?>"
                           class="col-5 col-form-label"><?php echo $CLICSHOPPING_Customers->getDef('entry_post_code'); ?></label>
                    <div class="col-md-5">
                      <?php echo Hash::displayDecryptedDataText($QaddressesBook->value('postcode')); ?>
                    </div>
                  </div>
                </div>
                <div class="col-md-5">
                  <div class="form-group row">
                    <label for="<?php echo $CLICSHOPPING_Customers->getDef('entry_city'); ?>"
                           class="col-5 col-form-label"><?php echo $CLICSHOPPING_Customers->getDef('entry_city'); ?></label>
                    <div class="col-md-5">
                      <?php echo Hash::displayDecryptedDataText($QaddressesBook->value('city')); ?>
                    </div>
                  </div>
                </div>
              </div>

              <div class="row">
                <div class="col-md-5">
                  <div class="form-group row">
                    <label for="<?php echo $CLICSHOPPING_Customers->getDef('entry_state'); ?>"
                           class="col-5 col-form-label"><?php echo $CLICSHOPPING_Customers->getDef('entry_state'); ?></label>
                    <div class="col-md-5">
                      <?php echo $CLICSHOPPING_Address->getZoneName($QaddressesBook->valueInt('country_id'), $QaddressesBook->valueInt('zone_id'), $QaddressesBook->value('state')); ?>
                    </div>
                  </div>
                </div>
                <div class="col-md-5">
                  <div class="form-group row">
                    <label for="<?php echo $CLICSHOPPING_Customers->getDef('entry_country'); ?>"
                           class="col-5 col-form-label"><?php echo $CLICSHOPPING_Customers->getDef('entry_country'); ?></label>
                    <div class="col-md-5">
                      <?php echo $QcountryAddressesBook->value('countries_name'); ?>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="mt-1"></div>
            <?php
            $number_address = $number_address + 1;
          }
        }
        ?>
        <div id="tab3Content"></div>
        <div class="alert alert-info">
          <div><?php echo '<h4><i class="bi bi-question-circle" title="' . $CLICSHOPPING_Customers->getDef('title_help_customers_image') . '"></i></h4> ' . $CLICSHOPPING_Customers->getDef('title_help_customers_image') ?></div>
          <div class="mt-1"></div>
          <div><?php echo $CLICSHOPPING_Customers->getDef('title_help_customers_default_address'); ?></div>
        </div>
      </div>
      <?php
      //################################################################################################################ -->
      //          ONGLET customers notes     //-->
      //################################################################################################################ -->
      echo $CLICSHOPPING_Wysiwyg::getWysiwyg();
      ?>
      <div class="tab-pane" id="tab6">
        <div class="mainTitle"><?php echo $CLICSHOPPING_Customers->getDef('customers_note'); ?></div>
        <div class="adminformTitle">
          <div class="row">
            <div class="col-md-12">
                <span>
                  <?php
                  $name = 'customers_notes';
                  $ckeditor_id = $CLICSHOPPING_Wysiwyg::getWysiwygId($name);

                  echo $CLICSHOPPING_Wysiwyg::textAreaCkeditor($name, 'soft', '750', '200', '', 'id="' . $ckeditor_id . '"');
                  ?>
                </span>
            </div>
          </div>
        </div>
        <div class="mt-1"></div>
        <div class="mainTitle"><?php echo $CLICSHOPPING_Customers->getDef('customers_note_summary'); ?></div>
        <div class="adminformTitle">
          <div class="row">
            <?php
            $QcustomersNotes = $CLICSHOPPING_Customers->db->prepare('select customers_notes_id,
                                                                            customers_id,
                                                                            customers_notes,
                                                                            customers_notes_date,
                                                                            user_administrator
                                                                    from :table_customers_notes
                                                                    where customers_id = :customers_id
                                                                    order by customers_notes_date desc
                                                                    ');
            $QcustomersNotes->bindInt(':customers_id', $_GET['cID']);
            $QcustomersNotes->execute();

            while ($QcustomersNotes->fetch()) {
              ?>
              <div class="col-md-12">
                <span><strong><?php echo DateTime::toShort($QcustomersNotes->value('customers_notes_date')) . '</strong> : ' . $QcustomersNotes->value('user_administrator'); ?></span>
              </div>
              <div class="col-md-12">
                <span><blockquote><?php echo $QcustomersNotes->value('customers_notes'); ?></blockquote></span>
              </div>
              <div class="mt-1"></div>
              <?php
            }
            ?>
          </div>
        </div>
      </div>
      <div id="tab4Content">
        <?php echo $CLICSHOPPING_Hooks->output('Customers', 'pageTab4', null, 'display'); ?>
      </div>
    </div>
  </div>
</div>
</div>
</form>
<div class="py-4"></div>
<script defer src="<?php echo CLICSHOPPING::link('Shop/ext/javascript/clicshopping/ClicShoppingAdmin/papers_verification.js'); ?>"></script>
