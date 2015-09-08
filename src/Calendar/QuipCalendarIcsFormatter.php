<?php
namespace QuipXml\Calendar;
use QuipXml\Quip;
use QuipXml\Xml\QuipXmlFormatter;
class QuipCalendarIcsFormatter extends QuipXmlFormatter {
  public function __construct($settings = NULL) {
    $this->settings = array_merge($this->settings, array(
      'fix_uid_length' => TRUE,
    ), (array) $settings);
  }

  public function getFormattedOuter($xml) {
    if ($this->settings['fix_uid_length']) {
      // Limit uid length to 71 (75-char line minus "UID:")
      $uid =& $xml->xpath('/iCalendar/vcalendar/uid');
      $val = $uid->html();
      if (strlen($val) > 71) {
        if (strpos($val, '@') === FALSE) {
          $val = substr($val, 0, 71);
        }
        else {
          $val = explode('@', $val, 2);
          $val = substr($val[0], 0, 70 - max(0, strlen($val[1]))) . '@' . substr($val[1], 0, 70);
        }
        $uid->html($val);
      }
    }
    $output = $this->getFormattedRecursiveIterator($xml, 'VCALENDAR');
    return $output;
  }

  private function getFormattedRecursiveIterator($xml, $tag = NULL) {
    $output = '';
    $cleantr = array(
      "\n" => '\\n',
      "\r" => '\\r',
      "," => '\\,',
      ";" => '\\;',
    );
    $lf = "\r\n";

    // Add the attributes
    $attrs = '';
    foreach ($xml->attributes() as $k => $v) {
      $k = strtoupper($k);
      $attrs .= ";$k=$v";
    }

    // Add the children.
    $number_children = 0;
    foreach ($xml->children() as $child) {
      ++$number_children;
      $output .= $this->getFormattedRecursiveIterator($child, $child->getName());
    }

    // Wrap in the tag.
    if (isset($tag) && strlen($tag) > 0) {
      $tag = strtoupper($tag);
      if ($number_children) {
        $output = "BEGIN:$tag$lf{$output}END:$tag$lf";
      }
      else {
        $value = strtr($xml->html(), $cleantr);
        if (substr($tag, 0, 2) === 'DT' && $attrs === '') {
          // Get the timezone.
          $tz = NULL;
          $tz_name = trim($xml->xpath('//x-wr-timezone')->html());
          if ($tz_name !== '') {
            $tz = new \DateTimeZone($tz_name);
            $utc = new \DateTimeZone("UTC");
          }

          // Apply the timezone shift to use UTC.
          if (substr($value, -1) !== 'Z' && isset($tz)) {
            $dt = new \DateTime($value, $tz);
            $dt->setTimezone($utc);
            $value = $dt->format('Ymd\THis\Z');
          }
        }
        $line = "$tag$attrs:" . $value;
        if (strlen($line) <= 75) {
          $output = $line . $lf;
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

}
