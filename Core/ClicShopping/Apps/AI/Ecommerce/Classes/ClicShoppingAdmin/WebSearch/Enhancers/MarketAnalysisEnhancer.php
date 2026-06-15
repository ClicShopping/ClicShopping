<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\WebSearch\Enhancers;

use ClicShopping\AI\InterfacesAI\WebSearchResultEnhancerInterface;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\WebSearch\EcommerceWebSearchFacade;
use ClicShopping\AI\DomainsAI\WebSearch\Helper\PriceBoundFilter;
use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\OM\Registry;

/**
 * MarketAnalysisEnhancer — Ecommerce result enhancer for price_comparison
 *
 * When the user asks "compare with Amazon / is my price aligned with the
 * market", the Hybrid pipeline returns 99 product cards but no actual
 * analysis. This enhancer adds the missing synthesis: it reuses the
 * existing {@see EcommerceWebSearchFacade::comparePrice()} to compute
 * stats (avg / min / max / competitive status) from the WebSearch results,
 * feeds them to the LLM through the Gpt facade, and injects a short
 * natural-language paragraph back into `$results['market_analysis']`.
 *
 * WebSearchFormatter renders that field at the top of the response, so the
 * user reads the answer to their question before scrolling to the cards.
 *
 * COMPLIANCE NOTES:
 * - The LLM call goes through {@see Gpt::getGptResponse()} (AGENTS.md:
 *   "Direct LLM API calls without LLPhant abstraction" is prohibited).
 * - All Amazon / brand knowledge stays in Apps/AI/Ecommerce. Core only
 *   sees the resulting HTML string in a generic field name.
 * - The enhancer NEVER throws — failure returns the input untouched.
 *
 * @package ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\WebSearch\Enhancers
 * @since 2026-05-25
 */
final class MarketAnalysisEnhancer implements WebSearchResultEnhancerInterface
{
    private const ENHANCER_ID = 'ecommerce-market-analysis-synthesis';

    /**
     * Max length we send to the LLM as the comparison summary. Keeps the
     * synthesis prompt tiny (the LLM only needs the stats, not the cards).
     */
    private const MAX_PROMPT_TOKENS = 500;

    public function getEnhancerId(): string
    {
        return self::ENHANCER_ID;
    }

    public function shouldEnhance(array $results, array $context): bool
    {
        // 1) Only price_comparison intents — the synthesis only makes sense
        //    when the user asked for a market comparison.
        if (($context['intent_type'] ?? null) !== 'price_comparison') {
            return false;
        }

        // 2) Need actual shopping data to compare against.
        if (empty($results['shopping_results']) || !is_array($results['shopping_results'])) {
            return false;
        }

        // 3) Need a product reference the user is asking about.
        if (empty($context['product_query']) && empty($context['query'])) {
            return false;
        }

        return true;
    }

    public function enhance(array $results, array $context): array
    {
        try {
            $facade = new EcommerceWebSearchFacade();

            // 1) Locate the internal product the user is asking about.
            $productQuery = $context['product_query'] ?? $context['query'];
            $languageId = isset($context['language_id']) ? (int) $context['language_id'] : null;

            $internal = $facade->findProductInDatabase($productQuery, $languageId);
            if ($internal === null || empty($internal['name']) || empty($internal['price'])) {
                // No internal match → no comparison possible.
                return $results;
            }

            // Bound the DISPLAYED shopping results to ±BOUND_PERCENT of the catalog price so
            if (!empty($results['shopping_results']) && is_array($results['shopping_results'])) {
                $cardBound = PriceBoundFilter::bound((float) $internal['price'], $results['shopping_results'], 'extracted_price');
                $results['shopping_results'] = $cardBound['kept'];
            }

            // 2) Compute competitor stats (avg / min / max / status / etc.)
            //    using the existing comparePrice() — single source of truth.
            $comparison = $facade->comparePrice(
                ['name' => $internal['name'], 'price' => $internal['price']],
                $results
            );

            if (empty($comparison['competitor_prices'])) {
                return $results;
            }

            // 3) Ask the LLM for a short natural-language synthesis (generated in English,
            //    per the "process in English" rule), then restitute it in the interface
            $synthesisText = $this->callLlm($internal, $comparison, $context);
            if ($synthesisText === '') {
                return $results;
            }
	    
            $synthesisText = SemanticAgent::translateToLanguage($synthesisText, $languageId);

            // 4) Wrap as HTML encart, ready for WebSearchFormatter to inline.
            $results['market_analysis'] = $this->buildHtmlEncart(
                $internal,
                $comparison,
                $synthesisText
            );

            return $results;
        } catch (\Throwable $e) {
            // Log but never break the response.
            (new SecurityLogger())->logStructured(
                'warning',
                'MarketAnalysisEnhancer',
                'enhance_failed',
                [
                    'error'   => $e->getMessage(),
                    'product' => $context['product_query'] ?? null,
                ]
            );
            return $results;
        }
    }

