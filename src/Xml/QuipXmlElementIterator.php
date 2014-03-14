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

  protected function _eachSetter($method, $arg1, $arg2 = NULL, $arg3 = NULL) {
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

  public function get($index = 0) {
    $eq = $this->eq($index);
    if ($eq instanceof self) {
      return FALSE;
    }
    return $eq->get();
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

  public function parent_() {
    return $this->_eachGetIterator('parent_');
  }

  public function prev_() {
    return $this->_eachGetIterator('prev_');
  }

  public function xpath($path) {
    if ($path{0} === '/') {
      $this->rewind();
      if ($this->valid()) {
        return $this->current()->xpath($path);
      }
      return $this->_getEmptyElement();
    }

    return $this->_eachGetIterator('xpath', $path);
  }

}