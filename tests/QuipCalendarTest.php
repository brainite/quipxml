<?php

/*
 * This file is part of the QuipXml package.
 *
 * (c) Greg Payne
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace QuipXml\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use QuipXml\Calendar\QuipCalendar;
use QuipXml\Calendar\QuipCalendarIcsFormatter;
use QuipXml\Calendar\QuipCalendarJsonFeedFormatter;

/**
 * Tests iCalendar loading and the ICS and JSON-feed formatters.
 */
#[CoversClass(QuipCalendar::class)]
#[CoversClass(QuipCalendarIcsFormatter::class)]
#[CoversClass(QuipCalendarJsonFeedFormatter::class)]
final class QuipCalendarTest extends TestCase {

  /**
   * Builds an iCalendar document from lines, joined with CRLF.
   *
   * @param string ...$lines
   *   The lines, without line endings.
   *
   * @return string
   *   The document.
   */
  private static function ics(string ...$lines): string {
    return implode("\r\n", $lines) . "\r\n";
  }

  /**
   * Components become nested elements and properties their children.
   */
  public function testLoadIcalComponents(): void {
    $cal = QuipCalendar::loadIcal(self::ics(
      'BEGIN:VCALENDAR',
      'VERSION:2.0',
      'BEGIN:VEVENT',
      'UID:uid@example.com',
      'SUMMARY:Community garden workday',
      'END:VEVENT',
      'BEGIN:VEVENT',
      'UID:second@example.com',
      'SUMMARY:Seed swap',
      'END:VEVENT',
      'END:VCALENDAR',
    ));

    $this->assertSame('iCalendar', $cal->getName());
    $this->assertSame('2.0', (string) $cal->vcalendar->version);
    $this->assertCount(2, $cal->vcalendar->vevent);
    $this->assertSame('Community garden workday', (string) $cal->vcalendar->vevent[0]->summary);
    $this->assertSame('second@example.com', (string) $cal->vcalendar->vevent[1]->uid);
  }

  /**
   * A line starting with a space continues the previous line.
   */
  public function testLoadIcalUnfoldsLines(): void {
    $cal = QuipCalendar::loadIcal(self::ics(
      'BEGIN:VCALENDAR',
      'BEGIN:VEVENT',
      'DESCRIPTION:Bring gloves and a ',
      ' trowel for the raised ',
      ' beds',
      'END:VEVENT',
      'END:VCALENDAR',
    ));

    $this->assertSame('Bring gloves and a trowel for the raised beds', (string) $cal->vcalendar->vevent->description);
  }

  /**
   * Property parameters become lower-case attributes.
   */
  public function testLoadIcalParameters(): void {
    $cal = QuipCalendar::loadIcal(self::ics(
      'BEGIN:VCALENDAR',
      'BEGIN:VEVENT',
      'DTSTART;TZID=Europe/Berlin:20260512T090000',
      'ATTENDEE;ROLE=CHAIR;CN=Alex Example:mailto:alex@example.org',
      'END:VEVENT',
      'END:VCALENDAR',
    ));
    $vevent = $cal->vcalendar->vevent;

    $this->assertSame('20260512T090000', (string) $vevent->dtstart);
    $this->assertSame('Europe/Berlin', (string) $vevent->dtstart['tzid']);
    $this->assertSame('mailto:alex@example.org', (string) $vevent->attendee);
    $this->assertSame('CHAIR', (string) $vevent->attendee['role']);
    $this->assertSame('Alex Example', (string) $vevent->attendee['cn']);
  }

  /**
   * An escaped newline becomes a line break and an ampersand is kept.
   */
  public function testLoadIcalEscapedNewlineAndAmpersand(): void {
    $cal = QuipCalendar::loadIcal(self::ics(
      'BEGIN:VCALENDAR',
      'BEGIN:VEVENT',
      'DESCRIPTION:Tools & seeds\nLunch provided',
      'END:VEVENT',
      'END:VCALENDAR',
    ));

    $this->assertSame("Tools & seeds\nLunch provided", (string) $cal->vcalendar->vevent->description);
  }

