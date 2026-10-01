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
use QuipXml\Xml\QuipXmlElement;
use QuipXml\Xml\QuipXmlElementIterator;
use QuipXml\Xml\QuipXmlFormatter;

/**
 * Compares edits against the expected outputs in Resources/XmlBasicList.xml.
 */
#[CoversClass(QuipXmlElement::class)]
#[CoversClass(QuipXmlElementIterator::class)]
final class QuipXmlBasicListTest extends TestCase {

  /**
   * The formatter both sides of each comparison use.
   */
  private QuipXmlFormatter $formatter;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->formatter = Quip::formatter();
  }

  /**
   * Loads a fresh copy of the list.
   */
  private function load(): QuipXmlElement {
    return Quip::load(__DIR__ . '/Resources/XmlBasicList.xml', 0, TRUE);
  }

  /**
   * Returns an expected output, formatted.
   */
  private function expected(string $method): string {
    return (string) $this->load()->qxpath("//output[@method = '$method']")->html($this->formatter);
  }

  /**
   * Returns the new content to insert, as markup.
   */
  private function newContent(): string {
    return (string) $this->load()->qxpath("//arg[@id = 'new-content']")->html();
  }

  /**
   * Counting matches.
   */
  public function testCount(): void {
    $quip = $this->load();

    $this->assertCount(1, $quip->qxpath('//original'));
    $this->assertCount(0, $quip->qxpath('//original/x'));
    $this->assertCount(0, $quip->qxpath('//original/x[1]'));
  }

  /**
   * After() on the target item.
   */
  public function testAfter(): void {
    $target = $this->load()->qxpath("//original//item[@class = 'target']");

    $this->assertSame($this->expected('after'), $target->after($this->newContent())->qxparent()->htmlOuter($this->formatter));
  }

  /**
   * Before() on the target item.
   */
  public function testBefore(): void {
    $target = $this->load()->qxpath("//original//item[@class = 'target']");

    $this->assertSame($this->expected('before'), $target->before($this->newContent())->qxparent()->htmlOuter($this->formatter));
  }

  /**
   * Before() and after() chained from a SimpleXML traversal.
   */
  public function testBeforeAfterFromTraversal(): void {
    $quip = $this->load();
    $add = $this->newContent();
    $target = $quip->original->list->qxpath("./item[@class = 'target']");

    $this->assertSame($this->expected('before-after'), $target->before($add)->after($add)->qxparent()->htmlOuter($this->formatter));
  }

  /**
   * SimpleXML traversal and Quip queries mix in any order.
   */
  public function testMixTraversal(): void {
    $quip = $this->load();
    $expected = ['one', 'two', 'three', 'four', 'five'];
    $routes = [
      $quip->qxpath('//original/list')->item,
      $quip->original->qxpath('./list')->item,
      $quip->qxpath('//original')->qxpath('./list')->item,
      $quip->qxpath('//original')->list->qxpath('./item'),
    ];
    foreach ($routes as $route) {
      $actual = [];
      foreach ($route ?? [] as $item) {
        $actual[] = trim((string) $item);
      }
      $this->assertSame($expected, $actual);
    }
  }

  /**
   * Missing nodes iterate nothing and verbs on them change nothing.
   */
  public function testSurviveEmpty(): void {
    $quip = $this->load();
    $this->assertCount(0, $quip->notfoundanywhere);
    $this->assertCount(0, $quip->qxpath('//notfoundanywhere'));
    $this->assertCount(0, $quip->qxpath('//notfoundanywhere')->qxpath('//original')->notfoundanywhere);

    $expected = $quip->html($this->formatter);
    $quip->notfoundanywhere->after('<div/>')->before('<div/>');
    $quip->qxpath('//notfoundanywhere')->after('<div/>')->before('<div/>');
    $this->assertSame($expected, $quip->html($this->formatter));
  }

  /**
   * Wrap() and unwrap() rebuild the list under a new element.
   */
  public function testWrapUnwrap(): void {
    $expected = $this->expected('list-newlist');

    $quip = $this->load();
    $target = $quip->qxpath("//original//item[@class = 'target']");
    $target->qxparent()->wrap('<newlist/>');
    $target->unwrap();
    $this->assertSame($expected, $quip->original->html($this->formatter));

    $quip = $this->load();
    $quip->original->wrapInner('<newlist />');
    $quip->original->newlist->html((string) $quip->qxpath('//original//list')->html());
    $this->assertSame($expected, $quip->original->html($this->formatter));

    $quip = $this->load();
    $quip->qxpath('//original//item')->qxparent()->wrapInner('<newlist />')->qxpath('./*[1]')->unwrap();
    $this->assertSame($expected, $quip->original->html($this->formatter));
  }

}
