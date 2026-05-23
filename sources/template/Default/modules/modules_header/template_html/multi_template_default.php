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
<div class="multiTemplateDefault-modern-header-container">
  <div class="col-md-<?php echo $content_width; ?>">
    <div class="multiTemplateDefault-header-top-row d-none d-md-flex">
      <div class="multiTemplateDefault-logo">
        <?php echo $logo_header; ?>
      </div>

      <div class="multiTemplateDefault-search">
        <?php echo $form_advanced_result; ?>
        <div class="input-group multiTemplateDefault-search-criteria">
          <label for="inputKeywordsSearchLogin" class="visually-hidden"></label>
          <?php echo HTML::inputField('keywords', null, 'required aria-required="true" id="inputKeywordsSearchLogin" placeholder="' . CLICSHOPPING::getDef('modules_header_multi_template_header_search') . '"', 'search'); ?>
          <span id="buttonKeywordsSearch">
            <label for="buttonKeywordsSearch"><?php echo HTML::button(null, 'bi bi-search', null, 'primary', null, 'sm'); ?></label>
          </span>
        </div>
        <?php echo HTML::hiddenField('search_in_description', '1'); ?>
        <?php echo $endform; ?>
      </div>

      <div class="multiTemplateDefault-header-top-right">
        <span class="multiTemplateDefault-title">
          <?php
          if (!$CLICSHOPPING_Customer->isLoggedOn()) {
            ?>
            <a data-bs-toggle="modal" data-bs-target="#loginModal"><?php echo CLICSHOPPING::getDef('modules_header_multi_template_account_login'); ?></a>
            <?php
          } else {
            ?>
            <span>
              <?php echo HTML::link(CLICSHOPPING::link(null, 'Account&LogOff'), '<i class="bi bi-box-arrow-right me-1 multiTemplateDefault-icon"></i><span class="d-none d-md-inline-block">' . CLICSHOPPING::getDef('modules_header_multi_template_account_logoff') . '</span>'); ?>
              <?php
              if ($CLICSHOPPING_Customer->getCustomerGuestAccount($CLICSHOPPING_Customer->getID()) == 0) {
                echo HTML::link(CLICSHOPPING::link(null, 'Account&Main'), '<i class="bi bi-person-circle me-1 multiTemplateDefault-icon"></i><span class="d-none d-md-inline-block">' . CLICSHOPPING::getDef('modules_header_multi_template_my_account') . '</span>');
              }
              ?>
            </span>
            <?php
          }
          ?>
        </span>

        <span class="multiTemplateDefault-language">
          <ul>
            <li class="multiTemplateDefault-language-item"><?php echo $languages_string; ?></li>
          </ul>
        </span>

        <span class="multiTemplateDefault-currency">
          <ul>
            <li class="multiTemplateDefault-currency-item"><?php echo $currency_header; ?></li>
          </ul>
        </span>

        <div class="multiTemplateDefault-cart-link">
          <?php
          if ($CLICSHOPPING_ShoppingCart->getCountContents() > 0) {
            echo '<ul><li class="dropdown multiTemplateDefault-shopping-cart">';
            echo '<a class="dropdown-toggle multiTemplateDefault-shopping-cart-toggle" data-bs-toggle="dropdown" href="#">';
            echo '<i class="bi bi-cart-fill multiTemplateDefault-icon"></i>&nbsp;&nbsp;' . $shopping_cart . '</a>';
            echo '<ul class="dropdown-menu">';

            echo '<table class="table multiTemplateDefault-cart-table">';
            echo '<thead>';
            echo '<tr>';
            echo '<th class="multiTemplateDefault-cart-qty cart-qty">' . CLICSHOPPING::getDef('table_heading_quantity') . '</th>';
            echo '<th class="multiTemplateDefault-cart-product cart-product">' . CLICSHOPPING::getDef('table_heading_products') . '</th>';
            echo '<th class="multiTemplateDefault-cart-total cart-total text-end"></th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            $products = $CLICSHOPPING_ShoppingCart->get_products();
            foreach ($products as $v) {
              echo '<tr>';
              echo '<td class="multiTemplateDefault-cart-qty cart-qty">' . HTML::outputProtected($v['quantity']) . '</td>';
              echo '<td class="multiTemplateDefault-cart-product cart-product">' . HTML::outputProtected($v['name']) . '</td>';
              echo '<td class="multiTemplateDefault-cart-total cart-total text-end">' . $CLICSHOPPING_Currencies->displayPrice($v['final_price'], $CLICSHOPPING_Tax->getTaxRate($v['tax_class_id']), $v['quantity']) . '</td>';
              echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';

            echo '<div class="h-divider multiTemplateDefault-divider"></div>';
            echo '<div class="d-flex justify-content-between align-items-center p-2">';
            echo '<span class="fw-bold"></span>';
            echo '<span class="fw-bold fs-5">' . $CLICSHOPPING_Currencies->format($CLICSHOPPING_ShoppingCart->show_total()) . '</span>';
            echo '</div>';

            echo '<div class="d-grid gap-2">';
            echo '<a href="' . CLICSHOPPING::link(null, 'Cart') . '" class="btn btn-primary multiTemplateDefault-shopping-small-cart"><i class="bi bi-cart-fill me-2"></i>' . CLICSHOPPING::getDef('modules_header_multi_template_shopping_cart_view_cart') . '</a>';
            echo '<a href="' . CLICSHOPPING::link(null, 'Checkout&Shipping') . '" class="btn btn-outline-primary multiTemplateDefault-checkout"><i class="bi bi-arrow-right-square-fill me-2"></i>' . CLICSHOPPING::getDef('modules_header_multi_template_shopping_cart_checkout') . '</a>';
            echo '</div>';

            echo '</ul></li></ul>';
          } else {
            echo '<ul><li class="multiTemplateDefault-shopping-cart-no-content"><i class="bi bi-cart-fill multiTemplateDefault-icon"></i>&nbsp;&nbsp;' . CLICSHOPPING::getDef('modules_header_multi_template_shopping_cart_no_content') . '</li></ul>';
          }
          ?>
        </div>
      </div>
    </div>


    <div class="multiTemplateDefault-header-mobile-top-row d-md-none">
      <div class="multiTemplateDefault-logo">
        <?php echo $logo_header; ?>
      </div>

      <div class="d-flex align-items-center">
        <?php
        if (!$CLICSHOPPING_Customer->isLoggedOn()) {
          ?>
          <a data-bs-toggle="modal" class="multiTemplateDefault-mobile-login-icon-link" data-bs-target="#loginModal">
            <i class="bi bi-person-fill multiTemplateDefault-icon"></i>
          </a>
          <?php
        } else {
          ?>
          <a class="multiTemplateDefault-mobile-login-icon-link" href="<?php echo CLICSHOPPING::link(null, 'Account&Main'); ?>">
            <i class="bi bi-person-circle multiTemplateDefault-icon"></i>
          </a>
          <a class="multiTemplateDefault-mobile-login-icon-link" href="<?php echo CLICSHOPPING::link(null, 'Account&LogOff'); ?>">
            <i class="bi bi-box-arrow-right multiTemplateDefault-icon"></i>
          </a>
          <?php
        }
        ?>
        <div class="multiTemplateDefault-cart-link">
          <?php
          if ($CLICSHOPPING_ShoppingCart->getCountContents() > 0) {
            echo '<ul><li class="dropdown multiTemplateDefault-shopping-cart">';
            echo '<a class="dropdown-toggle multiTemplateDefault-shopping-cart-toggle" data-bs-toggle="modal" data-bs-target="#headerModalShoppingCart">';
            echo '<i class="bi bi-cart-fill multiTemplateDefault-icon"></i>';
            if ($CLICSHOPPING_ShoppingCart->getCountContents() > 0) {
              echo '&nbsp;&nbsp;<span class="multiTemplateDefault-cart-count d-md-none">' . $CLICSHOPPING_ShoppingCart->getCountContents() . '</span>';
            }
            echo '</a>';
            echo '</li></ul>';
          } else {
            echo '<ul><li class="multiTemplateDefault-shopping-cart-no-content"><i class="bi bi-cart-fill multiTemplateDefault-icon"></i></li></ul>';
          }
          ?>
        </div>
      </div>

      <button class="navbar-toggler multiTemplateDefault-mobile-menu-toggle" type="button" data-bs-toggle="collapse"
              data-bs-target="#navCollapse" aria-controls="navCollapse" aria-expanded="false"
              aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
    </div>


    <div class="collapse navbar-collapse nav-collapse" id="navCollapse">
      <div class="multiTemplateDefault-title d-md-none">
        <?php
        if (!$CLICSHOPPING_Customer->isLoggedOn()) {
          ?>
          <?php echo HTML::link(CLICSHOPPING::link(null, 'Account&LogIn'), CLICSHOPPING::getDef('modules_header_multi_template_create_account')); ?>
          <?php
        } else {
          ?>
          <?php echo HTML::link(CLICSHOPPING::link(null, 'Account&LogOff'), CLICSHOPPING::getDef('modules_header_multi_template_account_logoff')); ?>
          <?php
          if ($CLICSHOPPING_Customer->getCustomerGuestAccount($CLICSHOPPING_Customer->getID()) == 0) {
            echo HTML::link(CLICSHOPPING::link(null, 'Account&Main'), CLICSHOPPING::getDef('modules_header_multi_template_my_account'));
          }
        }
        ?>
        <?php echo HTML::link(CLICSHOPPING::link(null, 'Info&Contact'), CLICSHOPPING::getDef('modules_header_multi_template_title_contact_us')); ?>
        <div class="multiTemplateDefault-language mt-3">
          <ul>
            <li class="multiTemplateDefault-language-item"><?php echo $languages_string; ?></li>
            <li class="multiTemplateDefault-currency-item"><?php echo $currency_header; ?></li>
          </ul>
        </div>
      </div>
    </div>
  </div>
  <div class=" row col-md-12 float-end multiTemplateDefault-shopping-cart-image"><?php echo $banner_header; ?></div>
</div>

<div class="modal fade" id="loginModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title"
            id="myModalLabel"><?php echo CLICSHOPPING::getDef('modules_header_multi_template_account_login') ?></h4>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                aria-label="Fermer"></button>
      </div>
      <div class="modal-body text-center">
        <?php echo $form; ?>
        <div class="mt-1"></div>
        <div class="row">
          <div class="col-md-12">
            <label for="inputAddressEmailLogin" class="visually-hidden"></label>
            <span class="col-md-3 float-start text-start multiTemplateDefault-login-text"
                  id="inputAddressEmailLogin"><?php echo CLICSHOPPING::getDef('modules_header_multi_template_header_email_address'); ?></span>
            <span
              class="col-md-9 float-end"><?php echo HTML::inputField('email_address', null, 'id="inputAddressEmail" autocomplete="username" aria-describedby="' . CLICSHOPPING::getDef('modules_header_multi_template_header_email_address') . '" placeholder="' . CLICSHOPPING::getDef('modules_header_multi_template_header_email_address') . '"', 'email'); ?></span>
          </div>
        </div>
        <div class="row">
          <div class="col-md-12">
            <label for="inputAddressPasswordLogin" class="visually-hidden"></label>
            <span class="col-md-3 float-start text-start multiTemplateDefault-password-text"
                  id="inputAddressPasswordLogin"><?php echo CLICSHOPPING::getDef('modules_header_multi_template_account_password'); ?></span>
            <span
              class="col-md-9 float-end"><?php echo HTML::inputField('password', null, 'id="current-password" autocomplete="current-password" aria-describedby="' . CLICSHOPPING::getDef('modules_header_multi_template_account_password') . '" placeholder="' . CLICSHOPPING::getDef('modules_header_multi_template_account_password') . '"', 'password'); ?></span>
          </div>
        </div>
        <div class="mt-1"></div>
        <div class="row">
          <div class="col-md-6 text-start">
            <span class="multiTemplateDefault-password">
              <?php echo HTML::link(CLICSHOPPING::link(null, 'Account&PasswordForgotten'), CLICSHOPPING::getDef('modules_header_multi_template_password_forgotten')); ?>
            </span>
          </div>
          <div class="col-md-6 text-end">
            <label for="<?php echo CLICSHOPPING::getDef('modules_header_multi_template_account_login'); ?>">
              <?php echo $login; ?>
            </label>
          </div>
        </div>
        <?php echo $endform; ?>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="headerModalShoppingCart" tabindex="-1" role="dialog" aria-labelledby="myModalLabelShoppingCart" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="myModalLabelShoppingCart"><?php echo CLICSHOPPING::getDef('modules_header_multi_template_shopping_cart_view_cart'); ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <div class="modal-body">
        <?php
        echo '<table class="table multiTemplateDefault-cart-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th class="multiTemplateDefault-cart-qty cart-qty">' . CLICSHOPPING::getDef('table_heading_quantity') . '</th>';
        echo '<th class="multiTemplateDefault-cart-product cart-product">' . CLICSHOPPING::getDef('table_heading_products') . '</th>';
        echo '<th class="multiTemplateDefault-cart-total cart-total text-end"></th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        $products = $CLICSHOPPING_ShoppingCart->get_products();
        foreach ($products as $v) {
          echo '<tr>';
          echo '<td class="multiTemplateDefault-cart-qty cart-qty">' . HTML::outputProtected($v['quantity']) . '</td>';
          echo '<td class="multiTemplateDefault-cart-product cart-product">' . HTML::outputProtected($v['name']) . '</td>';
          echo '<td class="multiTemplateDefault-cart-total cart-total text-end">' . $CLICSHOPPING_Currencies->displayPrice($v['final_price'], $CLICSHOPPING_Tax->getTaxRate($v['tax_class_id']), $v['quantity']) . '</td>';
          echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';

        echo '<div class="h-divider multiTemplateDefault-divider"></div>';
        echo '<div class="d-flex justify-content-between align-items-center p-2">';
        echo '<span class="fw-bold"></span>';
        echo '<span class="fw-bold fs-5">' . $CLICSHOPPING_Currencies->format($CLICSHOPPING_ShoppingCart->show_total()) . '</span>';
        echo '</div>';

        echo '<div class="d-grid gap-2 mt-3">';
        echo '<a href="' . CLICSHOPPING::link(null, 'Cart') . '" class="btn btn-primary multiTemplateDefault-shopping-small-cart"><i class="bi bi-cart-fill me-2"></i>' . CLICSHOPPING::getDef('modules_header_multi_template_shopping_cart_view_cart') . '</a>';
        echo '<a href="' . CLICSHOPPING::link(null, 'Checkout&Shipping') . '" class="btn btn-outline-primary multiTemplateDefault-checkout"><i class="bi bi-arrow-right-square-fill me-2"></i>' . CLICSHOPPING::getDef('modules_header_multi_template_shopping_cart_checkout') . '</a>';
        echo '</div>';
        ?>
      </div>
    </div>
  </div>
</div>