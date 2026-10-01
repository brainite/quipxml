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
use QuipXml\Quip;
use QuipXml\Xml\QuipXmlElement;

/**
 * Tests Quip::load() across its XML, HTML and object sources.
 */
#[CoversClass(Quip::class)]
final class QuipLoadTest extends TestCase {

  /**
   * Well-formed XML is read as XML, root first.
   */
  public function testLoadsWellFormedXml(): void {
    $quip = Quip::load('<catalog><book id="b1">Dune</book><book id="b2">Emma</book></catalog>');

    $this->assertInstanceOf(QuipXmlElement::class, $quip);
    $this->assertSame('catalog', $quip->getName());
    $this->assertCount(2, $quip->qxpath('//book'));
  }

  /**
   * A path is read when $data_is_url is set.
   */
  public function testLoadsFromFile(): void {
    $quip = Quip::load(__DIR__ . '/Resources/XmlBasicList.xml', 0, TRUE);

    $this->assertSame('example', $quip->getName());
    $this->assertCount(5, $quip->qxpath('//original//item'));
  }

  /**
   * Markup that is not well formed falls back to the HTML parser.
   */
  public function testFallsBackToHtmlForMalformedMarkup(): void {
    $quip = Quip::load('<p>Rain<br>then sun</p>');

    $this->assertSame('p', $quip->getName());
    $this->assertSame('Rain<br/>then sun', $quip->html());
  }

  /**
   * A whole HTML document keeps its html root.
   */
  public function testHtmlDocumentKeepsItsRoot(): void {
    $quip = Quip::load('<html><head><title>Forecast</title></head><body><p>Mild<br>and dry</p></body></html>');

    $this->assertSame('html', $quip->getName());
    $this->assertSame('Forecast', (string) $quip->head->title);
  }

  /**
   * An HTML fragment with a body returns the body.
   */
  public function testHtmlBodyFragmentReturnsTheBody(): void {
    $quip = Quip::load('<body><p>Mild<br>and dry</p></body>');

    $this->assertSame('body', $quip->getName());
  }

  /**
   * A fragment of several top-level nodes is wrapped in a div.
   */
  public function testFragmentWithSeveralRootsIsWrapped(): void {
    $quip = Quip::load('<p>Monday</p><p>Tuesday</p>');

    $this->assertSame('div', $quip->getName());
    $this->assertSame('<div><p>Monday</p><p>Tuesday</p></div>', $quip->htmlOuter());
  }

  /**
   * A non-breaking space entity survives a round trip.
   */
  public function testNbspSurvives(): void {
    $quip = Quip::load('<div>Hello&nbsp;World!</div>');

    $this->assertSame('<div>Hello&nbsp;World!</div>', $quip->htmlOuter());
    $this->assertSame('<div>Hello&nbsp;World!</div>', $quip->htmlOuter(Quip::formatter()));
  }

  /**
   * An unescaped ampersand in HTML is escaped and parsed.
   */
  public function testUnescapedAmpersandIsEscaped(): void {
    $quip = Quip::load('<p>Salt & pepper<br></p>');

    $this->assertSame('Salt &amp; pepper', $quip->text());
  }

  /**
   * LOAD_NS_UNWRAP drops namespaced tags and keeps their content.
   */
  public function testNamespaceUnwrap(): void {
    $quip = Quip::load('<doc><x:note>Bring an umbrella</x:note></doc>', 0, FALSE, '', FALSE, Quip::LOAD_NS_UNWRAP);

    $this->assertSame('<doc>Bring an umbrella</doc>', $quip->htmlOuter());
  }

  /**
   * LOAD_NS_STRIP keeps namespaced tags without their prefix.
   */
  public function testNamespaceStrip(): void {
    $quip = Quip::load('<doc><x:note>Bring an umbrella</x:note></doc>', 0, FALSE, '', FALSE, Quip::LOAD_NS_STRIP);

    $this->assertSame('<doc><note>Bring an umbrella</note></doc>', $quip->htmlOuter());
  }

  /**
   * LOAD_IGNORE_ERRORS restores the caller's libxml error setting.
   */
  public function testIgnoreErrorsRestoresLibxmlSetting(): void {
    $before = libxml_use_internal_errors(FALSE);
    try {
      Quip::load('<p>Rain<br>then sun</p>', 0, FALSE, '', FALSE, Quip::LOAD_IGNORE_ERRORS);
      $this->assertFalse(libxml_use_internal_errors());
    }
    finally {
      libxml_use_internal_errors($before);
    }
  }

  /**
   * The error handler and reporting level are restored after a load.
   */
  public function testRestoresErrorHandlingAfterSuccess(): void {
    [$handler, $level] = $this->installMarkerHandler();
    try {
      Quip::load('<p>Rain<br>then sun</p>');
      $this->assertSame($level, error_reporting());
      $this->assertSame($handler, set_error_handler(NULL));
    }
    finally {
      restore_error_handler();
      restore_error_handler();
      error_reporting($level);
    }
  }

  /**
   * The error handler and reporting level are restored when a load throws.
   */
  public function testRestoresErrorHandlingAfterFailure(): void {
    [$handler, $level] = $this->installMarkerHandler();
    try {
      try {
        Quip::load('   ');
        $this->fail('An empty document loaded.');
      }
      catch (\InvalidArgumentException) {
      }
      $this->assertSame($level, error_reporting());
      $this->assertSame($handler, set_error_handler(NULL));
    }
    finally {
      restore_error_handler();
      restore_error_handler();
      error_reporting($level);
    }
  }

  /**
   * An empty document is refused.
   */
  public function testEmptySourceThrows(): void {
    $this->expectException(\InvalidArgumentException::class);
    Quip::load('');
  }

  /**
   * An empty file is refused like an empty string.
   */
  public function testEmptyFileThrows(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Quip cannot load an empty document.');
    Quip::load(__DIR__ . '/Resources/Empty.xml', 0, TRUE);
  }

  /**
   * A SimpleXMLElement is wrapped without copying its document.
   */
  public function testLoadsSimpleXmlElementBySharingItsDocument(): void {
    $sxml = new \SimpleXMLElement('<garden><bed>tulips</bed></garden>');
    $quip = Quip::load($sxml);
    $sxml->bed = 'roses';

    $this->assertInstanceOf(QuipXmlElement::class, $quip);
    $this->assertSame('roses', (string) $quip->bed);
  }

  /**
   * A DOMDocument is wrapped without copying it.
   */
  public function testLoadsDomDocumentBySharingIt(): void {
    $dom = new \DOMDocument();
    $dom->loadXML('<garden><bed>tulips</bed></garden>');
    $quip = Quip::load($dom);
    $dom->getElementsByTagName('bed')->item(0)?->setAttribute('sunny', 'yes');

    $this->assertSame('yes', (string) $quip->bed['sunny']);
  }

  /**
   * Installs a recognisable error handler and reporting level.
   *
   * @return array{0: \Closure, 1: int}
   *   The handler and the level.
   */
  private function installMarkerHandler(): array {
    $handler = static fn (): bool => FALSE;
    set_error_handler($handler);
    $level = E_ALL & ~E_USER_NOTICE;
    error_reporting($level);
    return [$handler, $level];
  }

}
