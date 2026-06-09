<?php
/**
 * IntentDetectionPatterns.php
 *
 * DOMAIN-AGNOSTIC pattern definitions for fallback intent detection when LLM is unavailable.
 * This class provides regex-based patterns to detect query intent for routing
 * to appropriate search modes (Mode A, B, or C).
 *
 * MULTI-DOMAIN ARCHITECTURE:
 * - This class is DOMAIN-AGNOSTIC and works across all domains (Ecommerce, HR, Finance, etc.)
 * - Located in Core/ClicShopping/AI/DomainsAI/WebSearch/ (agnostic layer)
 * - No domain-specific logic or dependencies
 * - Reusable across different business contexts
 *
 * @package ClicShopping\AI\DomainsAI\WebSearch\Patterns
 * @since 2026-05-05
 *
 * @deprecated Pattern-based logic superseded by Pure LLM Mode
 *             This is a FALLBACK ONLY mechanism for when LLM fails or is unavailable
 *             Primary intent detection MUST use LLM via IntentRouter
 *             Scheduled for removal in Q3 2026
 *             Use IntentRouter with LLPhant for intent classification instead
 *
 * Requirements: 8.1, 8.2
 */

namespace ClicShopping\AI\DomainsAI\WebSearch\Patterns;

// DEPRECATED: Pattern-based logic superseded by Pure LLM Mode. FALLBACK ONLY. Scheduled for removal in Q3 2026.
// DOMAIN-AGNOSTIC: This class works across all domains (Ecommerce, HR, Finance, Trading, etc.)

class IntentDetectionPatterns
{
  // ============================================================================
  // FALLBACK PATTERNS - ENGLISH ONLY - DOMAIN-AGNOSTIC
  // ============================================================================
  // These patterns are ONLY used when LLM fails or is unavailable
  // Primary intent detection MUST use LLM via IntentRouter (Pure LLM Mode)
  // All patterns use English keywords only (per AGENTS.md requirements)
  //
  // MULTI-DOMAIN ARCHITECTURE:
  // - These patterns are DOMAIN-AGNOSTIC (work for Ecommerce, HR, Finance, etc.)
  // - No domain-specific logic or dependencies
  // - Generic intent detection applicable across business contexts
  // - Domain-specific patterns should be in Apps/AI/{Domain}/Patterns/
  // ============================================================================

  /**
   * Price-related patterns
   * Detects price queries in user input (English keywords only)
   * Indicates Mode B (Google Shopping) intent
   *
   * @var array<string>
   */
  public static array $pricePatterns = [
    '/\b(price|cost|how much)\b/i',
    '/\b(€|EUR|USD|\$|£|GBP|¥|JPY)\s*\d+/i',
    '/\b\d+\s*(€|EUR|USD|\$|£|GBP|¥|JPY)\b/i'
  ];

  /**
   * Shopping-related patterns
   * Detects shopping queries in user input (English keywords only)
   * Indicates Mode B (Google Shopping) intent
   *
   * @var array<string>
   */
  public static array $shoppingPatterns = [
    '/\b(buy|purchase|shop|order|shopping)\b/i',
    '/\b(where to buy|where can i buy)\b/i',
    '/\b(in stock|available)\b/i'
  ];

  /**
   * Comparison-related patterns
   * Detects comparison queries in user input (English keywords only)
   * Indicates Mode B (Google Shopping) or Hybrid mode intent
   *
   * @var array<string>
   */
  public static array $comparisonPatterns = [
    '/\b(compare|comparison|vs|versus)\b/i',
    '/\b(best price|cheapest)\b/i',
    '/\b(better than|worse than)\b/i',
    '/\b(alternative|alternatives|option|options)\b/i'
  ];

  /**
   * Site-specific pattern
   * Extracts target site from query (e.g., "site:<site>.fr")
   * Indicates Mode C (RAG WebSearch) or Hybrid mode intent
   *
   * @var string
   */
  public static string $siteSpecificPattern = '/\bsite:([a-z0-9\-\.]+)\b/i';

