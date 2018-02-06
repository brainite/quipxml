<?php
/*
 * This file is part of the QuipXml package.
 *
 * (c) Greg Payne
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace QuipXml\Xml\Exception;

class NotPermanentMemberException extends \ErrorException {
  public function __construct($message = null, $code = 0, \Exception $previous = null) {
    if (!isset($message)) {
      $message = 'Parent is not a permanent member of the XML tree. See QuipXmlElement::get()';
    }
    return parent::__construct($message, $code, $previous);
  }

}