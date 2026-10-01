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

namespace QuipXml\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QuipXml\Quip;
use QuipXml\Xml\QuipXmlElement;
use QuipXml\Xml\QuipXmlElementInterface;
use QuipXml\Xml\QuipXmlElementIterator;

/**
 * Tests a set of matched nodes.
 */
#[CoversClass(QuipXmlElementIterator::class)]
final class QuipXmlElementIteratorTest extends TestCase {

  /**
   * A small timetable with several matches for most queries.
   */
  private const TIMETABLE = '<timetable><day name="mon"><slot at="9">Yoga</slot><slot at="11">Swim</slot></day><day name="tue"><slot at="10">Pilates</slot></day><day name="wed"/></timetable>';

  /**
   * Loads a fresh copy of the timetable.
   */
  private function timetable(): QuipXmlElement {
    return Quip::load(self::TIMETABLE);
  }

  /**
   * Returns every slot as a set.
   */
  private function slots(QuipXmlElement $timetable): QuipXmlElementIterator {
    $slots = $timetable->qxpath('//slot');
    $this->assertInstanceOf(QuipXmlElementIterator::class, $slots);
    return $slots;
  }

  /**
   * The set implements the shared interface and counts its nodes.
   */
  public function testCountsItsNodes(): void {
    $timetable = $this->timetable();

    $this->assertInstanceOf(QuipXmlElementInterface::class, $this->slots($timetable));
    $this->assertCount(3, $this->slots($timetable));
    $this->assertCount(3, $timetable->qxpath('//day'));
  }

  /**
   * The set holds each node once and skips empty results.
   */
  public function testDeduplicatesAndSkipsEmpty(): void {
    $timetable = $this->timetable();
    $mon = $timetable->day[0];
    $set = new QuipXmlElementIterator([$mon, $timetable->day[0], $timetable->missing, 'not a node']);

    $this->assertCount(1, $set);
  }

  /**
   * An empty set reads as empty.
   */
  public function testEmptySet(): void {
    $set = new QuipXmlElementIterator([]);

    $this->assertCount(0, $set);
    $this->assertSame('', $set->html());
    $this->assertSame('', $set->text());
    $this->assertSame('', $set->getName());
    $this->assertSame('', (string) $set);
    $this->assertFalse($set->asXML());
    $this->assertTrue(self::isEmptyResult($set->eq()));
  }

  /**
   * Getters read the first node.
   */
  public function testGettersReadTheFirstNode(): void {
    $slots = $this->slots($this->timetable());

    $this->assertSame('Yoga', $slots->html());
    $this->assertSame('Yoga', $slots->text());
    $this->assertSame('<slot at="9">Yoga</slot>', $slots->htmlOuter());
    $this->assertSame('slot', $slots->getName());
    $this->assertSame('<slot at="9">Yoga</slot>', $slots->asXML());
    $this->assertSame($slots->asXML(), $slots->saveXML());
  }

  /**
   * Casting to string concatenates every node's text.
   */
  public function testStringConcatenatesEveryNode(): void {
    $this->assertSame('YogaSwimPilates', (string) $this->slots($this->timetable()));
  }

  /**
   * Eq() and dom() pick one node by position.
   */
  public function testEqAndDom(): void {
    $slots = $this->slots($this->timetable());

    $this->assertSame('Swim', (string) $slots->eq(1));
    $this->assertTrue(self::isEmptyResult($slots->eq(9)));
    $this->assertInstanceOf(\DOMElement::class, $slots->dom(2));
    $this->assertFalse($slots->dom(9));
  }

  /**
   * Insert and wrap verbs apply to every node.
   */
  public function testVerbsApplyToEveryNode(): void {
    $timetable = $this->timetable();
    $this->slots($timetable)->wrap('<b/>')->after('<hr/>');

    $this->assertCount(3, $timetable->qxpath('//b/slot'));
    $this->assertCount(3, $timetable->qxpath('//b/slot/following-sibling::*[1][self::hr]'));
  }

  /**
   * Append(), before() and wrapInner() apply to every node.
   */
  public function testAppendBeforeWrapInner(): void {
    $timetable = $this->timetable();
    $days = $timetable->qxpath('//day');
    $days->append('<slot at="18">Stretch</slot>')->before('<sep/>')->wrapInner('<slots/>');

    $this->assertCount(3, $timetable->qxpath('//day/slots/slot[@at="18"]'));
    $this->assertCount(3, $timetable->qxpath('//sep'));
  }