  /**
   * Location patterns
   * Detects location in user queries (English keywords, international city/country names)
   * Extracts location for currency/region mapping
   *
   * @var array<string>
   */
  public static array $locationPatterns = [
    '/\b(at|in)\s+([A-Z][a-zA-Z]+(?:\s+[A-Z][a-zA-Z]+)*)\b/',
    '/\b(France|Paris|Lyon|Marseille|Toulouse|Nice|Nantes|Strasbourg|Montpellier|Bordeaux|Lille|Rennes|Reims|Le Havre|Saint-Étienne|Toulon|Grenoble|Dijon|Angers|Nîmes|Villeurbanne|Clermont-Ferrand|Le Mans|Aix-en-Provence|Brest|Tours|Amiens|Limoges|Annecy|Perpignan|Boulogne-Billancourt|Metz|Besançon|Orléans|Saint-Denis|Argenteuil|Rouen|Mulhouse|Montreuil|Caen|Nancy|Tourcoing|Roubaix|Nanterre|Vitry-sur-Seine|Avignon|Créteil|Dunkerque|Poitiers|Asnières-sur-Seine|Courbevoie|Versailles|Colombes|Fort-de-France|Aulnay-sous-Bois|Saint-Paul|Rueil-Malmaison|Aubervilliers|Champigny-sur-Marne|Antibes|La Rochelle|Calais|Cannes|Béziers|Colmar|Bourges|Drancy|Mérignac|Saint-Maur-des-Fossés|Ajaccio|Levallois-Perret|Issy-les-Moulineaux|Noisy-le-Grand|Évry|Villeneuve-d\'Ascq|Neuilly-sur-Seine|Valence|Antony|Cergy|Pessac|Ivry-sur-Seine|Chambéry|Lorient|Montauban|Sarcelles|Niort|Maisons-Alfort|Saint-Quentin|Beauvais|Épinay-sur-Seine|Meaux|Chelles|Cholet|Fréjus|Vannes|Arles|Sartrouville|Hyères|Laval|Belfort|Clamart|Pantin|Bondy|Sevran|Vincennes|Bayonne|Albi|Cagnes-sur-Mer|Grasse|Corbeil-Essonnes|Vénissieux|Clichy|Troyes|Montrouge|Évreux|La Seyne-sur-Mer|Massy|Charleville-Mézières|Brive-la-Gaillarde|Carcassonne|Blois|Châteauroux|Chalon-sur-Saône|Mantes-la-Jolie|Valenciennes|Saint-Herblain|Suresnes|Puteaux|Gennevilliers|Vaulx-en-Velin|Livry-Gargan|Rosny-sous-Bois|Alfortville|Châtenay-Malabry|Villejuif|Fontenay-sous-Bois|Vitrolles|Thionville|Martigues|Aubagne|Salon-de-Provence|Castres|Talence|Wattrelos|Douai|Tarbes|Angoulême|Compiègne|Bourg-en-Bresse|Arras|Boulogne-sur-Mer|Chartres|Melun|Marcq-en-Barœul|Bron|Draguignan|Rezé|Gagny|Joué-lès-Tours|Meyzieu|Saint-Priest|Villefranche-sur-Saône|Savigny-sur-Orge|Conflans-Sainte-Honorine|Montélimar|Haguenau|Cherbourg-en-Cotentin|Vierzon|Lens|Échirolles|Sainte-Geneviève-des-Bois|Schiltigheim|Thonon-les-Bains|Garges-lès-Gonesse|Roanne|Épinal|Villepinte|Bagnolet|Pontault-Combault|Châlons-en-Champagne|Palaiseau|Poissy|Stains|Tremblay-en-France|Cambrai|Mâcon|Nevers|Auxerre|Alès|Liévin|Sainte-Marie|Sète|Istres|Montluçon|Colomiers|Plaisir|Sotteville-lès-Rouen|Franconville|Maubeuge|Villenave-d\'Ornon|Pontoise|Bagneux|Châtellerault|Savigny-le-Temple|Caluire-et-Cuire|Saintes|Hénin-Beaumont|Rillieux-la-Pape|Goussainville|Cagny|Thiais|Pierrefitte-sur-Seine|Chatou|Bezons|Houilles|Romainville|Nogent-sur-Marne|Gonesse|Herblay|Ermont|Athis-Mons|Draveil|Yerres|Ris-Orangis|Grigny|Viry-Châtillon|Montgeron|Brunoy|Limeil-Brévannes|Villeneuve-le-Roi|Ablon-sur-Seine|Orly|Choisy-le-Roi|Thiais|Fresnes|L\'Haÿ-les-Roses|Chevilly-Larue|Rungis|Cachan|Arcueil|Gentilly|Le Kremlin-Bicêtre|Ivry-sur-Seine|Charenton-le-Pont|Saint-Maurice|Joinville-le-Pont|Saint-Mandé|Vincennes|Montreuil|Bagnolet|Les Lilas|Le Pré-Saint-Gervais|Pantin|Aubervilliers|La Courneuve|Saint-Denis|L\'Île-Saint-Denis|Épinay-sur-Seine|Villetaneuse|Pierrefitte-sur-Seine|Stains|Dugny|Le Bourget|Drancy|Bobigny|Bondy|Noisy-le-Sec|Romainville|Les Pavillons-sous-Bois|Livry-Gargan|Clichy-sous-Bois|Montfermeil|Gagny|Villemomble|Rosny-sous-Bois|Neuilly-sur-Marne|Neuilly-Plaisance|Gournay-sur-Marne|Noisy-le-Grand|Bry-sur-Marne|Villiers-sur-Marne|Champigny-sur-Marne|Saint-Maur-des-Fossés|Créteil|Bonneuil-sur-Marne|Sucy-en-Brie|Noiseau|Ormesson-sur-Marne|La Queue-en-Brie|Chennevières-sur-Marne|Le Plessis-Trévise|Villiers-sur-Marne|Boissy-Saint-Léger|Limeil-Brévannes|Valenton|Villeneuve-Saint-Georges|Villeneuve-le-Roi|Ablon-sur-Seine|Athis-Mons|Juvisy-sur-Orge|Viry-Châtillon|Grigny|Ris-Orangis|Évry|Corbeil-Essonnes|Saint-Germain-lès-Corbeil|Soisy-sur-Seine|Draveil|Vigneux-sur-Seine|Montgeron|Brunoy|Yerres|Crosne|Villeneuve-Saint-Georges)\b/i',
    '/\b(USA|United States|US|America|New York|Los Angeles|Chicago|Houston|Phoenix|Philadelphia|San Antonio|San Diego|Dallas|San Jose)\b/i',
    '/\b(UK|United Kingdom|Britain|England|London|Manchester|Birmingham|Leeds|Glasgow|Liverpool|Newcastle|Sheffield)\b/i',
    '/\b(Japan|Tokyo|Osaka|Kyoto|Yokohama|Nagoya|Sapporo|Fukuoka|Kobe)\b/i',
    '/\b(Germany|Deutschland|Berlin|Hamburg|Munich|München|Cologne|Köln|Frankfurt|Stuttgart|Düsseldorf|Dortmund|Essen|Leipzig|Bremen|Dresden|Hanover|Hannover)\b/i',
    '/\b(Spain|España|Madrid|Barcelona|Valencia|Seville|Sevilla|Zaragoza|Málaga|Murcia|Palma|Las Palmas|Bilbao)\b/i',
    '/\b(Italy|Italia|Rome|Roma|Milan|Milano|Naples|Napoli|Turin|Torino|Palermo|Genoa|Genova|Bologna|Florence|Firenze|Bari|Catania|Venice|Venezia|Verona)\b/i'
  ];

