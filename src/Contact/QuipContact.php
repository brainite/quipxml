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

namespace QuipXml\Contact;

use QuipXml\Calendar\QuipCalendar;
use QuipXml\Quip;
use QuipXml\Xml\QuipXmlElement;

/**
 * Loads a vCard file into a Quip XML tree.
 *
 * The file is read the way QuipCalendar reads iCalendar, under a `<vcards>`
 * root, so each card is a `<vcard>` element whose children are its
 * properties. This is not an xCard (RFC 6351).
 */
class QuipContact extends QuipCalendar {

  /**
   * Loads a vCard file of one or more cards.
   *
   * @param string $source
   *   The vCard text, or a path or URL to it (with $data_is_url).
   * @param int $options
   *   Accepted for symmetry with Quip::load(); not used.
   * @param bool $data_is_url
   *   Whether $source is a path or URL rather than the text itself.
   * @param string $ns
   *   Accepted for symmetry with Quip::load(); not used.
   * @param bool $is_prefix
   *   Accepted for symmetry with Quip::load(); not used.
   * @param int $quip_options
   *   Accepted for symmetry with Quip::load(); not used.
   *
   * @return \QuipXml\Xml\QuipXmlElement
   *   The `<vcards>` root, holding one `<vcard>` per card.
   */
  public static function loadVcard(string $source, int $options = 0, bool $data_is_url = FALSE, string $ns = '', bool $is_prefix = FALSE, int $quip_options = 0): QuipXmlElement {
    // Get the content.
    if ($data_is_url) {
      $source = (string) file_get_contents($source);
    }

    // Initialize the XML object using an empty vcard.
    // This is NOT an xCard: https://tools.ietf.org/html/rfc6351
    $dom = new \DOMDocument();
    $dom->loadXML('<vcards/>');
    $root = $dom->documentElement;
    if ($root === NULL) {
      throw new \LogicException('The empty card list has no root element.');
    }

    // Strip off the bad white space.
    $source = trim((string) preg_replace("@[\n\r]+@s", "\n", $source));
    $lines = explode("\n", $source);

    // Iterate through the lines.
    $i = 0;
    while (count($lines) > $i) {
      self::loadICalElement($root, $lines, $i);
    }

    return Quip::load($dom);
  }

  /**
   * Initializes an empty contact.
   *
   * @param array<string, string>|null $defaults
   *   Overrides for the card's properties; only `FN` (the formatted name,
   *   default empty) is read.
   *
   * @return \QuipXml\Xml\QuipXmlElement
   *   The `<vcards>` root holding one `<vcard>`.
   */
  public static function loadEmpty(?array $defaults = NULL): QuipXmlElement {
    $defaults = array_merge([
      'FN' => '',
    ], (array) $defaults);
    $ical = implode("\n", [
      'BEGIN:VCARD',
      'VERSION:3.0',
      'PRODID:QuipContact',
      'N:',
      'FN:' . QuipContactVcfFormatter::escape((string) $defaults['FN']),
      'END:VCARD',
    ]);
    return self::loadVcard($ical);
  }

}
