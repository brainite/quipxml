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
class PerformanceTest extends \PHPUnit\Framework\TestCase {
  public function testMultipleReferences() {
    // Document how much memory is used when additional iterators are instantiated.
    $quip = Quip::load(__DIR__ . '/Resources/XmlBasicList.xml', 0, TRUE);
    $count = 1000;
    $v = array();
    $pre = memory_get_usage(FALSE);
    for ($i = 0; $i < $count; ++$i) {
      $v[] = $quip->xpath("//item");
    }
    $post = memory_get_usage(FALSE);
    $memory_per_reference = round(($post - $pre) / $count);
    // This test is targeted to PHP 5.3
    // 5.4 and 5.5 both use 25% less memory
    $this->assertLessThanOrEqual(21400, $memory_per_reference, "Early tests only show 21K per additional iterator in context.");

    // Document how much memory is used when additional iterators are instantiated.
    $quip = Quip::load(__DIR__ . '/Resources/XmlBasicList.xml', 0, TRUE);
    $count = 1000;
    $v = array();
    $pre = memory_get_usage(FALSE);
    for ($i = 0; $i < $count; ++$i) {
      $v[] = $quip->xpath("//original");
    }
    $post = memory_get_usage(FALSE);
    $memory_per_reference = round(($post - $pre) / $count);
    //     var_dump($memory_per_reference);
    // This test is targeted to PHP 5.3
    // 5.4 and 5.5 both use 25% less memory
    $this->assertLessThanOrEqual(2250, $memory_per_reference, "Early tests only show 2.2K per additional 1-item iterator in context.");
  }

}