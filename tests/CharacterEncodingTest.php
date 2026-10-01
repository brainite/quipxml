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

use QuipXml\Encoding\CharacterEncoding;
class CharacterEncodingTest extends \PHPUnit\Framework\TestCase {
  public function testAsciiTransliteration() {
    $test = "—’–";
    $expected = "-'-";
    $actual = CharacterEncoding::toHtml($test, 'ascii');
    $this->assertEquals($expected, $actual);

    $test = "á Á à À â Â ä Ä ã Ã å Å æ Æ ç Ç é É è È ê Ê ë Ë í Í ì Ì î Î ï Ï ñ Ñ ó Ó ò Ò ô Ô ö Ö õ Õ ø Ø ú Ú ù Ù û Û ü Ü";
    $expected = "a A a A a A a A a A a A ae AE c C e E e E e E e E i I i I i I i I n N o O o O o O o O o O o O u U u U u U u U";
    $actual = CharacterEncoding::toHtml($test, 'ascii');
    $this->assertEquals($expected, $actual);

    $not_supported = "œ Œ ß";
  }

}