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

namespace QuipXml\Calendar;

use QuipXml\Quip;
use QuipXml\Xml\QuipXmlElement;

/**
 * Loads an iCalendar (RFC 5545) file into a Quip XML tree.
 *
 * Each `BEGIN:X` … `END:X` component becomes an element named `x` in lower
 * case, and each property line becomes a child element named for the property
 * whose text is the value; property parameters (`DTSTART;TZID=…`) become
 * attributes. Folded lines are unfolded and an escaped `\n` becomes a line
 * break; other escapes (`\,`, `\;`) are kept as written. The tree is rooted
 * at an `<iCalendar>` element, so a calendar is read as `$quip->vcalendar`.
 */
class QuipCalendar {

  /**
   * Loads an iCalendar file.
   *
   * @param string $source
   *   The iCalendar text, or a path or URL to it (with $data_is_url).
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
   *   The `<iCalendar>` root, holding one element per top-level component.
   */
  public static function loadIcal(string $source, int $options = 0, bool $data_is_url = FALSE, string $ns = '', bool $is_prefix = FALSE, int $quip_options = 0): QuipXmlElement {
    // Get the content.
    if ($data_is_url) {
      $source = (string) file_get_contents($source);
    }

    // Initialize the XML object using an empty iCal element.
    $dom = new \DOMDocument();
    $dom->loadXML('<iCalendar xmlns:xCal="http://ietf.org/rfc/rfcXXXX.txt"></iCalendar>');
    $root = $dom->documentElement;
    if ($root === NULL) {
      throw new \LogicException('The empty calendar has no root element.');
    }

    // Strip off the bad white space.
    $source = trim((string) preg_replace("@[\n\r]+@s", "\n", $source));
    $lines = explode("\n", $source);

    // Iterate through the lines.
    $i = 0;
    while (count($lines) > $i) {
      QuipCalendar::loadICalElement($root, $lines, $i);
    }

    return Quip::load($dom);
  }

  /**
   * Initializes an empty calendar.
   *
   * @param array<string, string>|null $defaults
   *   Overrides for the calendar's `uid`, `name` (X-WR-CALNAME) and
   *   `timezone` (X-WR-TIMEZONE, default Etc/UTC).
   *
   * @return \QuipXml\Xml\QuipXmlElement
   *   The `<iCalendar>` root holding one empty `<vcalendar>`.
   */
  public static function loadEmpty(?array $defaults = NULL): QuipXmlElement {
    $uid = function_exists('uuid_create') ? uuid_create() : uniqid('quip-cal-');
    $defaults = array_merge([
      'uid' => $uid,
      'name' => 'New Calendar',
      'timezone' => 'Etc/UTC',
    ], (array) $defaults);
    $ical = implode("\n", [
      'BEGIN:VCALENDAR',
      'VERSION:2.0',
      'PRODID:-//Brainite//QuipCalendar 1.0//EN',
      'CALSCALE:GREGORIAN',
      'UID:' . $defaults['uid'],
      'X-WR-CALNAME:' . $defaults['name'],
      'X-WR-TIMEZONE:' . $defaults['timezone'],
      'END:VCALENDAR',
    ]);
    return QuipCalendar::loadIcal($ical);
  }

  /**
   * Reads the next element and its children from the iCalendar lines.
   *
   * @param \DOMNode $xml
   *   The node to add the element to.
   * @param list<string> $lines
   *   The lines of the file.
   * @param int $i
   *   The line to start at; advanced past the lines read.
   *
   * @return int
   *   The status code: 0 when an element was added, 1 at the parent's END.
   */
  // phpcs:ignore Drupal.NamingConventions.ValidFunctionName.ScopeNotCamelCaps -- a public API name that predates the standard; renaming it would break callers.
  public static function loadICalElement(\DOMNode &$xml, array &$lines, int &$i): int {
    // Get the first entry.
    $el = QuipCalendar::getICalEntry($lines, $i);
    $doc = $xml->ownerDocument;
    if ($doc === NULL) {
      throw new \InvalidArgumentException('The node to add to belongs to no document.');
    }

    // A line with no colon yields no entry, and so an empty component that
    // DOM refuses as an element name.
    $component = $el['component'] ?? '';

    // If this is an 'end' entry, then the parent is finished.
    if ($component === 'end') {
      return 1;
    }

    // If it is not a 'begin' entry, then it is a one-line "element".
    if ($component !== 'begin') {
      $node = $doc->createElement($component, str_replace('&', '&amp;', $el['content'] ?? ''));
      foreach ($el as $k => $v) {
        if (($k != 'component') && ($k != 'content')) {
          $node->setAttribute(strtolower($k), $v);
        }
      }
      $xml->appendChild($node);
      return 0;
    }

    // Start the new node.
    $node = $doc->createElement(strtolower($el['content']));
    if ($node === FALSE) {
      throw new \DOMException('The component name is not a valid element name.');
    }
    foreach ($el as $k => $v) {
      if (($k != 'component') && ($k != 'content')) {
        $node->setAttribute(strtolower($k), $v);
      }
    }

    // Add children until the 'end' entry.
    while (QuipCalendar::loadICalElement($node, $lines, $i) !== 1) {
      // Each pass adds one child.
    }

    $xml->appendChild($node);
    return 0;
  }

  /**
   * Reads the next property, unfolding its continuation lines.
   *
   * @param list<string> $lines
   *   The lines of the file.
   * @param int $i
   *   The line to start at; advanced past the lines read.
   *
   * @return array<string, string>
   *   The `component` (property name, lower case), the `content` (value) and
   *   one entry per parameter keyed by its lower-case name; empty when the
   *   line has no colon.
   */
  // phpcs:ignore Drupal.NamingConventions.ValidFunctionName.ScopeNotCamelCaps -- a public API name that predates the standard; renaming it would break callers.
  public static function getICalEntry(array &$lines, int &$i): array {
    // If there is no colon, then stop now. The cast keeps the coercion of a
    // line past the end that predates strict types.
    if (strpos((string) $lines[$i], ':') === FALSE) {
      $i++;
      return [];
    }

    // Initialize the element.
    $el = [];

    // Read the entire entry -- second lines start with blank space.
    $line = $lines[$i++];
    while (count($lines) > $i && substr($lines[$i], 0, 1) == ' ') {
      $line .= substr($lines[$i++], 1);
    }

    // Replace special characters.
    $line = (string) preg_replace('/\\\\n/', "\n", $line);

    // Read the key/val pair.
    [$k, $content] = explode(':', $line, 2);

    // If there is a semi-colon, get the parameters.
    if (strpos($k, ';') !== FALSE) {
      $params = explode(';', $k);
      $el['component'] = strtolower((string) array_shift($params));
      while (count($params)) {
        $arr = explode('=', (string) array_pop($params), 2);
        // The cast keeps the coercion of a parameter with no "=" that
        // predates strict types.
        $el[strtolower($arr[0])] = (string) $arr[1];
      }
    }
    else {
      $el['component'] = strtolower($k);
    }

    $el['content'] = $content;

    return $el;
  }

}