  /**
   * Market research patterns
   * Detects market research queries in user input (English keywords only)
   * Indicates Mode A (AI Overview) intent
   *
   * @var array<string>
   */
  public static array $marketResearchPatterns = [
    '/\b(trend|trends|trending)\b/i',
    '/\b(market|industry|sector)\b/i',
    '/\b(analysis|research|study)\b/i',
    '/\b(overview|summary|synthesis)\b/i'
  ];

  /**
   * Product discovery patterns
   * Detects product discovery queries in user input (English keywords only)
   * Indicates Mode A (AI Overview) intent
   *
   * @var array<string>
   */
  public static array $productDiscoveryPatterns = [
    '/\b(what is|what are)\b/i',
    '/\b(tell me about|explain|describe)\b/i',
    '/\b(features|specifications|specs)\b/i',
    '/\b(benefits|advantages|pros|cons)\b/i'
  ];

  /**
   * Detect intent from query using pattern matching
   *
   * ⚠️ DEPRECATED: This is a FALLBACK ONLY mechanism for Pure LLM Mode
   * Primary intent detection MUST use LLM via IntentRouter
   * This method is only called when LLM fails or is unavailable
   *
   * Returns structured intent array compatible with IntentRouter.
   *
   * @deprecated Use IntentRouter with LLPhant for LLM-based intent detection
   * @param string $query User query
   * @return array Intent structure with keys: product, intent, location, target_site, mode_hint
   */
  public static function detectIntent(string $query): array
  {
    $intent = [
      'product' => self::extractProduct($query),
      'intent' => 'product_discovery', // Default
      'location' => self::extractLocation($query),
      'target_site' => self::extractTargetSite($query),
      'mode_hint' => null,
      'confidence' => 0.5, // Pattern-based has lower confidence than LLM
      'detection_method' => 'pattern_fallback'
    ];

    // Check for site-specific query (highest priority)
    if ($intent['target_site'] !== null) {
      $intent['intent'] = 'market_research';
      $intent['mode_hint'] = 'mode_c'; // RAG WebSearch
      $intent['confidence'] = 0.7;
      return $intent;
    }

    // Check for price/shopping intent
    $hasPriceIntent = self::matchesAnyPattern($query, self::$pricePatterns);
    $hasShoppingIntent = self::matchesAnyPattern($query, self::$shoppingPatterns);
    $hasComparisonIntent = self::matchesAnyPattern($query, self::$comparisonPatterns);

    if ($hasPriceIntent || $hasShoppingIntent || $hasComparisonIntent) {
      $intent['intent'] = 'price_comparison';
      $intent['mode_hint'] = 'mode_b'; // Google Shopping
      $intent['confidence'] = 0.7;
      return $intent;
    }

    // Check for market research intent
    if (self::matchesAnyPattern($query, self::$marketResearchPatterns)) {
      $intent['intent'] = 'market_research';
      $intent['mode_hint'] = 'mode_a'; // AI Overview
      $intent['confidence'] = 0.6;
      return $intent;
    }

    // Check for product discovery intent
    if (self::matchesAnyPattern($query, self::$productDiscoveryPatterns)) {
      $intent['intent'] = 'product_discovery';
      $intent['mode_hint'] = 'mode_a'; // AI Overview
      $intent['confidence'] = 0.6;
      return $intent;
    }

    // Default: product discovery with Mode A
    $intent['mode_hint'] = 'mode_a';
    return $intent;
  }

