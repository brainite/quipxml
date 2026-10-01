<?php

/*
 * This file is part of the QuipXml package.
 *
 * (c) Greg Payne
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace QuipXml\Tests;

use QuipXml\Xml\QuipXmlFormatter;
use PHPUnit\Framework\TestCase;
use QuipXml\Quip;

/**
 *
 */
class BasicTest extends TestCase {
  protected $formatter = NULL;

  public function __construct() {
    parent::__construct();
    $this->formatter = Quip::formatter();
  }

  /**
   *
   */
  public function testCount() {
    $quip = Quip::load(__DIR__ . '/Resources/XmlBasicList.xml', 0, TRUE);

    $this->assertEquals(sizeof($quip->qxpath('//original')), 1);
    $this->assertEquals(sizeof($quip->qxpath('//original/x')), 0);
    $this->assertEquals(sizeof($quip->qxpath('//original/x[1]')), 0);
  }

  /**
   *
   */
  public function testXmlBasicList() {
    $formatter = $this->formatter;

    // Test after, xparent, html and htmlOuter.
    $quip = Quip::load(__DIR__ . '/Resources/XmlBasicList.xml', 0, TRUE);
    $add = $quip->qxpath("//arg[@id = 'new-content']")->html();
    $tgt = $quip->qxpath("//original//item[@class = 'target']");

    // Confirm that the $add content parses.
    $add_dom = Quip::load($add)->dom();
    $this->assertEquals($add_dom->nodeType, XML_ELEMENT_NODE);
    $this->assertEquals($add_dom->ownerDocument->nodeType, XML_DOCUMENT_NODE);

    // Apply changes and test.
    $actual = $tgt->after($add)->qxparent()->htmlOuter($formatter);
    $expected = $quip->qxpath("//output[@method = 'after']")->html($formatter);
    $this->assertEquals($expected, $actual);

    // Test before, xparent, html and htmlOuter.
    $quip = Quip::load(__DIR__ . '/Resources/XmlBasicList.xml', 0, TRUE);
    $add = $quip->qxpath("//arg[@id = 'new-content']")->html();
    $tgt = $quip->qxpath("//original//item[@class = 'target']");
    $actual = $tgt->before($add)->qxparent()->htmlOuter($formatter);
    $expected = $quip->qxpath("//output[@method = 'before']")->html($formatter);
    $this->assertEquals($expected, $actual);

    // Test SimpleXml traversal, before, xparent, html and htmlOuter.
    $quip = Quip::load(__DIR__ . '/Resources/XmlBasicList.xml', 0, TRUE);
    $add = $quip->qxpath("//arg[@id = 'new-content']")->html();
    $tgt = $quip->original->list->qxpath("./item[@class = 'target']");
    $actual = $tgt->before($add)->after($add)->qxparent()->htmlOuter($formatter);
    $expected = $quip->qxpath("//output[@method = 'before-after']")->html($formatter);
    $this->assertEquals($expected, $actual);
  }

  /**
   * @expectedException \QuipXml\Xml\Exception\NotPermanentMemberException
   */
  public function testSimpleXmlLimitsHtml() {
    $quip = Quip::load(__DIR__ . '/Resources/XmlBasicList.xml', 0, TRUE);
    $orig = $quip->qxpath('//original');
    $orig->x->html('1');
  }

  /**
   * @expectedException \QuipXml\Xml\Exception\NotPermanentMemberException
   */
  public function testSimpleXmlLimitsText() {
    $quip = Quip::load(__DIR__ . '/Resources/XmlBasicList.xml', 0, TRUE);
    $orig = $quip->qxpath('//original');
    $orig->x->text('1');
  }

  /**
   *
   */
  public function testSetNewChild() {
    $quip = Quip::load(__DIR__ . '/Resources/XmlBasicList.xml', 0, TRUE);
    $orig = $quip->qxpath('//original')->eq();
    $orig->x = 1;
    $expected = $orig->html($this->formatter);

    $quip = Quip::load(__DIR__ . '/Resources/XmlBasicList.xml', 0, TRUE);
    $orig = $quip->qxpath('//original');
    $orig->x = 1;
    $actual = $orig->html($this->formatter);
    $this->assertEquals($expected, $actual);

    $quip = Quip::load(__DIR__ . '/Resources/XmlBasicList.xml', 0, TRUE);
    $orig = $quip->qxpath('//original');
    $orig->get('x')->text('1');
    $actual = $orig->html($this->formatter);
    $this->assertEquals($expected, $actual);

    $quip = Quip::load(__DIR__ . '/Resources/XmlBasicList.xml', 0, TRUE);
    $orig = $quip->qxpath('//original');
    $orig->get('x')->html('1');
    $actual = $orig->html($this->formatter);
    $this->assertEquals($expected, $actual);

    $quip = Quip::load(__DIR__ . '/Resources/XmlBasicList.xml', 0, TRUE);
    $quip->get('//original/x[1]')->text('1');
    $orig = $quip->qxpath('//original');
    $actual = $orig->html($this->formatter);
    $this->assertEquals($expected, $actual);
  }

