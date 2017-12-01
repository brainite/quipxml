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

  static public function escape($text) {
    static $cleantr = array(
      '&lt;' => '<',
      '&gt;' => '>',
      '\\' => '\\\\',
      "\n" => '\\n',
      "\r" => '\\r',
      "," => '\\,',
      ";" => '\\;',
    );
    return strtr($text, $cleantr);
  }

  protected function &fixDtEnd(&$xml) {
    foreach ($xml->xpath('//vevent') as $vevent) {
      $dtstart = (string) $vevent->dtstart;
      $dtend = (string) $vevent->dtend;
      if (empty($dtstart)) {
        $vevent->dtstart = strftime('%Y%m%dT%H%M%S', time());
      }
      if ($dtend <= $dtstart) {
        $vevent->dtend = strftime('%Y%m%dT%H%M%S', strtotime($dtend) + 60);
      }
    }
  }

  protected function &fixUid(&$xml) {
    if ($this->settings['fix_uid_length']) {
      // Limit uid length to 71 (75-char line minus "UID:")
      $uid = &$xml->xpath('/iCalendar/vcalendar/uid');
      $val = $uid->html();
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

  protected function getDateTime($value, &$xml) {
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

    return $value;
  }

  public function getFormattedOuter($xml) {
    $this->fixUid($xml);
    $this->fixDtEnd($xml);
    $output = $this->getFormattedRecursiveIterator($xml, $xml->getName());
    return $output;
  }

  protected function getFormattedRecursiveIterator($xml, $tag = NULL) {
    static $lf = "\r\n";
    $output = '';

    // Add the attributes
    $attrs = '';
    foreach ($xml->attributes() as $k => $v) {
      $k = strtoupper($k);
      $attrs .= ";$k=$v";
    }

    // Add the children.
    $number_children = 0;
    $override_value = $this->getFormattedTagOverrideChildren($xml, $tag);
    if ($override_value === FALSE) {
      $output2 = '';
      foreach ($xml->children() as $child) {
        ++$number_children;
        $tmp = $this->getFormattedRecursiveIterator($child, $child->getName());
        if (preg_match('@^x-.*-@i', $child->getName())) {
          $output .= $tmp;
        }
        else {
          $output2 .= $tmp;
        }
      }
      $output .= $output2;
      unset($override_value);
    }

    // Wrap in the tag.
    if (isset($tag) && strlen($tag) > 0) {
      $tag = strtoupper($tag);
      if ($number_children) {
        $output = "BEGIN:$tag$lf{$output}END:$tag$lf";
      }
      else {
        $value = isset($override_value) ? $override_value
          : self::escape($xml->html());
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

  protected function getFormattedTagOverrideChildren($xml, $tag) {
    return FALSE;
  }

  protected function getFormattedTagOrderedChildren($xml, $children) {
    $output = '';
    foreach ($children as $i => $child) {
      if ($i !== 0) {
        $output .= ';';
      }
      $output .= $this->escape($xml->{$child}->html());
    }
    return $output;
  }

}
