<?php

/*
 * This file is part of the QuipXml package.
 *
 * (c) Greg Payne <1994413+stackpr@users.noreply.github.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace QuipXml\Css;

/**
 * Parses, edits and renders the declarations of an inline `style` attribute.
 *
 * Property names are normalized to trimmed lower case; values are kept as
 * given. Every mutator returns the object for chaining.
 */
class CssStyles {

  /**
   * The declarations, keyed by normalized property name.
   *
   * @var array<string, string|int|float>
   */
  protected array $styles = [];

  /**
   * Creates an object holding the parsed declarations of a style string.
   *
   * @param string|null $css
   *   A declaration list such as `color: red; margin: 0`, or NULL for an
   *   empty object.
   *
   * @return \QuipXml\Css\CssStyles
   *   The new object.
   */
  public static function factory(?string $css = NULL): CssStyles {
    $new = new CssStyles();
    return $new->parse($css);
  }

  /**
   * Removes a property.
   *
   * @param string $property
   *   The property name, in any case.
   *
   * @return $this
   *   The object, for chaining.
   */
  public function &delete(string $property): static {
    $property = $this->property($property);
    unset($this->styles[$property]);
    return $this;
  }

  /**
   * Reads one property, or all of them.
   *
   * @param string|null $property
   *   The property name, in any case, or NULL for every declaration.
   *
   * @return array<string, string|int|float>|string|int|float|null
   *   The value as it was set, NULL when the property is absent, or, without
   *   a property name, every value keyed by normalized property name.
   */
  public function get(?string $property = NULL): array|string|int|float|null {
    if (!isset($property)) {
      return $this->styles;
    }
    $property = $this->property($property);
    if (isset($this->styles[$property])) {
      return $this->styles[$property];
    }
    return NULL;
  }

  /**
   * Adds to or subtracts from a numeric property in place.
   *
   * The property is left alone when it is not set.
   *
   * @param string $property
   *   The property name, in any case.
   * @param string $op
   *   'add' or '+', 'subtract' or '-'. Any other operation removes the
   *   property.
   * @param string|int|float $value
   *   The operand: a number with the same unit as the current value (`5px`
   *   for `12px`, `2` for `1`).
   *
   * @return $this
   *   The object, for chaining.
   *
   * @throws \InvalidArgumentException
   *   When either value is not a number with an optional unit, or the two
   *   units differ.
   */
  public function &op(string $property, string $op, string|int|float $value): static {
    // Get the current value.
    $current = $this->get($property);
    if (!isset($current) || is_array($current)) {
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

      default:
        // An unknown operation sets no value, which removes the property.
        $new = NULL;
    }

    return $this->set($property, $new);
  }

  /**
   * Normalizes a property name.
   *
   * @param string $property
   *   The property name as given.
   *
   * @return string
   *   The name trimmed and lower-cased.
   */
  protected function property(string $property): string {
    $property = strtolower(trim($property));
    return $property;
  }

  /**
   * Adds the declarations of a style string, replacing same-named ones.
   *
   * Semicolons inside `url(...)` are kept. A declaration without a colon
   * removes that property.
   *
   * @param string|null $css
   *   A declaration list such as `color: red; margin: 0`; NULL is a no-op.
   *
   * @return $this
   *   The object, for chaining.
   */
  public function &parse(?string $css): static {
    if (!isset($css)) {
      return $this;
    }

    $initial = preg_split('@\s*;+\s*@s', trim($css, "; \t\r\n")) ?: [];
    $parts = [];
    while (!empty($initial)) {
      $p = (string) array_shift($initial);
      if (strpos($p, 'url(') !== FALSE) {
        while (!empty($initial) && !preg_match('@url\(.*\)@s', $p)) {
          $p .= ';' . array_shift($initial);
        }
      }
      $parts[] = $p;
    }
    foreach ($parts as $part) {
      $tmp = preg_split('@\s*:\s*@s', $part, 2) ?: [];
      if (empty($tmp[0])) {
        // Do nothing.
      }
      else {
        $this->set($tmp[0], $tmp[1] ?? NULL);
      }
    }
    return $this;
  }

  /**
   * Splits a numeric value into its number and unit.
   *
   * @param string|int|float $value
   *   A value such as `12px`, `-1.5em` or `2`.
   *
   * @return array{value: numeric-string, units: string}
   *   The number and the (possibly empty) unit.
   *
   * @throws \InvalidArgumentException
   *   When the value is not a number with an optional unit.
   */
  private function parseNumeric(string|int|float $value): array {
    // Define the numeric extractor.
    $extractor = "@^(?<value>-?\d+(?:\.\d+)?)(?<units>[^\s]*)$@s";
    if (!preg_match($extractor, trim((string) $value), $a) || !is_numeric($a['value'])) {
      throw new \InvalidArgumentException("Non-numeric property value cannot be parsed.");
    }
    return [
      'value' => $a['value'],
      'units' => $a['units'],
    ];
  }

  /**
   * Renders the declarations as a style string.
   *
   * Properties are sorted by name, which also reorders the stored
   * declarations.
   *
   * @return string
   *   The declarations as `name:value` pairs joined by `;`, e.g.
   *   `color:red;margin:0`, or an empty string when there are none.
   */
  public function render(): string {
    ksort($this->styles);
    $styles = [];
    foreach ($this->styles as $k => $v) {
      $styles[] = "$k:$v";
    }
    $flat = implode(';', $styles);
    return $flat;
  }

  /**
   * Sets one property, several properties, or removes one.
   *
   * @param string|array<string|int, string|int|float|null> $property
   *   The property name, in any case, or an array of values keyed by
   *   property name (a NULL value there removes that property).
   * @param string|int|float|null $value
   *   The value to store as given. NULL removes the named property; it must
   *   be NULL when $property is an array.
   *
   * @return $this
   *   The object, for chaining.
   *
   * @throws \TypeError
   *   When $property is an array and a $value is given.
   */
  public function &set(string|array $property, string|int|float|null $value = NULL): static {
    if (!isset($value)) {
      if (is_array($property)) {
        foreach ($property as $k => $v) {
          $this->set((string) $k, $v);
        }
        return $this;
      }
      else {
        return $this->delete($property);
      }
    }
    if (is_array($property)) {
      throw new \TypeError('A property list takes its values from the array; pass no separate value.');
    }

    // Set the property.
    $property = $this->property($property);
    $this->styles[$property] = $value;
    return $this;
  }

}
