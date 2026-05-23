<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Common;

use ClicShopping\OM\HTML;
use function strlen;
/**
 * Class HTMLOverrideCommon
 *
 * Provides methods to process and manipulate HTML content. It extends the `HTML` class
 * and adds functionality to strip HTML tags, clean and minify HTML, and minify JavaScript code.
 */
class HTMLOverrideCommon extends HTML
{
  /**
   * Removes invisible characters from a given text.
   *
   * @param string $text The text to clean.
   * @return string The cleaned text without invisible characters.
   */
  public static function removeInvisibleCharacters(string|null $text): string|null
  {
    if (!is_null($text)) {
      // List of invisible characters to remove (non-breaking spaces, zero-width characters, etc.)
      $invisibleChars = [
        "\u{200B}", // Zero Width Space
        "\u{200C}", // Zero Width Non-Joiner
        "\u{200D}", // Zero Width Joiner
        "\u{200E}", // Left-to-Right Mark
        "\u{200F}", // Right-to-Left Mark
        "\u{00A0}", // Non-breaking space
        "\u{202F}", // Narrow non-breaking space
        "\u{2060}", // Word joiner
        "\u{2028}", // Line separator
        "\u{2029}", // Paragraph separator
      ];

      foreach ($invisibleChars as $char) {
        $text = preg_replace('/' . preg_quote($char, '/') . '/u', '', $text);
      }
    }

    return $text;
  }

  /**
   * Cleans an HTML string by removing tags, JavaScript, and HTML entities.
   *
   * @param string $html The HTML content to clean.
   * @param int|null $maxLength Maximum length of the cleaned text.
   * @return string Cleaned and optionally truncated text.
   */
  public static function cleanHtmlOptimized(string $html, ?int $maxLength = null): string
  {
    // Supprime les balises <script> et <style>
    $clean = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
    $clean = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $clean);

    // Supprime toutes les autres balises HTML
    $clean = strip_tags($clean);

