<?php
/*
 * This file is part of the QuipXml package.
 *
 * (c) Greg Payne
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace QuipXml\OneLiner;
class OneLiner {
  static public function attributes(array $attrs = array()) {
    $ret = '';
    foreach ($attrs as $k => &$v) {
      $v = implode(' ', (array) $v);
      $ret .= " $k=\"" . htmlspecialchars($v, ENT_QUOTES, 'UTF-8') . '"';
    }
    return $ret;
  }

  static public function minifyHtml($html, $mode = 'html') {
    if (!isset($html)) {
      if ($mode === 'ob') {
        $html = ob_get_contents();
        ob_clean();
      }
    }
    $output = trim($html);
    if (strpos($output, '<pre') === FALSE) {
      $match = '@\s+(</?(?:li|ul|p)(?:>|\s))@s';
      $output = preg_replace($match, '\1', $output);
      $output = preg_replace("@\s*\n\s*@s", "\n", $output);
      $output = preg_replace('@[ \t]+@s', ' ', $output);
    }
    return $output;
  }

  static public function isHtmlEmpty($html) {
    if (!is_string($html) || $html == '') {
      return TRUE;
    }
    if (stripos($html, '<img') !== FALSE) {
      return FALSE;
    }
    $html = trim(strtr(strip_tags($html), array(
      '&nbsp;' => '',
    )));
    if ($html == '') {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Translate css classes.
   *
   * @param string $html
   * @param array $css_tr array('*' => array(' old ' => ' new ',),)
   * @return string
   */
  static public function htmlClassTr($html, $css_tr) {
    $html = preg_replace_callback('@<(?<tag>[a-z]+)(?<other>\s+[^>]*)class="(?<class>[^"]+)"@s', function ($attrs) use ($css_tr) {
      $class = ' '
        . strtr($attrs['class'], array(
          "\n" => ' ',
          "\r" => ' ',
          "\t" => ' ',
        )) . ' ';
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

  static public function wrap($wrapper, $content, $wrapIfEmpty = TRUE, $attrs = NULL) {
    // Catch uninteresting cases quickly.
    if (!isset($wrapper) || !is_string($wrapper) || $wrapper === '') {
      return $content;
    }

    // If empty, then stop.
    if (!$wrapIfEmpty && OneLiner::isHtmlEmpty($content)) {
      return $content;
    }

    // Just a tag name.
    if (preg_match('@^[a-z0-9]+$@si', $wrapper)) {
      if (isset($attrs)) {
        $output = "<$wrapper" . self::attributes($attrs)
          . ">$content</$wrapper>";
      }
      else {
        $output = "<$wrapper>$content</$wrapper>";
      }
      return $output;
    }

    // Handle opening tags.
    if (strpos($wrapper, '<') !== FALSE) {
      $output = $wrapper . $content;
      $parts = explode('<', $wrapper);
      array_shift($parts);
      $closed = array();
      foreach (array_reverse($parts) as $part) {
        if ($part{0} === '/') {
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
      return $output;
    }

    // If nothing works, then simply prepend the wrapper.
    $output = $wrapper . $content;
  }

}