<?php
namespace QuipXml\Calendar;
use QuipXml\Quip;
use QuipXml\Xml\QuipXmlFormatter;

/**
 * Implements the JSON feed as detailed for fullcalendar
 * @link http://fullcalendar.io/docs/event_data/events_array/
 */
class QuipCalendarJsonFeedFormatter extends QuipCalendarIcsFormatter {
  public function __construct($settings = NULL) {
    $this->settings = array_merge($this->settings, array(
      'fix_uid_length' => TRUE,
    ), (array) $settings);
  }

  protected function getDateTime($value, &$xml) {
    // Get the timezone.
    $tz = NULL;
    $tz_name = trim($xml->xpath('//x-wr-timezone')->html());
    if ($tz_name !== '') {
      $tz = new \DateTimeZone($tz_name);
      $utc = new \DateTimeZone("UTC");
    }

    // Apply the timezone shift to use UTC.
    if (isset($tz)) {
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

  public function getFormattedOuter($xml) {
    $this->fixUid($xml);
    $data = array();
    foreach ($xml->vevent as $vevent) {
      $data[] = $this->getFormattedEventIterator($vevent);
    }
    $output = json_encode($data, JSON_PRETTY_PRINT);
    return $output;
  }

  private function getFormattedEventIterator($vevent) {
    $event = array();
    $event['title'] = $vevent->summary->html();
    $event['start'] = $this->getDateTime($vevent->dtstart->html(), $vevent);
    $event['end'] = $this->getDateTime($vevent->dtend->html(), $vevent);
    $event['uid'] = $vevent->uid->html();
    if ($vevent->location) {
      $event['location'] = array();
      $event['location']['data'] = trim($vevent->location->html());
      foreach ($vevent->location->attributes() as $k => $v) {
        $k = strtolower($k);
        if (substr($k, 0, 2) === 'x-') {
          $k = substr($k, 2);
        }
        $event['location'][$k] = trim($v);
      }
      if ($event['location']['data'] === '') {
        unset($event['location']);
      }
    }

    foreach ($vevent->children() as $k => $v) {
      if (stripos($k, 'X-') !== FALSE) {
        $event[$k] = $v->html();
      }
    }
    return $event;
  }

}
