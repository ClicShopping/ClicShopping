<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/**
 *
 * In order to minimize the number and size of HTTP requests for CSS content,
 * this script combines multiple CSS files into a single file and compresses
 * it on-the-fly.
 *
 * To use this in your HTML, link to it in the usual way:
 * <link rel="stylesheet" type="text/css" media="screen, print" href="/css/compressed.css.php" />
 */

/* Add your CSS files to this array (THESE ARE ONLY EXAMPLES) */
/* all the css doesn't work fine */

function get_files($root_dir, $all_data = [])
{

// only include files with these extensions
  $allow_extensions = ['css'];
// make any specific files  you want to be excluded
  $ignore_files = ['general/stylesheet.css',
    'general/stylesheet_responsive.css',
    'general/link_general.css',
    'general/link_general_responsive.css',
    'modules_blog_content/modules_blog_content_general.css',
    'modules_boxes/modules_boxes_general.css',
    'modules_checkout_payment/modules_checkout_payment_general.css',
    'modules_checkout_shipping/modules_checkout_shipping_general.css',
    'modules_footer/modules_footer_general.css',
    'modules_front_page/modules_front_page_general.css',
    'modules_header/modules_header_general.css',
    'modules_index_categories/modules_index_categories_general.css',
    'modules_login/modules_login_general.css',
    'modules_products_featured/modules_products_featured_general.css',
    'modules_products_favorites/modules_products_favorites_general.css',
    'modules_products_info/modules_products_info_general.css',
    'modules_products_listing/modules_products_listing_general.css',
    'modules_products_new/modules_products_new_general.css',
    'modules_products_specials/modules_products_specials_general.css',
    'modules_shopping_cart/modules_shopping_cart_general.css',
    'modules_products_search/modules_products_search_general.css',
    'general/bootstrap_customize.css'
  ];

  $ignore_regex = '/^_/';
// skip these directories
  $ignore_dirs = ['.', '..'];

// run through content of root directory
  $dir_content = scandir($root_dir);

  foreach ($dir_content as $key => $content) {
    $path = $root_dir . DIRECTORY_SEPARATOR . $content;
    if (is_file($path) && is_readable($path)) {
// skip ignored files
      if (!\in_array($content, $ignore_files)) {
        if (preg_match($ignore_regex, $content) == 0) {
          $content_chunks = explode(".", $content);
          $ext = $content_chunks[\count($content_chunks) - 1];
// only include files with desired extensions
          if (\in_array($ext, $allow_extensions)) {
// save file name with path
            $all_data[] = $path;
          }
        }
      }
    } // if content is a directory and readable, add path and name
    elseif (is_dir($path) && is_readable($path)) {
      // skip any ignored dirs
      if (!\in_array($content, $ignore_dirs)) {
// recursive callback to open new directory
        $all_data = get_files($path, $all_data);
      }
    }
  } // end foreach
  return $all_data;
} // end get_files()


$root_dir = realpath(__DIR__);

$files_array = get_files($root_dir);
$files_css_replace = str_replace($root_dir . '/', '', $files_array);
$cssFilesaddon = $files_css_replace;

$cssFiles = ['general/stylesheet.css',
  'general/stylesheet_responsive.css',
  'general/link_general.css',
  'general/link_general_responsive.css',
  'modules_blog_content/modules_blog_content_general.css',
  'modules_boxes/modules_boxes_general.css',
  'modules_checkout_payment/modules_checkout_payment_general.css',
  'modules_checkout_shipping/modules_checkout_shipping_general.css',
  'modules_footer/modules_footer_general.css',
  'modules_front_page/modules_front_page_general.css',
  'modules_header/modules_header_general.css',
  'modules_index_categories/modules_index_categories_general.css',
  'modules_login/modules_login_general.css',
  'modules_products_featured/modules_products_featured_general.css',
  'modules_products_favorites/modules_products_favorites_general.css',
  'modules_products_info/modules_products_info_general.css',
  'modules_products_listing/modules_products_listing_general.css',
  'modules_products_new/modules_products_new_general.css',
  'modules_products_specials/modules_products_specials_general.css',
  'modules_shopping_cart/modules_shopping_cart_general.css',
  'modules_products_search/modules_products_search_general.css',
  'general/bootstrap_customize.css'
];

/**
 * Ideally, you wouldn't need to change any code beyond this point.
 */

$cssFiles = array_merge($cssFiles, $cssFilesaddon);


$buffer = "";

foreach ($cssFiles as $cssFile) {
  $buffer .= file_get_contents($cssFile);
}


// Remove comments
$buffer = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $buffer);

// Remove space after colons
$buffer = str_replace(': ', ':', $buffer);

// Remove whitespace
$buffer = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '    ', '    '), '', $buffer);

// Enable GZip encoding.
ob_start("ob_gzhandler");

// Enable caching
header('Cache-Control: public');

$timestamp = time() + 86400;
$tsstring = gmdate('D, d M Y H:i:s ', $timestamp) . 'GMT';
$etag = md5($timestamp);

$if_modified_since = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? false;
$if_none_match = $_SERVER['HTTP_IF_NONE_MATCH'] ?? false;
if ((($if_none_match && $if_none_match === $etag) || (!$if_none_match)) &&
  ($if_modified_since && $if_modified_since === $tsstring)) {
  header('HTTP/1.1 304 Not Modified');
  exit();
} else {
  header("Last-Modified: $tsstring");
  header("ETag: \"{$etag}\"");
}

// Set the correct MIME type, because Apache won't set it for us
header("Content-type: text/css");

// Write everything out
echo $buffer;