    /**
     * Build the synthesis prompt and invoke {@see Gpt::getGptResponse()}.
     *
     * The prompt deliberately ships only the structured stats from
     * comparePrice() — never the 140 KB of HTML cards (we learned that the
     * hard way during the critic-prompt trimming exercise).
     */
    private function callLlm(array $internal, array $comparison, array $context): string
    {
        $stats = $comparison['comparison'] ?? [];
        $internalPrice = (float) ($comparison['internal_price'] ?? 0);
        $competitorCount = (int) ($comparison['total_competitors_found'] ?? 0);
        $avg = (float) ($stats['average_competitor_price'] ?? 0);
        $cheapest = $stats['cheapest'] ?? null;
        $mostExpensive = $stats['most_expensive'] ?? null;
        $status = $comparison['competitive_status'] ?? 'unknown';

        // Pick the response language from the user query if known, default EN.
        $language = strtolower($context['language'] ?? '');
        $isFrench = ($language === 'fr' || $language === 'french' || $language === 'français');
        $responseLanguage = $isFrench ? 'French' : 'English';

        $promptLines = [
            "You are an e-commerce pricing analyst.",
            "Write a SHORT synthesis (3 to 5 sentences, no bullet points, no headings)",
            "explaining whether the merchant's price is aligned with the market.",
            "Be factual, neutral and quote the numbers.",
            "",
            "Respond in {$responseLanguage}.",
            "",
            "DATA:",
            "- product: " . $internal['name'],
            "- merchant_price: " . number_format($internalPrice, 2, '.', '') . " EUR",
            "- competitor_count: " . $competitorCount,
            "- average_competitor_price: " . number_format($avg, 2, '.', '') . " EUR",
            "- competitive_status: " . $status,
        ];

        if (is_array($cheapest) && isset($cheapest['competitor_price'], $cheapest['source'])) {
            $promptLines[] = sprintf(
                "- cheapest_competitor: %s at %.2f EUR",
                $cheapest['source'],
                (float) $cheapest['competitor_price']
            );
        }

        if (is_array($mostExpensive) && isset($mostExpensive['competitor_price'], $mostExpensive['source'])) {
            $promptLines[] = sprintf(
                "- most_expensive_competitor: %s at %.2f EUR",
                $mostExpensive['source'],
                (float) $mostExpensive['competitor_price']
            );
        }

        $prompt = implode("\n", $promptLines);

        $response = Gpt::getGptResponse($prompt, self::MAX_PROMPT_TOKENS, 0.3);

        if ($response === false || !is_string($response)) {
            return '';
        }

        return trim($response);
    }

    /**
     * Render the synthesis as an HTML encart for the top of the response.
     *
     * The enhancer runs BEFORE WebSearchFormatter (which loads the shared
     * ai_response_labels file in its constructor), so we load it explicitly
     * here — otherwise getDef() returns the raw key.
     */
    private function buildHtmlEncart(array $internal, array $comparison, string $synthesisText): string
    {
        Registry::get('Language')->loadDefinitions('ClicShoppingAdmin/ai_response_labels');

        $language = Registry::get('Language');

        $title       = $language->getDef('text_rag_market_analysis_title');
        $aiNotice    = $language->getDef('text_rag_market_analysis_ai_notice');
        $sourcesLbl  = $language->getDef('text_rag_market_analysis_sources');

        $productName   = htmlspecialchars((string) ($internal['name'] ?? ''));
        $synthesisHtml = nl2br(htmlspecialchars($synthesisText));
        $count         = (int) ($comparison['total_competitors_found'] ?? 0);

        // Explicit inline colors so the encart stays readable even when the
        // admin theme overrides Bootstrap's .alert-info (same fix as the
        // mode badges and Trends title).
        $html  = "<div class='market-analysis alert alert-info' "
               . "style='margin-bottom:15px; background:#d1ecf1; border:1px solid #bee5eb; "
               . "color:#0c5460; border-radius:6px; padding:12px 15px;'>";
        $html .= "<h5 style='margin:0 0 6px 0; color:#0c5460; font-size:1.05em;'>📊 "
               . htmlspecialchars($title) . " — "
               . "<span style='color:#212529;'>" . $productName . "</span></h5>";
        $html .= "<div class='market-analysis-body' style='color:#212529;'>{$synthesisHtml}</div>";

        // Transparency: tell the user the comparison was bounded (accessories excluded).
        $priceBound = $comparison['price_bound'] ?? [];
        if ((int) ($priceBound['excluded'] ?? 0) > 0) {
            $html .= "<div class='market-analysis-bound' style='margin-top:8px; font-size:0.85em; color:#856404; background:#fff3cd; border:1px solid #ffeeba; border-radius:4px; padding:6px 10px;'>⚠️ "
                   . htmlspecialchars($language->getDef('text_rag_price_bound_notice', ['bound' => (int) ($priceBound['bound_percent'] ?? PriceBoundFilter::BOUND_PERCENT), 'excluded' => (int) $priceBound['excluded']]))
                   . "</div>";
        }

        $html .= "<div class='market-analysis-footer' style='margin-top:8px; font-size:0.8em; color:#6c757d;'>";
        $html .= "🤖 " . htmlspecialchars($aiNotice) . " — " . $count . " " . htmlspecialchars($sourcesLbl);
        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }
}
