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

}