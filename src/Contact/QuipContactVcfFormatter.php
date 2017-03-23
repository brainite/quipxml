<?php
namespace QuipXml\Contact;

use QuipXml\Calendar\QuipCalendarIcsFormatter;
use QuipXml\Quip;
class QuipContactVcfFormatter extends QuipCalendarIcsFormatter {
  public function getFormattedOuter($xml) {
    $this->fixUid($xml);
    $this->fixDtEnd($xml);
    $output = $this->getFormattedRecursiveIterator($xml, 'VCARD');
    return $output;
  }

  protected function getFormattedTagOverrideChildren($xml, $tag) {
    switch ($tag) {
      case 'adr':
        $children = array(
          'pobox',
          'ext',
          'street',
          'locality',
          'region',
          'code',
          'country',
        );
        return $this->getFormattedTagOrderedChildren($xml, $children);

      case 'n':
        $children = array(
         'surname',
         'given',
         'additional',
         'prefix',
         'suffix',
         'suffix',
        );
        return $this->getFormattedTagOrderedChildren($xml, $children);

      default:
        return FALSE;
    }
  }

}