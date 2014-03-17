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

use QuipXml\Quip;
class BasicTest extends \PHPUnit_Framework_TestCase {
  protected $formatter = NULL;
  public function __construct() {
    parent::__construct();
    $this->formatter = Quip::formatter();
  }

  public function testXmlBasicList() {
    $formatter = $this->formatter;

    // Test after, parent_, html and htmlOuter
    $quip = Quip::load(__DIR__ . '/Resources/XmlBasicList.xml', 0, TRUE);
    $add = $quip->xpath("//arg[@id = 'new-content']")->html();
    $tgt = $quip->xpath("//original//item[@class = 'target']");
    $actual = $tgt->after($add)->parent_()->htmlOuter($formatter);
    $expected = $quip->xpath("//output[@method = 'after']")->html($formatter);
    $this->assertEquals($expected, $actual);

    // Test before, parent_, html and htmlOuter
    $quip = Quip::load(__DIR__ . '/Resources/XmlBasicList.xml', 0, TRUE);
    $add = $quip->xpath("//arg[@id = 'new-content']")->html();
    $tgt = $quip->xpath("//original//item[@class = 'target']");
    $actual = $tgt->before($add)->parent_()->htmlOuter($formatter);
    $expected = $quip->xpath("//output[@method = 'before']")->html($formatter);
    $this->assertEquals($expected, $actual);

    // Test SimpleXml traversal, before, parent_, html and htmlOuter
    $quip = Quip::load(__DIR__ . '/Resources/XmlBasicList.xml', 0, TRUE);
    $add = $quip->xpath("//arg[@id = 'new-content']")->html();
    $tgt = $quip->original->list->xpath("./item[@class = 'target']");
    $actual = $tgt->before($add)->after($add)->parent_()->htmlOuter($formatter);
    $expected = $quip->xpath("//output[@method = 'before-after']")->html($formatter);
    $this->assertEquals($expected, $actual);
  }

  public function testSetNewChild() {
    $quip = Quip::load(__DIR__ . '/Resources/XmlBasicList.xml', 0, TRUE);
    $orig = $quip->xpath('//original')->eq();
    $orig->x = 1;
    $expected = $orig->html($this->formatter);

    $quip = Quip::load(__DIR__ . '/Resources/XmlBasicList.xml', 0, TRUE);
    $orig = $quip->xpath('//original');
    $orig->x = 1;
    $actual = $orig->html($this->formatter);

    $this->assertEquals($expected, $actual);
  }

  public function testMixTraversal() {
    $quip = Quip::load(__DIR__ . '/Resources/XmlBasicList.xml', 0, TRUE);
    $expected = array(
      'one',
      'two',
      'three',
      'four',
      'five',
    );
    $test1 = $expected;
    foreach ($quip->xpath('//original/list')->item as $item) {
      $this->assertEquals(array_shift($test1), trim($item));
    }
    $this->assertEmpty($test1);

    $test2 = $expected;
    foreach ($quip->original->xpath('./list')->item as $item) {
      $this->assertEquals(array_shift($test2), trim($item));
    }
    $this->assertEmpty($test2);

    $test3 = $expected;
    foreach ($quip->xpath('//original')->xpath('./list')->item as $item) {
      $this->assertEquals(array_shift($test3), trim($item));
    }
    $this->assertEmpty($test3);

    $test4 = $expected;
    foreach ($quip->xpath('//original')->list->xpath('./item') as $item) {
      $this->assertEquals(array_shift($test4), trim($item));
    }
    $this->assertEmpty($test4);
  }

  public function testSurviveEmpty() {
    $quip = Quip::load(__DIR__ . '/Resources/XmlBasicList.xml', 0, TRUE);

    foreach ($quip->notfoundanywhere as $a) {
      $this->assertTrue(FALSE, 'SimpleXml skips foreach when not found');
    }

    foreach ($quip->xpath('//notfoundanywhere') as $a) {
      $this->assertTrue(FALSE, 'Quip skips foreach when not found');
    }

    foreach ($quip->xpath('//notfoundanywhere')->xpath('//original')->notfoundanywhere as $a) {
      $this->assertTrue(FALSE, 'Quip skips foreach when not found');
    }

    $missing = $quip->notfoundanywhere;
    $this->assertFalse((bool) $missing, 'SimpleXml element not found');
    $found = $missing->xpath('//original');
    $this->assertTrue((bool) $found, 'SimpleXml reference survives');

    $missing = $quip->xpath('//notfoundanywhere');
    $this->assertFalse((bool) $missing, 'SimpleXml element not found by xpath');
    $found = $missing->xpath('//original');
    $this->assertTrue((bool) $found, 'SimpleXml reference survives');

    $missing = $quip->xpath('//notfoundanywhere')->xpath('//stillnotfound');
    $this->assertFalse((bool) $missing, 'SimpleXml element not found by iterator xpath');
    $found = $missing->xpath('//original');
    $this->assertTrue((bool) $found, 'SimpleXml reference survives');

    $expected = $quip->html($this->formatter);
    $quip->notfoundanywhere->after('<div/>')->before('<div/>');
    $actual = $quip->html($this->formatter);
    $this->assertEquals($expected, $actual);
    $quip->xpath('//notfoundanywhere')->after('<div/>')->before('<div/>');
    $actual = $quip->html($this->formatter);
    $this->assertEquals($expected, $actual);
  }

  public function testTypeCast() {
    // SimpleXml conversion uses references.
    // Loading from SimpleXml preserves the original object.
    $expected = "TEST THE CAST";
    $sxml = simplexml_load_file(__DIR__ . '/Resources/XmlBasicList.xml');
    $quip = Quip::load($sxml);
    $sxml->original[0] = $expected;
    $actual = (string) $quip->original;
    $this->assertEquals($expected, $actual);
  }

}