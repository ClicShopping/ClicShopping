<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\HTML;
?>
<form name="install" id="installForm" action="install.php?step=2" method="post">
  <div id="content">
    <div class="page-header">
      <div class="container">
        <div></div>
        <h1><?php echo TEXT_DATABASE_SERVER; ?></h1>
      </div>
    </div>
    <div class="container">
      <div class="card">
        <div class="card-header"><i class="bi bi-sliders"></i><?php echo TEXT_STEP_INTRO_1; ?></div>
        <div class="card-body">
          <fieldset>
            <div class="row">
              <div class="col-md-4  order-md-2">
                <div class="card">
                  <div class="card-header">
                    <div class="card-title">
                      <?php echo TEXT_STEP_INTRO_1; ?>
                    </div>
                  </div>
                  <div class="card-body">
                    <p><?php echo TEXT_STEP_INTRO_2; ?></p>
                    <div class="progress">
                      <div class="progress-bar progress-bar-primary progress-bar-striped" role="progressbar"
                           aria-valuenow="25" aria-valuemin="0" aria-valuemax="100" style="width: 25%">25%
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-md-8 order-md-1">
                <div class="form-row">
                  <div class="form-group col required">
                    <label for="dbServer"><?php echo TEXT_DATABASE_SERVER; ?></label>
                    <?php echo HTML::inputField('DB_SERVER', null, 'required aria-required="true" id="dbServer" placeholder="localhost"'); ?>
                    <span class="help-block"><?php echo TEXT_DATABASE_SERVER_HELP; ?></span>
                  </div>
                </div>

                <div class="form-row">
                  <div class="form-group col required">
                    <label for="username"><?php echo TEXT_USERNAME; ?></label>
                    <?php echo HTML::inputField('DB_SERVER_USERNAME', null, 'required aria-required="true" id="username"'); ?>
                    <span class="help-block"><?php echo TEXT_USERNAME_HELP; ?></span>
                  </div>
                  <div class="form-group col">
                    <label for="password"><?php echo TEXT_PASSWORD; ?></label>
                    <?php echo HTML::passwordField('DB_SERVER_PASSWORD', null, 'required aria-required="true" id="password"'); ?>
                    <span class="help-block"><?php echo TEXT_PASSWORD_HELP; ?></span>
                  </div>
                </div>

                <div class="form-row">
                  <div class="form-group col-6 required">
                    <label for="dbName"><?php echo TEXT_DATABASE_NAME; ?></label>
                    <?php echo HTML::inputField('DB_DATABASE', null, 'required aria-required="true" id="dbName"'); ?>
                    <span class="help-block"><?php echo TEXT_DATABASE_HELP; ?></span>
                  </div>
                </div>
              </div>
            </div>
            <?php echo HTML::hiddenField('DB_TABLE_PREFIX', 'clic_', 'id="dbTablePrefix"'); ?>
          </fieldset>

          <br/><br/>
          <div class="row mt-3">
            <div for="demo"><h3> <?php echo TEXT_INFO_DATABASE; ?></h3></div>
            <div class="container">
              <div class="card bg-warning">
                <div class="card-body text-center">
                  <i class="bi bi-exclamation-triangle-fill text-danger"></i> <?php echo TEXT_ALERT_DATABASE; ?><br><br>
                  <div class="row justify-content-center">
	            <?php
	            $demo = [
	              ['id' => 'normal', 'text' => 'Normal'],
	            ];

                    echo '<div class="col-md-6 text-center">' . HTML::selectMenu('demo', $demo, 'demo', 'id="demo" class=\"form-control\"') . '</div>';
	            ?>
                  </div>
                </div>
              </div>
            </div>
            <br/>
            <div class="py-2"></div>
            <div class="col text-end">
              <?php
              echo HTML::button(TEXT_CONTINUE, null, null, 'success', ['params' => 'id="buttonDoImport" data-bs-toggle="modal" data-bs-target="#installModal"']) . '&nbsp;';
              ?>

              <?php
              echo HTML::button(TEXT_SKIP_DATABASE, null, null, 'warning', ['params' => 'id="buttonSkipImport"']);
              ?>
            </div>
          </div>
        </div>
      </div>
      <br/>
      <br/>
    </div>
  </div>
</form>

<!-- Modal -->
<div class="modal fade" id="installModal" tabindex="-1" aria-labelledby="installModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="installModalLabel"><?php echo TEXT_WAIT; ?></h4>
      </div>
      <div id="mBox"></div>
      <div class="modal-body">
      </div>
    </div>
  </div>
