<?php
/*
 * This file is part of the QuipXml package.
 *
 * (c) Greg Payne
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace QuipXml;

use QuipXml\Xml\QuipXmlElement;
use QuipXml\Xml\QuipXmlFormatter;
class Quip {
  static public function formatter($settings = NULL) {
    return new QuipXmlFormatter($settings);
  }

  static public function load($source, $options = 0, $data_is_url = FALSE, $ns = '', $is_prefix = FALSE) {
    try {
      $quip = new QuipXmlElement($source, $options, $data_is_url, $ns, $is_prefix);
    } catch (\Exception $e) {
      $data = $data_is_url ? file_get_contents($source) : $source;
      $dom = new \DOMDocument();
      $dom->loadHTML($data, $options);
      $quip = simplexml_import_dom($dom, '\\QuipXml\\Xml\\QuipXmlElement');
    }
    return $quip;
  }

}