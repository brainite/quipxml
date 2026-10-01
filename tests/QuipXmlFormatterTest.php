<?php

/*
 * This file is part of the QuipXml package.
 *
 * (c) Greg Payne
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace QuipXml\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use QuipXml\Quip;
use QuipXml\Xml\QuipXmlFormatter;

/**
 * Tests the indenting serializer.
 */
#[CoversClass(QuipXmlFormatter::class)]
final class QuipXmlFormatterTest extends TestCase {

  /**
   * The outer form indents and drops the XML declaration.
   */
  public function testFormattedOuter(): void {
    $quip = Quip::load('<menu><dish>Soup</dish><dish>Bread</dish></menu>');

    $this->assertSame("<menu>\n  <dish>Soup</dish>\n  <dish>Bread</dish>\n</menu>", (new QuipXmlFormatter())->getFormattedOuter($quip));
  }

  /**
   * The inner form concatenates each formatted child.
   */
  public function testFormattedInner(): void {
    $quip = Quip::load('<menu><dish>Soup</dish><dish>Bread</dish></menu>');

    $this->assertSame('<dish>Soup</dish><dish>Bread</dish>', (new QuipXmlFormatter())->getFormattedInner($quip));
  }

  /**
   * The openingTag setting keeps the XML declaration.
   */
  public function testOpeningTag(): void {
    $quip = Quip::load('<menu/>');

    $this->assertStringStartsWith('<?xml', Quip::formatter(['openingTag' => TRUE])->getFormattedOuter($quip));
  }

  /**
   * Without formatOutput the markup is passed through.
   */
  public function testWithoutFormatting(): void {
    $quip = Quip::load('<menu><dish>Soup</dish></menu>');

    $this->assertSame('<menu><dish>Soup</dish></menu>', Quip::formatter(['formatOutput' => FALSE])->getFormattedOuter($quip));
  }

  /**
   * Carriage returns are dropped and a non-breaking space is named.
   */
  public function testNormalisesCarriageReturnsAndNbsp(): void {
    $quip = Quip::load("<note>Tea&#13;\nand&nbsp;cake</note>");

    $this->assertSame("<note>Tea\nand&nbsp;cake</note>", Quip::formatter(['formatOutput' => FALSE])->getFormattedOuter($quip));
  }

}
