<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

use ClicShopping\OM\HTML;

// Generate unique ID for accordion
$uniqueId = 'faq-' . uniqid();
?>
<div class="col-md-<?php echo $content_width; ?>">
  <div class="mt-3 mb-3">
    <div class="modulesProductsInfoFaq" itemscope itemtype="https://schema.org/FAQPage">
      <h2 class="faq-title mb-3"><?php echo $faq_title; ?></h2>
      
      <div class="accordion" id="<?php echo $uniqueId; ?>">
        <?php
        $index = 0;
        foreach ($faq_data as $item) {
          if (!isset($item['q']) || !isset($item['a'])) {
            continue;
          }
          
          $question = HTML::outputProtected($item['q']);
          $answer = HTML::outputProtected($item['a']);
          $itemId = $uniqueId . '-item-' . $index;
          $isFirst = ($index === 0);
          ?>
          <div class="accordion-item" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
            <h3 class="accordion-header" id="heading-<?php echo $itemId; ?>">
              <button class="accordion-button<?php echo $isFirst ? '' : ' collapsed'; ?>" 
                      type="button" 
                      data-bs-toggle="collapse" 
                      data-bs-target="#collapse-<?php echo $itemId; ?>" 
                      aria-expanded="<?php echo $isFirst ? 'true' : 'false'; ?>" 
                      aria-controls="collapse-<?php echo $itemId; ?>">
                <span itemprop="name"><?php echo $question; ?></span>
              </button>
            </h3>
            <div id="collapse-<?php echo $itemId; ?>" 
                 class="accordion-collapse collapse<?php echo $isFirst ? ' show' : ''; ?>" 
                 aria-labelledby="heading-<?php echo $itemId; ?>" 
                 data-bs-parent="#<?php echo $uniqueId; ?>">
              <div class="accordion-body" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                <p itemprop="text"><?php echo $answer; ?></p>
              </div>
            </div>
          </div>
          <?php
          $index++;
        }
        ?>
      </div>
    </div>
  </div>
</div>
