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
use ClicShopping\AI\DomainsAI\WebSearch\Helper\NumericBandFilter;
use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\AI\Ecommerce\Config\EcommerceDefaults;
use ClicShopping\AI\Config\DomainConfig;

/**
 * MarketAnalysisEnhancer — Ecommerce result enhancer for comparative_lookup
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

    /** Named unit when the install declares none — stated to the reader, never silent. */
    private const CURRENCY_FALLBACK = 'USD';

    /**
     * Graphies SerpAPI actually emits, which the shop's own table does not carry: `currencies` holds
     * whatever the merchant typed as symbol (this install: 'EUR', 'USD', 'CAD'), never '€' or '¥'.
     * One unit has several graphies — the yen is written '¥' (U+00A5) or '￥' (U+FFE5).
     */
    private const WIRE_GRAPHIES = [
        '€' => 'EUR', '£' => 'GBP', '¥' => 'JPY', '￥' => 'JPY', 'US$' => 'USD', '$' => 'USD',
        'CHF' => 'CHF', 'Fr.' => 'CHF',
    ];

    public function getEnhancerId(): string
    {
        return self::ENHANCER_ID;
    }

    public function shouldEnhance(array $results, array $context): bool
    {
        // 1) Only comparative_lookup intents — the synthesis only makes sense
        //    when the user asked for a market comparison.
        if (($context['intent_type'] ?? null) !== 'comparative_lookup') {
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
            // 0) One unit, or no aggregate. The band and the average both compare the offers to the
            //    catalogue price; in two currencies that comparison elects and averages nonsense.
            [$regionCurrency, $regionEstablished] = $this->regionCurrency($context);
            $baseCurrency = $this->baseCurrency();

            if ($regionCurrency !== '' && $regionCurrency !== $baseCurrency) {
                $results['market_analysis'] = $this->buildCurrencyMismatchEncart($baseCurrency, $regionCurrency);

                return $results;
            }

            // The table above states the unit a region is EXPECTED to serve. The offers can
            // contradict it, never elect one: '$' is USD, CAD or AUD and needs the region to be read.
            $contradiction = self::contradictingGraphie($results['shopping_results'] ?? [], $baseCurrency);

            if ($contradiction !== '') {
                $results['market_analysis'] = $this->buildCurrencyContradictedEncart($baseCurrency, $contradiction);

                return $results;
            }

            $facade = new EcommerceWebSearchFacade();

            // 1) Locate the internal product the user is asking about.
            $productQuery = $context['product_query'] ?? $context['query'];
            $languageId = isset($context['language_id']) ? (int) $context['language_id'] : null;

            $candidates = $facade->findProductCandidates($productQuery, $languageId);

            // Nothing matched, or several products did: say so instead of dropping the comparison.
            if (\count($candidates) !== 1) {
                $results['market_analysis'] = $this->buildCandidatesEncart($productQuery, $candidates);

                return $results;
            }

            $internal = $candidates[0];

            if (empty($internal['name']) || empty($internal['price'])) {
                return $results;
            }

            // One reading of the price, one bounding, one population: what is rendered as an offer
            // is EXACTLY what the average counts. Two sets would put two counts on one screen.
            $unreadable = 0;
            $installments = 0;
            $priced = [];

            foreach (($results['shopping_results'] ?? []) as $offer) {
                if (!is_array($offer)) {
                    continue;
                }

                // A monthly payment is not a cash price: '$17.50/mo' would weigh on the average
                // as one. Set the row aside rather than widen the price pattern.
                if (!empty($offer['installment'])) {
                    $installments++;
                    continue;
                }

                $price = $facade->extractPriceFromResult($offer);

                if ($price === null) {
                    $unreadable++;
                    continue;
                }

                $offer['extracted_price'] = $price;
                $priced[] = $offer;
            }

            $cardBound = NumericBandFilter::bound((float) $internal['price'], $priced, 'extracted_price');
            $results['shopping_results'] = $cardBound['kept'];

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
                $synthesisText,
                $unreadable,
                $this->baseCurrencyIsDeclared() && $regionEstablished ? '' : $baseCurrency,
                $installments
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
     */
    private function callLlm(array $internal, array $comparison, array $context): string
    {
        $prompt = $this->buildPrompt($internal, $comparison, $context, $this->baseCurrency());

        $response = Gpt::getGptResponse($prompt, EcommerceDefaults::int('CLICSHOPPING_APP_ECOMMERCE_EC_WEB_MAX_PROMPT_TOKENS'), 0.3);

        if ($response === false || !is_string($response)) {
            return '';
        }

        return trim($response);
    }

    /**
     * The currency every amount of the encart is expressed in.
     *
     * Catalogue prices are stored in base currency and the offers are rendered as read; naming the
     * session currency would announce a conversion that never happened.
     *
     * @return string ISO code, {@see self::CURRENCY_FALLBACK} when the install declares none
     */
    private function baseCurrency(): string
    {
        return self::resolveCurrency(\defined('DEFAULT_CURRENCY') ? (string) DEFAULT_CURRENCY : null);
    }

    /**
     * @param string|null $declared What the install declares, null or empty when it declares nothing
     * @return string Never empty: an unnamed unit is what made the encart lie
     */
    private static function resolveCurrency(?string $declared): string
    {
        $declared = strtoupper(trim((string) $declared));

        return $declared !== '' ? $declared : self::CURRENCY_FALLBACK;
    }

    /** False when the encart is naming {@see self::CURRENCY_FALLBACK} for want of a declaration. */
    private function baseCurrencyIsDeclared(): bool
    {
        return \defined('DEFAULT_CURRENCY') && trim((string) DEFAULT_CURRENCY) !== '';
    }

    /**
     * The currency the offers were searched in, and whether anyone established it.
     *
     * @return array{0: string, 1: bool} Region currency ('' when unknown), region established
     */
    private function regionCurrency(array $context): array
    {
        $location = $context['location_params'] ?? [];

        return [
            strtoupper(trim((string) ($location['currency'] ?? ''))),
            empty($location['is_fallback']),
        ];
    }

    /**
     * Compose the synthesis prompt from the structured stats of comparePrice().
     *
     * It deliberately ships only those stats — never the 140 KB of HTML cards.
     *
     * @param string $currency Base currency code, appended to every amount
     */
    private function buildPrompt(array $internal, array $comparison, array $context, string $currency): string
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
        $cur = $currency !== '' ? ' ' . $currency : '';

        DomainConfig::loadLanguageFile('rag_market_analysis');
        $language = Registry::get('Language');

        return $language->getDef('llm_prompt_market_analysis', [
            'response_language' => $responseLanguage,
            'product' => (string) ($internal['name'] ?? ''),
            'merchant_price' => number_format($internalPrice, 2, '.', '') . $cur,
            'competitor_count' => (string) $competitorCount,
            'average_competitor_price' => number_format($avg, 2, '.', '') . $cur,
            'competitive_status' => (string) $status,
            'cheapest_line' => $this->extremeLine('llm_prompt_market_analysis_cheapest', $cheapest, $cur),
            'most_expensive_line' => $this->extremeLine('llm_prompt_market_analysis_most_expensive', $mostExpensive, $cur),
        ]);
    }

    /**
     * One "cheapest/most expensive competitor" line, or nothing when the stat is missing.
     *
     * @param string $key Language key of the line
     * @param mixed $extreme Stat from comparePrice(), any shape
     * @param string $cur Currency suffix, already spaced
     */
    private function extremeLine(string $key, mixed $extreme, string $cur): string
    {
        if (!is_array($extreme) || !isset($extreme['competitor_price'], $extreme['source'])) {
            return '';
        }

        return Registry::get('Language')->getDef($key, [
            'source' => (string) $extreme['source'],
            'price' => number_format((float) $extreme['competitor_price'], 2, '.', '') . $cur,
        ]);
    }

    /**
     * Render what to do when the catalogue did not name ONE product: nothing matched, or several did.
     *
     * A comparison that cannot name its reference must say so — dropping it silently left the user
     * with unbounded offers presented as competitors of a product never named.
     *
     * @param string $term Product term the question carried
     * @param array $candidates Catalogue products matching that term
     * @return string HTML encart
     */
    private function buildCandidatesEncart(string $term, array $candidates): string
    {
        $language = Registry::get('Language');
        $language->loadDefinitions('ClicShoppingAdmin/ai_response_labels');

        $currency = $this->baseCurrency();
        $cur = $currency !== '' ? ' ' . $currency : '';

        $html = "<div class='market-analysis alert alert-warning' "
              . "style='margin-bottom:15px; background:#fff3cd; border:1px solid #ffeeba; "
              . "color:#856404; border-radius:6px; padding:12px 15px;'>";

        if ($candidates === []) {
            $html .= "<div>⚠️ " . htmlspecialchars($language->getDef('text_rag_market_analysis_no_internal_product', ['term' => $term])) . "</div>";

            return $html . "</div>";
        }

        $html .= "<h5 style='margin:0 0 6px 0; color:#856404; font-size:1.05em;'>❓ "
               . htmlspecialchars($language->getDef('text_rag_market_analysis_which_product')) . "</h5>";
        $html .= "<div style='color:#212529;'>"
               . htmlspecialchars($language->getDef('text_rag_market_analysis_candidates_notice', ['count' => \count($candidates), 'term' => $term]))
               . "</div>";

        $html .= "<table class='table table-sm' style='margin-top:8px; background:#fff; color:#212529;'><thead><tr>"
               . "<th>" . htmlspecialchars($language->getDef('text_rag_market_analysis_candidate_name')) . "</th>"
               . "<th>" . htmlspecialchars($language->getDef('text_rag_market_analysis_candidate_model')) . "</th>"
               . "<th>" . htmlspecialchars($language->getDef('text_rag_market_analysis_candidate_price')) . "</th>"
               . "</tr></thead><tbody>";

        foreach ($candidates as $candidate) {
            $html .= "<tr><td>" . htmlspecialchars((string) ($candidate['name'] ?? ''))
                   . "</td><td>" . htmlspecialchars((string) ($candidate['model'] ?? ''))
                   . "</td><td>" . number_format((float) ($candidate['price'] ?? 0), 2, ',', ' ') . $cur . "</td></tr>";
        }

        $html .= "</tbody></table></div>";

        return $html;
    }

    /**
     * The graphie the offers are overwhelmingly written in, when it names ANOTHER unit than $currency.
     *
     * Returns '' whenever nothing is positively recognised: an unread token never refuses anything.
     *
     * @param array $offers Offers as received, before pricing and bounding
     * @param string $currency Unit the aggregate would be expressed in
     * @return string Contradicting graphie, '' when the offers corroborate or say nothing
     */
    private static function contradictingGraphie(array $offers, string $currency): string
    {
        $votes = [];
        $priced = 0;
        $graphies = self::currencyGraphies();

        foreach ($offers as $offer) {
            $price = is_array($offer) ? trim((string) ($offer['price'] ?? '')) : '';

            if ($price === '') {
                continue;
            }

            $priced++;
            $graphie = self::readGraphie($price, $graphies);

            if ($graphie !== '') {
                $votes[$graphie] = ($votes[$graphie] ?? 0) + 1;
            }
        }

        if ($priced === 0 || $votes === []) {
            return '';
        }

        arsort($votes);
        $dominant = (string) array_key_first($votes);

        // A refusal needs a majority of the PRICED offers, not of those that happened to be read.
        if ($votes[$dominant] * 2 <= $priced) {
            return '';
        }

        return $graphies[$dominant] === strtoupper($currency) ? '' : $dominant;
    }

    /** First known graphie contained in the price string, '' when none is recognised. */
    private static function readGraphie(string $price, array $graphies): string
    {
        foreach ($graphies as $graphie => $iso) {
            if (str_contains($price, $graphie)) {
                return $graphie;
            }
        }

        return '';
    }

    /**
     * Graphie => ISO, the shop's declared currencies on top of the wire ones.
     *
     * @return array<string, string> Longest graphie first: 'US$' must win over '$'
     */
    private static function currencyGraphies(): array
    {
        $declared = [];

        if (Registry::exists('Currencies')) {
            $currencies = Registry::get('Currencies');

            foreach ($currencies->getAll() as $row) {
                $code = strtoupper(trim((string) ($row['id'] ?? '')));

                if ($code === '') {
                    continue;
                }

                $declared[$code] = [$code, (string) $currencies->get('symbol_left', $code), (string) $currencies->get('symbol_right', $code)];
            }
        }

        return self::buildGraphies($declared);
    }

    /**
     * A graphie claimed by TWO units names none: it is dropped rather than made to elect one.
     *
     * That is what '$' is once a shop declares both USD and CAD with it — the blind spot becomes
     * silence instead of a wrong refusal.
     *
     * @param array<string, array<string>> $declared ISO => graphies the shop writes it with
     * @return array<string, string> Graphie => ISO, longest graphie first
     */
    private static function buildGraphies(array $declared): array
    {
        $claims = [];

        foreach (self::WIRE_GRAPHIES as $graphie => $iso) {
            $claims[$graphie][$iso] = true;
        }

        foreach ($declared as $iso => $graphies) {
            foreach ($graphies as $graphie) {
                $graphie = trim($graphie);

                if ($graphie !== '') {
                    $claims[$graphie][strtoupper((string) $iso)] = true;
                }
            }
        }

        $map = [];

        foreach ($claims as $graphie => $isos) {
            if (\count($isos) === 1) {
                $map[(string) $graphie] = (string) array_key_first($isos);
            }
        }

        uksort($map, static fn($a, $b) => mb_strlen((string) $b) <=> mb_strlen((string) $a));

        return $map;
    }

    /**
     * Refuse the synthesis when the offers came back written in another unit than the catalogue.
     *
     * Distinct from {@see self::buildCurrencyMismatchEncart()}: that one states where the offers were
     * SEARCHED, this one what they were RENDERED in — two different facts, two sentences.
     */
    private function buildCurrencyContradictedEncart(string $baseCurrency, string $graphie): string
    {
        Registry::get('Language')->loadDefinitions('ClicShoppingAdmin/ai_response_labels');

        return $this->buildCurrencyRefusalEncart(
            Registry::get('Language')->getDef('text_rag_market_analysis_currency_contradicted', [
                'base' => $baseCurrency,
                'graphie' => $graphie,
            ])
        );
    }

    /** Shared markup of both currency refusals: a warning, never a synthesis. */
    private function buildCurrencyRefusalEncart(string $sentence): string
    {
        return "<div class='market-analysis alert alert-warning' "
             . "style='margin-bottom:15px; background:#fff3cd; border:1px solid #ffeeba; "
             . "color:#856404; border-radius:6px; padding:12px 15px;'><div>⚠️ "
             . htmlspecialchars($sentence)
             . "</div></div>";
    }

    /**
     * Refuse the synthesis when the offers and the catalogue are not in the same unit.
     *
     * The cards stay: each one is factual in its own currency. Only the AGGREGATE is withheld —
     * an average across two units is a number with no meaning, and `COMPET-5` already settled that
     * a silent relabelling costs more than a loud refusal.
     *
     * @param string $baseCurrency Unit the catalogue price is stored in
     * @param string $regionCurrency Unit the offers were searched in
     */
    private function buildCurrencyMismatchEncart(string $baseCurrency, string $regionCurrency): string
    {
        Registry::get('Language')->loadDefinitions('ClicShoppingAdmin/ai_response_labels');

        return $this->buildCurrencyRefusalEncart(
            Registry::get('Language')->getDef('text_rag_market_analysis_currency_mismatch', [
                'base' => $baseCurrency,
                'region' => $regionCurrency,
            ])
        );
    }

    /**
     * Render the synthesis as an HTML encart for the top of the response.
     *
     * The enhancer runs BEFORE WebSearchFormatter (which loads the shared
     * ai_response_labels file in its constructor), so we load it explicitly
     * here — otherwise getDef() returns the raw key.
     *
     * @param int $unreadable Offers set aside because their price could not be read
     * @param string $assumedCurrency Non-empty when the unit was assumed rather than established
     * @param int $installments Offers set aside because they quote a monthly payment
     */
    private function buildHtmlEncart(array $internal, array $comparison, string $synthesisText, int $unreadable = 0, string $assumedCurrency = '', int $installments = 0): string
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
                   . htmlspecialchars($language->getDef('text_rag_price_bound_notice', ['bound' => (int) ($priceBound['bound_percent'] ?? NumericBandFilter::BOUND_PERCENT), 'excluded' => (int) $priceBound['excluded']]))
                   . "</div>";
        }

        // An aggregate names its population: say what was set aside, do not let the reader count.
        if ($unreadable > 0) {
            $html .= "<div class='market-analysis-unreadable' style='margin-top:6px; font-size:0.85em; color:#856404; background:#fff3cd; border:1px solid #ffeeba; border-radius:4px; padding:6px 10px;'>⚠️ "
                   . htmlspecialchars($language->getDef('text_rag_price_unreadable_notice', ['unreadable' => $unreadable]))
                   . "</div>";
        }

        if ($installments > 0) {
            $html .= "<div class='market-analysis-installment' style='margin-top:6px; font-size:0.85em; color:#856404; background:#fff3cd; border:1px solid #ffeeba; border-radius:4px; padding:6px 10px;'>⚠️ "
                   . htmlspecialchars($language->getDef('text_rag_price_installment_notice', ['installments' => $installments]))
                   . "</div>";
        }

        // The unit was assumed, not established: say it rather than let the figures pass for measured.
        if ($assumedCurrency !== '') {
            $html .= "<div class='market-analysis-assumed' style='margin-top:6px; font-size:0.85em; color:#856404; background:#fff3cd; border:1px solid #ffeeba; border-radius:4px; padding:6px 10px;'>⚠️ "
                   . htmlspecialchars($language->getDef('text_rag_market_analysis_currency_assumed', ['currency' => $assumedCurrency]))
                   . "</div>";
        }

        $html .= "<div class='market-analysis-footer' style='margin-top:8px; font-size:0.8em; color:#6c757d;'>";
        $html .= "🤖 " . htmlspecialchars($aiNotice) . " — " . $count . " " . htmlspecialchars($sourcesLbl);
        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }
}
