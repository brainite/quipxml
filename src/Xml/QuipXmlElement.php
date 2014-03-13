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
  protected function _contentToDom($content) {
    if ($content instanceof \SimpleXMLElement) {
      $new = dom_import_simplexml($content);
    }
    elseif ($content instanceof \DOMNode) {
      $new = $content;
    }
    elseif (is_string($content)) {
      $new = clone dom_import_simplexml(Quip::load($content));
    }
    else {
      throw new \InvalidArgumentException("Unknown type of content.");
    }
    $me = $this->get();
    if ($new->ownerDocument !== $me->ownerDocument) {
      $clone = $new->cloneNode(TRUE);
      $new = $me->ownerDocument->importNode($clone, TRUE);
    }
    return $new;
  }

  public function before($content) {
    $me = $this->get();
    $parent = $this->parent_()->get();
    $new = $this->_contentToDom($content);
    $parent->insertBefore($new, $me);
    return $this;
  }

  public function after($content) {
    $this->before($content);
    $this->prev()->before($this);
    return $this;
  }

  public function eq($index = 0) {
    return $this;
  }

  public function get($index = 0) {
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

  public function parent_() {
    return $this->xpath('..');
    $p =& parent::xpath('..');
    if (sizeof($p)) {
      return $p[0];
    }
    return new QuipXmlElementIterator(new \EmptyIterator());
  }

  public function prev() {
    return $this->xpath("preceding-sibling::*[1]");
  }

  public function xpath($path) {
    return new QuipXmlElementIterator(new \ArrayIterator(parent::xpath($path)));
  }

}