  /**
   * With $data_is_url the source is read from a file.
   */
  public function testLoadIcalFromFile(): void {
    $path = tempnam(sys_get_temp_dir(), 'quip-ics-');
    $this->assertIsString($path);
    file_put_contents($path, self::ics('BEGIN:VCALENDAR', 'X-WR-CALNAME:Garden club', 'END:VCALENDAR'));
    try {
      $cal = QuipCalendar::loadIcal($path, 0, TRUE);
    }
    finally {
      unlink($path);
    }

    $this->assertSame('Garden club', (string) $cal->vcalendar->{'x-wr-calname'});
  }

  /**
   * An empty calendar carries the given defaults.
   */
  public function testLoadEmptyWithDefaults(): void {
    $cal = QuipCalendar::loadEmpty([
      'uid' => 'cal@example.com',
      'name' => 'Garden club',
      'timezone' => 'Europe/Berlin',
    ]);
    $vcalendar = $cal->vcalendar;

    $this->assertSame('2.0', (string) $vcalendar->version);
    $this->assertSame('GREGORIAN', (string) $vcalendar->calscale);
    $this->assertSame('cal@example.com', (string) $vcalendar->uid);
    $this->assertSame('Garden club', (string) $vcalendar->{'x-wr-calname'});
    $this->assertSame('Europe/Berlin', (string) $vcalendar->{'x-wr-timezone'});
    $this->assertCount(0, $vcalendar->vevent);
  }

  /**
   * Without defaults an empty calendar is named, in UTC, with a fresh UID.
   */
  public function testLoadEmptyDefaults(): void {
    $vcalendar = QuipCalendar::loadEmpty()->vcalendar;

    $this->assertSame('New Calendar', (string) $vcalendar->{'x-wr-calname'});
    $this->assertSame('Etc/UTC', (string) $vcalendar->{'x-wr-timezone'});
    $this->assertNotSame('', (string) $vcalendar->uid);
    $this->assertNotSame((string) $vcalendar->uid, (string) QuipCalendar::loadEmpty()->vcalendar->uid);
  }

  /**
   * A calendar formats back to iCalendar, X-…-… properties first.
   */
  public function testFormatRoundTrip(): void {
    $cal = QuipCalendar::loadIcal(self::ics(
      'BEGIN:VCALENDAR',
      'VERSION:2.0',
      'UID:cal@example.com',
      'X-WR-CALNAME:Garden club',
      'BEGIN:VEVENT',
      'UID:uid@example.com',
      'SUMMARY:Community garden workday',
      'DTSTART:20260512T090000Z',
      'DTEND:20260512T120000Z',
      'LOCATION;X-ADDRESS=1 Example Road:Plot 4',
      'END:VEVENT',
      'END:VCALENDAR',
    ));

    $this->assertSame(self::ics(
      'BEGIN:VCALENDAR',
      'X-WR-CALNAME:Garden club',
      'VERSION:2.0',
      'UID:cal@example.com',
      'BEGIN:VEVENT',
      'UID:uid@example.com',
      'SUMMARY:Community garden workday',
      'DTSTART:20260512T090000Z',
      'DTEND:20260512T120000Z',
      'LOCATION;X-ADDRESS=1 Example Road:Plot 4',
      'END:VEVENT',
      'END:VCALENDAR',
    ), (new QuipCalendarIcsFormatter())->getFormattedOuter($cal->vcalendar));
  }

  /**
   * A DTEND equal to its DTSTART is moved a minute later.
   */
  public function testFormatFixesDtEnd(): void {
    $cal = QuipCalendar::loadIcal(self::ics(
      'BEGIN:VCALENDAR',
      'BEGIN:VEVENT',
      'DTSTART:20260512T090000',
      'DTEND:20260512T090000',
      'END:VEVENT',
      'END:VCALENDAR',
    ));

    $output = (new QuipCalendarIcsFormatter())->getFormattedOuter($cal->vcalendar);

    $this->assertStringContainsString("\r\nDTEND:20260512T090100\r\n", $output);
    $this->assertSame('20260512T090100', (string) $cal->vcalendar->vevent->dtend);
  }

