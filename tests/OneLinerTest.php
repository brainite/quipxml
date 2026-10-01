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
use QuipXml\OneLiner\OneLiner;

/**
 * Tests the one-line HTML string helpers.
 */
#[CoversClass(OneLiner::class)]
final class OneLinerTest extends TestCase {

  /**
   * Attributes render escaped, with array values joined by spaces.
   */
  public function testAttributes(): void {
    $this->assertSame(
      ' class="cake slice" title="Salt &amp; &quot;pepper&quot;" data-count="3"',
      OneLiner::attributes([
        'class' => ['cake', 'slice'],
        'title' => 'Salt & "pepper"',
        'data-count' => 3,
      ])
    );
  }

  /**
   * No attributes render as an empty string.
   */
  public function testAttributesEmpty(): void {
    $this->assertSame('', OneLiner::attributes());
  }

  /**
   * Whitespace collapses and disappears before list and paragraph tags.
   */
  public function testMinifyHtml(): void {
    $html = "  <ul>\n   <li>Bread</li>\n\n  <li>Rolls   and\tbuns</li>\n</ul>  ";
    $this->assertSame('<ul><li>Bread</li><li>Rolls and buns</li></ul>', OneLiner::minifyHtml($html));
  }

  /**
   * Runs of whitespace containing a newline fold into one newline.
   */
  public function testMinifyHtmlKeepsOneNewline(): void {
    $this->assertSame("<b>Rye</b>\n<i>Spelt</i>", OneLiner::minifyHtml("<b>Rye</b>  \n\n  <i>Spelt</i>"));
  }

  /**
   * A fragment containing a pre element is only trimmed.
   */
  public function testMinifyHtmlLeavesPreAlone(): void {
    $this->assertSame("<pre>  a\n\n  b </pre>", OneLiner::minifyHtml("  <pre>  a\n\n  b </pre>  "));
  }

  /**
   * In 'ob' mode the output buffer is minified and cleared.
   */
  public function testMinifyHtmlOutputBuffer(): void {
    ob_start();
    echo "  <p>Soup   of   the day</p>  ";
    $minified = OneLiner::minifyHtml(NULL, 'ob');
    $remaining = ob_get_clean();
    $this->assertSame('<p>Soup of the day</p>', $minified);
    $this->assertSame('', $remaining);
  }

  /**
   * NULL outside 'ob' mode minifies to an empty string.
   */
  public function testMinifyHtmlNull(): void {
    $this->assertSame('', OneLiner::minifyHtml(NULL));
  }

  /**
   * Emptiness of HTML fragments.
   *
   * @param mixed $html
   *   The fragment.
   * @param bool $expected
   *   Whether it counts as empty.
   */
  #[DataProvider('providerIsHtmlEmpty')]
  public function testIsHtmlEmpty(mixed $html, bool $expected): void {
    $this->assertSame($expected, OneLiner::isHtmlEmpty($html));
  }

  /**
   * Data for testIsHtmlEmpty().
   *
   * @return array<string, array{mixed, bool}>
   *   Fragment and expected result.
   */
  public static function providerIsHtmlEmpty(): array {
    return [
      'empty string' => ['', TRUE],
      'null' => [NULL, TRUE],
      'not a string' => [5, TRUE],
      'only tags and nbsp' => ['<p> &nbsp; </p>', TRUE],
      'hidden input' => ['<input type="hidden" name="token" value="1">', TRUE],
      'text' => ['<p>Bun</p>', FALSE],
      'image' => ['<img src="https://example.com/bun.png">', FALSE],
      'button' => ['<button></button>', FALSE],
      'iframe' => ['<iframe></iframe>', FALSE],
      'visible input' => ['<input type="text" name="q">', FALSE],
    ];
  }

  /**
   * Class maps apply to every tag, then to the named tag.
   */
  public function testHtmlClassTr(): void {
    $html = '<p id="intro" class="old  keep"><span class="old">x</span>';
    $map = [
      '*' => [' old ' => ' new '],
      'span' => [' new ' => ' newer '],
    ];
    $this->assertSame('<p id="intro" class="new  keep"><span class="newer">x</span>', OneLiner::htmlClassTr($html, $map));
  }

  /**
   * A class attribute mapped to nothing is removed.
   */
  public function testHtmlClassTrRemovesEmptiedAttribute(): void {
    $this->assertSame('<div  >', OneLiner::htmlClassTr('<div  class="gone">', ['*' => [' gone ' => ' ']]));
  }

  /**
   * Wrapping with each wrapper shape.
   *
   * @param string $wrapper
   *   The wrapper.
   * @param string $content
   *   The content.
   * @param array<string, string>|null $attrs
   *   Extra attributes.
   * @param string $expected
   *   The wrapped HTML.
   */
  #[DataProvider('providerWrap')]
  public function testWrap(string $wrapper, string $content, ?array $attrs, string $expected): void {
    $this->assertSame($expected, OneLiner::wrap($wrapper, $content, TRUE, $attrs));
  }

  /**
   * Data for testWrap().
   *
   * @return array<string, array{string, string, array<string, string>|null, string}>
   *   Wrapper, content, attributes and expected HTML.
   */
  public static function providerWrap(): array {
    return [
      'tag' => ['p', 'Bread', NULL, '<p>Bread</p>'],
      'tag with attributes' => ['p', 'Bread', ['class' => 'menu'], '<p class="menu">Bread</p>'],
      'empty img' => ['img', '', NULL, '<img />'],
      'empty img with attributes' => [
        'img',
        ' ',
        ['src' => 'https://example.com/a.png'],
        '<img src="https://example.com/a.png" />',
      ],
      'id and class suffixes' => [
        'div#main.note',
        'Bread',
        NULL,
        '<div id="main" class="note">Bread</div>',
      ],
      'suffix overrides attribute' => [
        'div.note',
        'x',
        ['class' => 'z', 'id' => 'q'],
        '<div class="note" id="q">x</div>',
      ],
      'img with suffix' => ['img#pic', '', NULL, '<img id="pic" />'],
      'opening markup' => [
        '<div><span class="x">',
        'Bread',
        NULL,
        '<div><span class="x">Bread</span></div>',
      ],
      'opening markup with a closed element' => [
        '<div><b>Day</b>',
        ' soup',
        NULL,
        '<div><b>Day</b> soup</div>',
      ],
      'plain prefix' => ['Note: ', 'Bread', NULL, 'Note: Bread'],
    ];
  }

  /**
   * Anything but a non-empty string wrapper returns the content as is.
   *
   * @param mixed $wrapper
   *   The wrapper.
   */
  #[DataProvider('providerWrapWithoutWrapper')]
  public function testWrapWithoutWrapper(mixed $wrapper): void {
    $this->assertSame('Bread', OneLiner::wrap($wrapper, 'Bread'));
  }

  /**
   * Data for testWrapWithoutWrapper().
   *
   * @return array<string, array{mixed}>
   *   Wrappers that wrap nothing.
   */
  public static function providerWrapWithoutWrapper(): array {
    return [
      'empty string' => [''],
      'null' => [NULL],
      'integer' => [5],
    ];
  }

  /**
   * With $wrapIfEmpty FALSE, empty content is returned unwrapped.
   */
  public function testWrapSkipsEmptyContent(): void {
    $this->assertSame('<b> </b>', OneLiner::wrap('p', '<b> </b>', FALSE));
    $this->assertSame('<p>Rye</p>', OneLiner::wrap('p', 'Rye', FALSE));
  }

}
