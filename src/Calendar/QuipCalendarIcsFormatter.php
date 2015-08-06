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
    );

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
        $output = "BEGIN:$tag\n{$output}END:$tag\n";
      }
      else {
        $output = "$tag$attrs:" . strtr($xml->html(), $cleantr) . "\n";
      }
    }
    return $output;
  }

}