  /**
   * A long calendar UID is shortened, keeping its domain.
   */
  public function testFormatShortensUidWithDomain(): void {
    $cal = QuipCalendar::loadEmpty(['uid' => str_repeat('a', 80) . '@example.com']);

    $output = (new QuipCalendarIcsFormatter())->getFormattedOuter($cal->vcalendar);

    $uid = str_repeat('a', 59) . '@example.com';
    $this->assertStringContainsString("\r\nUID:$uid\r\n", $output);
    $this->assertSame($uid, (string) $cal->vcalendar->uid);
  }

  /**
   * A long calendar UID without a domain is cut to 71 characters.
   */
  public function testFormatShortensUidWithoutDomain(): void {
    $cal = QuipCalendar::loadEmpty(['uid' => str_repeat('b', 90)]);

    $output = (new QuipCalendarIcsFormatter())->getFormattedOuter($cal->vcalendar);

    $this->assertStringContainsString("\r\nUID:" . str_repeat('b', 71) . "\r\n", $output);
  }

  /**
   * With fix_uid_length off a long UID is kept and folded.
   */
  public function testFormatKeepsUidWhenFixIsOff(): void {
    $cal = QuipCalendar::loadEmpty(['uid' => str_repeat('c', 80)]);

    $output = (new QuipCalendarIcsFormatter(['fix_uid_length' => FALSE]))->getFormattedOuter($cal->vcalendar);

    $this->assertStringContainsString("\r\nUID:" . str_repeat('c', 71) . "\r\n " . str_repeat('c', 9) . "\r\n", $output);
  }

  /**
   * A floating time is converted from X-WR-TIMEZONE to UTC.
   */
  public function testFormatConvertsFloatingTimeToUtc(): void {
    $cal = QuipCalendar::loadIcal(self::ics(
      'BEGIN:VCALENDAR',
      'X-WR-TIMEZONE:America/New_York',
      'BEGIN:VEVENT',
      'DTSTART:20260115T090000',
      'DTEND:20260115T110000Z',
      'END:VEVENT',
      'END:VCALENDAR',
    ));

    $output = (new QuipCalendarIcsFormatter())->getFormattedOuter($cal->vcalendar);

    $this->assertStringContainsString("\r\nDTSTART:20260115T140000Z\r\n", $output);
    $this->assertStringContainsString("\r\nDTEND:20260115T110000Z\r\n", $output);
    $this->assertSame('20260115T140000Z', (string) $cal->vcalendar->vevent->dtstart);
  }

  /**
   * A time with a TZID parameter is written as given.
   */
  public function testFormatKeepsTimeWithTzid(): void {
    $cal = QuipCalendar::loadIcal(self::ics(
      'BEGIN:VCALENDAR',
      'X-WR-TIMEZONE:America/New_York',
      'BEGIN:VEVENT',
      'DTSTART;TZID=Europe/Berlin:20260115T090000',
      'DTEND;TZID=Europe/Berlin:20260115T100000',
      'END:VEVENT',
      'END:VCALENDAR',
    ));

    $output = (new QuipCalendarIcsFormatter())->getFormattedOuter($cal->vcalendar);

    $this->assertStringContainsString("\r\nDTSTART;TZID=Europe/Berlin:20260115T090000\r\n", $output);
    $this->assertStringContainsString("\r\nDTEND;TZID=Europe/Berlin:20260115T100000\r\n", $output);
  }

  /**
   * A line over 75 octets is folded with a leading space.
   */
  public function testFormatFoldsLongLines(): void {
    $text = str_repeat('0123456789', 20);
    $cal = QuipCalendar::loadIcal(self::ics('BEGIN:VCALENDAR', "DESCRIPTION:$text", 'END:VCALENDAR'));

    $output = (new QuipCalendarIcsFormatter())->getFormattedOuter($cal->vcalendar);

    $line = "DESCRIPTION:$text";
    $folded = substr($line, 0, 75) . "\r\n " . substr($line, 75, 74) . "\r\n " . substr($line, 149) . "\r\n";
    $this->assertSame("BEGIN:VCALENDAR\r\n{$folded}END:VCALENDAR\r\n", $output);
  }

