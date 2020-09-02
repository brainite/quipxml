<?php
namespace QuipXml\Contact;
use QuipXml\Quip;
use QuipXml\Calendar\QuipCalendar;
class QuipContact extends QuipCalendar {
  /**
   *
   * @param unknown $source
   * @param number $options
   * @param string $data_is_url
   * @param string $ns
   * @param string $is_prefix
   * @param number $quip_options
   * @return \QuipXml\Xml\QuipXmlElement
   */
  static public function loadVcard($source, $options = 0, $data_is_url = FALSE, $ns = '', $is_prefix = FALSE, $quip_options = 0) {
    // Get the content.
    if ($data_is_url) {
      $source = file_get_contents($source);
      $data_is_url = FALSE;
    }

    // Initialize the XML object using an empty vcard.
    // This is NOT an xCard: https://tools.ietf.org/html/rfc6351
    $dom = new \DOMDocument;
    $dom->loadXML('<vcards/>');

    // Strip off the bad white space.
    $source = trim(preg_replace("@[\n\r]+@s", "\n", $source));
    $lines = explode("\n", $source);

    // Iterate through the lines
    $i = 0;
    while (count($lines) > $i) {
      self::loadICalElement($dom->documentElement, $lines, $i);
    }

    return Quip::load($dom);
  }

  /**
   * Initialize an empty contact.
   * @param array $defaults
   * @return \QuipXml\Xml\QuipXmlElement
   */
  static public function loadEmpty($defaults = NULL) {
    $defaults = array_merge(array(
      'FN' => '',
    ), (array) $defaults);
    $ical = implode("\n", array(
      'BEGIN:VCARD',
      'VERSION:3.0',
      'PRODID:QuipContact',
      'N:',
      'FN:' . QuipContactVcfFormatter::escape($defaults['FN']),
      'END:VCARD',
    ));
    return self::loadVcard($ical);
  }

}