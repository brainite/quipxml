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
  const LOAD_NS_UNWRAP = 1;
  const LOAD_NS_STRIP = 2;
  const LOAD_IGNORE_ERRORS = 4;

  static public function formatter($settings = NULL) {
    return new QuipXmlFormatter($settings);
  }

  /**
   * @param mixed $source
   * @param number $options
   * @param bool $data_is_url
   * @param string $ns
   * @param bool $is_prefix
   * @param number $quip_options
   * @throws \ErrorException
   * @throws \Exception
   * @return \QuipXml\Xml\QuipXmlElement
   */
  static public function load($source, $options = 0, $data_is_url = FALSE, $ns = '', $is_prefix = FALSE, $quip_options = 0) {
    set_error_handler(function ($errno, $errstr, $errfile, $errline) {
      throw new \ErrorException($errstr, $errno);
    }, E_WARNING);
    $error_reporting_level = error_reporting();
    error_reporting(E_ERROR | E_WARNING);
    $use_errors = NULL;
    if ($quip_options & self::LOAD_IGNORE_ERRORS) {
      $use_errors = libxml_use_internal_errors(TRUE);
    }
    $return = function () use (&$use_errors, &$error_reporting_level) {
      if (isset($use_errors)) {
        libxml_clear_errors();
        libxml_use_internal_errors($use_errors);
      }
      restore_error_handler();
      error_reporting($error_reporting_level);
    };

    if ($quip_options & self::LOAD_NS_UNWRAP) {
      if ($data_is_url) {
        $source = file_get_contents($source);
        $data_is_url = FALSE;
      }
      $source = preg_replace('@</?[a-z]+:.+?>@si', '', $source);
    }
    elseif ($quip_options & self::LOAD_NS_STRIP) {
      if ($data_is_url) {
        $source = file_get_contents($source);
        $data_is_url = FALSE;
      }
      $source = preg_replace('@<[a-z]+:@si', '<', $source);
      $source = preg_replace('@</[a-z]+:@si', '</', $source);
    }

    try {
      if ($source instanceof \SimpleXMLElement) {
        $dom = dom_import_simplexml($source);
        $return();
        return simplexml_import_dom($dom, '\\QuipXml\\Xml\\QuipXmlElement');
      }

      if ($source instanceof \DOMDocument) {
        $return();
        return simplexml_import_dom($source, '\\QuipXml\\Xml\\QuipXmlElement');
      }

      if (!$data_is_url) {
        $source = strtr($source, array(
          '&nbsp;' => '&#xA0;',
        ));
      }
      $quip = new QuipXmlElement($source, $options, $data_is_url, $ns, $is_prefix);
    }
    catch (\Exception $e) {
      try {
        $data = $data_is_url ? file_get_contents($source) : $source;
        $data = strtr($data, array(
          '&nbsp;' => '&#xA0;',
        ));
        $dom = new \DOMDocument();
        if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
          $dom->loadHTML($data, $options);

          if (preg_match('@<html.*<body@si', $data)) {
            $cursor = &$dom;
          }
          elseif (preg_match('@<body@i', $data)) {
            $cursor = &$dom->getElementsByTagName('body')->item(0);
          }
          else {
            // If there is no single root element, wrap the HTML and try again.
            $children = &$dom->getElementsByTagName('body')->item(0)->childNodes;
            if ($children->count() > 1) {
              return static::load("<div>$data</div>", $options, FALSE, $ns, $is_prefix, $quip_options);
            }

            // hhvm returns the root by default rather than the original imported item.
            $cursor = $children->item(0);
          }
        }
        else {
          $dom->loadHTML($data);
          $cursor = &$dom;
        }
        $quip = simplexml_import_dom($cursor, '\\QuipXml\\Xml\\QuipXmlElement');
      }
      catch (\Exception $e) {
        $return();
        throw $e;
      }
    }
    $return();

    return $quip;
  }

}
