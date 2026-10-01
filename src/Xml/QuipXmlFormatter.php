<?php

/*
 * This file is part of the QuipXml package.
 *
 * (c) Greg Payne <1994413+stackpr@users.noreply.github.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace QuipXml\Xml;

/**
 * Serializes a node as indented XML for html() and htmlOuter().
 *
 * Settings: `formatOutput` indents (default TRUE); `preserveWhitespace` keeps
 * the source's whitespace text nodes, which stops the indenting (default
 * FALSE); `openingTag` keeps the `<?xml …?>` declaration (default FALSE).
 * Carriage returns are dropped and a non-breaking space is written `&nbsp;`.
 */
class QuipXmlFormatter {

  /**
   * The formatting settings.
   *
   * @var array<string, bool>
   */
  protected array $settings = [
    'preserveWhitespace' => FALSE,
    'formatOutput' => TRUE,
    'openingTag' => FALSE,
  ];

  /**
   * Creates a formatter.
   *
   * @param array<string, bool>|null $settings
   *   Settings that override the defaults.
   */
  public function __construct(?array $settings = NULL) {
    $this->settings = array_merge($this->settings, (array) $settings);
  }

  /**
   * Formats the children of a node, without the node's own tags.
   *
   * @param \SimpleXMLElement $xml
   *   The node.
   *
   * @return string
   *   Each child's formatted markup, concatenated.
   */
  public function getFormattedInner(\SimpleXMLElement $xml): string {
    $this->settings['openingTag'] = FALSE;
    $str = '';
    foreach ($xml->children() ?? [] as $child) {
      $str .= $this->getFormattedOuter($child);
    }
    return $str;
  }

  /**
   * Formats a node, including its own tags.
   *
   * @param \SimpleXMLElement $xml
   *   The node.
   *
   * @return string
   *   The formatted markup.
   */
  public function getFormattedOuter(\SimpleXMLElement $xml): string {
    $str = (string) $xml->asXML();
    if ($this->settings['formatOutput']) {
      $dom = new \DOMDocument();
      $dom->preserveWhiteSpace = (bool) $this->settings['preserveWhitespace'];
      $dom->formatOutput = TRUE;
      $dom->loadXML($str);
      $str = (string) $dom->saveXML();
    }

    if (!$this->settings['openingTag']) {
      $str = (string) preg_replace('@^<\?xml.*?\?>\s*@s', '', $str);
    }

    return trim(strtr($str, [
      '&#xD;' => '',
      '&#13;' => '',
      "\r\n" => "\n",
      '&#xA0;' => '&nbsp;',
    ]));
  }

}
