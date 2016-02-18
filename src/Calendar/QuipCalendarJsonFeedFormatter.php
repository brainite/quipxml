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

    //     // Add the attributes
    //     $attrs = '';
    //     foreach ($xml->attributes() as $k => $v) {
    //       $k = strtoupper($k);
    //       $attrs .= ";$k=$v";
    //     }

    //     // Add the children.
    //     $number_children = 0;
    //     foreach ($xml->children() as $child) {
    //       ++$number_children;
    //       $output .= $this->getFormattedRecursiveIterator($child, $child->getName());
    //     }

    //     // Wrap in the tag.
    //     if (isset($tag) && strlen($tag) > 0) {
    //       $tag = strtoupper($tag);
    //       if ($number_children) {
    //         $output = "BEGIN:$tag$lf{$output}END:$tag$lf";
    //       }
    //       else {
    //         $value = strtr($xml->html(), $cleantr);
    //         if (substr($tag, 0, 2) === 'DT' && $attrs === '') {
    //           // Get the timezone.
    //           $tz = NULL;
    //           $tz_name = trim($xml->xpath('//x-wr-timezone')->html());
    //           if ($tz_name !== '') {
    //             $tz = new \DateTimeZone($tz_name);
    //             $utc = new \DateTimeZone("UTC");
    //           }

    //           // Apply the timezone shift to use UTC.
    //           if (substr($value, -1) !== 'Z' && isset($tz)) {
    //             $dt = new \DateTime($value, $tz);
    //             $dt->setTimezone($utc);
    //             $value = $dt->format('Ymd\THis\Z');
    //           }
    //         }
    //         $line = "$tag$attrs:" . $value;
    //         if (strlen($line) <= 75) {
    //           $output = $line . $lf;
    //         }
    //         else {
    //           $output = substr($line, 0, 75) . $lf;
    //           $line = substr($line, 75);
    //           while (strlen($line) > 74) {
    //             $output .= ' ' . substr($line, 0, 74) . $lf;
    //             $line = substr($line, 74);
    //           }
    //           $output .= " $line$lf";
    //         }
    //       }
    //     }
    return $event;
  }

}
