<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM;

use XMLWriter;
use function count;
use function is_array;

/**
 * Class providing utility functions to convert between XML and PHP arrays.
 */
class XML
{
  /**
   * Converts an XML input into an associative array.
   *
   * @param mixed $xml The XML data to be converted. It should be an instance of \SimpleXMLElement or a valid XML string.
   * @return mixed The resulting associative array representing the XML structure or a string for CDATA elements.
   */
  public static function toArray($xml)
  {
    if ($xml instanceof \SimpleXMLElement) {
      $attributes = $xml->attributes();

      foreach ($attributes as $k => $v) {
        if ($v) {
          $a[$k] = (string)$v;
        }
      }

      $x = $xml;
      $xml = get_object_vars($xml);
    }

    if (is_array($xml)) {
      if (count($xml) == 0) {
        return (string)$x->__toString(); // for CDATA
      }

      foreach ($xml as $key => $value) {
        $r[$key] = self::toArray($value);
      }

      if (isset($a)) {
        $r['@attributes'] = $a; // attributes
      }

      return $r;
    }

    return (string)$xml;
  }

  /**
   * Converts an associative array into an XML string.
   *
   * @param array $data The data to be converted into XML format.
   * @param string $encoding The character encoding to be used in the XML document. Defaults to 'UTF-8'.
   * @return string The generated XML string.
   */
  public static function fromArray($data, string $encoding = 'UTF-8')
  {
    $xml = new \XMLWriter();
    $xml->openMemory();
    $xml->setIndent(true);
    $xml->setIndentString('  ');
    $xml->startDocument('1.0', $encoding);
    self::_write($xml, $data);
    $xml->endDocument();

    return $xml->outputMemory(true);
  }

  /**
   * Recursively writes data into an XMLWriter object.
   *
   * @param XMLWriter $xml The XMLWriter instance used to build the XML document.
   * @param mixed $data The data to be written into the XML. It can be an associative array or value.
   * @param string|null $parent The name of the parent element, if applicable.
   * @param bool $add_to_parent_element Flag indicating whether elements should be added to a parent element.
   *
   * @return void
   */
  protected static function _write(\XMLWriter $xml, $data, $parent = null, bool $add_to_parent_element = false)
  {
    foreach ($data as $key => $value) {
      if (is_array($value)) {
        if (is_int($key)) {
          if ($add_to_parent_element === false) {
            $add_to_parent_element = true;

            self::_write($xml, $value, $parent, $add_to_parent_element);
            $xml->endElement();
          } else {
            $xml->startElement($parent);
            self::_write($xml, $value, $parent, $add_to_parent_element);
            $xml->endElement();
          }
        } else {
          $xml->startElement($key);
          self::_write($xml, $value, $key, $add_to_parent_element);
          $xml->endElement();
        }
      } else {
        if (($pos = strpos($key, '-CDATA')) !== false) {
          $key = substr($key, 0, $pos);

          $xml->startElement($key);
          $xml->writeCData($value);
          $xml->endElement();
        } else {
          $xml->writeElement($key, $value);
        }
      }
    }
  }
}