  /**
   *
   */
  public function testMixTraversal() {
    $quip = Quip::load(__DIR__ . '/Resources/XmlBasicList.xml', 0, TRUE);
    $expected = [
      'one',
      'two',
      'three',
      'four',
      'five',
    ];
    $test1 = $expected;
    foreach ($quip->qxpath('//original/list')->item as $item) {
      $this->assertEquals(array_shift($test1), trim($item));
    }
    $this->assertEmpty($test1);

    $test2 = $expected;
    foreach ($quip->original->qxpath('./list')->item as $item) {
      $this->assertEquals(array_shift($test2), trim($item));
    }
    $this->assertEmpty($test2);

    $test3 = $expected;
    foreach ($quip->qxpath('//original')->qxpath('./list')->item as $item) {
      $this->assertEquals(array_shift($test3), trim($item));
    }
    $this->assertEmpty($test3);

    $test4 = $expected;
    foreach ($quip->qxpath('//original')->list->qxpath('./item') as $item) {
      $this->assertEquals(array_shift($test4), trim($item));
    }
    $this->assertEmpty($test4);
  }

  /**
   *
   */
  public function testNbsp() {
    $test = '<div>Hello&nbsp;World!</div>';
    $expected = '<div>Hello&nbsp;World!</div>';
    $quip = Quip::load($test);
    $actual = $quip->htmlOuter();
    $this->assertEquals($expected, $actual);
    $actual = $quip->htmlOuter(new QuipXmlFormatter());
    $this->assertEquals($expected, $actual);
  }

  /**
   *
   */
  public function testSurviveEmpty() {
    $quip = Quip::load(__DIR__ . '/Resources/XmlBasicList.xml', 0, TRUE);

    foreach ($quip->notfoundanywhere as $a) {
      $this->assertTrue(FALSE, 'SimpleXml skips foreach when not found');
    }

    foreach ($quip->qxpath('//notfoundanywhere') as $a) {
      $this->assertTrue(FALSE, 'Quip skips foreach when not found');
    }

    foreach ($quip->qxpath('//notfoundanywhere')->qxpath('//original')->notfoundanywhere as $a) {
      $this->assertTrue(FALSE, 'Quip skips foreach when not found');
    }

    $missing = $quip->notfoundanywhere;
    $this->assertFalse((bool) $missing, 'SimpleXml element not found');
    $found = $missing->qxpath('//original');
    $this->assertTrue((bool) $found, 'SimpleXml reference survives');

    $missing = $quip->qxpath('//notfoundanywhere');
    $this->assertFalse((bool) $missing, 'SimpleXml element not found by xpath');
    $found = $missing->qxpath('//original');
    $this->assertTrue((bool) $found, 'SimpleXml reference survives');

    $missing = $quip->qxpath('//notfoundanywhere')->qxpath('//stillnotfound');
    $this->assertFalse((bool) $missing, 'SimpleXml element not found by iterator xpath');
    $found = $missing->qxpath('//original');
    $this->assertTrue((bool) $found, 'SimpleXml reference survives');

    $expected = $quip->html($this->formatter);
    $quip->notfoundanywhere->after('<div/>')->before('<div/>');
    $actual = $quip->html($this->formatter);
    $this->assertEquals($expected, $actual);
    $quip->qxpath('//notfoundanywhere')->after('<div/>')->before('<div/>');
    $actual = $quip->html($this->formatter);
    $this->assertEquals($expected, $actual);
  }

  /**
   *
   */
  public function testTypeCast() {
    // SimpleXml conversion uses references.
    // Loading from SimpleXml preserves the original object.
    $expected = "TEST THE CAST";
    $sxml = simplexml_load_file(__DIR__ . '/Resources/XmlBasicList.xml');
    $quip = Quip::load($sxml);
    $sxml->original = $expected;
    $actual = (string) $quip->original;
    $this->assertEquals($expected, $actual);
  }

  /**
   *
   */
  public function testWrapUnwrap() {
    $quip = Quip::load(__DIR__ . '/Resources/XmlBasicList.xml', 0, TRUE);
    $expected = $quip->qxpath("//output[@method = 'list-newlist']")->html($this->formatter);

    $quip = Quip::load(__DIR__ . '/Resources/XmlBasicList.xml', 0, TRUE);
    $tgt = $quip->qxpath("//original//item[@class = 'target']");
    $tgt->qxparent()->wrap('<newlist/>');
    $tgt->unwrap();
    $actual = $quip->original->html($this->formatter);
    $this->assertEquals($expected, $actual);

    $quip = Quip::load(__DIR__ . '/Resources/XmlBasicList.xml', 0, TRUE);
    $quip->original->wrapInner('<newlist />');
    $quip->original->newlist->html($quip->qxpath("//original//list")->html());
    $actual = $quip->original->html($this->formatter);
    $this->assertEquals($expected, $actual);

    $quip = Quip::load(__DIR__ . '/Resources/XmlBasicList.xml', 0, TRUE);
    $list = $quip->qxpath("//original//item")->qxparent()->wrapInner('<newlist />')->qxpath("./*[1]")->unwrap();
    $actual = $quip->original->html($this->formatter);
    $this->assertEquals($expected, $actual);
  }

}
