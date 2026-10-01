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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QuipXml\Quip;
use QuipXml\Xml\Exception\NotPermanentMemberException;
use QuipXml\Xml\QuipXmlElement;
use QuipXml\Xml\QuipXmlElementInterface;
use QuipXml\Xml\QuipXmlElementIterator;

/**
 * Tests the verbs of a single QuipXmlElement node.
 */
#[CoversClass(QuipXmlElement::class)]
final class QuipXmlElementTest extends TestCase {

  /**
   * The recipe document most tests edit.
   */
  private const RECIPE = '<recipe><title>Lemon cake</title><steps><step n="1">Whisk</step><step n="2">Fold</step><step n="3">Bake</step></steps><notes/></recipe>';

  /**
   * Loads a fresh copy of the recipe.
   */
  private function recipe(): QuipXmlElement {
    return Quip::load(self::RECIPE);
  }

  /**
   * Returns the steps element's markup.
   */
  private static function steps(QuipXmlElement $recipe): string {
    return $recipe->qxpath('//steps')->htmlOuter();
  }

  /**
   * The element implements the shared interface.
   */
  public function testImplementsTheInterface(): void {
    $this->assertInstanceOf(QuipXmlElementInterface::class, $this->recipe());
  }

  /**
   * Before() inserts a previous sibling.
   */
  public function testBefore(): void {
    $recipe = $this->recipe();
    $recipe->qxpath('//step[@n="2"]')->eq(0)->before('<step n="1.5">Rest</step>');

    $this->assertSame('<steps><step n="1">Whisk</step><step n="1.5">Rest</step><step n="2">Fold</step><step n="3">Bake</step></steps>', self::steps($recipe));
  }

  /**
   * After() inserts the next sibling, including after the last child.
   */
  public function testAfter(): void {
    $recipe = $this->recipe();
    $recipe->qxpath('//step[@n="3"]')->eq(0)->after('<step n="4">Cool</step>');

    $this->assertSame('<steps><step n="1">Whisk</step><step n="2">Fold</step><step n="3">Bake</step><step n="4">Cool</step></steps>', self::steps($recipe));
  }

  /**
   * Append() adds a last child.
   */
  public function testAppend(): void {
    $recipe = $this->recipe();
    $recipe->steps->append('<step n="4">Cool</step>');

    $this->assertSame('Cool', (string) $recipe->steps->step[3]);
  }

  /**
   * The insert verbs work on an element with no children and no attributes.
   */
  public function testInsertVerbsWorkOnAnEmptyElement(): void {
    $recipe = $this->recipe();
    $recipe->notes->append('<p>Use fresh lemons</p>')->before('<hr/>')->after('<footer/>');

    $this->assertSame('<recipe><title>Lemon cake</title><steps><step n="1">Whisk</step><step n="2">Fold</step><step n="3">Bake</step></steps><hr/><notes><p>Use fresh lemons</p></notes><footer/></recipe>', $recipe->htmlOuter());
  }

  /**
   * Wrap() puts the node inside new markup.
   */
  public function testWrap(): void {
    $recipe = $this->recipe();
    $recipe->title->wrap('<header/>');

    $this->assertSame('Lemon cake', (string) $recipe->header->title);
  }

  /**
   * WrapInner() puts the node's children inside new markup.
   */
  public function testWrapInner(): void {
    $recipe = $this->recipe();
    $recipe->steps->wrapInner('<ol/>');

    $this->assertCount(3, $recipe->qxpath('/recipe/steps/ol/step'));
  }

  /**
   * Unwrap() removes the parent and keeps the node attached.
   */
  public function testUnwrap(): void {
    $recipe = $this->recipe();
    $step = $recipe->qxpath('//step[@n="1"]')->eq(0);
    $result = $step->unwrap();

    $this->assertSame($step, $result);
    $this->assertCount(0, $recipe->qxpath('//steps'));
    $this->assertCount(3, $recipe->qxpath('/recipe/step'));
    $this->assertSame('Whisk', $step->text());
  }

