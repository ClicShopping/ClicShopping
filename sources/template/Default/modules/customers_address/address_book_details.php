<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Hash;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Shop\AddressBook;

$CLICSHOPPING_Address = Registry::get('Address');
$CLICSHOPPING_Customer = Registry::get('Customer');
$CLICSHOPPING_Db = Registry::get('Db');

if (!isset($_SESSION['process'])) {
  $process = false;
} else {
  $process = true;
}
?>
<div class="mt-1"></div>
<div class="hr"></div>
<?php
if (($CLICSHOPPING_Customer->getCustomersGroupID() == 0 && ACCOUNT_COMPANY == 'true') || ($CLICSHOPPING_Customer->getCustomersGroupID() != 0 && ACCOUNT_COMPANY_PRO == 'true')) {
  $QaccountGroup = $CLICSHOPPING_Db->prepare('select customers_company
                                                 from :table_customers
                                                 where customers_id = :customers_id
                                               ');
  $QaccountGroup->bindInt(':customers_id', (int)$CLICSHOPPING_Customer->getID());
  $QaccountGroup->execute();
  ?>
  <div class="card">
    <div class="card-header">
      <span class="alert-warning float-end"
            role="alert"><?php echo CLICSHOPPING::getDef('form_required_information'); ?></span>
      <h3><span><?php echo CLICSHOPPING::getDef('entry_company'); ?></span></h3>
    </div>
    <div class="card-block">
      <div class="card-text">
        <div class="row">
          <div class="col-md-12">
            <div class="form-group row">
              <label for="InputCompany"
                     class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_company'); ?></label>
              <div class="col-sm-6 col-md-4">
                <?php
                if (isset($_GET['Edit']) && is_numeric($_GET['edit'])) {
                  echo HTML::inputField('company', $entry['company'] ?? '', 'id="InputCompany" aria-describedby="' . CLICSHOPPING::getDef('entry_company') . '" placeholder="' . CLICSHOPPING::getDef('entry_company') . '"');
                } else {
                  echo HTML::inputField('company', $QaccountGroup->value('customers_company'), 'id="InputCompany" aria-describedby="' . CLICSHOPPING::getDef('entry_company') . '" placeholder="' . CLICSHOPPING::getDef('entry_company') . '"');
                }

                if (($CLICSHOPPING_Customer->getCustomersGroupID() == 0 && ENTRY_COMPANY_MIN_LENGTH > 0) || ($CLICSHOPPING_Customer->getCustomersGroupID() != 0 && ENTRY_COMPANY_PRO_MIN_LENGTH > 0)) {
                  echo '&nbsp;' . (!\is_null(CLICSHOPPING::getDef('entry_company_text')) ? '<span class="text-warning">' . CLICSHOPPING::getDef('entry_company_text') . '</span>' : '');
                }
                ?>
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
<div class="mt-1"></div>
<div class="card">
  <div class="card-header">
    <span class="alert-warning float-end" role="alert"><?php echo CLICSHOPPING::getDef('form_required'); ?></span>
    <h3><span><?php echo CLICSHOPPING::getDef('category_personnal'); ?></span></h3>
  </div>
  <div class="card-block">
    <div class="card-text">
      <?php
      if (((ACCOUNT_GENDER == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) || ((ACCOUNT_GENDER_PRO == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0))) {
        $male = $female = false;

        if (isset($gender)) {
          $male = ($gender == 'm') ? true : false;
          $female = !$male;
        } elseif (isset($entry['gender'])) {
          $male = ($entry['gender'] == 'm') ? true : false;
          $female = !$male;
        }
        ?>
        <div class="mt-1"></div>
        <div class="row">
          <div class="col-md-12">
            <div class="form-group row">
              <label for="gender"
                     class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_gender'); ?></label>
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
        <div class="col-md-12">
          <div class="form-group row">
            <label for="InputFirstName"
                   class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_first_name'); ?></label>
            <div class="col-sm-6 col-md-4">
              <?php
              if (empty($entry['firstname'])) {
                echo HTML::inputField('firstname', ($CLICSHOPPING_Customer->hasDefaultAddress() ? $CLICSHOPPING_Customer->getFirstName() : null), 'required aria-required="true" id="InputFirstName" autocomplete="name" aria-describedby="' . CLICSHOPPING::getDef('entry_first_name') . '" placeholder="' . CLICSHOPPING::getDef('entry_first_name') . '" minlength="' . ENTRY_FIRST_NAME_PRO_MIN_LENGTH . '"');
              } else {
                $firstname = null;

                if (isset($entry) && is_array($entry) && isset($entry['firstname'])) {
                  $firstname = Hash::displayDecryptedDataText($entry['firstname']);
                }

                echo HTML::inputField(
                  'firstname',
                  $firstname,
                  'required aria-required="true" id="InputFirstName" autocomplete="name" aria-describedby="' .
                  CLICSHOPPING::getDef('entry_first_name') .
                  '" placeholder="' . CLICSHOPPING::getDef('entry_first_name') .
                  '" minlength="' . ENTRY_FIRST_NAME_PRO_MIN_LENGTH . '"'
                );
              }

              if (($CLICSHOPPING_Customer->getCustomersGroupID() == 0 && ENTRY_FIRST_NAME_MIN_LENGTH > 0) || ($CLICSHOPPING_Customer->getCustomersGroupID() != 0 && ENTRY_FIRST_NAME_PRO_MIN_LENGTH > 0)) {
                echo '&nbsp;' . (!\is_null(CLICSHOPPING::getDef('entry_first_name_text')) ? '<span class="text-warning">' . CLICSHOPPING::getDef('entry_first_name_text') . '</span>' : '');
              }
              ?>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-md-12">
          <div class="form-group row">
            <label for="InputLastName"
                   class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_last_name'); ?></label>
            <div class="col-sm-6 col-md-4">
              <?php
              if (empty($entry['lastname'])) {
                echo HTML::inputField('lastname', ($CLICSHOPPING_Customer->hasDefaultAddress() ? $CLICSHOPPING_Customer->getLastName() : null), 'required aria-required="true" id="InputLastName" autocomplete="name" aria-describedby="' . CLICSHOPPING::getDef('entry_last_name') . '" placeholder="' . CLICSHOPPING::getDef('entry_last_name') . '" minlength="' . ENTRY_LAST_NAME_PRO_MIN_LENGTH . '"');
              } else {
                $lastname = null;

                if (isset($entry) && is_array($entry) && isset($entry['lastname'])) {
                  $lastname = Hash::displayDecryptedDataText($entry['lastname']);
                }

                echo HTML::inputField(
                  'lastname',
                  $lastname,
                  'required aria-required="true" id="InputLastName" autocomplete="name" aria-describedby="' .
                  CLICSHOPPING::getDef('entry_last_name') .
                  '" placeholder="' . CLICSHOPPING::getDef('entry_last_name') .
                  '" minlength="' . ENTRY_LAST_NAME_PRO_MIN_LENGTH . '"'
                );
              }

              if (($CLICSHOPPING_Customer->getCustomersGroupID() == 0 && ENTRY_LAST_NAME_MIN_LENGTH > 0) || ($CLICSHOPPING_Customer->getCustomersGroupID() != 0 && ENTRY_LAST_NAME_PRO_MIN_LENGTH > 0)) {
                echo '&nbsp;' . (!\is_null(CLICSHOPPING::getDef('entry_last_name_text')) ? '<span class="text-warning">' . CLICSHOPPING::getDef('entry_last_name_text') . '</span>' : '');
              }
              ?>
            </div>
          </div>
        </div>
      </div>
      <?php
      if (isset($_GET['newcustomer']) && HTML::sanitize($_GET['newcustomer']) == 1) {
        ?>
        <div class="row">
          <div class="col-md-12">
            <div class="form-group row">
              <label for="InputTelephone"
                     class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_telephone_number'); ?></label>
              <div class="col-sm-6 col-md-4">
                <?php
                $telephone = null;

                if (isset($entry) && is_array($entry) && isset($entry['telephone'])) {
                  $telephone = Hash::displayDecryptedDataText($entry['telephone']);
                }

                echo HTML::inputField(
                  'customers_telephone',
                  $telephone,
                  'rel="txtTooltipPhone" title="' . CLICSHOPPING::getDef('entry_phone_dgrp') .
                  '" data-bs-toggle="tooltip" data-placement="right" required aria-required="true" id="InputTelephone" autocomplete="tel" aria-describedby="' .
                  CLICSHOPPING::getDef('entry_telephone_number') .
                  '" placeholder="' . CLICSHOPPING::getDef('entry_telephone_number') . '"',
                  'phone'
                );
                ?>
              </div>
            </div>
          </div>
        </div>
        <?php
        if (ACCOUNT_CELLULAR_PHONE == 'true') {
          ?>
          <div class="row">
            <div class="col-md-12">
              <div class="form-group row">
                <label for="InputCellularPhone"
                       class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_cellular_phone_number'); ?></label>
                <div class="col-sm-6 col-md-4">
                  <?php echo HTML::inputField('customers_cellular_phone', null, 'rel="txtTooltipPhone" title="' . CLICSHOPPING::getDef('entry_phone_dgrp') . '" data-bs-toggle="tooltip" data-placement="right" id="InputCellularPhone" autocomplete="tel" aria-describedby="' . CLICSHOPPING::getDef('entry_cellular_phone_number') . '" placeholder="' . CLICSHOPPING::getDef('entry_cellular_phone_number') . '"', 'phone'); ?>
                </div>
              </div>
            </div>
          </div>
          <?php
        }
      } // end $_GET['newcustomer']
      ?>
    </div>
  </div>
</div>
<div class="mt-1"></div>
<div class="card">
  <div class="card-header">
    <span class="alert-warning float-end" role="alert"><?php echo CLICSHOPPING::getDef('form_required'); ?></span>
    <h3><span><?php echo CLICSHOPPING::getDef('new_address_title'); ?></span></h3>
  </div>
  <div class="card-block">
    <div class="card-text">
      <div class="mt-1"></div>
      <div class="row">
        <div class="col-md-12">
          <div class="form-group row">
            <label for="InputStreetAddress"
                   class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_street_address'); ?></label>
            <div class="col-sm-6 col-md-4">
              <?php
              $street_address = null;

              if (isset($entry) && is_array($entry) && isset($entry['street_address'])) {
                $street_address = Hash::displayDecryptedDataText($entry['street_address']);
              }

              echo HTML::inputField(
                'street_address',
                $street_address,
                'required aria-required="true" id="InputStreetAddress" aria-describedby="' .
                CLICSHOPPING::getDef('entry_street_address') .
                '" placeholder="' . CLICSHOPPING::getDef('entry_street_address') .
                '" minlength="' . ENTRY_STREET_ADDRESS_PRO_MIN_LENGTH . '"'
              );

              if (($CLICSHOPPING_Customer->getCustomersGroupID() == 0 && ENTRY_STREET_ADDRESS_MIN_LENGTH > 0) || ($CLICSHOPPING_Customer->getCustomersGroupID() != 0 && ENTRY_STREET_ADDRESS_PRO_MIN_LENGTH > 0)) {
                echo '&nbsp;' . (!\is_null(CLICSHOPPING::getDef('entry_street_address_text')) ? '<span class="text-warning">' . CLICSHOPPING::getDef('entry_street_address_text') . '</span>' : '');
              }
              ?>
            </div>
          </div>
        </div>
      </div>
      <?php
      if (((ACCOUNT_SUBURB == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) || ((ACCOUNT_SUBURB_PRO == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0))) {
        ?>
        <div class="row">
          <div class="col-md-12">
            <div class="form-group row">
              <label for="InputSuburb"
                     class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_suburb'); ?></label>
              <div class="col-sm-6 col-md-4">
                <?php
                $suburb = null;

                if (isset($entry) && is_array($entry) && isset($entry['suburb'])) {
                  $suburb = Hash::displayDecryptedDataText($entry['suburb']);
                }

                echo HTML::inputField(
                  'suburb',
                  $suburb,
                  'id="InputSuburb" aria-describedby="' .
                  CLICSHOPPING::getDef('entry_suburb') .
                  '" placeholder="' . CLICSHOPPING::getDef('entry_suburb') . '"'
                );
                ?>
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
            <div class="col-sm-6 col-md-4">
              <?php
              $postcode = null;

              if (isset($entry) && is_array($entry) && isset($entry['postcode'])) {
                $postcode = Hash::displayDecryptedDataText($entry['postcode']);
              }

              echo HTML::inputField(
                'postcode',
                $postcode,
                'required aria-required="true" id="InputPostCode" aria-describedby="' .
                CLICSHOPPING::getDef('entry_post_code') .
                '" placeholder="' . CLICSHOPPING::getDef('entry_post_code') . '"'
              );

              if (($CLICSHOPPING_Customer->getCustomersGroupID() == 0 && ENTRY_POSTCODE_MIN_LENGTH > 0) || ($CLICSHOPPING_Customer->getCustomersGroupID() != 0 && ENTRY_POSTCODE_PRO_MIN_LENGTH > 0)) {
                echo '&nbsp;' . (!\is_null(CLICSHOPPING::getDef('entry_post_code_text')) ? '<span class="text-warning">' . CLICSHOPPING::getDef('entry_post_code_text') . '</span>' : '');
              }
              ?>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-md-12">
          <div class="form-group row">
            <label for="InputCity"
                   class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_city'); ?></label>
            <div class="col-sm-6 col-md-4">
              <?php
              $city = null;

              if (isset($entry) && is_array($entry) && isset($entry['city'])) {
                $city = Hash::displayDecryptedDataText($entry['city']);
              }

              echo HTML::inputField(
                'city',
                $city,
                'required aria-required="true" id="InputCity" aria-describedby="' .
                CLICSHOPPING::getDef('entry_city') .
                '" placeholder="' . CLICSHOPPING::getDef('entry_city') . '"'
              );

              if (($CLICSHOPPING_Customer->getCustomersGroupID() == 0 && ENTRY_CITY_MIN_LENGTH > 0) || ($CLICSHOPPING_Customer->getCustomersGroupID() != 0 && ENTRY_CITY_PRO_MIN_LENGTH > 0)) {
                echo '&nbsp;' . (!\is_null(CLICSHOPPING::getDef('entry_city_text')) ? '<span class="text-warning">' . CLICSHOPPING::getDef('entry_city_text') . '</span>' : '');
              }
              ?>
            </div>
          </div>
        </div>
      </div>
      <?php
      if (((ACCOUNT_STATE == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) || ((ACCOUNT_STATE_PRO == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0))) {
        if (ACCOUNT_STATE_DROPDOWN == 'true' && !isset($_GET['Edit'])) {
          ?>
          <div class="row">
            <div class="col-md-12">
              <div class="form-group row">
                <label for="InputCountry"
                       class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_country'); ?></label>
                <div class="col-sm-6 col-md-4">
                  <?php echo HTML::selectMenuCountryList('country', null, 'onchange="update_zone(this.form);" aria-required="true"'); ?>
                  <?php echo(!\is_null(CLICSHOPPING::getDef('entry_country_text')) ? '<span class="text-warning">' . CLICSHOPPING::getDef('entry_country_text') . '</span>' : ''); ?>
                </div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-12">
              <div class="form-group row">
                <label for="InputState"
                       class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_state'); ?></label>
                <div class="col-sm-6 col-md-4">
                  <?php echo HTML::selectField('state', $CLICSHOPPING_Address->getPrepareCountryZonesPullDown(), null, 'aria-required="true"'); ?>
                  <?php echo(!\is_null(CLICSHOPPING::getDef('entry_state_text')) ? '<span class="text-warning">' . CLICSHOPPING::getDef('entry_state_text') . '</span>' : ''); ?>
                </div>
              </div>
            </div>
          </div>
          <?php
          include_once(CLICSHOPPING::getConfig('dir_root', 'Shop') . 'ext/javascript/clicshopping/ClicShoppingAdmin/state_dropdown.php');
        } else {
          if (isset($entry['country_id']) && !\is_null($entry['country_id'])) {
            $country_id = $entry['country_id'];
          } elseif (isset($_POST['country']) && !empty($_POST['country'])) {
            $country_id = HTML::sanitize($_POST['country']);
          } else {
            $country_id = HTML::sanitize(STORE_COUNTRY);
          }
          ?>
          <div class="row">
            <div class="col-md-12">
              <div class="form-group row">
                <label for="InputCountry"
                       class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_country'); ?></label>
                <div class="col-sm-6 col-md-4">
                  <?php echo HTML::selectMenuCountryList('country', $country_id, 'aria-required="true"'); ?>
                  <?php echo(!\is_null(CLICSHOPPING::getDef('entry_country_text')) ? '<span class="text-warning">' . CLICSHOPPING::getDef('entry_country_text') . '</span>' : ''); ?>
                </div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-12">
              <div class="form-group row">
                <label for="InputState"
                       class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_state'); ?></label>
                <div class="col-sm-6 col-md-4">
                  <?php
                  if ($process === true) {
                    if ($_SESSION['entry_state_has_zones'] === true) {
                      $zones_array = [];

                      if (isset($_POST['country'])) {
                        $country_id = HTML::sanitize($_POST['country']);
                      }

                      if (!empty($entry['zone_id']) && !empty($entry['state'])) {
                        $Qcheck = $CLICSHOPPING_Db->prepare('select zone_name,
                                                       zone_id
                                               from :table_zones
                                               where zone_country_id = :zone_country_id                                                                           
                                               and zone_status = 0
                                               order by zone_name
                                              ');
                        $Qcheck->bindInt(':zone_country_id', $country_id);
                        $Qcheck->execute();
                      } else {
                        $Qcheck = $CLICSHOPPING_Db->prepare('select zone_name,
                                                       zone_id
                                               from :table_zones
                                               where zone_country_id = :zone_country_id
                                               and zone_id = :zone_id  
                                               and zone_status = 0
                                               order by zone_name
                                              ');
                        $Qcheck->bindInt(':zone_country_id', $country_id);
                        $Qcheck->bindInt(':zone_id', $entry['zone_id']);
                        $Qcheck->execute();
                      }

                      if ($Qcheck->rowCount() > 1) {
                        while ($Qcheck->fetch()) {
                          $zones_array[] = [
                            'id' => $Qcheck->value('zone_name'),
                            'text' => $Qcheck->value('zone_name')
                          ];
                        }
                      }

                      if (\is_array($zones_array) && \count($zones_array) > 0) {
                        echo HTML::selectMenu('state', $zones_array, 'id="inputState" aria-describedby="atState"');
                      } else {

                        if (!\is_null($entry['state']) && $entry['country_id'] != 0) {
                          $country_id = $CLICSHOPPING_Address->getZoneName($entry['country_id'], $entry['zone_id'], $entry['state']);
                        }

                        if (is_numeric($country_id)) {
                          echo $CLICSHOPPING_Address->getZoneDropdown($country_id);
                        } else {
                          if ($Qcheck->value('zone_name') !== false) {
                            $state = $Qcheck->value('zone_name');
                            echo HTML::inputField('state', $state, 'id="inputState" placeholder="' . CLICSHOPPING::getDef('entry_state') . '"  aria-describedby="atState"');
                          } else {

                            if (!empty($entry['state'])) {
                              $state = $entry['state'];
                              echo HTML::inputField('state', $state, 'id="inputState" placeholder="' . CLICSHOPPING::getDef('entry_state') . '"  aria-describedby="atState"');
                            } else {
                              $country_id = HTML::sanitize($_POST['country']);

                              echo $CLICSHOPPING_Address->getZoneDropdown($country_id);
                            }
                          }
                        }
                      }
                    } else {
                      if (!empty($entry['state'])) {
                        echo HTML::inputField('state', $entry['state'], 'id="inputState" placeholder="' . CLICSHOPPING::getDef('entry_state') . '" aria-describedby="atState"');
                      } else {
                        echo HTML::inputField('state', '', 'id="inputState" placeholder="' . CLICSHOPPING::getDef('entry_state') . '" aria-describedby="atState"');
                      }
                    }
                  } else {
                    if (isset($entry['country_id']) && $entry['country_id'] != 0) {
                      $country_id = $CLICSHOPPING_Address->getZoneName($entry['country_id'], $entry['zone_id'], $entry['state']);
                    } else {
                      $country_id = '';
                    }

                    echo HTML::inputField('state', $country_id, 'id="atState" placeholder="' . CLICSHOPPING::getDef('entry_state') . '" aria-required="true" aria-describedby="atState"');
                  }

                  if (((!\is_null(CLICSHOPPING::getDef('entry_state_text'))) && (ENTRY_STATE_MIN_LENGTH > 0) && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) || ((!\is_null(CLICSHOPPING::getDef('entry_state_text'))) && (ENTRY_STATE_PRO_MIN_LENGTH > 0) && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0))) {
                    echo '&nbsp;<span class="text-warning">' . CLICSHOPPING::getDef('entry_state_text') . '</span>';
                  }
                  ?>
                </div>
              </div>
            </div>
          </div>
          <?php
        }
      }

      if (isset($_GET['newcustomer']) === false) {
//   Allow or not to customer change this address ou to change the default address if oddo is activated.
        if ((isset($_GET['edit']) && ($CLICSHOPPING_Customer->getDefaultAddressID() != HTML::sanitize($_GET['edit']) && AddressBook::countCustomersModifyAddressDefault() == 1)) || ((isset($_GET['edit']) === false && (AddressBook::countCustomersModifyAddressDefault() == 1)))) {
          ?>
          <div class="row">
            <div class="col-md-12">
              <div class="form-group row">
                <label for="InputNewsletter"
                       class="col-sm-3 col-md-3 col-form-label"><?php echo CLICSHOPPING::getDef('set_as_primary'); ?></label>
                <div class="col-sm-2 col-md-2">
                  <ul class="list-group list-group-flush">
                    <li class="list-group-item-slider">
                      <label class="switch">
                        <?php echo HTML::checkboxField('primary', 'on', false, 'class="success" id="InputNewsletter" aria-label="' . CLICSHOPPING::getDef('set_as_primary') . '"'); ?>
                        <span class="slider"></span>
                      </label>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
          <?php
        }
      } else {
        echo HTML::hiddenField('primary', 'on', true, 'id="primary"');
      }
      ?>
    </div>
  </div>
</div>
<div class="mt-1"></div>