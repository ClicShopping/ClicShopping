<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\HTML;
use ClicShopping\OM\ObjectInfo;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Configuration\TemplateEmail\Classes\ClicShoppingAdmin\TemplateEmailAdmin;

$CLICSHOPPING_TemplateEmail = Registry::get('TemplateEmail');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');

$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_Hooks = Registry::get('Hooks');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Wysiwyg = Registry::get('Wysiwyg');

$QtemplateEmailDescription = $CLICSHOPPING_TemplateEmail->db->prepare('select ted.language_id,
                                                                                ted.template_email_name,
                                                                                ted.template_email_short_description,
                                                                                ted.template_email_description,
                                                                                te.template_email_variable,
                                                                                te.template_email_id,
                                                                                te.customers_group_id
                                                                         from :table_template_email te,
                                                                              :table_template_email_description ted
                                                                         where te.template_email_id = :template_email_id
                                                                         and te.template_email_id = ted.template_email_id
                                                                        ');
$QtemplateEmailDescription->bindInt(':template_email_id', (int)$_GET['tID']);
$QtemplateEmailDescription->execute();

$template_email_description = $QtemplateEmailDescription->fetch();

$tInfo = new ObjectInfo($QtemplateEmailDescription->toArray());

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
?>
<!-- body //-->
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/mail.gif', $CLICSHOPPING_TemplateEmail->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-2 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_TemplateEmail->getDef('heading_title'); ?></span>
          <span class="col-md-9 text-end">
<?php
echo HTML::form('template_emails', $CLICSHOPPING_TemplateEmail->link('TemplateEmail&Update&ID=' . $_GET['tID']));
echo HTML::hiddenField('template_email', $_GET['tID']);
echo HTML::button($CLICSHOPPING_TemplateEmail->getDef('button_cancel'), null, $CLICSHOPPING_TemplateEmail->link('TemplateEmail&page=' . $page . '&tID=' . $_GET['tID']), 'warning') . '&nbsp;';
echo HTML::button($CLICSHOPPING_TemplateEmail->getDef('button_update'), null, null, 'success');

echo $CLICSHOPPING_Wysiwyg::getWysiwyg();
?>
            </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>

  <div>
    <ul class="nav nav-tabs flex-column flex-sm-row" role="tablist" id="myTab">
      <li
        class="nav-item"><?php echo '<a href="#tab1" role="tab" data-bs-toggle="tab" class="nav-link active">' . $CLICSHOPPING_TemplateEmail->getDef('tab_general') . '</a>'; ?></li>
      <li
        class="nav-item"><?php echo '<a href="#tab2" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_TemplateEmail->getDef('tab_description'); ?></a></li>
    </ul>
    <div class="tabsClicShopping">
      <div class="tab-content">
        <!-- ############################################################ //-->
        <!--          ONGLET Information General                          //-->
        <!-- ############################################################ //-->
        <div class="tab-pane active" id="tab1">
          <div
            class="col-md-12 mainTitle"><?php echo $CLICSHOPPING_TemplateEmail->getDef('title_information_name'); ?></div>
          <div class="adminformTitle">
            <div class="row">
              <div class="col-md-12">
                <div class="form-group row">
                  <label for="name"
                         class="col-12 col-form-label"><?php echo $CLICSHOPPING_TemplateEmail->getDef('template_email_text_name'); ?>
                </div>
              </div>
            </div>
            <?php
            $languages = $CLICSHOPPING_Language->getLanguages();
            for ($i = 0, $n = \count($languages); $i < $n; $i++) {
              ?>
              <div class="row">
                <div class="col-md-8">
                  <div class="form-group row">
                    <label for="code"
                           class="col-3 col-form-label"><?php echo $CLICSHOPPING_Language->getImage($languages[$i]['code']); ?></label>
                    <div class="col-md-7">
                      <?php echo HTML::inputField('template_email_name[' . $languages[$i]['id'] . ']', (isset($template_email_name[$languages[$i]['id']]) ? $template_email_name[$languages[$i]['id']] : TemplateEmailAdmin::getTemplateEmailName($tInfo->template_email_id, $languages[$i]['id'])), 'maxlength="250", size="50"', true) . '&nbsp;'; ?>
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
                  <label for="description"
                         class="col-12 col-form-label"><?php echo $CLICSHOPPING_TemplateEmail->getDef('template_email_text_short_description'); ?></label>
                </div>
              </div>
            </div>
            <?php
            $languages = $CLICSHOPPING_Language->getLanguages();
            for ($i = 0, $n = \count($languages); $i < $n; $i++) {
              ?>
              <div class="row">
                <div class="col-md-8">
                  <div class="form-group row">
                    <label for="code"
                           class="col-5 col-form-label"><?php echo $CLICSHOPPING_Language->getImage($languages[$i]['code']); ?></label>
                    <div class="col-md-5">
                      <?php echo HTML::inputField('template_email_short_description[' . $languages[$i]['id'] . ']', (isset($template_email_short_description[$languages[$i]['id']]) ? $template_email_short_description[$languages[$i]['id']] : TemplateEmailAdmin::getTemplateEmailShortDescription($tInfo->template_email_id, $languages[$i]['id'])), 'maxlength="250", size="50"', true) . '&nbsp;'; ?>
                    </div>
                  </div>
                </div>
              </div>
              <?php
            }
            ?>
          </div>
          <div class="mt-1"></div>
          <div class="alert alert-info">
            <div><?php echo '<h4><i class="bi bi-question-circle" title="' . $CLICSHOPPING_TemplateEmail->getDef('help_title_onglet_general') . '"></i></h4> ' . $CLICSHOPPING_TemplateEmail->getDef('help_title_onglet_general') ?></div>
            <div class="mt-1"></div>
            <div><?php echo $CLICSHOPPING_TemplateEmail->getDef('text_help_template'); ?></div>
          </div>
        </div>

        <!-- ############################################################ //-->
        <!--          ONGLET Information Description                      //-->
        <!-- ############################################################ //-->
        <div class="tab-pane" id="tab2">
          <div class="col-md-12 mainTitle"><?php echo $CLICSHOPPING_TemplateEmail->getDef('title_message'); ?></div>
          <div class="adminformTitle">
            <div class="accordion" id="accordionExample">
              <?php
              for ($i = 0, $n = \count($languages); $i < $n; $i++) {
                ?>
                <div class="accordion-item">
                  <h2 class="accordion-header" id="heading<?php $i; ?>">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse"
                            data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                      <?php echo $CLICSHOPPING_Language->getImage($languages[$i]['code']); ?>
                    </button>
                  </h2>
                  <?php
                  if ($i == 0) {
                    $show = ' show';
                  } else {
                    $show = '';
                  }
                  ?>

                  <div id="collapseOne" class="accordion-collapse collapse <?php echo $show; ?>"
                       aria-labelledby="heading<?php $i; ?>" data-bs-parent="#accordionExample">
                    <div class="accordion-body">
                      <div class="row">
                        <div class="col-md-12">
                          <div class="form-group row">
                            <div class="col-md-12">
                              <?php
                              $name = 'template_email_description[' . $languages[$i]['id'] . ']';
                              $ckeditor_id = $CLICSHOPPING_Wysiwyg::getWysiwygId($name);

                              echo $CLICSHOPPING_Wysiwyg::textAreaCkeditor($name, 'soft', '750', '300', (isset($template_email_description[$languages[$i]['id']]) ? str_replace('& ', '&amp; ', trim($template_email_description[$languages[$i]['id']])) : TemplateEmailAdmin::getTemplateEmailDescription($tInfo->template_email_id, $languages[$i]['id'])), 'id="' . $ckeditor_id . '"');
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
            </div>
          </div>
          <div class="mt-1"></div>
          <div class="alert alert-info">
            <div><?php echo '<h4><i class="bi bi-question-circle" title="' . $CLICSHOPPING_TemplateEmail->getDef('help_title_onglet_general') . '"></i></h4> ' . $CLICSHOPPING_TemplateEmail->getDef('help_title_onglet_general') ?></div>
            <div class="mt-1"></div>
            <div><?php echo $CLICSHOPPING_TemplateEmail->getDef('text_help_template'); ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>
  </form>
</div>