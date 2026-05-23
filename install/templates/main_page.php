<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

$languages_array = [['id' => 'english', 'text' => 'English'],
  ['id' => 'french', 'text' => 'Francais'],
];

require_once('Core/languages/' . $language . '.php');

$template = 'main_page';
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <meta name="generator" content="ClicShoppingAI, GenAI E-Commerce B2B/B2C Open Source Solutions /">
  <meta name="robots" content="noindex,nofollow">
  <title>ClicShoppingAI, GenAI E-Commerce B2B/B2C Open Source Solutions</title>
  <link rel="shortcut icon" href="../images/favicon.png" type="image/x-icon"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="templates/main_page/stylesheet.css">

  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
</head>

<body>
<div class="container-fluid">
  <div class="row" style="margin-top: 10px; margin-bottom: 20px;" id="storeLogo">
    <div class="col-sm-6">
      <a href="index.php"><img src="../images/logo_clicshopping_1.png" border="0" width="284" height="105"
                               title="ClicShoppingAI" alt="ClicShopping AI" style="margin: 10px 10px 0px 10px;"/></a>
    </div>

    <div id="headerShortcuts" class="col-sm-6 text-end">
      <ul class="list-unstyled list-inline">
        <li><a href="https://www.clicshopping.org" target="_blank">ClicShopping Website</a></li>
        <li><a href="https://www.clicshopping.org" target="_blank">Support</a></li>
        <li><a href="https://clicshopping.org" target="_blank">Documentation</a></li>
      </ul>
    </div>
  </div>

  <?php require_once('templates/pages/' . $page_contents); ?>

  <div class="row">
    <div class="col-md-12">
      <footer>
        <div style="padding-top:1rem;">
          <div class="card">
            <div class="card-footer">
              <div class="text-center">
                  <small>Copyright &copy; 2008-<?php echo date('Y'); ?> <a href="https://www.clicshopping.org" target="_blank" rel="noreferrer" alt="ClicShopping AI">ClicShopping AI(TM)</a> - Brand deposed at INPI</small>
                </div>
              </div>
            </div>
          </div>
        </footer>
      </div>
    </div>
  </div>
  <!-- JavaScript Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