  /**
   * Unwrap() never removes the root, which has no parent to take its place.
   */
  public function testUnwrapNeverRemovesTheRoot(): void {
    $recipe = $this->recipe();
    $before = $recipe->htmlOuter();

    $this->assertTrue(self::isEmptyResult($recipe->unwrap()));
    $this->assertTrue(self::isEmptyResult($recipe->title->unwrap()));
    $this->assertSame($before, $recipe->htmlOuter());
  }

  /**
   * SetTag() with a bare name keeps the attributes and children.
   */
  public function testSetTagKeepsAttributes(): void {
    $recipe = $this->recipe();
    $new = $recipe->qxpath('//step[@n="2"]')->eq(0)->setTag('task');

    $this->assertSame('<task n="2">Fold</task>', $new->htmlOuter());
    $this->assertCount(2, $recipe->qxpath('//step'));
  }

  /**
   * SetTag() with an opening tag takes that tag's attributes instead.
   */
  public function testSetTagWithAnOpeningTag(): void {
    $recipe = $this->recipe();
    $recipe->qxpath('//step[@n="2"]')->eq(0)->setTag('<task kind="gentle">');

    $this->assertSame('<task kind="gentle">Fold</task>', $recipe->qxpath('//task')->htmlOuter());
  }

  /**
   * SetTag() on an empty element carrying an attribute stays well formed.
   */
  public function testSetTagOnAnEmptyElementWithAnAttribute(): void {
    $row = Quip::load('<tr><td colspan="2"/></tr>');
    $row->td->setTag('th');

    $this->assertSame('<tr><th colspan="2"/></tr>', $row->htmlOuter());
  }

  /**
   * Remove() deletes the node and reports it.
   */
  public function testRemove(): void {
    $recipe = $this->recipe();

    $this->assertTrue($recipe->qxpath('//step[@n="1"]')->eq(0)->remove());
    $this->assertCount(2, $recipe->qxpath('//step'));
    $this->assertFalse($recipe->qxpath('//nothing')->remove());
  }

  /**
   * Html() reads inner markup as XML, so an empty element self-closes.
   */
  public function testHtmlReadsInnerXml(): void {
    $quip = Quip::load('<p>Line one<br/>line two</p>');

    $this->assertSame('Line one<br/>line two', $quip->html());
  }

  /**
   * Html() with markup replaces the children.
   */
  public function testHtmlWritesInnerMarkup(): void {
    $recipe = $this->recipe();
    $recipe->title->html('Lemon <em>drizzle</em> cake');

    $this->assertSame('<title>Lemon <em>drizzle</em> cake</title>', $recipe->title->htmlOuter());
  }

  /**
   * Html() with a formatter returns indented children.
   */
  public function testHtmlWithFormatter(): void {
    $quip = Quip::load('<list><a>1</a><b>2</b></list>');

    $this->assertSame('<a>1</a><b>2</b>', $quip->html(Quip::formatter()));
  }

  /**
   * HtmlOuter() reads the node's own markup without an XML declaration.
   */
  public function testHtmlOuter(): void {
    $this->assertSame('<title>Lemon cake</title>', $this->recipe()->title->htmlOuter());
  }

  /**
   * Text() strips tags but keeps entities as written.
   */
  public function testTextKeepsEntities(): void {
    $quip = Quip::load('<p>Fish <b>&amp;</b> chips</p>');

    $this->assertSame('Fish &amp; chips', $quip->text());
  }

  /**
   * Text() with content replaces the children and escapes a bare ampersand.
   */
  public function testTextWritesContent(): void {
    $recipe = $this->recipe();
    $recipe->title->text('Salt & pepper');

    $this->assertSame('<title>Salt &amp; pepper</title>', $recipe->title->htmlOuter());
    $recipe->title->text(42);
    $this->assertSame('42', (string) $recipe->title);
  }