  /**
   * Extract location from query
   *
   * @param string $query User query
   * @return string|null Detected location or null
   */
  public static function extractLocation(string $query): ?string
  {
    foreach (self::$locationPatterns as $pattern) {
      if (preg_match($pattern, $query, $matches)) {
        // Return the captured location (group 2 for preposition patterns, group 0 for direct patterns)
        return isset($matches[2]) ? trim($matches[2]) : trim($matches[0]);
      }
    }

    return null;
  }

  /**
   * Extract target site from query
   *
   * Looks for "site:domain.com" pattern in query.
   *
   * @param string $query User query
   * @return string|null Detected site domain or null
   */
  public static function extractTargetSite(string $query): ?string
  {
    if (preg_match(self::$siteSpecificPattern, $query, $matches)) {
      return strtolower(trim($matches[1]));
    }

    return null;
  }

  /**
   * Extract product name from query
   *
   * Simple extraction: remove common words and patterns.
   * For better extraction, use LLM via IntentRouter.
   *
   * @param string $query User query
   * @return string Extracted product name
   */
  private static function extractProduct(string $query): string
  {
    // Remove site: pattern
    $product = preg_replace(self::$siteSpecificPattern, '', $query);

    // Remove location prepositions (English only for internal processing)
    $product = preg_replace('/\b(at|in)\s+[A-Z][a-zA-Z]+/i', '', $product);

    // Remove common question words (English only for internal processing)
    $product = preg_replace('/\b(what is|where|how much|tell me about)\b/i', '', $product);

    // Remove price/shopping keywords (English only for internal processing)
    $product = preg_replace('/\b(price|cost|buy|purchase|compare)\b/i', '', $product);

    // Clean up whitespace
    $product = preg_replace('/\s+/', ' ', $product);
    $product = trim($product);

    return $product ?: 'unknown';
  }

  /**
   * Check if query matches any pattern in array
   *
   * @param string $query User query
   * @param array<string> $patterns Array of regex patterns
   * @return bool True if any pattern matches
   */
  private static function matchesAnyPattern(string $query, array $patterns): bool
  {
    foreach ($patterns as $pattern) {
      if (preg_match($pattern, $query)) {
        return true;
      }
    }

    return false;
  }
}
