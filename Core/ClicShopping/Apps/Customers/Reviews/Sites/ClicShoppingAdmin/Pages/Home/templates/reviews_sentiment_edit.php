<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Customers\Reviews\Classes\ClicShoppingAdmin\ReviewsAdmin;
use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;

$CLICSHOPPING_Reviews = Registry::get('Reviews');
$CLICSHOPPING_Hooks = Registry::get('Hooks');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');
$CLICSHOPPING_ProductsAdmin = Registry::get('ProductsAdmin');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Wysiwyg = Registry::get('Wysiwyg');

$Qreviews = $CLICSHOPPING_Reviews->db->prepare('select r.reviews_id,
                                                       r.products_id,
                                                       p.products_image,
                                                       rs.id,
                                                       rs.sentiment_status,
                                                       rs.sentiment_approved,
                                                       rs.review_count,
                                                       rs.positive_pct,
                                                       rs.neutral_pct,
                                                       rs.negative_pct,
                                                       rs.rating_stddev,
                                                       rs.date_modified,
                                                       rsd.description,
                                                       rsd.critic_verdict
                                                from :table_reviews r
                                                        left join :table_reviews_sentiment rs on (r.reviews_id = rs.reviews_id)
                                                        left join :table_reviews_sentiment_description rsd on (rs.id = rsd.id),
                                                     :table_products p
                                                where p.products_id = r.products_id
                                                and r.reviews_id = :reviews_id
                                                and rs.id = rsd.id
                                                ');

$Qreviews->bindInt('reviews_id', (int)$_GET['rID']);
$Qreviews->execute();

$languages = $CLICSHOPPING_Language->getLanguages();

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

echo HTML::form('sentiment', $CLICSHOPPING_Reviews->link('ReviewsSentiment&Save&rID=' . (int)$_GET['rID'] . '&page=' . $page), 'post', 'enctype="multipart/form-data"');
echo $CLICSHOPPING_Wysiwyg::getWysiwyg();
?>

<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/reviews.gif', $CLICSHOPPING_Reviews->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-5 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Reviews->getDef('heading_title'); ?></span>
          <span class="col-md-6 text-end">
           <?php
             echo HTML::button($CLICSHOPPING_Reviews->getDef('button_back'), null, $CLICSHOPPING_Reviews->link('ReviewsSentiment&page=' . $page), 'primary') . '&nbsp;';
             echo HTML::button($CLICSHOPPING_Reviews->getDef('button_save'), null, null, 'success') . '&nbsp;';
           ?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div id="reviewsTabs" style="overflow: auto;">
    <ul class="nav nav-tabs flex-column flex-sm-row" role="tablist" id="myTab">
      <li
        class="nav-item"><?php echo '<a href="#tab1" role="tab" data-bs-toggle="tab" class="nav-link active">' . $CLICSHOPPING_Reviews->getDef('tab_general') . '</a>'; ?></li>
      <li
        class="nav-item"><?php echo '<a href="#tab2" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_Reviews->getDef('tab_analysis') . '</a>'; ?></li>
    </ul>
    <div class="tabsClicShopping">
      <div class="tab-content">
        <?php
        // -------------------------------------------------------------------
        //          ONGLET General sur la description de la categorie
        // -------------------------------------------------------------------
        ?>
        <div class="tab-pane active" id="tab1">
          <div class="col-md-12 mainTitle">
            <div class="row">
              <span class="col-md-6"><?php echo $CLICSHOPPING_Reviews->getDef('text_description_sentiment'); ?></span>
              <span class="col-md-6 text-end"><?php echo AdministratorAdmin::getUserAdmin() . HTML::hiddenField('user_admin', AdministratorAdmin::getUserAdmin()); ?></span>
            </div>
          </div>
          <div class="adminformTitle">
            <div class="accordion" id="accordionExample">
              <?php
              for ($i = 0, $n = \count($languages); $i < $n; $i++) {
                $languages_id = $languages[$i]['id'];
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
                      <div class="col-md-12" id="ReviewsSentimentDescription<?php echo $languages[$i]['id']; ?>">
                        <?php
                        $name = 'reviews_sentiment_description[' . $languages_id . ']';
                        $ckeditor_id = $CLICSHOPPING_Wysiwyg::getWysiwygId($name);

                        echo $CLICSHOPPING_Wysiwyg::textAreaCkeditor($name, 'soft', '750', '300', (isset($reviews_sentiment_description[$languages_id]) ? str_replace('& ', '&amp; ', trim($reviews_sentiment_description[$languages_id])) : ReviewsAdmin::getSentimentDescription($Qreviews->valueInt('id'), $languages_id)), 'id="' . $ckeditor_id . '"');
                        ?>
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
        <?php
        // -------------------------------------------------------------------
        //          ONGLET Analyse / Indicateurs (ABSA)
        // -------------------------------------------------------------------
        $sentiment_id  = (int)$Qreviews->valueInt('id');
        $analysis_products_id = (int)$Qreviews->valueInt('products_id');
        $review_count  = (int)$Qreviews->valueInt('review_count');
        $rating_stddev = (float)$Qreviews->value('rating_stddev');
        $confidence    = \ClicShopping\Apps\Customers\Reviews\Classes\Shared\ReviewSentiment\SentimentMetrics::confidenceLevel($review_count);
        $polarized     = $rating_stddev >= \ClicShopping\Apps\Customers\Reviews\Classes\Shared\ReviewSentiment\SentimentMetrics::POLARIZATION_STDDEV_THRESHOLD;
        $critic_verdict = $Qreviews->value('critic_verdict');

        // Engagement (read-only) — helpful votes on the AI summary (reviews_id = 0)
        $Qai = $CLICSHOPPING_Reviews->db->prepare('select
                    sum(case when vote = 1 then 1 else 0 end) as yes,
                    sum(case when vote = 0 then 1 else 0 end) as no
                  from :table_reviews_vote
                  where products_id = :products_id and reviews_id = 0');
        $Qai->bindInt(':products_id', $analysis_products_id);
        $Qai->execute();
        $ai_yes = (int)$Qai->valueInt('yes');
        $ai_no  = (int)$Qai->valueInt('no');
        ?>
        <div class="tab-pane" id="tab2">
          <div class="col-md-12 mainTitle">
            <span><?php echo $CLICSHOPPING_Reviews->getDef('tab_analysis'); ?></span>
          </div>
          <div class="adminformTitle">
            <table class="table table-striped">
              <tbody>
                <tr><td><?php echo $CLICSHOPPING_Reviews->getDef('text_reviews_analysed'); ?></td><td><?php echo $review_count; ?></td></tr>
                <tr><td><?php echo $CLICSHOPPING_Reviews->getDef('text_positive_pct'); ?></td><td><?php echo $Qreviews->valueInt('positive_pct'); ?>&nbsp;%</td></tr>
                <tr><td><?php echo $CLICSHOPPING_Reviews->getDef('text_neutral_pct'); ?></td><td><?php echo $Qreviews->valueInt('neutral_pct'); ?>&nbsp;%</td></tr>
                <tr><td><?php echo $CLICSHOPPING_Reviews->getDef('text_negative_pct'); ?></td><td><?php echo $Qreviews->valueInt('negative_pct'); ?>&nbsp;%</td></tr>
                <tr><td><?php echo $CLICSHOPPING_Reviews->getDef('text_confidence'); ?></td><td><?php echo $CLICSHOPPING_Reviews->getDef('text_confidence_' . $confidence); ?></td></tr>
                <tr><td><?php echo $CLICSHOPPING_Reviews->getDef('text_dispersion'); ?></td><td><?php echo $CLICSHOPPING_Reviews->getDef($polarized ? 'text_polarized' : 'text_homogeneous') . ' (σ=' . number_format($rating_stddev, 2) . ')'; ?></td></tr>
                <tr><td><?php echo $CLICSHOPPING_Reviews->getDef('text_critic_verdict'); ?></td><td><?php echo $critic_verdict !== '' ? $CLICSHOPPING_Reviews->getDef('text_verdict_' . $critic_verdict) : '-'; ?></td></tr>
                <tr><td><?php echo $CLICSHOPPING_Reviews->getDef('text_last_updated'); ?></td><td><?php echo HTML::outputProtected((string)$Qreviews->value('date_modified')); ?></td></tr>
                <tr><td><?php echo $CLICSHOPPING_Reviews->getDef('text_ai_engagement'); ?></td><td><?php echo $CLICSHOPPING_Reviews->getDef('modules_products_reviews_text_useful_vote_yes') . ' (' . $ai_yes . ') / ' . $CLICSHOPPING_Reviews->getDef('modules_products_reviews_text_useful_vote_no') . ' (' . $ai_no . ')'; ?></td></tr>
              </tbody>
            </table>
            <?php
            if ($ai_no > $ai_yes) {
              echo '<div class="alert alert-warning" role="alert">' . $CLICSHOPPING_Reviews->getDef('text_ai_engagement_alert') . '</div>';
            }

            for ($i = 0, $n = \count($languages); $i < $n; $i++) {
              $languages_id = (int)$languages[$i]['id'];

              $Qjson = $CLICSHOPPING_Reviews->db->prepare('select analysis_json
                          from :table_reviews_sentiment_description
                          where id = :id and language_id = :language_id');
              $Qjson->bindInt(':id', $sentiment_id);
              $Qjson->bindInt(':language_id', $languages_id);
              $Qjson->execute();

              $analysis = \ClicShopping\Apps\Customers\Reviews\Classes\Shared\ReviewSentiment\SentimentAnalysisData::fromJson($Qjson->value('analysis_json'), '');

              if (!$analysis->isStructured()) {
                continue;
              }

              echo '<div class="card mt-2"><div class="card-body">';
              echo '<h6>' . $CLICSHOPPING_Language->getImage($languages[$i]['code']) . '</h6>';

              if ($analysis->getThemes() !== []) {
                echo '<p><strong>' . $CLICSHOPPING_Reviews->getDef('text_themes') . '</strong></p><p>';
                foreach ($analysis->getThemes() as $theme) {
                  echo ' <span class="badge text-bg-light">' . HTML::outputProtected($theme['label']) . ' (' . (int)$theme['frequency'] . ', ' . HTML::outputProtected($theme['sentiment']) . ')</span>';
                }
                echo '</p>';
              }

              $quotes = $analysis->getQuotes();
              if ($quotes['positive'] !== []) {
                echo '<p><strong>' . $CLICSHOPPING_Reviews->getDef('text_quotes_positive') . '</strong></p><ul>';
                foreach ($quotes['positive'] as $quote) {
                  echo '<li>' . HTML::outputProtected($quote) . '</li>';
                }
                echo '</ul>';
              }
              if ($quotes['negative'] !== []) {
                echo '<p><strong>' . $CLICSHOPPING_Reviews->getDef('text_quotes_negative') . '</strong></p><ul>';
                foreach ($quotes['negative'] as $quote) {
                  echo '<li>' . HTML::outputProtected($quote) . '</li>';
                }
                echo '</ul>';
              }

              echo '</div></div>';
            }
            ?>
          </div>
        </div>
        <div class="mt-1"></div>
        <?php echo $CLICSHOPPING_Hooks->output('ReviewsSentimentEdit', 'PageTab', null, 'display'); ?>
      </div>
    </div>
  </div>
</div>
</form>
