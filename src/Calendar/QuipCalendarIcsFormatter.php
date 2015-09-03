<?php
namespace QuipXml\Calendar;
use QuipXml\Quip;
use QuipXml\Xml\QuipXmlFormatter;
class QuipCalendarIcsFormatter extends QuipXmlFormatter {
  public function getFormattedOuter($xml) {
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
        $output = "$tag$attrs:" . $value . $lf;
      }
    }
    return $output;
  }

}
