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

namespace QuipXml\OneLiner;

/**
 * Small HTML string helpers: attributes, wrapping, minifying, class mapping.
 */
class OneLiner {

  /**
   * Renders an attribute list.
   *
   * @param array<string|int, mixed> $attrs
   *   Attribute values keyed by name. An array value is joined with spaces
   *   (`['class' => ['a', 'b']]` renders `class="a b"`).
   *
   * @return string
   *   Each attribute as ` name="value"` with a leading space and the value
   *   HTML-escaped, or an empty string for no attributes.
   */
  public static function attributes(array $attrs = []): string {
    $ret = '';
    foreach ($attrs as $k => &$v) {
      $v = implode(' ', (array) $v);
      $ret .= " $k=\"" . htmlspecialchars($v, ENT_QUOTES, 'UTF-8') . '"';
    }
    return $ret;
  }

  /**
   * Collapses insignificant whitespace in an HTML fragment.
   *
   * Trims the fragment, drops whitespace before `li`, `ul`, `p` and `br`
   * tags, folds each run of whitespace containing a newline into one
   * newline and each run of spaces and tabs into one space. A fragment
   * containing `<pre` is only trimmed.
   *
   * @param string|null $html
   *   The fragment. NULL with $mode 'ob' takes (and clears) the current
   *   output buffer instead.
   * @param string $mode
   *   The mode: 'html' to minify $html, or 'ob' to minify the output
   *   buffer when $html is NULL.
   *
   * @return string
   *   The minified fragment.
   */
  public static function minifyHtml(?string $html, string $mode = 'html'): string {
    if (!isset($html)) {
      if ($mode === 'ob') {
        $html = ob_get_contents();
        ob_clean();
      }
    }
    $output = trim((string) $html);
    if (strpos($output, '<pre') === FALSE) {
      // Remove whitespace before certain tags.
      $match = '@\s+(</?(?:li|ul|p|br)(?:>|\s))@si';
      $output = (string) preg_replace($match, '\1', $output);
      $output = (string) preg_replace("@\s*\n\s*@s", "\n", $output);
      $output = (string) preg_replace('@[ \t]+@s', ' ', $output);
    }
    return $output;
  }