    // Décodage des entités HTML pour récupérer du texte lisible
    $clean = html_entity_decode($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $clean = self::removeInvisibleCharacters($clean);
    // Normalisation des espaces
    $clean = preg_replace('/\s+/', ' ', trim($clean));

    // Tronquer si une longueur max est spécifiée
    if ($maxLength !== null && mb_strlen($clean, 'UTF-8') > $maxLength) {
      $clean = mb_substr($clean, 0, $maxLength - 3, 'UTF-8') . '...';
    }

    // Sécurisation XSS (pour affichage web)
    return htmlspecialchars($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');
  }

  /**
   * Cleans an HTML text by removing unnecessary content for embedding.
   * - Removes scripts, styles, images, iframes, external links.
   * - Retains only the text useful for indexing and searching.
   *
   * @param string $html The HTML content to clean.
   * @return string Cleaned and structured text for embedding.
   */
 public static function cleanHtmlForEmbedding(string $html): string
 {
     if (empty($html)) {
         return '';
     }

     // Pre-process: Convert common encoded characters
     $html = str_replace(['&nbsp;', '&amp;', '&quot;', '&lt;', '&gt;'], [' ', '&', '"', '<', '>'], $html);

     // Preserve meaningful line breaks before stripping tags
     $html = str_replace(['</p>', '</div>', '</h1>', '</h2>', '</h3>', '</h4>', '</h5>', '</h6>', '<br>', '<br/>', '<hr>'], "\n", $html);

     // Strip all non-content elements while preserving meaningful structure
     $clean = preg_replace([
         // Remove scripts, styles and other non-content elements
         '/<(script|style|iframe|object|embed|noscript|svg|canvas|meta|link|form|button|input|select|textarea)[^>]*>.*?<\/\1>/is',
         // Remove images but preserve alt text
         '/<img[^>]*alt=["\']([^"\']*)["\'][^>]*>/i',
         // Replace links with their text content
         '/<a\b[^>]*>(.*?)<\/a>/i',
         // Remove all remaining HTML tags
         '/<[^>]*>/'
     ], [
         '',
         '$1',
         '$1',
         ' '
     ], $html);

     // Convert HTML entities and clean text
     $clean = html_entity_decode($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');
     $clean = self::removeInvisibleCharacters($clean);

     // Normalize whitespace while preserving meaningful breaks
     $clean = preg_replace('/\s*\n\s*/', "\n", $clean);
     $clean = preg_replace('/[ \t]+/', ' ', $clean);
     $clean = preg_replace('/\n{3,}/', "\n\n", $clean);

     // Keep only valid text characters while preserving structure
     $clean = preg_replace('/[^\p{L}\p{N}\p{P}\s]/u', '', $clean);

     return trim($clean);
 }

  /**
   * Cleans HTML text for SEO by removing harmful tags and normalizing the content.
   *
   * @param string $html The HTML content to clean.
   * @return string Cleaned and optimized text for SEO.
   */
  public static function cleanHtmlForSEO(string $html): string
  {
    // Supprime les balises nuisibles au SEO (scripts, styles, iframes, objets, boutons)
    $clean = preg_replace('/<(script|style|iframe|object|embed|noscript|svg|canvas|meta|link|button|form|input|select|textarea)[^>]*>.*?<\/\1>/is', '', $html);

    // Supprime les balises <img> (images)
    $clean = preg_replace('/<img[^>]*>/i', '', $clean);

    // Supprime les balises <a> mais garde le texte du lien
    $clean = preg_replace('/<a\b[^>]*>(.*?)<\/a>/i', '\1', $clean);

    // Supprime toutes les autres balises HTML
    $clean = strip_tags($clean);

    // Décodage des entités HTML
    $clean = html_entity_decode($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $clean = self::removeInvisibleCharacters($clean);
    // Supprime uniquement les caractères spéciaux non pertinents (évite de supprimer - , / |)
    $clean = preg_replace('/[^\p{L}\p{N}\s,\/|.-]/u', '', $clean);

    // Normalisation des espaces
    $clean = preg_replace('/\s+/', ' ', trim($clean));

    return $clean;
  }

  /**
   * Minifies the given HTML by removing unnecessary whitespaces and optimizing formatting while preserving functionality.
   *
   * @param string $input The HTML string to be minified.
   * @return string The minified HTML string.
   */
  public static function getMinifyHtml(string $input)
  {
    if (trim($input) === '') return $input;
    // Remove extra white-space(s) between HTML attribute(s)
    $input = preg_replace_callback('#<([^\/\s<>!]+)(?:\s+([^<>]*?)\s*|\s*)(\/?)>#s', function ($matches) {
      return '<' . $matches[1] . preg_replace('#([^\s=]+)(\=([\'"]?)(.*?)\3)?(\s+|$)#s', ' $1$2', $matches[2]) . $matches[3] . '>';
    }, str_replace("\r", "", $input));

    if (str_contains($input, '</script>')) {
      $input = preg_replace_callback('#<script(.*?)>(.*?)</script>#is', function ($matches) {
        return '<script' . $matches[1] . '>' . static::getMinifyJS($matches[2]) . '</script>';
      }, $input);
    }

    $array_string = [
      // t = text
      // o = tag open
      // c = tag close
      // Keep important white-space(s) after self-closing HTML tag(s)
      '#<(img|input)(>| .*?>)#s',
      // Remove a line break and two or more white-space(s) between tag(s)
      '#(<!--.*?-->)|(>)(?:\n*|\s{2,})(<)|^\s*|\s*$#s',
      '#(<!--.*?-->)|(?<!\>)\s+(<\/.*?>)|(<[^\/]*?>)\s+(?!\<)#s', // t+c || o+t
      '#(<!--.*?-->)|(<[^\/]*?>)\s+(<[^\/]*?>)|(<\/.*?>)\s+(<\/.*?>)#s', // o+o || c+c
      '#(<!--.*?-->)|(<\/.*?>)\s+(\s)(?!\<)|(?<!\>)\s+(\s)(<[^\/]*?\/?>)|(<[^\/]*?\/?>)\s+(\s)(?!\<)#s', // c+t || t+o || o+t -- separated by long white-space(s)
      '#(<!--.*?-->)|(<[^\/]*?>)\s+(<\/.*?>)#s', // empty tag
      '#<(img|input)(>| .*?>)<\/\1>#s', // reset previous fix
      '#(&nbsp;)&nbsp;(?![<\s])#', // clean up ...
      '#(?<=\>)(&nbsp;)(?=\<)#', // --ibid
      // Remove HTML comment(s) except IE comment(s)
      '#\s*<!--(?!\[if\s).*?-->\s*|(?<!\>)\n+(?=\<[^!])#s'
    ];

    $array_replace = [
      '<$1$2</$1>',
      '$1$2$3',
      '$1$2$3',
      '$1$2$3$4$5',
      '$1$2$3$4$5$6$7',
      '$1$2$3',
      '<$1$2',
      '$1 ',
      '$1',
      ""
    ];

    return preg_replace($array_string, $array_replace, $input);
  }

  /**
   * Normalizes a string for use as an atomic key in embeddings.
   * Converts to lowercase, replaces spaces and accented characters.
   *
   * @param string $text The text to normalize.
   * @return string The normalized text in snake_case format.
   */
  public static function normalizeForAtomicKey(string $text): string
  {
    // Convert to lowercase
    $normalized = strtolower($text);
    
    // Replace accented characters with their base equivalents
    $accents = [
      'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
      'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
      'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
      'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
      'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
      'ý' => 'y', 'ÿ' => 'y',
      'ñ' => 'n', 'ç' => 'c'
    ];
    
    $normalized = str_replace(array_keys($accents), array_values($accents), $normalized);
    
    // Replace spaces and special characters with underscores
    $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized);
    
    // Remove leading/trailing underscores
    $normalized = trim($normalized, '_');
    
    return $normalized;
  }

  /**
   * Clean HTML entities from JSON response
   * 
   * Specifically designed for cleaning LLM JSON responses that may contain:
   * - Malformed entities without semicolon (&quot instead of &quot;)
   * - Standard HTML entities (&nbsp;, &amp;, etc.)
   * - Mixed encoding issues
   * 
   * Used by ClassificationEngine for cleaning GPT JSON responses.
   * 
   * @param string $text Text to clean (typically JSON string)
   * @return string Cleaned text with entities decoded
   */
  public static function cleanJsonEntities(string $text): string
  {
    // First pass: Handle malformed entities without semicolon using regex
    // This MUST come before str_replace to catch patterns like &quot:
    $cleaned = preg_replace('/&quot(?![a-z0-9;])/i', '"', $text);
    $cleaned = preg_replace('/&apos(?![a-z0-9;])/i', "'", $cleaned);
    $cleaned = preg_replace('/&amp(?![a-z0-9;])/i', '&', $cleaned);
    $cleaned = preg_replace('/&lt(?![a-z0-9;])/i', '<', $cleaned);
    $cleaned = preg_replace('/&gt(?![a-z0-9;])/i', '>', $cleaned);
    
    // Second pass: Convert common encoded characters with semicolon
    $cleaned = str_replace(
      ['&nbsp;', '&amp;', '&quot;', '&lt;', '&gt;', '&apos;'],
      [' ', '&', '"', '<', '>', "'"],
      $cleaned
    );
    
    // Third pass: Standard html_entity_decode for any remaining well-formed entities
    $cleaned = html_entity_decode($cleaned, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    return $cleaned;
  }

  /**
   * Minifies a block of JavaScript code by removing unnecessary characters such as comments,
   * extra whitespaces, and semicolons. Also converts certain JavaScript notations to more concise formats.
   *
   * @param string $input The JavaScript code to be minified.
   * @return string The minified JavaScript code.
   */
  public static function getMinifyJS(string $input)
  {
    if (trim($input) === '') return $input;

    $array_string = [
      // Remove comment(s)
      '#\s*("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')\s*|\s*\/\*(?!\!|@cc_on)(?>[\s\S]*?\*\/)\s*|\s*(?<![\:\=])\/\/.*(?=[\n\r]|$)|^\s*|\s*$#',
      // Remove white-space(s) outside the string and regex
      '#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\'|\/\*(?>.*?\*\/)|\/(?!\/)[^\n\r]*?\/(?=[\s.,;]|[gimuy]|$))|\s*([!%&*\(\)\-=+\[\]\{\}|;:,.<>?\/])\s*#s',
      // Remove the last semicolon
      '#;+\}#',
      // Minify object attribute(s) except JSON attribute(s). From `{'foo':'bar'}` to `{foo:'bar'}`
      '#([\{,])([\'])(\d+|[a-z_][a-z0-9_]*)\2(?=\:)#i',
      // --ibid. From `foo['bar']` to `foo.bar`
      '#([a-z0-9_\)\]])\[([\'"])([a-z_][a-z0-9_]*)\2\]#i'
    ];

    $array_replace = [
      '$1',
      '$1$2',
      '}',
      '$1$3',
      '$1.$3'
    ];

    return preg_replace($array_string, $array_replace, $input);
  }

  /**
   * Clean JSON response from LLM
   *
   * Removes markdown code blocks and other formatting
   *
   * @param string $response Raw response
   * @return string Cleaned JSON
   */
  public static function cleanJsonResponse(string $response): string
  {
    // Remove markdown code blocks
    $cleaned = preg_replace('/```json\s*/i', '', $response);
    $cleaned = preg_replace('/```\s*$/i', '', $cleaned);

    // Remove leading/trailing whitespace
    $cleaned = trim($cleaned);

    // Decode HTML entities (Gpt::getGptResponse applies htmlspecialchars)
    $cleaned = html_entity_decode($cleaned, ENT_QUOTES, 'UTF-8');

    return $cleaned;
  }

  /**
   * Escape HTML content for JavaScript injection
   * 
   * @param string $html HTML content to escape
   * @return string Escaped content safe for JavaScript
   */
  public static function escapeForJs(string $html): string
  {
    // Remove newlines and escape quotes
    $html = str_replace(["\r\n", "\r", "\n"], '', $html);
    $html = str_replace("'", "\\'", $html);
    $html = str_replace('"', '\\"', $html);
    return $html;
  }
}