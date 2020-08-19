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
class QuipXmlElementIterator extends \IteratorIterator {
  public function __construct($iterator) {
    $arr = array();
    $prevs = array();
    foreach ($iterator as $v) {
      $dom = $v->dom();
      if (!$dom) {
        continue;
      }
      foreach ($prevs as $prev) {
        if ($dom->isSameNode($prev)) {
          continue (2);
        }
      }
      $arr[] = $v;
      $prevs[] = $dom;
    }
    $it = new \ArrayIterator($arr);
    parent::__construct($it);
  }

  public function __get($name) {
    $arr = array();
    foreach ($this as $el) {
      $arr[] = $el->$name;
    }
    if (sizeof($arr) == 1) {
      return $arr[0];
    }
    return new QuipXmlElementIterator(new \ArrayIterator($arr));
  }

  public function __set($name, $value) {
    foreach ($this as $el) {
      $el->$name = $value;
    }
  }

  protected function _eachGetIterator($method, $arg1 = NULL, $arg2 = NULL, $arg3 = NULL) {
    $it = new \AppendIterator();
    $arr = array();
    foreach ($this as $el) {
      $tmp = $el->$method($arg1, $arg2, $arg3);
      if ($tmp instanceof \Iterator) {
        $it->append($tmp);
      }
      else {
        $arr[] = $tmp;
      }
    }
    if (!empty($arr)) {
      $it->append(new \ArrayIterator($arr));
    }
    if ($it->valid()) {
      return new QuipXmlElementIterator($it);
    }
    return $this->{'no results'};
  }

  protected function _eachSetter($method, $arg1 = NULL, $arg2 = NULL, $arg3 = NULL) {
    foreach ($this as $el) {
      $el->$method($arg1, $arg2, $arg3);
    }
    return $this;
  }

  protected function _getEmptyElement() {
    foreach ($this as $el) {
      $results = $el->xpath('/*');
      return $results[0]->{uniqid('empty element')};
    }
  }

  protected function _singleGetter($method, $arg1 = NULL) {
    $this->rewind();
    if ($this->valid()) {
      return $this->current()->$method($arg1);
    }
  }

  /**
   * Adds a child element to the XML node
   * @link http://www.php.net/manual/en/simplexmlelement.addchild.php
   * @param name string                The name of the child element to add.
   * @param value string[optional]     If specified, the value of the child element.
   * @param namespace string[optional] If specified, the namespace to which the child element belongs.
   * @return QuipXmlElementIterator    The addChild method returns a QuipXmlElementIterator
   *                                   object representing the child(ren) added to the XML node(s).
   */
  public function addChild($name, $value = null, $namespace = null) {
    return $this->_eachGetIterator('addChild', $name, $value, $namespace);
  }

  public function after($content) {
    return $this->_eachSetter('after', $content);
  }

  public function before($content) {
    return $this->_eachSetter('before', $content);
  }

  public function eq($index = 0) {
    $this->rewind();
    for ($i = 0; $i < $index; ++$i) {
      if (!$this->valid()) {
        return $this->_getEmptyElement();
      }
      $this->next();
    }
    if ($this->valid()) {
      return $this->current();
    }
    return $this->_getEmptyElement();
  }

  public function dom($index = 0) {
    $eq = $this->eq($index);
    if ($eq instanceof self) {
      return FALSE;
    }
    return $eq->dom();
  }

  public function get($path) {
    return $this->_eachGetIterator('get', $path);
  }

  public function html($content = NULL) {
    if (isset($content)) {
      if ($content instanceof QuipXmlFormatter) {
        return $this->_singleGetter('html', $content);
      }
      return $this->_eachSetter('html', $content);
    }
    return $this->_singleGetter('html');
  }

  public function htmlOuter($content = NULL) {
    if (isset($content)) {
      if ($content instanceof QuipXmlFormatter) {
        return $this->_singleGetter('htmlOuter', $content);
      }
      return $this->_eachSetter('htmlOuter', $content);
    }
    return $this->_singleGetter('htmlOuter');
  }

  public function remove() {
    return $this->_eachSetter('remove');
  }

  public function setTag($tag) {
    return $this->_eachSetter('setTag', $tag);
  }

  public function text($content = NULL) {
    if (isset($content)) {
      if ($content instanceof QuipXmlFormatter) {
        return $this->_singleGetter('text', $content);
      }
      return $this->_eachSetter('text', $content);
    }
    return $this->_singleGetter('text');
  }

  public function unwrap() {
    return $this->_eachSetter('unwrap');
  }

  public function wrap($content) {
    return $this->_eachSetter('wrap', $content);
  }

  public function wrapInner($content) {
    return $this->_eachSetter('wrapInner', $content);
  }

  public function xparent() {
    return $this->_eachGetIterator('xparent');
  }

  public function xprev() {
    return $this->_eachGetIterator('xprev');
  }

  /**
   * Runs XPath query on XML data
   * @link http://www.php.net/manual/en/simplexmlelement.xpath.php
   * @param path string An XPath path
   * @return QuipXmlElementIterator
   */
  public function xpath($path) {
    if ($path[0] === '/') {
      $this->rewind();
      if ($this->valid()) {
        return $this->current()->xpath($path);
      }
      return $this->_getEmptyElement();
    }

    return $this->_eachGetIterator('xpath', $path);
  }

}