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
 * Writes a calendar's events as a FullCalendar JSON event feed.
 *
 * Each VEVENT child of the node passed becomes an object with `title`,
 * `start`, `end` and `uid`, a `location` object (its text as `data`, plus its
 * parameters, `X-` prefix dropped) when it has one, and each X- property
 * under its lower-case name. Times are written in the calendar's
 * X-WR-TIMEZONE with their UTC offset.
 *
 * @link http://fullcalendar.io/docs/event_data/events_array/
 */
class QuipCalendarJsonFeedFormatter extends QuipCalendarIcsFormatter {

  /**
   * @inheritDoc
   */
  public function __construct(?array $settings = NULL) {
    $this->settings = array_merge($this->settings, [
      'fix_uid_length' => TRUE,
    ], (array) $settings);
  }

  /**
   * Converts a date-time to the calendar's timezone.
   *
   * @param string $value
   *   The date-time; one ending in `Z` is UTC, and any other is read in PHP's
   *   default timezone.
   * @param \QuipXml\Xml\QuipXmlElement $xml
   *   Any node of the calendar document, to read X-WR-TIMEZONE from.
   *
   * @return string
   *   The date-time in the calendar's timezone with its offset
   *   (`Ymd\THisO`), or as given when the calendar has no X-WR-TIMEZONE.
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
    if (isset($tz, $utc)) {
      if (substr($value, -1) !== 'Z') {
        $dt = new \DateTime($value);
        $dt->setTimezone($tz);
      }
      else {
        $dt = new \DateTime($value, $utc);
        $dt->setTimezone($tz);
      }
      $value = $dt->format('Ymd\THisO');
    }

    return $value;
  }

  /**
   * Formats the events of a calendar as a JSON array.
   *
   * @param \SimpleXMLElement $xml
   *   The `<vcalendar>` element; a plain SimpleXMLElement is wrapped as a
   *   Quip node of the same document.
   *
   * @return string
   *   The pretty-printed JSON array of events.
   */
  public function getFormattedOuter(\SimpleXMLElement $xml): string {
    $quip = $xml instanceof QuipXmlElement ? $xml : Quip::load($xml);
    $this->fixUid($quip);
    $data = [];
    foreach ($quip->vevent as $vevent) {
      $data[] = $this->getFormattedEventIterator($vevent);
    }
    return (string) json_encode($data, JSON_PRETTY_PRINT);
  }

  /**
   * Builds the feed object for one event.
   *
   * @param \QuipXml\Xml\QuipXmlElement $vevent
   *   The `<vevent>` element.
   *
   * @return array<string, string|array<string, string>>
   *   The event's feed properties.
   */
  private function getFormattedEventIterator(QuipXmlElement $vevent): array {
    $event = [];
    $event['title'] = (string) $vevent->summary->html();
    $event['start'] = $this->getDateTime((string) $vevent->dtstart->html(), $vevent);
    $event['end'] = $this->getDateTime((string) $vevent->dtend->html(), $vevent);
    $event['uid'] = (string) $vevent->uid->html();
    if ($vevent->location) {
      $location = [];
      $location['data'] = trim((string) $vevent->location->html());
      foreach ($vevent->location->attributes() ?? [] as $k => $v) {
        $k = strtolower($k);
        if (substr($k, 0, 2) === 'x-') {
          $k = substr($k, 2);
        }
        $location[$k] = trim((string) $v);
      }
      if ($location['data'] !== '') {
        $event['location'] = $location;
      }
    }

    foreach ($vevent->children() ?? [] as $k => $v) {
      if (stripos($k, 'X-') !== FALSE) {
        $event[$k] = (string) $v->html();
      }
    }
    return $event;
  }

}
