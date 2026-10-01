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

namespace QuipXml\Contact;

use QuipXml\Calendar\QuipCalendarIcsFormatter;
use QuipXml\Quip;
use QuipXml\Xml\QuipXmlElement;

/**
 * Writes a card loaded by QuipContact back out as vCard text.
 *
 * Formatting follows QuipCalendarIcsFormatter; the node passed is always
 * wrapped as BEGIN:VCARD … END:VCARD, and an N or ADR whose parts are child
 * elements (`surname`, `given`, … / `pobox`, `street`, …) is written as its
 * semicolon-separated structured value.
 */
class QuipContactVcfFormatter extends QuipCalendarIcsFormatter {

  /**
   * Formats a card, including its BEGIN and END lines.
   *
   * @param \SimpleXMLElement $xml
   *   The `<vcard>` element; a plain SimpleXMLElement is wrapped as a Quip
   *   node of the same document.
   *
   * @return string
   *   The vCard text, with CRLF line endings.
   */
  public function getFormattedOuter(\SimpleXMLElement $xml): string {
    $quip = $xml instanceof QuipXmlElement ? $xml : Quip::load($xml);
    $this->fixUid($quip);
    $this->fixDtEnd($quip);
    return $this->getFormattedRecursiveIterator($quip, 'VCARD');
  }

  /**
   * Builds the structured N and ADR values from their child elements.
   *
   * @param \QuipXml\Xml\QuipXmlElement $xml
   *   The node.
   * @param string|null $tag
   *   The node's name, in lower case.
   *
   * @return string|false
   *   The semicolon-separated value for `adr` and `n`, otherwise FALSE.
   */
  protected function getFormattedTagOverrideChildren(QuipXmlElement $xml, ?string $tag): string|false {
    switch ($tag) {
      case 'adr':
        $children = [
          'pobox',
          'ext',
          'street',
          'locality',
          'region',
          'code',
          'country',
        ];
        return $this->getFormattedTagOrderedChildren($xml, $children);

      case 'n':
        $children = [
          'surname',
          'given',
          'additional',
          'prefix',
          'suffix',
          'suffix',
        ];
        return $this->getFormattedTagOrderedChildren($xml, $children);

      default:
        return FALSE;
    }
  }

}