  /**
   * A long SUMMARY is cut to one line with an ellipsis.
   */
  public function testFormatTruncatesSummary(): void {
    $text = str_repeat('Weeding ', 12);
    $cal = QuipCalendar::loadIcal(self::ics('BEGIN:VCALENDAR', "SUMMARY:$text", 'END:VCALENDAR'));

    $output = (new QuipCalendarIcsFormatter())->getFormattedOuter($cal->vcalendar);

    $this->assertSame("BEGIN:VCALENDAR\r\n" . substr("SUMMARY:$text", 0, 72) . "...\r\nEND:VCALENDAR\r\n", $output);
  }

  /**
   * Line breaks, commas and semicolons are escaped.
   */
  public function testEscape(): void {
    $this->assertSame('a\\\\b\\nc\\,d\\;e<f>', QuipCalendarIcsFormatter::escape("a\\b\nc,d;e&lt;f&gt;"));
  }

  /**
   * Events become FullCalendar objects in the calendar's timezone.
   */
  public function testJsonFeed(): void {
    $cal = QuipCalendar::loadIcal(self::ics(
      'BEGIN:VCALENDAR',
      'X-WR-TIMEZONE:America/New_York',
      'BEGIN:VEVENT',
      'UID:uid@example.com',
      'SUMMARY:Community garden workday',
      'DTSTART:20260512T130000Z',
      'DTEND:20260512T160000Z',
      'LOCATION;X-ADDRESS=1 Example Road;ALTREP=plot:Plot 4',
      'X-COLOR:green',
      'END:VEVENT',
      'BEGIN:VEVENT',
      'UID:second@example.com',
      'SUMMARY:Seed swap',
      'DTSTART:20260601T150000Z',
      'DTEND:20260601T160000Z',
      'LOCATION:',
      'END:VEVENT',
      'END:VCALENDAR',
    ));

    $json = (new QuipCalendarJsonFeedFormatter())->getFormattedOuter($cal->vcalendar);

    $this->assertSame([
      [
        'title' => 'Community garden workday',
        'start' => '20260512T090000-0400',
        'end' => '20260512T120000-0400',
        'uid' => 'uid@example.com',
        'location' => [
          'data' => 'Plot 4',
          'altrep' => 'plot',
          'address' => '1 Example Road',
        ],
        'x-color' => 'green',
      ],
      [
        'title' => 'Seed swap',
        'start' => '20260601T110000-0400',
        'end' => '20260601T120000-0400',
        'uid' => 'second@example.com',
      ],
    ], json_decode($json, TRUE));
  }

  /**
   * Without X-WR-TIMEZONE times are passed through as written.
   */
  public function testJsonFeedWithoutTimezone(): void {
    $cal = QuipCalendar::loadIcal(self::ics(
      'BEGIN:VCALENDAR',
      'BEGIN:VEVENT',
      'UID:uid@example.com',
      'SUMMARY:Community garden workday',
      'DTSTART:20260512T090000',
      'DTEND:20260512T120000',
      'END:VEVENT',
      'END:VCALENDAR',
    ));

    $json = (new QuipCalendarJsonFeedFormatter())->getFormattedOuter($cal->vcalendar);

    $this->assertSame([
      [
        'title' => 'Community garden workday',
        'start' => '20260512T090000',
        'end' => '20260512T120000',
        'uid' => 'uid@example.com',
      ],
    ], json_decode($json, TRUE));
  }

  /**
   * A long calendar UID is shortened before the feed is written.
   */
  public function testJsonFeedShortensCalendarUid(): void {
    $cal = QuipCalendar::loadEmpty(['uid' => str_repeat('d', 80)]);

    $this->assertSame('[]', (new QuipCalendarJsonFeedFormatter())->getFormattedOuter($cal->vcalendar));
    $this->assertSame(str_repeat('d', 71), (string) $cal->vcalendar->uid);
  }

}
