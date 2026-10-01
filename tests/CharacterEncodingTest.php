<?php

declare(strict_types=1);

/*
 * This file is part of the QuipXml package.
 *
 * (c) Greg Payne
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace QuipXml\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QuipXml\Encoding\CharacterEncoding;

/**
 * Tests HTML entity conversion and character-encoding helpers.
 */
#[CoversClass(CharacterEncoding::class)]
final class CharacterEncodingTest extends TestCase {

  /**
   * The 'ascii' preset transliterates accents and typographic punctuation.
   */
  public function testAsciiTransliteration(): void {
    $test = "—’–";
    $expected = "-'-";
    $actual = CharacterEncoding::toHtml($test, 'ascii');
    $this->assertEquals($expected, $actual);

    $test = "á Á à À â Â ä Ä ã Ã å Å æ Æ ç Ç é É è È ê Ê ë Ë í Í ì Ì î Î ï Ï ñ Ñ ó Ó ò Ò ô Ô ö Ö õ Õ ø Ø ú Ú ù Ù û Û ü Ü";
    $expected = "a A a A a A a A a A a A ae AE c C e E e E e E e E i I i I i I i I n N o O o O o O o O o O o O u U u U u U u U";
    $actual = CharacterEncoding::toHtml($test, 'ascii');
    $this->assertEquals($expected, $actual);
  }

  /**
   * The 'ascii' preset keeps characters it cannot transliterate as numbers.
   */
  public function testAsciiKeepsUntransliterableAsNumeric(): void {
    $this->assertSame('Creme "fresh" &#339; &#8240;', CharacterEncoding::toHtml('Crème “fresh” œ ‰', 'ascii'));
  }

  /**
   * Option sets and the text each turns the input into.
   *
   * @param string $source
   *   The input.
   * @param string|array<string, mixed>|null $params
   *   The preset or options.
   * @param string $expected
   *   The converted output.
   */
  #[DataProvider('providerToHtml')]
  public function testToHtml(string $source, string|array|null $params, string $expected): void {
    $this->assertSame($expected, CharacterEncoding::toHtml($source, $params));
  }

  /**
   * Data for testToHtml().
   *
   * @return array<string, array{string, string|array<string, mixed>|null, string}>
   *   Input, options and expected output.
   */
  public static function providerToHtml(): array {
    $menu = 'Crème brûlée — €4';
    return [
      'ascii text is unchanged' => ['Plain rye bread', NULL, 'Plain rye bread'],
      'named entities by default' => [
        $menu,
        NULL,
        'Cr&egrave;me br&ucirc;l&eacute;e &mdash; &euro;4',
      ],
      'named entities from utf-8' => [
        $menu,
        ['from_encoding' => 'UTF-8'],
        'Cr&egrave;me br&ucirc;l&eacute;e &mdash; &euro;4',
      ],
      'numeric entities' => [
        $menu,
        ['entities_prefer_numeric' => TRUE],
        'Cr&#232;me br&#251;l&#233;e &#8212; &#8364;4',
      ],
      'numeric entities from utf-8' => [
        $menu,
        ['from_encoding' => 'UTF-8', 'entities_prefer_numeric' => TRUE],
        'Cr&#232;me br&#251;l&#233;e &#8212; &#8364;4',
      ],
      'transliterate option' => [$menu, ['transliterate_ascii' => TRUE], 'Creme brulee - &#8364;4'],
      'unnamed characters stay numeric' => ['Pi 𝜋 day 🍰', NULL, 'Pi &#120587; day &#127856;'],
      'numeric input becomes named' => ['&#233; and &#x2014;', NULL, '&eacute; and &mdash;'],
      'named input becomes numeric' => [
        '&eacute; and &mdash;',
        ['entities_prefer_numeric' => TRUE],
        '&#233; and &#8212;',
      ],
      'carriage returns removed' => ["Line one\r\nLine two", NULL, "Line one\nLine two"],
      'carriage returns kept' => [
        "Line one\r\nLine two",
        ['remove_carriage_return' => FALSE],
        "Line one\r\nLine two",
      ],
      'tags ignored' => ['<b>Rye</b> loaf', ['tags' => 'ignore'], '<b>Rye</b> loaf'],
      'tags disabled' => ['<b>Rye</b> loaf', ['tags' => 'disable'], '&lt;b&gt;Rye&lt;/b&gt; loaf'],
      'tags removed' => ['<b>Rye</b> loaf', ['tags' => 'remove'], 'Rye loaf'],
      'unknown preset is ignored' => ['Rye & spelt', 'no such preset', 'Rye & spelt'],
      'latin-1 source' => [
        "Caf\xe9 au lait",
        ['from_encoding' => 'ISO-8859-1'],
        'Caf&eacute; au lait',
      ],
      'invalid utf-8 byte' => ["Rye \xff loaf", NULL, 'Rye ? loaf'],
    ];
  }

  /**
   * Ampersand escaping options.
   *
   * @param array<string, bool> $params
   *   The escaping option.
   * @param string $expected
   *   The converted output.
   */
  #[DataProvider('providerAmpersandEscaping')]
  public function testAmpersandEscaping(array $params, string $expected): void {
    $source = 'Rolls & buns &amp; tarts &copy; &#169; a&b;';
    $this->assertSame($expected, CharacterEncoding::toHtml($source, $params));
  }

