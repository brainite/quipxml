<?php
/*
 * This file is part of the QuipXml package.
 *
 * (c) Greg Payne
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace QuipXml\Xml;
class QuipXmlFormatter {
  protected $settings = array(
    'preserveWhitespace' => FALSE,
    'formatOutput' => TRUE,
    'openingTag' => FALSE,
  );

  public function __construct($settings = NULL) {
    $this->settings = array_merge($this->settings, (array) $settings);
  }

  public function getFormattedInner($xml) {
    $this->settings['openingTag'] = FALSE;
    $str = '';
    foreach ($xml->children() as $child) {
      $str .= $this->getFormattedOuter($child);
    }
    return $str;
  }

  public function getFormattedOuter($xml) {
    if ($this->settings['formatOutput']) {
      $dom = new \DOMDocument();
      $dom->preserveWhiteSpace = $this->settings['preserveWhitespace'];
      $dom->formatOutput = $this->settings['formatOutput'];
      $dom->loadXML($xml->asXml());
      $str = $dom->saveXML();
    }
    else {
      $str = $xml->asXml();
    }

    if (!$this->settings['openingTag']) {
      $str = preg_replace('@^<\?xml.*?\?>\s*@s', '', $str);
    }

    $str = strtr($str, array(
      '&#xD;' => '',
      "\r\n" => "\n",
    ));
    trim($str);
    return $str;
  }

}