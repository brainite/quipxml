<?php
/*
 * This file is part of the QuipXml package.
 *
 * (c) Greg Payne
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace QuipXml\Encoding;
class CharacterEncoding {
  const MODE_ORDINAL_NAME = 1;
  const MODE_ENTITYDEC_ENTITYNAME = 2;
  const MODE_ENTITYDEC_NAME = 5;
  const MODE_ENTITYNAME_ENTITYDEC = 4;
  const MODE_ENTITYHEX_ENTITYNAME = 3;
  const MODE_CHAR_ENTITYDEC = 6;
  static public function getEntitiesMap($entitySets = NULL, $mode = self::MODE_ORDINAL_NAME) {
    $entitySets = (array) $entitySets;
    if (empty($entitySets)) {
      $entitySets = array(
        // https://www.w3.org/TR/html4/sgml/entities.html
        'HTMLLAT1',
        'HTMLSYMBOL',
        'HTMLSPECIAL',
        // http://www.w3schools.com/charsets/ref_html_8859.asp
        'ISO-8859-1',
        // https://dev.w3.org/html5/html-author/charref
        // 'HTML5',
        'ISO-8859-1:BYTES',
      );
    }

    // Define the sets.
    static $charsets = array(
      'HTMLLAT1' => array(
        160 => 'nbsp',
        161 => 'iexcl',
        162 => 'cent',
        163 => 'pound',
        164 => 'curren',
        165 => 'yen',
        166 => 'brvbar',
        167 => 'sect',
        168 => 'uml',
        169 => 'copy',
        170 => 'ordf',
        171 => 'laquo',
        172 => 'not',
        173 => 'shy',
        174 => 'reg',
        175 => 'macr',
        176 => 'deg',
        177 => 'plusmn',
        178 => 'sup2',
        179 => 'sup3',
        180 => 'acute',
        181 => 'micro',
        182 => 'para',
        183 => 'middot',
        184 => 'cedil',
        185 => 'sup1',
        186 => 'ordm',
        187 => 'raquo',
        188 => 'frac14',
        189 => 'frac12',
        190 => 'frac34',
        191 => 'iquest',
        192 => 'Agrave',
        193 => 'Aacute',
        194 => 'Acirc',
        195 => 'Atilde',
        196 => 'Auml',
        197 => 'Aring',
        198 => 'AElig',
        199 => 'Ccedil',
        200 => 'Egrave',
        201 => 'Eacute',
        202 => 'Ecirc',
        203 => 'Euml',
        204 => 'Igrave',
        205 => 'Iacute',
        206 => 'Icirc',
        207 => 'Iuml',
        208 => 'ETH',
        209 => 'Ntilde',
        210 => 'Ograve',
        211 => 'Oacute',
        212 => 'Ocirc',
        213 => 'Otilde',
        214 => 'Ouml',
        215 => 'times',
        216 => 'Oslash',
        217 => 'Ugrave',
        218 => 'Uacute',
        219 => 'Ucirc',
        220 => 'Uuml',
        221 => 'Yacute',
        222 => 'THORN',
        223 => 'szlig',
        224 => 'agrave',
        225 => 'aacute',
        226 => 'acirc',
        227 => 'atilde',
        228 => 'auml',
        229 => 'aring',
        230 => 'aelig',
        231 => 'ccedil',
        232 => 'egrave',
        233 => 'eacute',
        234 => 'ecirc',
        235 => 'euml',
        236 => 'igrave',
        237 => 'iacute',
        238 => 'icirc',
        239 => 'iuml',
        240 => 'eth',
        241 => 'ntilde',
        242 => 'ograve',
        243 => 'oacute',
        244 => 'ocirc',
        245 => 'otilde',
        246 => 'ouml',
        247 => 'divide',
        248 => 'oslash',
        249 => 'ugrave',
        250 => 'uacute',
        251 => 'ucirc',
        252 => 'uuml',
        253 => 'yacute',
        254 => 'thorn',
        255 => 'yuml',
      ),
      'HTMLSYMBOL' => array(
        402 => 'fnof',
        913 => 'Alpha',
        914 => 'Beta',
        915 => 'Gamma',
        916 => 'Delta',
        917 => 'Epsilon',
        918 => 'Zeta',
        919 => 'Eta',
        920 => 'Theta',
        921 => 'Iota',
        922 => 'Kappa',
        923 => 'Lambda',
        924 => 'Mu',
        925 => 'Nu',
        926 => 'Xi',
        927 => 'Omicron',
        928 => 'Pi',
        929 => 'Rho',
        931 => 'Sigma',
        932 => 'Tau',
        933 => 'Upsilon',
        934 => 'Phi',
        935 => 'Chi',
        936 => 'Psi',
        937 => 'Omega',
        945 => 'alpha',
        946 => 'beta',
        947 => 'gamma',
        948 => 'delta',
        949 => 'epsilon',
        950 => 'zeta',
        951 => 'eta',
        952 => 'theta',
        953 => 'iota',
        954 => 'kappa',
        955 => 'lambda',
        956 => 'mu',
        957 => 'nu',
        958 => 'xi',
        959 => 'omicron',
        960 => 'pi',
        961 => 'rho',
        962 => 'sigmaf',
        963 => 'sigma',
        964 => 'tau',
        965 => 'upsilon',
        966 => 'phi',
        967 => 'chi',
        968 => 'psi',
        969 => 'omega',
        977 => 'thetasym',
        978 => 'upsih',
        982 => 'piv',
        8226 => 'bull',
        8230 => 'hellip',
        8242 => 'prime',
        8243 => 'Prime',
        8254 => 'oline',
        8260 => 'frasl',
        8472 => 'weierp',
        8465 => 'image',
        8476 => 'real',
        8482 => 'trade',
        8501 => 'alefsym',
        8592 => 'larr',
        8593 => 'uarr',
        8594 => 'rarr',
        8595 => 'darr',
        8596 => 'harr',
        8629 => 'crarr',
        8656 => 'lArr',
        8657 => 'uArr',
        8658 => 'rArr',
        8659 => 'dArr',
        8660 => 'hArr',
        8704 => 'forall',
        8706 => 'part',
        8707 => 'exist',
        8709 => 'empty',
        8711 => 'nabla',
        8712 => 'isin',
        8713 => 'notin',
        8715 => 'ni',
        8719 => 'prod',
        8721 => 'sum',
        8722 => 'minus',
        8727 => 'lowast',
        8730 => 'radic',
        8733 => 'prop',
        8734 => 'infin',
        8736 => 'ang',
        8743 => 'and',
        8744 => 'or',
        8745 => 'cap',
        8746 => 'cup',
        8747 => 'int',
        8756 => 'there4',
        8764 => 'sim',
        8773 => 'cong',
        8776 => 'asymp',
        8800 => 'ne',
        8801 => 'equiv',
        8804 => 'le',
        8805 => 'ge',
        8834 => 'sub',
        8835 => 'sup',
        8836 => 'nsub',
        8838 => 'sube',
        8839 => 'supe',
        8853 => 'oplus',
        8855 => 'otimes',
        8869 => 'perp',
        8901 => 'sdot',
        8968 => 'lceil',
        8969 => 'rceil',
        8970 => 'lfloor',
        8971 => 'rfloor',
        9001 => 'lang',
        9002 => 'rang',
        9674 => 'loz',
        9824 => 'spades',
        9827 => 'clubs',
        9829 => 'hearts',
        9830 => 'diams',
      ),
      'HTMLSPECIAL' => array(
        34 => 'quot',
        38 => 'amp',
        60 => 'lt',
        62 => 'gt',
        338 => 'OElig',
        339 => 'oelig',
        352 => 'Scaron',
        353 => 'scaron',
        376 => 'Yuml',
        710 => 'circ',
        732 => 'tilde',
        8194 => 'ensp',
        8195 => 'emsp',
        8201 => 'thinsp',
        8204 => 'zwnj',
        8205 => 'zwj',
        8206 => 'lrm',
        8207 => 'rlm',
        8211 => 'ndash',
        8212 => 'mdash',
        8216 => 'lsquo',
        8217 => 'rsquo',
        8218 => 'sbquo',
        8220 => 'ldquo',
        8221 => 'rdquo',
        8222 => 'bdquo',
        8224 => 'dagger',
        8225 => 'Dagger',
        8240 => 'permil',
        8249 => 'lsaquo',
        8250 => 'rsaquo',
        8364 => 'euro',
      ),
      'ISO-8859-1' => array(
        34 => 'quot',
        38 => 'amp',
        60 => 'lt',
        62 => 'gt',
        128 => 'euro',
        130 => 'sbquo',
        131 => 'fnof',
        132 => 'bdquo',
        133 => 'hellip',
        134 => 'dagger',
        135 => 'Dagger',
        136 => 'circ',
        137 => 'permil',
        138 => 'Scaron',
        139 => 'lsaquo',
        140 => 'OElig',
        142 => 'Zcaron',
        145 => 'lsquo',
        146 => 'rsquo',
        147 => 'ldquo',
        148 => 'rdquo',
        149 => 'bull',
        150 => 'ndash',
        151 => 'mdash',
        152 => 'tilde',
        153 => 'trade',
        154 => 'scaron',
        155 => 'rsaquo',
        156 => 'oelig',
        158 => 'zcaron',
        159 => 'Yuml',
        160 => 'nbsp',
        161 => 'iexcl',
        162 => 'cent',
        163 => 'pound',
        164 => 'curren',
        165 => 'yen',
        166 => 'brvbar',
        167 => 'sect',
        168 => 'uml',
        169 => 'copy',
        170 => 'ordf',
        171 => 'laquo',
        172 => 'not',
        173 => 'shy',
        174 => 'reg',
        175 => 'macr',
        176 => 'deg',
        177 => 'plusmn',
        178 => 'sup2',
        179 => 'sup3',
        180 => 'acute',
        181 => 'micro',
        182 => 'para',
        183 => 'middot',
        184 => 'cedil',
        185 => 'sup1',
        186 => 'ordm',
        187 => 'raquo',
        188 => 'frac14',
        189 => 'frac12',
        190 => 'frac34',
        191 => 'iquest',
        192 => 'Agrave',
        193 => 'Aacute',
        194 => 'Acirc',
        195 => 'Atilde',
        196 => 'Auml',
        197 => 'Aring',
        198 => 'AElig',
        199 => 'Ccedil',
        200 => 'Egrave',
        201 => 'Eacute',
        202 => 'Ecirc',
        203 => 'Euml',
        204 => 'Igrave',
        205 => 'Iacute',
        206 => 'Icirc',
        207 => 'Iuml',
        208 => 'ETH',
        209 => 'Ntilde',
        210 => 'Ograve',
        211 => 'Oacute',
        212 => 'Ocirc',
        213 => 'Otilde',
        214 => 'Ouml',
        215 => 'times',
        216 => 'Oslash',
        217 => 'Ugrave',
        218 => 'Uacute',
        219 => 'Ucirc',
        220 => 'Uuml',
        221 => 'Yacute',
        222 => 'THORN',
        223 => 'szlig',
        224 => 'agrave',
        225 => 'aacute',
        226 => 'acirc',
        227 => 'atilde',
        228 => 'auml',
        229 => 'aring',
        230 => 'aelig',
        231 => 'ccedil',
        232 => 'egrave',
        233 => 'eacute',
        234 => 'ecirc',
        235 => 'euml',
        236 => 'igrave',
        237 => 'iacute',
        238 => 'icirc',
        239 => 'iuml',
        240 => 'eth',
        241 => 'ntilde',
        242 => 'ograve',
        243 => 'oacute',
        244 => 'ocirc',
        245 => 'otilde',
        246 => 'ouml',
        247 => 'divide',
        248 => 'oslash',
        249 => 'ugrave',
        250 => 'uacute',
        251 => 'ucirc',
        252 => 'uuml',
        253 => 'yacute',
        254 => 'thorn',
        255 => 'yuml',
      ),
      'ISO-8859-1:BYTES' => array(
        128 => "\xe2\x82\xac",
        130 => "\xe2\x80\x9a",
        131 => "\xc6\x92",
        132 => "\xe2\x80\x9e",
        133 => "\xe2\x80\xa6",
        134 => "\xe2\x80\xa0",
        135 => "\xe2\x80\xa1",
        136 => "\xcb\x86",
        137 => "\xe2\x80\xb0",
        138 => "\xc5\xa0",
        139 => "\xe2\x80\xb9",
        140 => "\xc5\x92",
        142 => "\xc5\xbd",
        145 => "\xe2\x80\x98",
        146 => "\xe2\x80\x99",
        147 => "\xe2\x80\x9c",
        148 => "\xe2\x80\x9d",
        149 => "\xe2\x80\xa2",
        150 => "\xe2\x80\x93",
        151 => "\xe2\x80\x94",
        152 => "\xcb\x9c",
        153 => "\xe2\x84\xa2",
        154 => "\xc5\xa1",
        155 => "\xe2\x80\xba",
        156 => "\xc5\x93",
        158 => "\xc5\xbe",
        159 => "\xc5\xb8",
      ),
      // iconv underperformed for transliteration:
      //   https://stackoverflow.com/questions/13614622/transliterate-any-convertible-utf8-char-into-ascii-equivalent
      //   'Kaloúdēs' became 'Kalo?d?s'
      // Thus, building a custom character reference.
      // Need to add extended Latin:
      //   https://www.w3schools.com/charsets/ref_utf_latin_extended_a.asp
      //   https://www.w3schools.com/charsets/ref_utf_latin_extended_b.asp
      //   https://www.w3schools.com/charsets/ref_utf_letterlike.asp
      //   and consider: https://plato.stanford.edu/symbols/entities.html
      'TRANSLITERATE_ASCII' => array(
        145 => '\'',
        146 => '\'',
        147 => '"',
        148 => '"',
        150 => '-',
        151 => '-',
        160 => ' ',
        169 => '(c)',
        171 => '"',
        174 => '(R)',
        177 => '+/i',
        187 => '"',
        188 => '1/4',
        189 => '1/2',
        190 => '3/4',
        // 191 => 'iquest',
        192 => 'A',
        193 => 'A',
        194 => 'A',
        195 => 'A',
        196 => 'A',
        197 => 'A',
        198 => 'AE',
        199 => 'C',
        200 => 'E',
        201 => 'E',
        202 => 'E',
        203 => 'E',
        204 => 'I',
        205 => 'I',
        206 => 'I',
        207 => 'I',
        // 208 => 'ETH',
        209 => 'N',
        210 => 'O',
        211 => 'O',
        212 => 'O',
        213 => 'O',
        214 => 'O',
        215 => 'x',
        // times
        216 => 'O',
        217 => 'U',
        218 => 'U',
        219 => 'U',
        220 => 'U',
        221 => 'Y',
        // 222 => 'THORN',
        223 => 's',
        224 => 'a',
        225 => 'a',
        226 => 'a',
        227 => 'a',
        228 => 'a',
        229 => 'a',
        230 => 'ae',
        231 => 'c',
        232 => 'e',
        233 => 'e',
        234 => 'e',
        235 => 'e',
        236 => 'i',
        237 => 'i',
        238 => 'i',
        239 => 'i',
        // 240 => 'eth',
        241 => 'n',
        242 => 'o',
        243 => 'o',
        244 => 'o',
        245 => 'o',
        246 => 'o',
        248 => 'o',
        249 => 'u',
        250 => 'u',
        251 => 'u',
        252 => 'u',
        253 => 'y',
        // 254 => 'thorn',
        255 => 'y',
        275 => 'e',
        8211 => '-',
        8212 => '-',
        8216 => '\'',
        8217 => '\'',
        8220 => '"',
        8221 => '"',
      ),
    );
    if (!isset($charsets['HTML5'])) {
      $charsets['HTML5'] = array_flip(Html5Entities::getNamedEntities());
    }

    // Build the list of entities.
    $ordinals = array();
    foreach ($entitySets as $id) {
      $id = strtoupper($id);
      if (isset($charsets[$id])) {
        $ordinals = array_replace($charsets[$id], $ordinals);
      }
    }

    // Adjust for the mode.
    switch ($mode) {
      case self::MODE_ENTITYDEC_ENTITYNAME:
        $entities = array();
        foreach ($ordinals as $k => $v) {
          $entities["&#$k;"] = "&$v;";
        }
        return $entities;

      case self::MODE_ENTITYDEC_NAME:
        $entities = array();
        foreach ($ordinals as $k => $v) {
          $entities["&#$k;"] = $v;
        }
        return $entities;

      case self::MODE_ENTITYHEX_ENTITYNAME:
        $entities = array();
        foreach ($ordinals as $k => $v) {
          $entities["&#x" . dechex($k) . ";"] = "&$v;";
        }
        return $entities;

      case self::MODE_ENTITYNAME_ENTITYDEC:
        $entities = array();
        foreach ($ordinals as $k => $v) {
          $entities["&$v;"] = "&#$k;";
        }
        return $entities;

      case self::MODE_CHAR_ENTITYDEC:
        $entities = array();
        foreach ($ordinals as $k => $v) {
          $entities[$v] = "&#$k;";
        }
        return $entities;

      case self::MODE_ORDINAL_NAME:
      default:
        return $ordinals;
    }
  }

  static public function toHtml($source, $params = NULL) {
    if (!is_string($source)) {
      return $source;
    }

    $output = $source;

    // Normalize params.
    if (!is_array($params)) {
      if (is_string($params)) {
        $params = array(
          'default_settings' => $params,
        );
      }
      else {
        $params = (array) $params;
      }
    }

    // Expand the default settings
    if (isset($params['default_settings'])) {
      switch ($params['default_settings']) {
        case 'ascii':
          $params = array_replace(array(
            'from_encoding' => 'UTF-8',
            'from_encoding_aggressive' => TRUE,
            'entities_prefer_numeric' => TRUE,
            'transliterate_ascii' => TRUE,
          ), $params);
          break;

        case 'user input':
          $params = array_replace(array(
            'purify' => TRUE,
          ), $params);
          break;
      }
    }

    // Default params.
    $params = array_replace(array(
      'default_settings' => NULL,
      'from_encoding' => NULL,
      // from: UTF-8, ISO-8859-1
      'from_encoding_aggressive' => TRUE,
      'transliterate_ascii' => FALSE,
      'entities_prefer_numeric' => FALSE,
      'remove_carriage_return' => TRUE,
      'escape_ampersand' => FALSE,
      'escape_ampersand_selective' => FALSE,
      'escape_entities' => FALSE,
      'tags' => 'ignore',
      'tags_allowed' => NULL,
      'purify' => FALSE,
    // Potential params:
    //       'quotes' => ENT_NOQUOTES,
    //       // doctype = ENT_HTML5, ENT_XML1, ENT_HTML401
    //       'doctype' => ENT_XHTML,
    //       'allow_tags' => TRUE,
    ), $params);

    // Force configurations.
    if ($params['transliterate_ascii']) {
      $params['entities_prefer_numeric'] = TRUE;
    }

    // If a source charset is provided, then convert to UTF-8.
    if (isset($params['from_encoding'])) {
      if ($params['from_encoding'] === 'UTF-8') {
        /**
         * Info on entity translations:
         * @link http://www.w3.org/TR/xhtml-modularization/dtd_module_defs.html#a_xhtml_character_entities
         * Most of the multibyte problems can be addressed by casting the character set out of UTF-8!
         */
        if (preg_match('/[\194-\226]/', $output)) {
          // utf8_decode breaks things it does not understand, so we use a fancier tactic.
          // $data = utf8_decode($data);

          /* Only do the slow convert if there are 8-bit characters */
          /* avoid using 0xA0 (\240) in ereg ranges. RH73 does not like that */
          if (!preg_match("/[\200-\237\241-\377]/", $output)) {
          }
          else {
            // decode three byte unicode characters
            $output = preg_replace_callback("/([\340-\357])([\200-\277])([\200-\277])/", function ($matches) {
              return '&#'
                . ((ord($matches[1]) - 224) * 4096
                  + (ord($matches[2]) - 128) * 64 + (ord($matches[3]) - 128))
                . ';';
            }, $output);
            // decode two byte unicode characters
            $output = preg_replace_callback("/([\300-\337])([\200-\277])/", function ($matches) {
              return '&#'
                . ((ord($matches[1]) - 192) * 64 + (ord($matches[2]) - 128))
                . ';';
            }, $output);
          }
        }
        if ($params['from_encoding_aggressive']) {
          $output = strtr($output, self::getEntitiesMap('ISO-8859-1:BYTES', self::MODE_CHAR_ENTITYDEC));
        }
      }
      else {
        if (function_exists('mb_convert_encoding')) {
          $output = mb_convert_encoding($output, 'UTF-8', $params['from_encoding']);
        }
        else {
          $output = iconv($params['from_encoding'], 'UTF-8', $output);
        }
      }
    }

    // Remove carriage returns
    if ($params['remove_carriage_return']) {
      $output = strtr($output, array(
        "\r" => '',
      ));
    }

    // Escape the ampersand if requested.
    if ($params['escape_ampersand']) {
      $output = str_replace('&', '&amp;', $output);
    }
    if ($params['escape_ampersand_selective']) {
      $tmp = explode('&', $output);
      $output = array_shift($tmp);
      foreach ($tmp as $t) {
        if (preg_match("@^[^\s;]{0,10};@s", $t)) {
          $output .= '&' . $t;
        }
        else {
          $output .= '&amp;' . $t;
        }
      }
    }

    // Attempt fast encoding
    if (function_exists('mb_convert_encoding')) {
      if ($params['from_encoding']) {
        $output = mb_convert_encoding($output, 'HTML-ENTITIES', 'UTF-8');
      }
      else {
        $output = mb_convert_encoding($output, 'HTML-ENTITIES');
      }
    }
    else {
      // Convert non-ASCII characters to entities.
      while (preg_match('/[^\00-\177]/', $output, $arr)) {
        $output = str_replace($arr[0], '&#' . ord($arr[0]) . ';', $output);
      }
    }

    // Remove numeric entities whenever possible.
    if (!$params['entities_prefer_numeric'] && strpos($output, '&#') !== FALSE) {
      $output = strtr($output, self::getEntitiesMap(NULL, self::MODE_ENTITYDEC_ENTITYNAME));
      if (strpos($output, '&#x') !== FALSE) {
        $output = preg_replace_callback('@&#x([^;]*);@s', function ($matches) {
          return '&#x' . strtoupper(ltrim($matches[1], '0')) . ';';
        }, $output);
        $output = strtr($output, self::getEntitiesMap(NULL, self::MODE_ENTITYHEX_ENTITYNAME));
      }
    }

    // Transliterate any remaining entities to ASCII when possible.
    //   (i.e., remove accents)
    if ($params['transliterate_ascii'] && strpos($output, '&') !== FALSE) {
      if (strpos($output, '&#') !== FALSE) {
        $output = strtr($output, self::getEntitiesMap('TRANSLITERATE_ASCII', self::MODE_ENTITYDEC_NAME));
      }
      if (strpos($output, '&') !== FALSE) {
        $html = self::getEntitiesMap('HTMLLAT1', self::MODE_ORDINAL_NAME);
        foreach (self::getEntitiesMap('TRANSLITERATE_ASCII', self::MODE_ORDINAL_NAME) as $k => $v) {
          if (isset($html[$k])) {
            $output = str_replace("&{$html[$k]};", $v, $output);
          }
        }
      }
    }

    // Replace < > to disable tags.
    switch ($params['tags']) {
      case 'ignore':
        break;

      case 'remove':
        $output = strip_tags($output);
        $output = strtr($output, array(
          '<' => '&lt;',
          '>' => '&gt;',
        ));
        break;

      case 'disable':
      default:
        $output = strtr($output, array(
          '<' => '&lt;',
          '>' => '&gt;',
        ));
    }

    // Convert to numeric when necessary.
    if ($params['entities_prefer_numeric']) {
      if (strpos($output, '&') !== FALSE) {
        $output = strtr($output, self::getEntitiesMap(NULL, self::MODE_ENTITYNAME_ENTITYDEC));
      }
    }

    // Perform basic xss filtering.
    if ($params['purify'] !== FALSE) {
      $purified = FALSE;
      $purify = $params['purify'];
      if ($purify === TRUE) {
        if (function_exists('filter_xss')) {
          $purify = 'filter_xss';
        }
        elseif (class_exists('HTMLPurifier')) {
          $purify = 'htmlpurifier';
        }
      }
      switch ($purify) {
        case 'filter_xss':
        // https://api.drupal.org/api/drupal/includes%21common.inc/function/filter_xss/7.x
          if (is_array($params['tags_allowed'])) {
            $output = filter_xss($output, $params['tags_allowed']);
          }
          else {
            $output = filter_xss($output);
          }
          $purified = TRUE;
          break;
        case 'htmlpurifier':
        // http://htmlpurifier.org/live/configdoc/plain.html
          $config = HTMLPurifier_Config::createDefault();
          if (is_array($params['tags_allowed'])) {
            $config->set('HTML.AllowedElements', join(',', $params['tags_allowed']));
          }
          $purifier = new HTMLPurifier($config);
          $output = $purifier->purify($output);
          $purified = TRUE;
          break;
      }
      if (!$purified) {
        throw new \InvalidArgumentException("Invalid purify setting - unable to locate purifier.");
      }
    }

    // Escape all entities
    if ($params['escape_entities']) {
      $output = str_replace('&', '&amp;', $output);
    }

    // Potential strategy that disables all tags:
    //     $output = htmlentities($output, ENT_SUBSTITUTE | $params['quotes']
    //       | $params['doctype'], $params['charset'], $params['double_encode']);

    return $output;
  }

  static public function toHtmlSafe($source, $set_conf = NULL) {
    static $conf = array(
      'default_settings' => 'user input',
    );
    if (isset($set_conf)) {
      if ($set_conf === FALSE) {
        $conf = array(
          'default_settings' => 'user input',
        );
      }
      elseif (is_array($set_conf)) {
        $conf = array_replace($conf, $set_conf);
      }
    }
    return self::toHtml($source, $conf);
  }

}