  /**
   * Writing into a missing child throws, as there is nowhere to write.
   */
  #[DataProvider('missingChildWrites')]
  public function testWritingToMissingChildThrows(string $verb): void {
    $this->expectException(NotPermanentMemberException::class);
    $this->recipe()->missing->{$verb}('x');
  }

  /**
   * The writes that need an existing node.
   *
   * @return array<string, array{string}>
   *   Verb names.
   */
  public static function missingChildWrites(): array {
    return [
      'html' => ['html'],
      'text' => ['text'],
    ];
  }

  /**
   * Get() returns an existing path.
   */
  public function testGetFindsAnExistingPath(): void {
    $this->assertSame('Bake', (string) $this->recipe()->get('//steps/step[3]'));
  }

  /**
   * Get() creates the missing part of a path.
   */
  public function testGetCreatesTheMissingPath(): void {
    $recipe = $this->recipe();
    $recipe->get('//notes/tip/text')->text('Zest first');

    $this->assertSame('<notes><tip><text>Zest first</text></tip></notes>', $recipe->notes->htmlOuter());
  }

  /**
   * Get() adds siblings until a positional filter can be met.
   */
  public function testGetCreatesPositionalSiblings(): void {
    $recipe = $this->recipe();
    $created = $recipe->get('//notes/tip[2]');
    $created->text('second');

    $this->assertSame('<notes><tip/><tip>second</tip></notes>', $recipe->notes->htmlOuter());
  }

  /**
   * Get() refuses to create an axis or a wildcard.
   */
  public function testGetRefusesComplexCreation(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->recipe()->get('//notes/*/tip');
  }

  /**
   * Assigning a property and get() build the same child.
   */
  public function testPropertyAssignmentMatchesGet(): void {
    $assigned = $this->recipe();
    $assigned->notes->tip = 'Zest first';
    $built = $this->recipe();
    $built->get('//notes/tip')->text('Zest first');

    $this->assertSame($assigned->htmlOuter(), $built->htmlOuter());
  }

  /**
   * Dom() exposes the DOM node, and FALSE for anything else.
   */
  public function testDom(): void {
    $recipe = $this->recipe();

    $this->assertInstanceOf(\DOMElement::class, $recipe->title->dom());
    $this->assertFalse($recipe->title->dom(1));
    $this->assertFalse($recipe->missing->dom());
  }

  /**
   * Eq() on one node returns that node.
   */
  public function testEqReturnsItself(): void {
    $title = $this->recipe()->title;

    $this->assertSame($title, $title->eq());
  }

  /**
   * AddChild() returns a Quip node.
   */
  public function testAddChildReturnsQuipNode(): void {
    $child = $this->recipe()->notes->addChild('tip', 'Zest first');

    $this->assertInstanceOf(QuipXmlElement::class, $child);
    $this->assertSame('<tip>Zest first</tip>', $child->htmlOuter());
  }

  /**
   * Qxpath() returns a set for matches.
   */
  public function testQxpathReturnsSet(): void {
    $steps = $this->recipe()->qxpath('//step');

    $this->assertInstanceOf(QuipXmlElementIterator::class, $steps);
    $this->assertCount(3, $steps);
  }

  /**
   * Qxpath() returns an empty element when nothing matched.
   */
  public function testQxpathReturnsEmptyForNoMatch(): void {
    $none = $this->recipe()->qxpath('//nothing');

    $this->assertInstanceOf(QuipXmlElement::class, $none);
    $this->assertTrue(self::isEmptyResult($none));
    $this->assertCount(0, $none);
    $this->assertSame('', $none->html());
    $this->assertSame('', $none->text());
  }