  /**
   * Text() and html() with content write every node.
   */
  public function testWritersApplyToEveryNode(): void {
    $timetable = $this->timetable();
    $slots = $this->slots($timetable);
    $slots->text('Closed');

    $this->assertSame('ClosedClosedClosed', (string) $slots);
    $slots->html('<em>Booked</em>');
    $this->assertCount(3, $timetable->qxpath('//slot/em'));
  }

  /**
   * Remove() deletes every node and reports whether any went.
   */
  public function testRemove(): void {
    $timetable = $this->timetable();

    $this->assertTrue($this->slots($timetable)->remove());
    $this->assertCount(0, $timetable->qxpath('//slot'));
  }

  /**
   * Unwrap() removes every node's parent.
   */
  public function testUnwrap(): void {
    $timetable = $this->timetable();
    $this->slots($timetable)->unwrap();

    $this->assertCount(0, $timetable->qxpath('//day[slot]'));
    $this->assertCount(3, $timetable->qxpath('/timetable/slot'));
  }

  /**
   * SetTag() renames every node and returns the new set.
   */
  public function testSetTag(): void {
    $timetable = $this->timetable();
    $classes = $this->slots($timetable)->setTag('class');

    $this->assertCount(3, $classes);
    $this->assertSame('<class at="9">Yoga</class>', $classes->htmlOuter());
  }

  /**
   * A relative query runs from every node; an absolute one runs once.
   */
  public function testQxpath(): void {
    $timetable = $this->timetable();
    $days = $timetable->qxpath('//day');

    $this->assertCount(3, $days->qxpath('./slot'));
    $this->assertCount(3, $days->qxpath('//slot'));
    $this->assertTrue(self::isEmptyResult($days->qxpath('./nothing')));
  }

  /**
   * Qxparent() and qxprev() collect every node's result once.
   */
  public function testQxparentAndQxprev(): void {
    $slots = $this->slots($this->timetable());

    $this->assertCount(2, $slots->qxparent());
    $this->assertSame('Yoga', (string) $slots->qxprev());
  }

  /**
   * Get() finds or creates the path under every node.
   */
  public function testGet(): void {
    $timetable = $this->timetable();
    $notes = $timetable->qxpath('//day')->get('note');

    $this->assertCount(3, $notes);
    $this->assertCount(3, $timetable->qxpath('//day/note'));
  }

  /**
   * AddChild() and addAttribute() apply to every node.
   */
  public function testAddChildAndAttribute(): void {
    $timetable = $this->timetable();
    $days = $timetable->qxpath('//day');
    $added = $days->addChild('note', 'Bring water');
    $days->addAttribute('open', 'yes');

    $this->assertNotNull($added);
    $this->assertCount(3, $added);
    $this->assertCount(3, $timetable->qxpath('//day[@open="yes"]/note'));
  }

  /**
   * Children() flattens every node's children into one set.
   */
  public function testChildrenFlatten(): void {
    $children = $this->timetable()->qxpath('//day')->children();
    $this->assertNotNull($children);

    $this->assertCount(3, $children);
    $this->assertSame('YogaSwimPilates', (string) $children);
  }

  /**
   * Attributes() flattens every node's attributes into one set.
   */
  public function testAttributesFlatten(): void {
    $attributes = $this->slots($this->timetable())->attributes();

    $this->assertCount(3, $attributes);
    $this->assertSame('at', $attributes->getName());
    $this->assertSame('9', $attributes->text());
    $this->assertSame('91110', (string) $attributes);
  }

  /**
   * An attribute set writes and removes every value.
   */
  public function testAttributeSetWritesAndRemoves(): void {
    $timetable = $this->timetable();
    $attributes = $this->slots($timetable)->attributes();
    $attributes->text('tbc');

    $this->assertCount(3, $timetable->qxpath('//slot[@at="tbc"]'));
    $this->assertTrue($attributes->remove());
    $this->assertCount(0, $timetable->qxpath('//slot[@at]'));
  }

  /**
   * Structural verbs refuse an attribute set.
   */
  public function testAttributeSetRefusesStructuralVerbs(): void {
    $this->expectException(\LogicException::class);
    $this->slots($this->timetable())->attributes()->wrap('<x/>');
  }

