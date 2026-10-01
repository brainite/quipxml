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
use QuipXml\Xml\QuipXmlFormatter;

/**
 * Writes a tree loaded by QuipCalendar back out as iCalendar text.
 *
 * Before writing it repairs the tree in place: a VEVENT without a DTSTART
 * gets the current time, one whose DTEND is not after its DTSTART gets a new
 * DTEND, and the calendar UID is shortened to fit one line (setting
 * `fix_uid_length`, default TRUE). A DT* property without parameters is
 * converted from the calendar's X-WR-TIMEZONE to UTC, in the output and in
 * the tree. Lines are folded at 75 octets, except SUMMARY, which is cut to
 * one line for Outlook.
 */
class QuipCalendarIcsFormatter extends QuipXmlFormatter {

  /**
   * Creates a formatter.
   *
   * @param array<string, bool>|null $settings
   *   Settings that override the defaults, including `fix_uid_length`.
   */
  public function __construct(?array $settings = NULL) {
    $this->settings = array_merge($this->settings, [
      'fix_uid_length' => TRUE,
    ], (array) $settings);
  }

  /**
   * Escapes a value for an iCalendar property.
   *
   * @param string $text
   *   The value as html() reads it, with `<` and `>` as entities.
   *
   * @return string
   *   The value with backslashes, line breaks, commas and semicolons escaped.
   */
  public static function escape(string $text): string {
    static $cleantr = [
      '&lt;' => '<',
      '&gt;' => '>',
      '\\' => '\\\\',
      "\n" => '\\n',
      "\r" => '\\r',
      "," => '\\,',
      ";" => '\\;',
    ];
    return strtr($text, $cleantr);
  }

  /**
   * Gives every VEVENT a DTSTART and a DTEND after it.
   *
   * @param \QuipXml\Xml\QuipXmlElement $xml
   *   Any node of the calendar document; every VEVENT in it is fixed.
   *
   * @return $this
   *   The formatter.
   */
  protected function fixDtEnd(QuipXmlElement &$xml): static {
    foreach ($xml->qxpath('//vevent') as $vevent) {
      $dtstart = (string) $vevent->dtstart;
      $dtend = (string) $vevent->dtend;
      if (empty($dtstart)) {
        $vevent->dtstart = date('Ymd\THis', time());
      }
      if ($dtend <= $dtstart) {
        // strtotime() of an empty DTEND is FALSE, which counts as 0.
        $vevent->dtend = date('Ymd\THis', (int) strtotime($dtend) + 60);
      }
    }

    return $this;
  }

  /**
   * Shortens the calendar UID to fit on one 75-octet line.
   *
   * @param \QuipXml\Xml\QuipXmlElement $xml
   *   Any node of the calendar document.
   *
   * @return $this
   *   The formatter.
   */
  protected function fixUid(QuipXmlElement &$xml): static {
    if ($this->settings['fix_uid_length']) {
      // Limit uid length to 71 (75-char line minus "UID:")
      $uid = $xml->qxpath('/iCalendar/vcalendar/uid');
      $val = (string) $uid->html();
      if (strlen($val) > 71) {
        if (strpos($val, '@') === FALSE) {
          $val = substr($val, 0, 71);
        }
        else {
          $val = explode('@', $val, 2);
          $val = substr($val[0], 0, 70 - max(0, strlen($val[1]))) . '@'
            . substr($val[1], 0, 70);
        }
        $uid->html($val);
      }
    }

    return $this;
  }

  /**
   * Converts a date-time from the calendar's timezone to UTC.
   *
   * @param string $value
   *   The date-time; one ending in `Z` is already UTC and is kept.
   * @param \QuipXml\Xml\QuipXmlElement $xml
   *   Any node of the calendar document, to read X-WR-TIMEZONE from.
   *
   * @return string
   *   The date-time in UTC (`Ymd\THis\Z`), or as given when the calendar has
   *   no X-WR-TIMEZONE.
   */
  protected function getDateTime(string $value, QuipXmlElement &$xml): string {
    // Get the timezone.
    $tz = $utc = NULL;
    $tz_name = trim((string) $xml->qxpath('//x-wr-timezone')->html());
    if ($tz_name !== '') {
      $tz = new \DateTimeZone($tz_name);
      $utc = new \DateTimeZone('UTC');
    }

    // Apply the timezone shift to use UTC.
    if (substr($value, -1) !== 'Z' && isset($tz, $utc)) {
      $dt = new \DateTime($value, $tz);
      $dt->setTimezone($utc);
      $value = $dt->format('Ymd\THis\Z');
    }

    return $value;
  }