  /**
   * An empty result can still run an absolute query on its document.
   */
  public function testEmptyResultKeepsItsDocument(): void {
    $recipe = $this->recipe();

    $this->assertFalse(self::isEmptyResult($recipe->qxpath('//nothing')->qxpath('//title')));
    $this->assertFalse(self::isEmptyResult($recipe->qxpath('//nothing')->qxpath('//nowhere')->qxpath('//title')));
    $this->assertTrue(self::isEmptyResult($recipe->qxpath('//nothing')->qxpath('./title')));
  }

  /**
   * Verbs on an empty result change nothing.
   */
  public function testVerbsOnAnEmptyResultAreNoOps(): void {
    $recipe = $this->recipe();
    $before = $recipe->htmlOuter();
    $none = $recipe->qxpath('//nothing');
    $none->after('<x/>')->before('<x/>')->append('<x/>')->wrap('<x/>')->wrapInner('<x/>');
    $recipe->missing->after('<x/>')->before('<x/>');

    $this->assertSame($before, $recipe->htmlOuter());
  }

  /**
   * Qxparent() and qxprev() step up and back.
   */
  public function testQxparentAndQxprev(): void {
    $step = $this->recipe()->qxpath('//step[@n="2"]')->eq(0);

    $this->assertSame('steps', $step->qxparent()->getName());
    $this->assertSame('Whisk', $step->qxprev()->text());
    $this->assertTrue(self::isEmptyResult($step->qxprev()->qxprev()));
  }

  /**
   * An attribute node reads, writes and removes its value.
   */
  public function testAttributeNodeValue(): void {
    $recipe = $this->recipe();
    $n = $recipe->qxpath('//step[@n="2"]/@n')->eq(0);

    $this->assertSame('2', $n->text());
    $this->assertSame('2', $n->html());
    $this->assertSame('n="2"', $n->htmlOuter());
    $n->text('two');
    $this->assertSame('two', (string) $recipe->steps->step[1]['n']);
    $this->assertTrue($n->remove());
    $this->assertNull($recipe->steps->step[1]['n']);
  }

  /**
   * Structural verbs refuse an attribute node.
   */
  #[DataProvider('structuralVerbs')]
  public function testStructuralVerbsRefuseAnAttribute(string $verb, ?string $argument): void {
    $n = $this->recipe()->qxpath('//step/@n')->eq(0);

    $this->expectException(\LogicException::class);
    $argument === NULL ? $n->{$verb}() : $n->{$verb}($argument);
  }

  /**
   * The verbs that need an element.
   *
   * @return array<string, array{string, string|null}>
   *   Verb names and arguments.
   */
  public static function structuralVerbs(): array {
    return [
      'before' => ['before', '<x/>'],
      'after' => ['after', '<x/>'],
      'append' => ['append', '<x/>'],
      'wrap' => ['wrap', '<x/>'],
      'wrapInner' => ['wrapInner', '<x/>'],
      'setTag' => ['setTag', 'x'],
      'unwrap' => ['unwrap', NULL],
      'html' => ['html', '<x/>'],
    ];
  }

  /**
   * The retired query methods throw with the replacement's name.
   */
  #[DataProvider('retiredMethods')]
  public function testRetiredMethodsThrow(string $method, string $message): void {
    $title = $this->recipe()->title;

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($message);
    $method === 'xpath' ? $title->xpath('..') : $title->{$method}();
  }

  /**
   * The retired methods and their messages.
   *
   * @return array<string, array{string, string}>
   *   Method names and messages.
   */
  public static function retiredMethods(): array {
    return [
      'xpath' => ['xpath', 'QuipXml 1.0 retired xpath(); call qxpath(), which returns a QuipXmlElementIterator.'],
      'xparent' => ['xparent', 'QuipXml 1.0 retired xparent(); call qxparent().'],
      'xprev' => ['xprev', 'QuipXml 1.0 retired xprev(); call qxprev().'],
    ];
  }

  /**
   * Whether a result is an empty result rather than a node or set.
   */
  private static function isEmptyResult(QuipXmlElementInterface $result): bool {
    return $result instanceof QuipXmlElement && $result->dom() === FALSE;
  }

}
