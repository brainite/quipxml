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

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use QuipXml\Quip;

/**
 * Documents the memory each query result holds; excluded from the default run.
 */
#[CoversNothing]
#[Group('benchmark')]
final class PerformanceTest extends TestCase {

  /**
   * Measures the memory a set of several nodes and a set of one node hold.
   */
  public function testMultipleReferences(): void {
    $this->assertLessThanOrEqual(21400, $this->bytesPerQuery('//item'), 'A five-node set should stay under 21 KB.');
    $this->assertLessThanOrEqual(2250, $this->bytesPerQuery('//original'), 'A one-node set should stay under 2.2 KB.');
  }

  /**
   * Returns the average memory a held query result costs.
   */
  private function bytesPerQuery(string $path): float {
    $quip = Quip::load(__DIR__ . '/Resources/XmlBasicList.xml', 0, TRUE);
    $count = 1000;
    $held = [];
    $before = memory_get_usage();
    for ($i = 0; $i < $count; ++$i) {
      $held[] = $quip->qxpath($path);
    }
    $this->assertCount($count, $held);
    return round((memory_get_usage() - $before) / $count);
  }

}