  /**
   * Data for testAmpersandEscaping().
   *
   * @return array<string, array{array<string, bool>, string}>
   *   Option and expected output.
   */
  public static function providerAmpersandEscaping(): array {
    return [
      'none' => [[], 'Rolls & buns &amp; tarts &copy; &copy; a&b;'],
      'every ampersand' => [
        ['escape_ampersand' => TRUE],
        'Rolls &amp; buns &amp;amp; tarts &amp;copy; &amp;#169; a&amp;b;',
      ],
      'stray ampersands only' => [
        ['escape_ampersand_selective' => TRUE],
        'Rolls &amp; buns &amp; tarts &copy; &copy; a&b;',
      ],
      'every ampersand of the output' => [
        ['escape_entities' => TRUE],
        'Rolls &amp; buns &amp;amp; tarts &amp;copy; &amp;copy; a&amp;b;',
      ],
    ];
  }

  /**
   * Anything but a string passes through unchanged.
   *
   * @param mixed $source
   *   The non-string input.
   */
  #[DataProvider('providerNonString')]
  public function testToHtmlPassesNonStringThrough(mixed $source): void {
    $this->assertSame($source, CharacterEncoding::toHtml($source));
    $this->assertSame($source, CharacterEncoding::toHtmlSafe($source));
  }

  /**
   * Data for testToHtmlPassesNonStringThrough().
   *
   * @return array<string, array{mixed}>
   *   Non-string inputs.
   */
  public static function providerNonString(): array {
    return [
      'null' => [NULL],
      'integer' => [42],
      'array' => [['crumb']],
    ];
  }

  /**
   * An unknown purifier is refused.
   */
  public function testToHtmlRejectsUnknownPurifier(): void {
    $this->expectException(\InvalidArgumentException::class);
    CharacterEncoding::toHtml('Rye', ['purify' => 'no such purifier']);
  }

  /**
   * The 'user input' preset is refused when no purifier is installed.
   */
  public function testToHtmlSafeRequiresPurifier(): void {
    if (function_exists('filter_xss') || class_exists('HTMLPurifier')) {
      $this->markTestSkipped('A purifier is installed.');
    }
    $this->expectException(\InvalidArgumentException::class);
    CharacterEncoding::toHtmlSafe('<b>Rye</b>', FALSE);
  }

  /**
   * Converting between encodings.
   */
  public function testToEncoding(): void {
    $this->assertSame('Café', CharacterEncoding::toEncoding("Caf\xe9", 'ISO-8859-1', 'UTF-8'));
    $this->assertSame("Caf\xe9", CharacterEncoding::toEncoding('Café', 'UTF-8', 'ISO-8859-1'));
  }

  /**
   * Each map mode shapes the same entity differently.
   *
   * @param int $mode
   *   A MODE_* constant.
   * @param int|string $key
   *   The key for é.
   * @param string $value
   *   The value for é.
   */
  #[DataProvider('providerEntitiesMapModes')]
  public function testGetEntitiesMapModes(int $mode, int|string $key, string $value): void {
    $map = CharacterEncoding::getEntitiesMap('HTMLLAT1', $mode);
    $this->assertArrayHasKey($key, $map);
    $this->assertSame($value, $map[$key]);
  }

  /**
   * Data for testGetEntitiesMapModes().
   *
   * @return array<string, array{int, int|string, string}>
   *   Mode, key and value for é.
   */
  public static function providerEntitiesMapModes(): array {
    return [
      'ordinal to name' => [CharacterEncoding::MODE_ORDINAL_NAME, 233, 'eacute'],
      'decimal to entity' => [CharacterEncoding::MODE_ENTITYDEC_ENTITYNAME, '&#233;', '&eacute;'],
      'decimal to name' => [CharacterEncoding::MODE_ENTITYDEC_NAME, '&#233;', 'eacute'],
      'entity to decimal' => [CharacterEncoding::MODE_ENTITYNAME_ENTITYDEC, '&eacute;', '&#233;'],
      'hex to entity' => [CharacterEncoding::MODE_ENTITYHEX_ENTITYNAME, '&#xe9;', '&eacute;'],
      'name to decimal' => [CharacterEncoding::MODE_CHAR_ENTITYDEC, 'eacute', '&#233;'],
    ];
  }

  /**
   * Set ids are case-insensitive, and unknown ones contribute nothing.
   */
  public function testGetEntitiesMapSets(): void {
    $this->assertSame('eacute', CharacterEncoding::getEntitiesMap('htmllat1')[233]);
    $this->assertSame('e', CharacterEncoding::getEntitiesMap('TRANSLITERATE_ASCII')[233]);
    $this->assertSame('eacute', CharacterEncoding::getEntitiesMap('HTML5')[233]);
    $this->assertSame([], CharacterEncoding::getEntitiesMap(['no such set']));
  }

  /**
   * The default sets name the HTML 4 entities.
   */
  public function testGetEntitiesMapDefaultSets(): void {
    $map = CharacterEncoding::getEntitiesMap();
    $this->assertSame('nbsp', $map[160]);
    $this->assertSame('hearts', $map[9829]);
    $this->assertSame('mdash', $map[8212]);
    $this->assertSame('amp', $map[38]);
  }

}