  /**
   * Formats a calendar component, including its BEGIN and END lines.
   *
   * @param \SimpleXMLElement $xml
   *   The component, typically the `<vcalendar>` element; a plain
   *   SimpleXMLElement is wrapped as a Quip node of the same document.
   *
   * @return string
   *   The iCalendar text, with CRLF line endings.
   */
  public function getFormattedOuter(\SimpleXMLElement $xml): string {
    $quip = $xml instanceof QuipXmlElement ? $xml : Quip::load($xml);
    $this->fixUid($quip);
    $this->fixDtEnd($quip);
    return $this->getFormattedRecursiveIterator($quip, $quip->getName());
  }

  /**
   * Formats a node and its children as iCalendar lines.
   *
   * Children are written in three groups: X-…-… properties, then the other
   * properties, then the nested components.
   *
   * @param \QuipXml\Xml\QuipXmlElement $xml
   *   The node.
   * @param string|null $tag
   *   The property or component name; without one only the children are
   *   written.
   *
   * @return string
   *   The iCalendar lines, with CRLF line endings.
   */
  protected function getFormattedRecursiveIterator(QuipXmlElement $xml, ?string $tag = NULL): string {
    static $lf = "\r\n";
    $output = '';

    // Add the attributes.
    $attrs = '';
    foreach ($xml->attributes() ?? [] as $k => $v) {
      $k = strtoupper($k);
      $attrs .= ";$k=$v";
    }

    // Add the children.
    $number_children = 0;
    $override_value = $this->getFormattedTagOverrideChildren($xml, $tag);
    if ($override_value === FALSE) {
      // Sort output by groups: x-*-* properties, then the other properties,
      // then the items with children.
      $output2 = $output3 = '';
      foreach ($xml->children() ?? [] as $child) {
        ++$number_children;
        $tmp = $this->getFormattedRecursiveIterator($child, $child->getName());
        if (preg_match('@^x-.*-@i', $child->getName())) {
          $output .= $tmp;
        }
        elseif (preg_match('@^BEGIN:@s', $tmp)) {
          $output3 .= $tmp;
        }
        else {
          $output2 .= $tmp;
        }
      }
      $output .= $output2 . $output3;
      $override_value = NULL;
    }

    // Wrap in the tag.
    if (isset($tag) && strlen($tag) > 0) {
      $tag = strtoupper($tag);
      if ($number_children) {
        $output = "BEGIN:$tag$lf{$output}END:$tag$lf";
      }
      else {
        $value = $override_value ?? self::escape((string) $xml->html());
        if (substr($tag, 0, 2) === 'DT' && $attrs === '') {
          $value = $this->getDateTime($value, $xml);
          $xml->html($value);
        }
        $line = "$tag$attrs:" . $value;
        if (strlen($line) <= 75) {
          $output = $line . $lf;
        }
        elseif ($tag === 'SUMMARY') {
          // SUMMARY must be on one line for Outlook 2013
          // https://www.witti.ws/blog/2017/07/23/outlook-ics
          $output = substr($line, 0, 72) . '...' . $lf;
        }
        else {
          $output = substr($line, 0, 75) . $lf;
          $line = substr($line, 75);
          while (strlen($line) > 74) {
            $output .= ' ' . substr($line, 0, 74) . $lf;
            $line = substr($line, 74);
          }
          $output .= " $line$lf";
        }
      }
    }
    return $output;
  }

  /**
   * Returns a property value built from the node's children, if any.
   *
   * A subclass overrides this for structured properties whose parts are
   * child elements, such as a vCard N or ADR.
   *
   * @param \QuipXml\Xml\QuipXmlElement $xml
   *   The node.
   * @param string|null $tag
   *   The node's name as the caller passed it, in lower case.
   *
   * @return string|false
   *   The escaped value, or FALSE to format the children as usual.
   */
  protected function getFormattedTagOverrideChildren(QuipXmlElement $xml, ?string $tag): string|false {
    return FALSE;
  }

  /**
   * Joins the named children's values with semicolons.
   *
   * @param \QuipXml\Xml\QuipXmlElement $xml
   *   The node.
   * @param list<string> $children
   *   The child element names, in output order; a missing one is empty.
   *
   * @return string
   *   The escaped values joined with `;`.
   */
  protected function getFormattedTagOrderedChildren(QuipXmlElement $xml, array $children): string {
    $output = '';
    foreach ($children as $i => $child) {
      if ($i !== 0) {
        $output .= ';';
      }
      $output .= $this->escape((string) $xml->{$child}->html());
    }
    return $output;
  }

}
