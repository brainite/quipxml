<?php
/*
 * This file is part of the QuipXml package.
 *
 * (c) Greg Payne
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace QuipXml\Css;
class CssStyles {
  protected $styles = array();

  static public function factory($css = NULL) {
    $new = new CssStyles;
    return $new->parse($css);
  }

  public function &delete($property) {
    $property = $this->property($property);
    unset($this->styles[$property]);
    return $this;
  }

  public function get($property = NULL) {
    if (!isset($property)) {
      return $this->styles;
    }
    $property = $this->property($property);
    if (isset($this->styles[$property])) {
      return $this->styles[$property];
    }
    return NULL;
  }

  public function &op($property, $op, $value) {
    // Get the current value.
    $current = $this->get($property);
    if (!isset($current)) {
      return $this;
    }

    // Get the parsed data for the calculation.
    $a = $this->parseNumeric($current);
    $b = $this->parseNumeric($value);
    if ($a['units'] !== $b['units']) {
      throw new \InvalidArgumentException("Property values using different types cannot be combined.");
    }

    switch ($op) {
      case 'add':
      case '+':
        $new = ($a['value'] + $b['value']) . $a['units'];
        break;

      case 'subtract':
      case '-':
        $new = ($a['value'] - $b['value']) . $a['units'];
        break;
    }

    return $this->set($property, $new);
  }

  protected function property($property) {
    $property = strtolower(trim($property));
    return $property;
  }

  public function &parse($css) {
    if (!isset($css)) {
      return $this;
    }

    $styles = array();
    $initial = preg_split('@\s*;+\s*@s', trim($css, "; \t\r\n"));
    $parts = array();
    while (!empty($initial)) {
      $p = array_shift($initial);
      if (strpos($p, 'url(') !== FALSE) {
        while (!empty($initial) && !preg_match('@url\(.*\)@s', $p)) {
          $p .= ';' . array_shift($initial);
        }
      }
      $parts[] = $p;
    }
    foreach ($parts as $part) {
      $tmp = preg_split('@\s*:\s*@s', $part, 2);
      if (empty($tmp[0])) {
        // Do nothing.
      }
      else {
        $this->set($tmp[0], $tmp[1]);
      }
    }
    return $this;
  }

  private function parseNumeric($value) {
    // Define the numeric extractor
    $extractor = "@^(?<value>-?\d+(?:\.\d+)?)(?<units>[^\s]*)$@s";
    if (!preg_match($extractor, trim($value), $a)) {
      throw new \InvalidArgumentException("Non-numeric property value cannot be parsed.");
    }
    return array(
      'value' => $a['value'],
      'units' => $a['units'],
    );
  }

  public function render() {
    ksort($this->styles);
    $styles = array();
    foreach ($this->styles as $k => $v) {
      $styles[] = "$k:$v";
    }
    $flat = join(';', $styles);
    return $flat;
  }

  public function &set($property, $value = NULL) {
    if (!isset($value)) {
      if (is_array($property)) {
        foreach ($property as $k => $v) {
          $this->set($k, $v);
        }
        return $this;
      }
      else {
        return $this->delete($property);
      }
    }

    // Set the property.
    $property = $this->property($property);
    $this->styles[$property] = $value;
    return $this;
  }

}
