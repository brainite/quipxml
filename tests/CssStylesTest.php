<?php

declare(strict_types=1);

/*
 * This file is part of the QuipXml package.
 *
 * (c) Greg Payne
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace QuipXml\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QuipXml\Css\CssStyles;

/**
 * Tests parsing, editing and rendering of inline style declarations.
 */
#[CoversClass(CssStyles::class)]
final class CssStylesTest extends TestCase {

  /**
   * The factory parses a declaration list, normalizing property names.
   */
  public function testFactoryParsesDeclarations(): void {
    $styles = CssStyles::factory(' Color : Teal ; MARGIN:0;; width:10px ');
    $this->assertSame([
      'color' => 'Teal',
      'margin' => '0',
      'width' => '10px',
    ], $styles->get());
  }

  /**
   * The factory without a style string gives an empty object.
   */
  public function testFactoryWithoutCssIsEmpty(): void {
    $this->assertSame([], CssStyles::factory()->get());
    $this->assertSame('', CssStyles::factory()->render());
  }

  /**
   * Semicolons inside url(...) do not split a declaration.
   */
  public function testParseKeepsSemicolonsInsideUrl(): void {
    $styles = CssStyles::factory('background: url(data:image/png;base64,AAAA) no-repeat; color: navy');
    $this->assertSame('url(data:image/png;base64,AAAA) no-repeat', $styles->get('background'));
    $this->assertSame('navy', $styles->get('color'));
  }

  /**
   * Parsing again adds to and replaces the existing declarations.
   */
  public function testParseMergesIntoExistingDeclarations(): void {
    $styles = CssStyles::factory('color: navy; padding: 1em');
    $styles->parse('color: olive; border: 0');
    $this->assertSame('border:0;color:olive;padding:1em', $styles->render());
  }

  /**
   * Parsing NULL leaves the object unchanged.
   */
  public function testParseNullIsNoOp(): void {
    $styles = CssStyles::factory('color: navy');
    $this->assertSame($styles, $styles->parse(NULL));
    $this->assertSame('color:navy', $styles->render());
  }

  /**
   * Property names are matched in any case and with surrounding space.
   */
  public function testGetIsCaseInsensitive(): void {
    $styles = CssStyles::factory('font-size: 12px');
    $this->assertSame('12px', $styles->get(' FONT-SIZE '));
  }

  /**
   * Reading an absent property gives NULL.
   */
  public function testGetMissingPropertyIsNull(): void {
    $this->assertNull(CssStyles::factory('color: navy')->get('margin'));
  }

  /**
   * Setting a single property stores the value as given.
   */
  public function testSetSingleProperty(): void {
    $styles = CssStyles::factory();
    $this->assertSame($styles, $styles->set('Line-Height', '1.4'));
    $this->assertSame('1.4', $styles->get('line-height'));
  }

  /**
   * Setting from an array sets each entry and removes NULL ones.
   */
  public function testSetFromArray(): void {
    $styles = CssStyles::factory('margin: 0; color: navy');
    $styles->set(['Padding' => '2px', 'margin' => NULL]);
    $this->assertSame('color:navy;padding:2px', $styles->render());
  }

  /**
   * Setting a property to NULL removes it.
   */
  public function testSetNullRemovesProperty(): void {
    $styles = CssStyles::factory('margin: 0; color: navy');
    $styles->set('margin');
    $this->assertSame('color:navy', $styles->render());
  }

  /**
   * Deleting removes the property, matched in any case.
   */
  public function testDeleteRemovesProperty(): void {
    $styles = CssStyles::factory('margin: 0; color: navy');
    $this->assertSame($styles, $styles->delete(' MARGIN '));
    $this->assertSame(['color' => 'navy'], $styles->get());
  }

  /**
   * Rendering sorts the properties by name.
   */
  public function testRenderSortsByProperty(): void {
    $styles = CssStyles::factory('z-index: 2; color: navy; border: 0');
    $this->assertSame('border:0;color:navy;z-index:2', $styles->render());
  }

  /**
   * Arithmetic on numeric properties, keeping the unit.
   *
   * @param string $css
   *   The starting declarations.
   * @param string $op
   *   The operation.
   * @param string $value
   *   The operand.
   * @param string $expected
   *   The resulting value of the 'width' property.
   */
  #[DataProvider('providerOp')]
  public function testOp(string $css, string $op, string $value, string $expected): void {
    $styles = CssStyles::factory($css);
    $this->assertSame($styles, $styles->op('width', $op, $value));
    $this->assertSame($expected, $styles->get('width'));
  }

  /**
   * Data for testOp().
   *
   * @return array<string, array{string, string, string, string}>
   *   Starting css, operation, operand and expected width.
   */
  public static function providerOp(): array {
    return [
      'add pixels' => ['width: 10px', 'add', '5px', '15px'],
      'plus sign' => ['width: 10px', '+', '2.5px', '12.5px'],
      'subtract' => ['width: 4em', 'subtract', '1.5em', '2.5em'],
      'minus sign into negative' => ['width: 1em', '-', '3em', '-2em'],
      'unitless' => ['width: 1', '-', '0.25', '0.75'],
    ];
  }

  /**
   * An operation on an absent property changes nothing.
   */
  public function testOpOnMissingPropertyIsNoOp(): void {
    $styles = CssStyles::factory('color: navy');
    $styles->op('width', '+', '5px');
    $this->assertSame(['color' => 'navy'], $styles->get());
  }

  /**
   * Values with different units cannot be combined.
   */
  public function testOpRejectsMixedUnits(): void {
    $this->expectException(\InvalidArgumentException::class);
    CssStyles::factory('width: 10px')->op('width', '+', '1em');
  }

  /**
   * A non-numeric value cannot take part in arithmetic.
   */
  public function testOpRejectsNonNumericValue(): void {
    $this->expectException(\InvalidArgumentException::class);
    CssStyles::factory('width: auto')->op('width', '+', '1px');
  }

}