</div>
<script>
  $(function () {
    var formSubmited = false;
    var formSuccess = false;
    var dbNameToCreate;
    var doImport = true;

    function prepareDB() {
      if (formSubmited == true) {
        return false;
      }

      formSubmited = true;

      $('#installModal .modal-body').html('<p><i class="bi bi-arrow-repeat"></i> Testing database connection..</p>');

      $('#installModal').modal({
        keyboard: false,
        show: true
      });

      var dbParams = {
        server: $('#dbServer').val(),
        username: $('#username').val(),
        password: $('#password').val(),
        name: $('#dbName').val(),
        prefix: $('#dbTablePrefix').val(),
        demo: $('#demo').val()
      };

      var dbCheckUrl = 'rpc.php?action=dbCheck';

      if (dbParams.name == dbNameToCreate) {
        dbCheckUrl = dbCheckUrl + '&createDb=true';
      }

      $.post(dbCheckUrl, dbParams, function (response) {
        if (('status' in response) && ('message' in response)) {
          if ((response.status == '1') && (response.message == 'success')) {
            if (doImport === true) {
              $('#installModal .modal-body').html('<p><i class="bi bi-arrow-repeat"></i> The database structure is now being imported. Please be patient during this procedure.</p>');

              $.post('rpc.php?action=dbImport', dbParams, function (response2) {
                if (('status' in response2) && ('message' in response2)) {
                  if ((response2.status == '1') && (response2.message == 'success')) {
                    $('#installModal .modal-body').html('<div class="alert alert-success" role="alert"><i class="bi bi-hand-thumbs-up"></i> Database imported successfully. Proceeding to next step..</div>');

                    formSuccess = true;

                    setTimeout(function () {
                      $('#installForm').submit();
                    }, 2000);
                  } else {
                    $('#installModal').modal('hide');

                    $('#mBox').html('<div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-circle-fill text-danger"></i> There was a problem importing the database. The following error had occured:<br><br><strong>%s</strong><br><br>Please verify the connection parameters and try again.</div>'.replace('%s', response2.message));

                    formSubmited = false;
                  }
                } else {
                  $('#installModal').modal('hide');

                  $('#mBox').html('<div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-circle-fill text-danger"></i> There was a problem importing the database. Please verify the connection parameters and try again.</div>');

                  formSubmited = false;
                }
              }, 'json').fail(function () {
                $('#installModal').modal('hide');

                $('#mBox').html('<div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-circle-fill text-danger"></i> There was a problem importing the database. Please verify the connection parameters and try again.</div>');

                formSubmited = false;
              });
            } else {
              $('#installModal .modal-body').html('<div class="alert alert-success" role="alert"><i class="bi bi-hand-thumbs-up"></i> Database connection made successfully. Proceeding to next step..</div>');

              formSuccess = true;

              setTimeout(function () {
                $('#installForm').submit();
              }, 2000);
            }
          } else {
            $('#installModal').modal('hide');

            if ((response.status == '1049') && (dbParams.name != dbNameToCreate)) {
              dbNameToCreate = dbParams.name;

              var result_error = 'The database name of \'' + dbParams.name.replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;') + '\' does not exist. If you submit the form again with the same database name, an attempt will be made to create it.';

              $('#mBox').html('<div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-circle-fill text-danger"></i> <strong>%s</strong></div>'.replace('%s', result_error));
            } else {
              $('#mBox').html('<div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-circle-fill text-danger"></i> There was a problem connecting to the database server. The following error had occured:<br><br><strong>%s</strong><br><br>Please verify the connection parameters and try again.</div>'.replace('%s', response.message));
            }

            formSubmited = false;
          }
        } else {
          $('#installModal').modal('hide');

          $('#mBox').html('<div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-circle-fill text-danger"></i> There was a problem connecting to the database server. Please verify the connection parameters and try again.</div>');

          formSubmited = false;
        }
      }, 'json').fail(function () {
        $('#installModal').modal('hide');

        $('#mBox').html('<div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-circle-fill text-danger"></i> There was a problem connecting to the database server. Please verify the connection parameters and try again.</div>');

        formSubmited = false;
      });
    }

    // disable ENTER and force click on continue buttons
    $('#installForm').on('keyup keypress', function (e) {
      var keyCode = e.keyCode || e.which;

      if (keyCode === 13) {
        e.preventDefault();

        return false;
      }
    });

    $('#installForm').submit(function (e) {
      if (formSuccess == false) {
        e.preventDefault();

        prepareDB();
      } else {
        if (doImport !== true) {
          $('#installForm').append('<input type="hidden" name="DB_SKIP_IMPORT" value="true">');
        }
      }
    });

    $('#buttonDoImport').on('click', function (e) {
      doImport = true;
    });

    $('#buttonSkipImport').on('click', function (e) {
      doImport = false;
    });
  });
</script>

