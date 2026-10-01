<?php

namespace QuipXml\Calendar;

use QuipXml\Quip;

/**
 *
 */
class QuipCalendar {

  /**
   *
   * @param unknown $source
   * @param int $options
   * @param string $data_is_url
   * @param string $ns
   * @param string $is_prefix
   * @param int $quip_options
   * @return \QuipXml\Xml\QuipXmlElement
   */
  public static function loadIcal($source, $options = 0, $data_is_url = FALSE, $ns = '', $is_prefix = FALSE, $quip_options = 0) {
    // Get the content.
    if ($data_is_url) {
      $source = file_get_contents($source);
      $data_is_url = FALSE;
    }

    // Initialize the XML object using an empty iCal element.
    $xml = '<iCalendar xmlns:xCal="http://ietf.org/rfc/rfcXXXX.txt"></iCalendar>';
    $dom = new \DOMDocument();
    $dom->loadXML($xml);

    // Strip off the bad white space.
    $source = trim(preg_replace("@[\n\r]+@s", "\n", $source));
    $lines = explode("\n", $source);

    // Iterate through the lines.
    $i = 0;
    while (count($lines) > $i) {
      QuipCalendar::loadICalElement($dom->documentElement, $lines, $i);
    }

    return Quip::load($dom);
  }

  /**
   * Initialize an empty calendar.
   *
   * @param array $defaults
   *
   * @return \QuipXml\Xml\QuipXmlElement
   */
  public static function loadEmpty($defaults = NULL) {
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
   * Get the next element and its children from the iCal array.
   *
   * @param DOMElement The xml node to add to.
   * @param Array The exploded iCal file.
   * @param int The line to start at.
   *
   * @return int The status code (0=success, 1=end of parent).
   */
  public static function loadICalElement(&$xml, &$lines, &$i) {
    // Get the first entry.
    $el = QuipCalendar::getICalEntry($lines, $i);
    $doc = $xml->ownerDocument;

    // If this is an 'end' entry, then the parent is finished.
    if ($el['component'] == 'end') {
      return 1;
    }

    // If it is not a 'begin' entry, then it is a one-line "element".
    if (!isset($el['component']) || $el['component'] !== 'begin') {
      $node = $doc->createElement($el['component'], str_replace('&', '&amp;', $el['content']));
      foreach ($el as $k => $v) {
        if (($k != 'component') && ($k != 'content')) {
          $node->setAttribute(strtolower($k), $v);
        }
      }
      $xml->appendChild($node);
      return 0;
    }

    // Start the new node.
    $tmp = strtolower($el['content']);
    $node = $doc->createElement($tmp);
    foreach ($el as $k => $v) {
      if (($k != 'component') && ($k != 'content')) {
        $node->setAttribute(strtolower($k), $v);
      }
    }

    // Look for the 'end' entry with the right content.
    $entry = [];
    while (TRUE) {
      switch (QuipCalendar::loadICalElement($node, $lines, $i)) {
        // End of parent;.
        case 1:
          break (2);

        // Child was added.
        case 0:
          break;

        // Error.

        default:
          break;
      }
    }

    $xml->appendChild($node);
    return 0;
  }

  /**
   * Get the next key-value pair.
   *
   * @param Array The parsed iCal file.
   * @param int The line to start at.
   *
   * @return Array The key, value and parameters for the next element.
   */
  public static function getICalEntry(&$lines, &$i) {
    // If there is no colon, then stop now.
    if (strpos($lines[$i], ':') === FALSE) {
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
    $line = preg_replace([
      '/\\\\n/'
    ], [
      "\n"
    ], $line);

    // Read the  key/val pair.
    [$k, $content] = explode(':', $line, 2);

    // If there is a semi-colon, get the parameters.
    if (strpos($k, ';') !== FALSE) {
      $params = explode(';', $k);
      $el['component'] = strtolower(array_shift($params));
      while (count($params)) {
        $arr = explode('=', array_pop($params), 2);
        $el[strtolower($arr[0])] = $arr[1];
      }
    }
    else {
      $el['component'] = strtolower($k);
    }

    // Fix the content formatting.
    $content = preg_replace('/\n/', "\n", $content);
    $el['content'] = $content;

    return $el;
  }

}
