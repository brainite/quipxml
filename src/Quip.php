<?php

/*
 * This file is part of the QuipXml package.
 *
 * (c) Greg Payne
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace QuipXml;

use QuipXml\Encoding\CharacterEncoding;
use QuipXml\Xml\QuipXmlElement;
use QuipXml\Xml\QuipXmlFormatter;

/**
 * Entry point: loads XML or HTML into a QuipXmlElement.
 */
class Quip {

  /**
   * Removes namespaced tags (`<w:p>`), keeping their contents.
   */
  const LOAD_NS_UNWRAP = 1;

  /**
   * Removes the prefix from namespaced tags (`<w:p>` becomes `<p>`).
   */
  const LOAD_NS_STRIP = 2;

  /**
   * Collects libxml parse errors instead of raising them.
   */
  const LOAD_IGNORE_ERRORS = 4;

  /**
   * Creates a formatter for html() and htmlOuter().
   *
   * @param array<string, bool>|null $settings
   *   Overrides for QuipXmlFormatter's settings.
   *
   * @return \QuipXml\Xml\QuipXmlFormatter
   *   The formatter.
   */
  public static function formatter(?array $settings = NULL): QuipXmlFormatter {
    return new QuipXmlFormatter($settings);
  }

  /**
   * Loads XML, falling back to the HTML parser when it is not well formed.
   *
   * Well-formed XML is read by SimpleXML. Anything else is read by
   * DOMDocument::loadHTML(): a full document keeps its `<html>`, a fragment
   * with a `<body>` returns the body, and a fragment of several top-level
   * nodes is wrapped in a `<div>` so it has one root. `&nbsp;` is accepted in
   * both cases.
   *
   * @param string|\SimpleXMLElement|\DOMDocument $source
   *   The markup, a path or URL to it (with $data_is_url), or an existing
   *   SimpleXML node or DOM document to wrap without copying.
   * @param int $options
   *   LIBXML_* options for the parser.
   * @param bool $data_is_url
   *   Whether $source is a path or URL rather than markup.
   * @param string $ns
   *   A namespace prefix or URI to read the document in.
   * @param bool $is_prefix
   *   Whether $ns is a prefix rather than a URI.
   * @param int $quip_options
   *   A bitmask of the Quip::LOAD_* constants.
   *
   * @return \QuipXml\Xml\QuipXmlElement
   *   The root node.
   *
   * @throws \InvalidArgumentException
   *   When the source is empty.
   * @throws \ErrorException
   *   When neither parser can read the source.
   */
  public static function load(string|\SimpleXMLElement|\DOMDocument $source, int $options = 0, bool $data_is_url = FALSE, string $ns = '', bool $is_prefix = FALSE, int $quip_options = 0): QuipXmlElement {
    if ($source instanceof \SimpleXMLElement) {
      return self::import(dom_import_simplexml($source));
    }
    if ($source instanceof \DOMDocument) {
      return self::import($source);
    }

    // Raise parser warnings as exceptions so a failed XML parse can fall
    // back to the HTML parser, and restore the caller's settings afterwards.
    set_error_handler(static function (int $errno, string $errstr): never {
      throw new \ErrorException($errstr, $errno);
    }, E_WARNING);
    $error_reporting_level = error_reporting(E_ERROR | E_WARNING);
    $use_errors = ($quip_options & self::LOAD_IGNORE_ERRORS) ? libxml_use_internal_errors(TRUE) : NULL;

    try {
      if ($data_is_url && ($quip_options & (self::LOAD_NS_UNWRAP | self::LOAD_NS_STRIP))) {
        $source = (string) file_get_contents($source);
        $data_is_url = FALSE;
      }
      if ($quip_options & self::LOAD_NS_UNWRAP) {
        $source = (string) preg_replace('@</?[a-z]+:.+?>@si', '', $source);
      }
      elseif ($quip_options & self::LOAD_NS_STRIP) {
        $source = (string) preg_replace(['@<[a-z]+:@si', '@</[a-z]+:@si'], ['<', '</'], $source);
      }
      if (!$data_is_url && trim($source) === '') {
        throw new \InvalidArgumentException('Quip cannot load an empty document.');
      }

      try {
        $markup = $data_is_url ? $source : strtr($source, ['&nbsp;' => '&#xA0;']);
        return new QuipXmlElement($markup, $options, $data_is_url, $ns, $is_prefix);
      }
      catch (\Exception) {
        $data = strtr($data_is_url ? (string) file_get_contents($source) : $source, ['&nbsp;' => '&#xA0;']);
        return self::loadHtml($data, $options, $ns, $is_prefix, $quip_options);
      }
    }
    finally {
      if ($use_errors !== NULL) {
        libxml_clear_errors();
        libxml_use_internal_errors($use_errors);
      }
      restore_error_handler();
      error_reporting($error_reporting_level);
    }
  }

  /**
   * Reads markup with the HTML parser.
   *
   * @param string $data
   *   The markup.
   * @param int $options
   *   LIBXML_* options for the parser.
   * @param string $ns
   *   Passed on when a fragment is wrapped and loaded again.
   * @param bool $is_prefix
   *   Passed on when a fragment is wrapped and loaded again.
   * @param int $quip_options
   *   Passed on when a fragment is wrapped and loaded again.
   *
   * @return \QuipXml\Xml\QuipXmlElement
   *   The document, the body, or the fragment's single root.
   */
  private static function loadHtml(string $data, int $options, string $ns, bool $is_prefix, int $quip_options): QuipXmlElement {
    $dom = new \DOMDocument();
    try {
      $dom->loadHTML($data, $options);
    }
    catch (\Exception) {
      // Typically an unescaped ampersand; escape it and parse again.
      $data = CharacterEncoding::toHtml($data, [
        'entities_prefer_numeric' => FALSE,
        'escape_ampersand_selective' => TRUE,
      ]);
      $dom->loadHTML($data, $options);
    }

    if (preg_match('@<html.*<body@si', $data)) {
      return self::import($dom);
    }
    $body = $dom->getElementsByTagName('body')->item(0);
    if ($body === NULL) {
      throw new \ErrorException('The HTML parser found no body in the markup.');
    }
    if (preg_match('@<body@i', $data)) {
      return self::import($body);
    }
    // A fragment needs a single root; wrap several top-level nodes in one.
    if ($body->childNodes->count() > 1) {
      return self::load("<div>$data</div>", $options, FALSE, $ns, $is_prefix, $quip_options);
    }
    // The fragment's single top-level node, rather than the body around it.
    $root = $body->childNodes->item(0);
    if ($root === NULL) {
      throw new \ErrorException('The HTML parser found no element in the markup.');
    }
    return self::import($root);
  }

  /**
   * Wraps a DOM node as a QuipXmlElement, sharing its document.
   *
   * @param \DOMNode $node
   *   The node.
   *
   * @return \QuipXml\Xml\QuipXmlElement
   *   The node as a Quip element.
   *
   * @throws \ErrorException
   *   When SimpleXML cannot import the node.
   */
  private static function import(\DOMNode $node): QuipXmlElement {
    $quip = simplexml_import_dom($node, QuipXmlElement::class);
    if (!$quip instanceof QuipXmlElement) {
      throw new \ErrorException('SimpleXML could not import the node.');
    }
    return $quip;
  }

}
