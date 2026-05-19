<?php
/**
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

  use ClicShopping\OM\HTML;
  use ClicShopping\OM\CLICSHOPPING;
  use ClicShopping\OM\Registry;
  use ClicShopping\OM\Is;
  use ClicShopping\OM\Hash;
  use ClicShopping\OM\HTTP;
  use ClicShopping\Sites\ClicShoppingAdmin\ActionRecorderAdmin;

  use ClicShopping\Apps\Configuration\TemplateEmail\Classes\ClicShoppingAdmin\TemplateEmailAdmin;
  use ClicShopping\Sites\ClicShoppingAdmin\EmailVerification;

  $login_request = true;

  require_once __DIR__ . '/Core/OM.php';

  $CLICSHOPPING_Db = Registry::get('Db');
  $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
  $CLICSHOPPING_Mail = Registry::get('Mail');
  $CLICSHOPPING_Hooks = Registry::get('Hooks');
  $CLICSHOPPING_Template = Registry::get('TemplateAdmin');

  $action = $_GET['action'] ?? '';

// prepare to logout an active administrator if the login page is accessed again
  if (isset($_SESSION['admin'])) {
    $action = 'logoff';
  }

  if (!\is_null($action)) {
    switch ($action) {
      case 'process':
        $CLICSHOPPING_Hooks->call('PreAction', 'Process');
        $username = '';
        $password = '';

        if (isset($_SESSION['redirect_origin'], $_SESSION['redirect_origin']['auth_user']) && !isset($_POST['username'])) {
          $username = HTML::sanitize($_SESSION['redirect_origin']['auth_user']);
          $password = HTML::sanitize($_SESSION['redirect_origin']['auth_pw']);
        } else {
          if (isset($_POST['username'], $_POST['password'])) {
            $username = HTML::sanitize($_POST['username']);
            $password = HTML::sanitize($_POST['password']);
          } else {
            CLICSHOPPING::redirect('login.php');
          }
        }

        if (!empty($username)) {
          Registry::set('ActionRecorderAdmin', new ActionRecorderAdmin('ar_admin_login', null, $username));
          $CLICSHOPPING_ActionRecorder = Registry::get('ActionRecorderAdmin');

          if ($CLICSHOPPING_ActionRecorder->canPerform()) {
            $sql_array = [
              'id',
              'user_name',
              'user_password',
              'name',
              'first_name',
              'access',
              'status'
            ];

            $Qadmin = $CLICSHOPPING_Db->get('administrators', $sql_array, ['user_name' => $username, 'status' => 1]);

            if ($Qadmin->fetch() !== false) {
              if (Hash::verify($password, $Qadmin->value('user_password'))) {
                $_SESSION['admin'] = [
                  'id' => $Qadmin->valueInt('id'),
                  'username' => $Qadmin->value('user_name'),
                  'access' => $Qadmin->value('access'),
                  'status' => $Qadmin->value('status')
                ];

                $CLICSHOPPING_ActionRecorder->_user_id = $_SESSION['admin']['id'];
                $CLICSHOPPING_ActionRecorder->record();

                //****************************
                // Check Double authtification
                //****************************
                if (EmailVerification::isEnabledForAdmin($username)) {
                  // Stocker les informations de session pour une utilisation ultérieure
                  $_SESSION['username'] = $username;
                  $_SESSION['password'] = $password;

                  CLICSHOPPING::redirect('login.php', 'action=emailVerify');
                } elseif (isset($_SESSION['redirect_origin'])) {
                  $page = $_SESSION['redirect_origin']['page'];

                  $get_string = http_build_query($_SESSION['redirect_origin']['get']);

                  unset($_SESSION['redirect_origin']);
                  unset($_SESSION['email_verified']);

                  $CLICSHOPPING_Hooks->call('Login', 'Process');

                  CLICSHOPPING::redirect($page, $get_string);
                } else {
                  CLICSHOPPING::redirect();
                }
              }
            }

            if (isset($_POST['username'])) {
              $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('error_invalid_administrator'), 'error');

              $CLICSHOPPING_Hooks->call('Login', 'ErrorProcess');
            }
          } else {
            $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('error_action_recorder', ['module_action_recorder_admin_login_minutes' => (\defined('MODULE_ACTION_RECORDER_ADMIN_LOGIN_MINUTES') ? (int)MODULE_ACTION_RECORDER_ADMIN_LOGIN_MINUTES : 5)]));
          }

          if (isset($_POST['username'])) {
            $CLICSHOPPING_ActionRecorder->record(false);
          }
        }

      break;

      case 'logoff':
        $CLICSHOPPING_Hooks->call('Account', 'LogoutBefore');

        unset($_SESSION['admin']);
        unset($_SESSION['email_verified']);

        if (isset($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW']) && !empty($_SERVER['PHP_AUTH_PW'])) {
          $_SESSION['auth_ignore'] = true;
        }

        $CLICSHOPPING_Hooks->call('Account', 'LogoutAfter');

        CLICSHOPPING::redirect();
      break;

      case 'create':
        $CLICSHOPPING_Hooks->call('PreAction', 'Create');

        $Qcheck = $CLICSHOPPING_Db->get('administrators', 'id', null, null, 1);

        if (!$Qcheck->check()) {
          $username = HTML::sanitize($_POST['username']);
          $password = HTML::sanitize($_POST['password']);
          $name = HTML::sanitize($_POST['name']);
          $first_name = HTML::sanitize($_POST['first_name']);

          if (!empty($username)) {
            $insert_array = [
              'user_name' => $username,
              'user_password' => Hash::encrypt($password),
              'name' => $name,
              'first_name' => $first_name,
              'access' => 1
            ];

            $CLICSHOPPING_Db->save('administrators', $insert_array);
          }
        }

        $CLICSHOPPING_Hooks->call('Login', 'Create');

        CLICSHOPPING::redirect('login.php');

      break;

      case 'send_password':
        $error = false;

        $CLICSHOPPING_Hooks->call('PreAction', 'SendPassword');

        if ($error === false) {
          $username = HTML::sanitize($_POST['username']);

            $Qcheck = $CLICSHOPPING_Db->prepare('select id
                                                 from :table_administrators
                                                 where user_name = :user_name
                                                 limit 1
                                                ');
            $Qcheck->bindValue(':user_name', $username);
            $Qcheck->execute();

            if ($Qcheck->rowCount() == 1 && Is::EmailAddress($username)) {
            $new_password = Hash::getRandomString((int)ENTRY_PASSWORD_MIN_LENGTH);
            $crypted_password = Hash::encrypt($new_password);

            $Qupdate = $CLICSHOPPING_Db->prepare('update :table_administrators
                                                   set user_password = :user_password
                                                   where user_name = :user_name
                                                   limit 1
                                                ');
            $Qupdate->bindValue(':user_password', $crypted_password);
            $Qupdate->bindValue(':user_name', $username);

            $Qupdate->execute();

            $body_subject = CLICSHOPPING::getDef('email_password_reminder_subject', ['store_name' => STORE_NAME]);

            $text_array = [
              'store_name' => STORE_NAME,
              'remote_address' => $_SERVER['REMOTE_ADDR'],
              'new_password' => $new_password
            ];

            $email_body = CLICSHOPPING::getDef('email_password_reminder_body', $text_array) . "\n";
            $email_body .= TemplateEmailAdmin::getTemplateEmailSignature() . "\n";
            $email_body .= TemplateEmailAdmin::getTemplateEmailTextFooter();

            $to_addr = $username;
            $from_name = STORE_OWNER;
            $from_addr = STORE_OWNER_EMAIL_ADDRESS;
            $to_name = NULL;
            $subject = $body_subject;

            $CLICSHOPPING_Mail->addHtml($email_body);
            $CLICSHOPPING_Mail->send($to_addr, $from_name, $from_addr, $to_name, $subject);

            $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('success_password_sent'), 'success');
          } else {
            $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('text_no_email_address_found'), 'error, again 1 time before to block your IP address');
          }

          $CLICSHOPPING_Hooks->call('Login', 'SendPassword');

          CLICSHOPPING::redirect('login.php');
        }
      break;

      //******************************
      //  Double authentification by email
      //******************************
      case 'emailVerify':
        $error = false;

        if (isset($_POST['username'], $_POST['password'])) {
          $_SESSION['username'] = HTML::sanitize($_POST['username']);
          $_SESSION['password'] = HTML::sanitize($_POST['password']);

          $username = $_SESSION['username'];
          $password = $_SESSION['password'];

          $sql_array = [
            'id',
            'user_name',
            'user_password',
            'access',
            'status'
          ];

          $Qcheck = $CLICSHOPPING_Db->get('administrators', $sql_array, ['user_name' => $username, 'status' => 1]);

          if (!empty($Qcheck->value('user_name'))) {
            if (Hash::verify($password, $Qcheck->value('user_password'))) {
              if (EmailVerification::sendVerificationCode($username)) {
                $_SESSION['email_verified'] = true;
              }
            } else {
              $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('error_invalid_administrator'), 'error');
              CLICSHOPPING::redirect('login.php');
            }
          } else {
            $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('error_invalid_administrator'), 'error');
            CLICSHOPPING::redirect('login.php');
          }
        } else {
          $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('error_invalid_administrator'), 'error');

          $CLICSHOPPING_Hooks->call('Login', 'ErrorProcess');
        }

        break;

        case 'email_code':
          $error = false;

          if (isset($_POST['email_code_sent'])) {
            $email_code = HTML::sanitize($_POST['email_code_sent']);
            $username = HTML::sanitize($_SESSION['username']);

            $check = EmailVerification::verifyCode($username, $email_code);

            if ($check === true) {
              $sql_array = [
                'id',
                'user_name',
                'user_password',
                'access',
                'status'
              ];

              $Qadmin = $CLICSHOPPING_Db->get('administrators', $sql_array, ['user_name' => $username, 'status' => 1]);

              if ($Qadmin->fetch() !== false) {
                $_SESSION['admin'] = [
                  'id' => $Qadmin->valueInt('id'),
                  'username' => $Qadmin->value('user_name'),
                  'access' => $Qadmin->value('access'),
                  'status' => $Qadmin->value('status'),
                ];

                if (isset($_SESSION['redirect_origin'])) {
                  $page = $_SESSION['redirect_origin']['page'];
                  $get_string = http_build_query($_SESSION['redirect_origin']['get']);

                  CLICSHOPPING::redirect($page, $get_string);
                } else {
                  CLICSHOPPING::redirect();
                }
              }
            } else {
              $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('text_email_code_invalid'), 'error');
              CLICSHOPPING::redirect('login.php?action=emailVerify');
            }
          } else {
            $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('text_email_code_required'), 'error');
            CLICSHOPPING::redirect('login.php?action=emailVerify');
          }
        break;

        case 'resend_code':
          if (isset($_SESSION['username'])) {
            $username = HTML::sanitize($_SESSION['username']);

            $CLICSHOPPING_Db = Registry::get('Db');
            $CLICSHOPPING_Mail = Registry::get('Mail');

            $admin_check = EmailVerification::checkuser($username);

            if (!empty($admin_check['user_name'])) {
              $code_length = defined('EMAIL_VERIFICATION_CODE_LENGTH') ? (int)EMAIL_VERIFICATION_CODE_LENGTH : 6;
              $code_length = max(4, min(8, $code_length)); // Limiter entre 4 et 8

              $verification_code = HTML::generateRandomNumber();

              $expiry_minutes = defined('EMAIL_VERIFICATION_CODE_EXPIRY') ? (int)EMAIL_VERIFICATION_CODE_EXPIRY : 15;
              $expiry_time = date('Y-m-d H:i:s', time() + ($expiry_minutes * 60));

              $update_array = [
                'email_verification_code' => $verification_code,
                'email_verification_expiry' => $expiry_time
              ];

              $CLICSHOPPING_Db->save('administrators', $update_array, ['user_name' => $username]);

              // Envoyer l'email avec le code
              $body_subject = CLICSHOPPING::getDef('email_verification_subject', ['store_name' => STORE_NAME]);

              $text_array = [
                'store_name' => STORE_NAME,
                'verification_code' => $verification_code,
                'expiry_minutes' => $expiry_minutes
              ];

              $email_body = CLICSHOPPING::getDef('email_verification_body', $text_array) . "\n";
              $email_body .= TemplateEmailAdmin::getTemplateEmailSignature() . "\n";
              $email_body .= TemplateEmailAdmin::getTemplateEmailTextFooter();

              $to_addr = $username;
              $from_name = STORE_OWNER;
              $from_addr = STORE_OWNER_EMAIL_ADDRESS;
              $to_name = null;
              $subject = $body_subject;

              $CLICSHOPPING_Mail->addHtml($email_body);
              $CLICSHOPPING_Mail->send($to_addr, $from_name, $from_addr, $to_name, $subject);

              $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('success_email_verification_code_sent'), 'success');
            } else {
              $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('error_invalid_administrator'), 'error');
              CLICSHOPPING::redirect('login.php');
            }
          } else {
            CLICSHOPPING::redirect('login.php');
          }
        break;
    }
  }

  $Qcheck = $CLICSHOPPING_Db->get('administrators', 'id', null, null, 1);

  if (!$Qcheck->check()) {
    $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('text_create_first_administrator'), 'warning');
  }

  require_once($CLICSHOPPING_Template->getTemplateHeaderFooterAdmin('header.php'));
  require_once('background.php');

  $ip = HTTP::getIpAddress();

  if (Is::IpAddress($ip) && (!empty($ip) || !\is_null($ip))) {
    $url = "https://ipinfo.io/{$ip}/geo";
    $options = [
      'http' => [
        'ignore_errors' => true, // Ignore HTTP errors and fetch the response
      ],
    ];

    $context = stream_context_create($options);

    $details = file_get_contents($url, false, $context);

    $http_response_header = $http_response_header ?? [];
    $responseCode = isset($http_response_header[0]) ? (int)explode(' ', $http_response_header[0])[1] : null;

    if ($details === false || ($responseCode !== null && $responseCode >= 400)) {
      if ($responseCode == 429) {
        // Handle the "Too Many Requests" error
        echo "Error: Too Many Requests. Please wait and try again later.";
      } else {
        // Handle other errors
        echo "Error: Something went wrong. Please try again later.";
      }
    } else {
      try {
        $details = json_decode($details, true, 512, JSON_THROW_ON_ERROR);
      } catch (\JsonException $e) {
        // Non-JSON response (e.g. HTML error page) — ignore silently
        $details = null;
      }

      if ($details !== null && isset($details['country'])) {
        $country = $details['country'];
        echo "<script>$('svg path[data-country-code=' + " . json_encode($country) . " + ']').attr('fill', '#197ac6').attr('fill-opacity', '0.15');</script>";
      }
    }
  }
?>
  <div class="loader-wrapper"></div>
<?php
  if ($Qcheck->check()) {
    if (EMAIL_VERIFICATION_ENABLED_ADMIN == 'True') {
      $form_action = 'emailVerify';
      $action = 'verificatiion_enable';
    } else {
      $form_action = 'process';
    }
    $button_text = CLICSHOPPING::getDef('button_login');
  } else {
    $form_action = 'create';
    $button_text = CLICSHOPPING::getDef('button_create_administrator');
  }


  if (!empty($_SESSION['email_verified']) && $_SESSION['email_verified'] === true) {
    ?>
    <?php echo HTML::form('email_verification', CLICSHOPPING::link('login.php', 'action=email_code')); ?>

    <div id="loginModal" tabindex="-1" role="document" aria-hidden="true" style="padding-top:10rem;">
      <div class="modal-dialog">
        <div class="modal-content" style="border: none; align-items: center;">
          <div class="modal-header">
            <h4><?php echo CLICSHOPPING::getDef('heading_title_email_verification'); ?></h4>
          </div>
          <div class="modal-body" style="width:40rem; padding-top:3rem;">
            <div class="col-md-12 center-block">
              <div class="input-group">
                <span class="input-group-addon" id="basic-addon1"></span>
                <?php echo HTML::inputField('email_code_sent', null, 'required aria-required="true" autocomplete="off" autofocus placeholder="' . CLICSHOPPING::getDef('entry_email_verification_code_placeholder') . '"', 'password'); ?>
                <?php echo HTML::button(CLICSHOPPING::getDef('button_verify'), null, null, 'primary'); ?>
              </div>
              <div class="mt-1"></div>
              <div class="col-md-12">
                <div class="row">
                  <span class="col-md-6">
                    <?php echo HTML::button(CLICSHOPPING::getDef('button_resend_code'), null, CLICSHOPPING::link('login.php', 'action=resend_code'), 'success'); ?>
                  </span>
                  <span class="col-md-6 text-end">
                    <?php echo HTML::button(CLICSHOPPING::getDef('button_back'), null, 'login.php?action=logoff', 'warning'); ?>
                  </span>
                </div>
              </div>
              <div class="mt-1"></div>
            </div>
            <div class="mt-4"></div>
            <div class="modal-footer">
              <div class="col-md-12">
                <div class="row">
                  <div class="alert alert-info" role="alert">
                    <?php echo CLICSHOPPING::getDef('text_email_verification_instruction'); ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="py-3"></div>
    </div>
    </form>
    <?php
  } elseif ($form_action == 'emailVerify') {
    echo HTML::form('login', CLICSHOPPING::link('login.php', 'action=' . $form_action), 'post', 'id="login"', ['tokenize' => true]);
    ?>
      <div id="loginModal" tabindex="-1" role="document" aria-hidden="true" style="padding-top:10rem;">
      <div class="modal-dialog">
        <div class="modal-content" style="background-color: transparent; border: none; align-items: center;">
          <div class="modal-header">
            <h1 style="color:#233C7A; text-align: center;"><?php echo CLICSHOPPING::getDef('heading_title'); ?></h1>
          </div>
          <div class="modal-body" style="width:20rem; padding-top:3rem;">
            <div class="col-md-12 center-block">
              <div class="input-group">
                <span class="input-group-addon" id="basic-addon1"></span>
                <?php echo HTML::inputField('username', '', 'placeholder="' . CLICSHOPPING::getDef('text_username') . '" required aria-required="true" autocomplete="off" aria-describedby="basic-addon1"'); ?>
              </div>
              <div class="mt-1"></div>
              <div class="input-group">
                <span class="input-group-addon" id="basic-addon1"></span>
                <?php echo HTML::passwordField('password', '', 'placeholder="' . CLICSHOPPING::getDef('text_password') . '" required aria-required="true" autocomplete="off" aria-describedby="basic-addon1"'); ?>
              </div>
              <div class="mt-1"></div>
              <div class="text-end">
                <label for="buttonText"><?php echo HTML::button($button_text, null, null, 'primary'); ?></label>
              </div>
              <div class="mt-1"></div>
            </div>

              <div class="modal-footer">
                <div class="col-md-12">
                  <div class="row">
                    <div class="col-md-6">
                      <label for="buttononlineCatalog"><a href="../index.php"> <button class="btn text-start" data-bs-dismiss="modal" aria-hidden="true"><?php echo CLICSHOPPING::getDef('header_title_online_catalog'); ?></button></a></label>
                    </div>
                    <div class="col-md-6">
                      <label for="buttonNewPassword"><a href="<?php echo CLICSHOPPING::link('login.php', 'action=password'); ?>"><button class="btn text-end" data-bs-dismiss="modal" aria-hidden="true"><?php echo CLICSHOPPING::getDef('text_new_text_password'); ?></button></a></label>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="py-3"></div>
      </div>
      </form>
    <?php
  } elseif ($action != 'password') {
   ?>
    <div id="loginModal" tabindex="-1" role="document" aria-hidden="true" style="padding-top:10rem;">
      <div class="modal-dialog">
        <div class="modal-content" style="background-color: transparent; border: none; align-items: center;">
          <div class="modal-header">
            <h1 style="color:#233C7A; text-align: center; display: flex; align-items: center; justify-content: center; gap: 10px;">
              <img src="../images/logo_clicshopping.png" alt="ClicShopping" style="height: 40px; vertical-align: middle;">
              <?php echo CLICSHOPPING::getDef('heading_title'); ?>

          </div>
          <?php echo HTML::form('login', CLICSHOPPING::link('login.php', 'action=' . $form_action)); ?>
          <div class="modal-body" style="width:20rem; padding-top:3rem;">
            <div class="col-md-12 center-block">
              <?php
                if ($form_action == 'create') {
              ?>
              <div class="input-group">
                <span class="input-group-addon" id="basic-addon1"></span>
                <?php echo HTML::inputField('first_name', '', 'placeholder="' . CLICSHOPPING::getDef('text_firstname') . '" required aria-required="true" autocomplete="off" aria-describedby="basic-addon1"'); ?>
              </div>
              <div class="mt-1"></div>
              <div class="input-group">
                <span class="input-group-addon" id="basic-addon1"></span>
                <?php echo HTML::inputField('name', '', 'placeholder="' . CLICSHOPPING::getDef('text_name') . '" required aria-required="true" autocomplete="off" aria-describedby="basic-addon1"'); ?>
              </div>
              <div class="mt-1"></div>
              <?php
                }
              ?>
              <div class="input-group">
                <span class="input-group-addon" id="basic-addon1"></span>
                <?php echo HTML::inputField('username', '', 'placeholder="' . CLICSHOPPING::getDef('text_username') . '" required aria-required="true" autocomplete="off" aria-describedby="basic-addon1"'); ?>
              </div>
              <div class="mt-1"></div>
              <div class="input-group">
                <span class="input-group-addon" id="basic-addon1"></span>
                <?php echo HTML::passwordField('password', '', 'placeholder="' . CLICSHOPPING::getDef('text_password') . '" required aria-required="true" autocomplete="off" aria-describedby="basic-addon1"'); ?>
              </div>
              <div class="mt-1"></div>
              <div class="text-end">
                <label for="buttonText"><?php echo HTML::button($button_text, null, null, 'primary'); ?></label>
              </div>
              <div class="mt-1"></div>
            </div>
          </div>
          </form>
          <div class="modal-footer">
            <div class="col-md-12">
              <div class="row">
                  <div class="col-md-6">
                  <label for="buttononlineCatalog"><a href="../index.php"><button class="btn text-start" data-bs-dismiss="modal" aria-hidden="true"><?php echo CLICSHOPPING::getDef('header_title_online_catalog'); ?></button></a></label>
                </div>
                <div class="col-md-6 text-end">
                  <label for="buttonNewPassword"><a href="<?php echo CLICSHOPPING::link('login.php', 'action=password'); ?>"> <button class="btn text-end" data-bs-dismiss="modal" aria-hidden="true"><?php echo CLICSHOPPING::getDef('text_new_text_password'); ?></button></a></label>
                </div>
              </div>
            </div>
          </div>
          <div class="py-3"></div>
        </div>
      </div>
    </div>
  <?php
  } else {
    ?>
    <?php echo HTML::form('send_password', CLICSHOPPING::link('login.php', 'action=send_password')); ?>
    <div id="loginModal" tabindex="-1" role="document" aria-hidden="true" style="padding-left:10px; padding-right:10px">
      <div class="modal-dialog">
        <div class="modal-content" style="background-color: transparent; border: none; align-items: center;">
          <div class="modal-header">
            <h2 style="color:#233C7A;"><?php echo CLICSHOPPING::getDef('heading_title_sent_password'); ?></h2>
          </div>
          <div class="modal-body" style="width:30rem; padding-top:3rem;">
            <div class="col-md-11 text-center">
              <div class="text-danger"
                   style="font-size:12px; padding-bottom:10px;"><?php echo CLICSHOPPING::getDef('text_sent_password'); ?></div>
              <div class="input-group">
                <?php echo HTML::inputField('username', '', 'size="150" placeholder="' . CLICSHOPPING::getDef('text_email_lost_password') . '" required aria-required="true" autocomplete="off" aria-describedby="basic-addon1"'); ?>
              </div>
              <div class="mt-1"></div>
            </div>
          </div>
          <div class="mt-1"></div>
          <div class="row col-md-12">
            <div class="col-md-6">
              <label for="buttonHeaderAdministration"><a href="<?php echo CLICSHOPPING::link('login.php'); ?>"><button class="btn btn-secondary text-start" type="button"><?php echo CLICSHOPPING::getDef('header_title_administration'); ?></button></a></label>
            </div>
            <div class="col-md-6 text-end">
              <label for="buttonSubmit"><?php echo HTML::button(CLICSHOPPING::getDef('button_submit'), null, null, 'primary'); ?></label>
            </div>
          </div>
          <div class="py-3"></div>
        </div>
      </div>
    </div>
    </form>
   <?php
  }
  ?>
  <div class="clearfix"></div>
<?php
  require_once($CLICSHOPPING_Template->getTemplateHeaderFooterAdmin('footer.php'));
  require_once($CLICSHOPPING_Template->getTemplateHeaderFooterAdmin('application_bottom.php'));