  /**
   * Tells whether an HTML fragment would show nothing.
   *
   * A fragment is empty when it is not a string, or when no image, button,
   * iframe or non-hidden input remains and its text, with `&nbsp;`
   * removed, is only whitespace.
   *
   * @param mixed $html
   *   The fragment.
   *
   * @return bool
   *   TRUE when nothing visible remains.
   */
  public static function isHtmlEmpty(mixed $html): bool {
    if (!is_string($html) || $html == '') {
      return TRUE;
    }
    if (preg_match('@<(?:img|button|iframe)@is', $html)) {
      return FALSE;
    }
    if (stripos($html, '<input') !== FALSE) {
      $html = (string) preg_replace('@<input[^>]*type="hidden"[^>]*>@si', '', $html);
      if (stripos($html, '<input') !== FALSE) {
        return FALSE;
      }
    }
    $html = trim(strtr(strip_tags($html), [
      '&nbsp;' => '',
    ]));
    if ($html == '') {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Rewrites the class attributes of an HTML fragment through strtr() maps.
   *
   * Each class attribute is padded with a space on either side, so a map
   * key of ' old ' matches the whole class name. Only tags with another
   * attribute or whitespace before `class=` are rewritten, and an attribute
   * left empty is removed.
   *
   * @param string $html
   *   The fragment.
   * @param array<string, array<string, string>> $css_tr
   *   Replacement maps keyed by tag name; the required '*' map applies to
   *   every tag, before the tag's own map. For example
   *   `['*' => [' old ' => ' new '], 'p' => [' lead ' => ' intro ']]`.
   *
   * @return string
   *   The fragment with its class attributes rewritten.
   */
  public static function htmlClassTr(string $html, array $css_tr): string {
    $html = (string) preg_replace_callback('@<(?<tag>[a-z]+)(?<other>\s+[^>]*)class="(?<class>[^"]+)"@s', function ($attrs) use ($css_tr) {
      $class = ' '
        . strtr($attrs['class'], [
          "\n" => ' ',
          "\r" => ' ',
          "\t" => ' ',
        ]) . ' ';
      $class = strtr($class, $css_tr['*']);
      if (isset($css_tr[$attrs['tag']])) {
        $class = strtr($class, $css_tr[$attrs['tag']]);
      }
      $class = trim($class);
      if ($class !== '') {
        $class = 'class="' . $class . '"';
      }
      return '<' . $attrs['tag'] . $attrs['other'] . $class;
    }, $html);
    return $html;
  }

  /**
   * Wraps content in markup.
   *
   * The wrapper takes one of four shapes:
   * - a tag name (`p`), giving `<p>content</p>`;
   * - a tag name with CSS-style id and class suffixes (`div#main.note`),
   *   giving `<div id="main" class="note">content</div>`;
   * - opening markup (`<div><span>`), which the matching closing tags in
   *   reverse order follow;
   * - any other string, simply prepended.
   * An `img` wrapper around empty content renders a self-closing tag.
   *
   * @param mixed $wrapper
   *   The wrapper. Anything other than a non-empty string returns $content
   *   unchanged.
   * @param mixed $content
   *   The content, usually an HTML string.
   * @param bool $wrapIfEmpty
   *   FALSE returns $content unchanged when isHtmlEmpty() says it is empty.
   * @param array<string|int, mixed>|null $attrs
   *   Attributes for a tag-name wrapper, as for attributes(); an id or class
   *   suffix of the wrapper overrides the same key here.
   *
   * @return mixed
   *   The wrapped HTML string, or $content unchanged.
   */
  public static function wrap(mixed $wrapper, mixed $content, bool $wrapIfEmpty = TRUE, ?array $attrs = NULL): mixed {
    // Catch uninteresting cases quickly.
    if (!isset($wrapper) || !is_string($wrapper) || $wrapper === '') {
      return $content;
    }

    // If empty, then stop.
    if (!$wrapIfEmpty && OneLiner::isHtmlEmpty($content)) {
      return $content;
    }

    // Just a tag name. Separate from logic below for improved speed.
    if (preg_match('@^[a-z0-9]+$@si', $wrapper)) {
      if (strpos(' img ', " $wrapper ") !== FALSE && trim((string) $content) === '') {
        if (isset($attrs)) {
          $output = "<$wrapper" . self::attributes($attrs) . " />";
        }
        else {
          $output = "<$wrapper />";
        }
      }
      else {
        if (isset($attrs)) {
          $output = "<$wrapper" . self::attributes($attrs)
            . ">$content</$wrapper>";
        }
        else {
          $output = "<$wrapper>$content</$wrapper>";
        }
      }
    }
    // Tag name with CSS-style attributes.
    elseif (preg_match('@^(?<tag>[a-z0-9]+)[#\.][a-z0-9#\.\-]+$@si', $wrapper, $arr)) {
      $attrs = isset($attrs) ? (array) $attrs : [];
      $tmp = substr($wrapper, strlen($arr['tag']));
      $wrapper = $arr['tag'];
      while (preg_match('@^(?<type>[#\.])(?<value>[^#\.]+)(?:[#\.]|$)@s', $tmp, $arr)) {
        $tmp = substr($tmp, 1 + strlen($arr['value']));
        switch ($arr['type']) {
          case '#':
            $attrs['id'] = $arr['value'];
            break;

          case '.':
            if (!isset($attrs['class']) || strlen($attrs['class']) == 0) {
              $attrs['class'] = $arr['value'];
            }
            else {
              $attrs['class'] = $arr['value'];
            }
            break;
        }
      }
      if (strpos(' img ', " $wrapper ") !== FALSE && trim((string) $content) === '') {
        $output = "<$wrapper" . self::attributes($attrs) . " />";
      }
      else {
        $output = "<$wrapper" . self::attributes($attrs)
          . ">$content</$wrapper>";
      }
    }
    // Handle opening tags.
    elseif (strpos($wrapper, '<') !== FALSE) {
      $output = $wrapper . $content;
      $parts = explode('<', $wrapper);
      array_shift($parts);
      $closed = [];
      foreach (array_reverse($parts) as $part) {
        if ($part[0] === '/') {
          if (preg_match('@^/([^>]*)>@s', $part, $arr)) {
            $tag = trim($arr[1]);
            if (!isset($closed[$tag])) {
              $closed[$tag] = 1;
            }
            else {
              ++$closed[$tag];
            }
          }
        }
        else {
          if (preg_match('@^([^>\s]*)[\s>]@s', $part, $arr)) {
            $tag = trim($arr[1]);
            if (isset($closed[$tag]) && $closed[$tag] > 0) {
              --$closed[$tag];
            }
            else {
              $output .= "</$tag>";
            }
          }
        }
      }
    }
    // If nothing works, then simply prepend the wrapper.
    else {
      $output = $wrapper . $content;
    }

    return $output;
  }

}
