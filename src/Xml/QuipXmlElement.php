<?php
/*
 * This file is part of the QuipXml package.
 *
 * (c) Greg Payne
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace QuipXml\Xml;

use QuipXml\Quip;
class QuipXmlElement extends \SimpleXMLElement {
  protected function _contentToDom($content, $return_parent = FALSE) {
    if ($content instanceof \SimpleXMLElement) {
      $new = dom_import_simplexml($content);
      if ($return_parent) {
        $new = $new->parentNode;
      }
    }
    elseif ($content instanceof \DOMNode) {
      $new = $content;
      if ($return_parent) {
        $new = $new->parentNode;
      }
    }
    elseif (is_string($content)) {
      if ($return_parent) {
        $new = Quip::load("<root>$content</root>")->dom();
      }
      else {
        $new = Quip::load($content)->dom();
      }
    }
    else {
      throw new \InvalidArgumentException("Unknown type of content.");
    }
    $me = $this->dom();
    if ($new->ownerDocument !== $me->ownerDocument) {
      $clone = $new->cloneNode(TRUE);
      $new = $me->ownerDocument->importNode($clone, TRUE);
    }
    return $new;
  }

  protected function _getEmptyElement() {
    $results = parent::xpath('/*');
    return $results[0]->{uniqid('empty element')};
  }

  /**
   * Add the content before this node.
   * @param mixed $content
   * @return \QuipXml\Xml\QuipXmlElement
   */
  public function before($content) {
    if (FALSE === (bool) $this) {
      return $this;
    }
    $me = $this->dom();
    $parent = $this->xparent()->dom();
    $new = $this->_contentToDom($content);
    $parent->insertBefore($new, $me);
    return $this;
  }

  /**
   * Add the content after this node.
   * @param mixed $content
   * @return \QuipXml\Xml\QuipXmlElement
   */
  public function after($content) {
    if (FALSE === (bool) $this) {
      return $this;
    }
    $me = $this->dom();
    $parent = $this->xparent()->dom();
    $new = $this->_contentToDom($content);
    if (isset($me->nextSibling)) {
      $parent->insertBefore($new, $me->nextSibling);
    }
    else {
      $parent->appendChild($new);
    }
    return $this;
  }

  /**
   * Add the content before this node.
   * @param int $index
   * @return \QuipXml\Xml\QuipXmlElement
   */
  public function eq($index = 0) {
    return $this;
  }

  /**
   * Get the DOM object associated with this node.
   * @param int $index
   * @return DOMElement|FALSE
   */
  public function dom($index = 0) {
    if ($index == 0) {
      $dom = dom_import_simplexml($this);
      return $dom;
    }
    return FALSE;
  }

  /**
   * @todo set the html content.
   * @todo adjust the xml for html display.
   * @return mixed
   */
  public function html($content = NULL) {
    if (!isset($content)) {
      $str = trim(parent::asXML());
      do {
        list ($open, $str) = explode('>', $str, 2);
      } while (substr($open, -1) === '?');
      $tmp = explode('<', $str);
      array_pop($tmp);
      $str = join('<', $tmp);
      $str = trim($str);
      return $str;
    }
    elseif ($content instanceof QuipXmlFormatter) {
      return $content->getFormattedInner($this);
    }
    else {
      $new = $this->_contentToDom($content, TRUE);
      $me = $this->dom();
      while ($me->childNodes->length != 0) {
        $me->removeChild($me->childNodes->item(0));
      }
      foreach ($new->childNodes as $child) {
        $me->appendChild($child->cloneNode(TRUE));
      }
    }
    return $this;
  }

  public function htmlOuter($content = NULL) {
    if (!isset($content)) {
      return trim(parent::asXML());
    }
    elseif ($content instanceof QuipXmlFormatter) {
      return $content->getFormattedOuter($this);
    }
    return $this;
  }

  public function unwrap() {
    $parent = $this->xparent()->dom();
    if (!$parent) {
      return $this->_getEmptyElement();
    }
    foreach ($parent->childNodes as $child) {
      $parent->parentNode->insertBefore($child->cloneNode(TRUE), $parent);
    }
    $parent->parentNode->removeChild($parent);
    return $this;
  }

  public function wrap($content) {
    $me = $this->dom();
    $parent = $me->parentNode;
    $new = $this->_contentToDom($content);
    $wrapper = $parent->insertBefore($new, $me);
    $wrapper->appendChild($me);
    return $this;
  }

  public function wrapInner($content) {
    $me = $this->dom();
    $new = $this->_contentToDom($content);
    while ($me->childNodes->length != 0) {
      $c = $me->childNodes->item(0);
      $new->appendChild($c);
    }
    $child = $me->appendChild($new);
    return $this;
  }

  /**
   * Get the parent node or an empty iterator.
   * @return \QuipXml\Xml\QuipXmlElement
   */
  public function xparent() {
    return $this->xpath('..');
    $p =& parent::xpath('..');
    if (sizeof($p)) {
      return $p[0];
    }
    return $this->_getEmptyElement();
  }

  /**
   * Get the preceding sibling for this node
   * @return \QuipXml\Xml\QuipXmlElementIterator
   */
  public function xprev() {
    return $this->xpath("preceding-sibling::*[1]");
  }

  /**
   * Wrap the xpath results in a Quip iterator.
   * @see SimpleXMLElement::xpath()
   * @return \QuipXml\Xml\QuipXmlElementIterator
   */
  public function xpath($path) {
    $results = parent::xpath($path);
    if (empty($results)) {
      return $this->_getEmptyElement();
    }
    return new QuipXmlElementIterator(new \ArrayIterator($results));
  }

}