  /**
   * Namespace getters merge every node's namespaces.
   */
  public function testNamespacesMerge(): void {
    $doc = Quip::load('<r xmlns:a="urn:a"><a:x xmlns:b="urn:b"><b:k/></a:x><y xmlns:c="urn:c"><c:z/></y></r>');
    $children = $doc->qxpath('/r/*');

    $this->assertSame(['a' => 'urn:a'], $children->getNamespaces());
    $this->assertSame(['a' => 'urn:a', 'b' => 'urn:b', 'c' => 'urn:c'], $children->getNamespaces(TRUE));
    $this->assertSame(['b' => 'urn:b', 'c' => 'urn:c'], $children->getDocNamespaces(FALSE, FALSE));
  }

  /**
   * A prefix registered on the set reaches every node's queries.
   */
  public function testRegisterXpathNamespace(): void {
    $doc = Quip::load('<r xmlns:w="urn:weather"><day><w:sky>clear</w:sky></day><day><w:sky>grey</w:sky></day></r>');
    $days = $doc->qxpath('//day');

    $this->assertTrue($days->registerXPathNamespace('wx', 'urn:weather'));
    $this->assertSame('cleargrey', (string) $days->qxpath('./wx:sky'));
  }

  /**
   * Reading a property returns the single node's children, or all of them.
   */
  public function testPropertyRead(): void {
    $timetable = $this->timetable();

    $this->assertSame('Yoga', (string) $timetable->qxpath('//day[@name="mon"]')->slot);
    $this->assertCount(3, $timetable->qxpath('//day')->slot);
  }

  /**
   * Writing a property writes it on every node.
   */
  public function testPropertyWrite(): void {
    $timetable = $this->timetable();
    $timetable->qxpath('//day')->note = 'Bring water';

    $this->assertCount(3, $timetable->qxpath('//day/note'));
  }

  /**
   * Iterating yields every node in order.
   */
  public function testIteration(): void {
    $names = [];
    foreach ($this->timetable()->qxpath('//day') as $day) {
      $names[] = (string) $day['name'];
    }

    $this->assertSame(['mon', 'tue', 'wed'], $names);
  }

  /**
   * The retired query methods throw with the replacement's name.
   */
  #[DataProvider('retiredMethods')]
  public function testRetiredMethodsThrow(string $method, string $message): void {
    $slots = $this->slots($this->timetable());

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($message);
    $method === 'xpath' ? $slots->xpath('..') : $slots->{$method}();
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
   * Unwrap() removes each shared parent once and nothing above it.
   */
  public function testUnwrapRemovesEachParentOnce(): void {
    $doc = Quip::load('<r><g><p><a/><b/></p></g><q><c/></q></r>');
    $doc->qxpath('//p/* | //q/*')->unwrap();

    $this->assertSame('<r><g><a/><b/></g><c/></r>', $doc->htmlOuter());
  }

  /**
   * Attributes() flattens several attributes from several nodes.
   */
  public function testAttributesFlattenSeveralPerNode(): void {
    $attributes = Quip::load('<r><i a="1" b="2"/><i c="3"/></r>')->qxpath('//i')->attributes();

    $this->assertNotNull($attributes);
    $this->assertCount(3, $attributes);
    $this->assertSame('123', (string) $attributes);
  }

  /**
   * The first node's namespace wins a shared prefix.
   */
  public function testNamespaceMergeKeepsTheFirst(): void {
    $doc = Quip::load('<r><x xmlns:p="urn:one"><p:a/></x><y xmlns:p="urn:two"><p:b/></y></r>');

    $this->assertSame(['p' => 'urn:one'], $doc->qxpath('/r/*')->getNamespaces(TRUE));
  }

  /**
   * A property no node has reads as an empty result, and isset() agrees.
   */
  public function testMissingPropertyIsEmpty(): void {
    $days = $this->timetable()->qxpath('//day');

    $this->assertTrue(self::isEmptyResult($days->nothing));
    $this->assertFalse(isset($days->nothing));
    $this->assertTrue(isset($days->slot));
  }

  /**
   * Children flattened from a set keep the set's registered prefixes (#5).
   */
  public function testFlattenedChildrenKeepRegisteredPrefixes(): void {
    $doc = Quip::load('<r xmlns="urn:r"><day><slot/></day><day><slot/></day></r>');
    $doc->registerXPathNamespace('t', 'urn:r');
    $slots = $doc->qxpath('//t:day')->children();

    $this->assertNotNull($slots);
    $this->assertCount(2, $slots->qxpath('self::t:slot'));
  }

  /**
   * Whether a result is an empty result rather than a node or set.
   */
  private static function isEmptyResult(QuipXmlElementInterface $result): bool {
    return $result instanceof QuipXmlElement && $result->dom() === FALSE;
  }

}
