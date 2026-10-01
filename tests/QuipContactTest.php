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
use PHPUnit\Framework\TestCase;
use QuipXml\Contact\QuipContact;
use QuipXml\Contact\QuipContactVcfFormatter;

/**
 * Tests vCard loading and the vCard formatter.
 */
#[CoversClass(QuipContact::class)]
#[CoversClass(QuipContactVcfFormatter::class)]
final class QuipContactTest extends TestCase {

  /**
   * Builds a vCard document from lines, joined with CRLF.
   *
   * @param string ...$lines
   *   The lines, without line endings.
   *
   * @return string
   *   The document.
   */
  private static function vcf(string ...$lines): string {
    return implode("\r\n", $lines) . "\r\n";
  }

  /**
   * Each card becomes a vcard element, its parameters attributes.
   */
  public function testLoadVcard(): void {
    $cards = QuipContact::loadVcard(self::vcf(
      'BEGIN:VCARD',
      'VERSION:3.0',
      'FN:Alex Example',
      'EMAIL;TYPE=INTERNET:alex@example.org',
      'END:VCARD',
      'BEGIN:VCARD',
      'VERSION:3.0',
      'FN:Sam Sample',
      'END:VCARD',
    ));

    $this->assertSame('vcards', $cards->getName());
    $this->assertCount(2, $cards->vcard);
    $this->assertSame('Alex Example', (string) $cards->vcard[0]->fn);
    $this->assertSame('alex@example.org', (string) $cards->vcard[0]->email);
    $this->assertSame('INTERNET', (string) $cards->vcard[0]->email['type']);
    $this->assertSame('Sam Sample', (string) $cards->vcard[1]->fn);
  }

  /**
   * A folded line is unfolded.
   */
  public function testLoadVcardUnfoldsLines(): void {
    $cards = QuipContact::loadVcard(self::vcf(
      'BEGIN:VCARD',
      'NOTE:Volunteers on ',
      ' Saturdays',
      'END:VCARD',
    ));

    $this->assertSame('Volunteers on Saturdays', (string) $cards->vcard->note);
  }

  /**
   * An empty contact carries its escaped formatted name.
   */
  public function testLoadEmpty(): void {
    $vcard = QuipContact::loadEmpty(['FN' => 'Example, Alex'])->vcard;

    $this->assertSame('3.0', (string) $vcard->version);
    $this->assertSame('QuipContact', (string) $vcard->prodid);
    $this->assertSame('', (string) $vcard->n);
    $this->assertSame('Example\\, Alex', (string) $vcard->fn);
  }

  /**
   * A card formats back to vCard text.
   */
  public function testFormat(): void {
    $cards = QuipContact::loadVcard(self::vcf(
      'BEGIN:VCARD',
      'VERSION:3.0',
      'FN:Alex Example',
      'EMAIL;TYPE=INTERNET:alex@example.org',
      'END:VCARD',
    ));

    $this->assertSame(self::vcf(
      'BEGIN:VCARD',
      'VERSION:3.0',
      'FN:Alex Example',
      'EMAIL;TYPE=INTERNET:alex@example.org',
      'END:VCARD',
    ), (new QuipContactVcfFormatter())->getFormattedOuter($cards->vcard));
  }

  /**
   * An ADR built from child elements is written as its structured value.
   */
  public function testFormatStructuredAddress(): void {
    $cards = QuipContact::loadVcard(self::vcf('BEGIN:VCARD', 'VERSION:3.0', 'END:VCARD'));
    $adr = $cards->vcard->addChild('adr');
    $this->assertNotNull($adr);
    $adr->addAttribute('type', 'HOME');
    $adr->addChild('street', '1 Example Road');
    $adr->addChild('locality', 'Exampleton');
    $adr->addChild('code', '12345');
    $adr->addChild('country', 'Examplia');

    $this->assertSame(self::vcf(
      'BEGIN:VCARD',
      'VERSION:3.0',
      'ADR;TYPE=HOME:;;1 Example Road;Exampleton;;12345;Examplia',
      'END:VCARD',
    ), (new QuipContactVcfFormatter())->getFormattedOuter($cards->vcard));
  }

}
