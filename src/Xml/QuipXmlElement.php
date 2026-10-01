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

namespace QuipXml\Xml;

use QuipXml\Xml\Exception\NotPermanentMemberException;
use QuipXml\Encoding\CharacterEncoding;
use QuipXml\Quip;

/**
 * A SimpleXML node with chainable, jQuery-style verbs.
 *
 * Quip::load() returns one, and so does every SimpleXML traversal from it
 * (`$quip->list->item`), because SimpleXML keeps the class it was loaded
 * with. A query (qxpath(), qxparent(), qxprev()) returns a
 * QuipXmlElementIterator over the matches, or an empty QuipXmlElement when
 * nothing matched.
 *
 * The node may be an element or, from attributes(), an attribute. The
 * structural verbs (before, after, append, wrap, wrapInner, unwrap, setTag)
 * throw a \LogicException on an attribute; html() and text() read its value,
 * text() writes it, and remove() deletes it.
 *
 * SimpleXMLElement's own xpath() returns an array, and a method of that name
 * returning anything else cannot be a legal override, so the Quip query
 * methods carry a `q` prefix and xpath(), xparent() and xprev() throw.
 */
class QuipXmlElement extends \SimpleXMLElement implements QuipXmlElementInterface {

  /**
   * The document root behind each empty result this class handed out.
   *
   * An empty result is a missing child, which SimpleXML cannot query from;
   * remembering its root lets an absolute qxpath() on it still search the
   * document it came from.
   *
   * @var \WeakMap<\QuipXml\Xml\QuipXmlElement, \QuipXml\Xml\QuipXmlElement>|null
   */
  private static ?\WeakMap $emptyRoots = NULL;

  /**
   * The XPath namespace prefixes registered on each node.
   *
   * SimpleXML keeps a registration on the one object it was made on; Quip
   * copies it onto every node it hands back from that object (#5).
   *
   * @var \WeakMap<\QuipXml\Xml\QuipXmlElement, array<string, string>>|null
   */
  private static ?\WeakMap $xpathNamespaces = NULL;

  /**
   * Converts content into a DOM node that can be inserted next to this node.
   *
   * @param string|\SimpleXMLElement|\DOMNode $content
   *   Markup, or a node to copy in.
   * @param bool $return_parent
   *   For markup, return a wrapper element holding every top-level node of
   *   the markup; for a node, return its parent.
   *
   * @return \DOMNode
   *   A node owned by this node's document.
   *
   * @throws \InvalidArgumentException
   *   When the content does not resolve to a node.
   * @throws \QuipXml\Xml\Exception\NotPermanentMemberException
   *   When this node is not attached to a document.
   */
  protected function contentToDom(string|\SimpleXMLElement|\DOMNode $content, bool $return_parent = FALSE): \DOMNode {
    if ($content instanceof \SimpleXMLElement) {
      try {
        $new = dom_import_simplexml($content);
      }
      catch (\TypeError $e) {
        throw new \InvalidArgumentException('The content is not a node in a document.', 0, $e);
      }
    }
    elseif ($content instanceof \DOMNode) {
      $new = $content;
    }
    else {
      $new = Quip::load($return_parent ? "<root>$content</root>" : $content)->dom();
      $return_parent = FALSE;
    }
    if ($return_parent && $new instanceof \DOMNode) {
      $new = $new->parentNode;
    }
    if (!$new instanceof \DOMNode) {
      throw new \InvalidArgumentException('The content does not resolve to a node.');
    }

    $me = $this->dom();
    if ($me === FALSE || $me->ownerDocument === NULL) {
      throw new NotPermanentMemberException();
    }
    if ($new->ownerDocument !== $me->ownerDocument) {
      $new = $me->ownerDocument->importNode($new->cloneNode(TRUE), TRUE);
    }
    return $new;
  }

  /**
   * Returns an empty result tied to this node's document.
   *
   * @return \QuipXml\Xml\QuipXmlElement
   *   A missing child that casts to FALSE and iterates nothing.
   */
  protected function getEmptyElement(): QuipXmlElement {
    self::$emptyRoots ??= new \WeakMap();
    $found = parent::xpath('/*');
    $root = is_array($found) ? ($found[0] ?? NULL) : NULL;
    if (!$root instanceof self) {
      $root = self::$emptyRoots[$this] ?? new self('<empty/>');
    }
    // '#empty' is not a legal XML name, so it never matches a real child.
    $empty = $root->{'#empty'};
    if (!$empty instanceof self) {
      throw new \LogicException('SimpleXML did not return a ' . self::class . '.');
    }
    self::$emptyRoots[$empty] = $root;
    return $this->passNamespaces($empty);
  }

  /**
   * Registers this node's XPath namespace prefixes on a node it returns.
   *
   * @param T $node
   *   The node being handed back.
   *
   * @return T
   *   The same node.
   *
   * @template T of \QuipXml\Xml\QuipXmlElement
   */
  private function passNamespaces(QuipXmlElement $node): QuipXmlElement {
    if ($node !== $this) {
      foreach (self::$xpathNamespaces[$this] ?? [] as $prefix => $namespace) {
        $node->registerXPathNamespace($prefix, $namespace);
      }
    }
    return $node;
  }

  /**
   * Returns the element node a structural verb acts on.
   *
   * @param string $verb
   *   The verb, for the error message.
   *
   * @return \DOMElement|null
   *   The element, or NULL when this is an empty result or a missing node.
   *
   * @throws \LogicException
   *   When this node is an attribute.
   */
  private function elementNode(string $verb): ?\DOMElement {
    $me = $this->dom();
    if ($me instanceof \DOMAttr) {
      throw new \LogicException("$verb() does not apply to an attribute node.");
    }
    return $me instanceof \DOMElement ? $me : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function addChild(string $qualifiedName, ?string $value = NULL, ?string $namespace = NULL): ?static {
    $child = parent::addChild($qualifiedName, $value, $namespace);
    return $child instanceof static ? $this->passNamespaces($child) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function after(string|\SimpleXMLElement|\DOMNode $content): static {
    $me = $this->elementNode('after');
    if ($me !== NULL && $me->parentNode !== NULL) {
      $me->parentNode->insertBefore($this->contentToDom($content), $me->nextSibling);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function append(string|\SimpleXMLElement|\DOMNode $content): static {
    $me = $this->elementNode('append');
    if ($me !== NULL) {
      $me->appendChild($this->contentToDom($content));
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function attributes(?string $namespaceOrPrefix = NULL, bool $isPrefix = FALSE): ?static {
    $attributes = parent::attributes($namespaceOrPrefix, $isPrefix);
    return $attributes instanceof static ? $this->passNamespaces($attributes) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function before(string|\SimpleXMLElement|\DOMNode $content): static {
    $me = $this->elementNode('before');
    if ($me !== NULL && $me->parentNode !== NULL) {
      $me->parentNode->insertBefore($this->contentToDom($content), $me);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function children(?string $namespaceOrPrefix = NULL, bool $isPrefix = FALSE): ?static {
    $children = parent::children($namespaceOrPrefix, $isPrefix);
    return $children instanceof static ? $this->passNamespaces($children) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function dom(int $index = 0): \DOMNode|false {
    if ($index !== 0) {
      return FALSE;
    }
    try {
      return dom_import_simplexml($this);
    }
    catch (\TypeError) {
      // A missing child has no node behind it.
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   *
   * A single node returns itself whatever the index.
   */
  public function eq(int $index = 0): QuipXmlElement {
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function get(string $path): QuipXmlElement {
    // Walk the longest prefix of the path that already exists.
    $parts = explode('/', $path);
    $prev = (string) array_shift($parts);
    $prefix = $prev;
    $cursor = $this;
    while (TRUE) {
      if (preg_match('@[^/]@', $prefix)) {
        $match = $this->qxpath($prefix);
        if ($match instanceof QuipXmlElementIterator && count($match) > 0) {
          $cursor = $match->eq(0);
        }
        else {
          array_unshift($parts, $prev);
          break;
        }
      }
      if (empty($parts)) {
        break;
      }
      $prev = (string) array_shift($parts);
      $prefix .= '/' . $prev;
    }

    // Only plain names and positional filters can be created.
    if (preg_match("@[:\.\*]|//@s", implode('/', $parts))) {
      throw new \InvalidArgumentException("Unable to init XML path ($prefix) containing [:.*] or //");
    }

    foreach ($parts as $part) {
      if (strpos($part, '[') === FALSE) {
        $next = $cursor->addChild($part);
      }
      elseif (preg_match('@^(?<name>.*)\[(?<pos>\d+)\]$@s', $part, $arr)) {
        // Add siblings of that name until the position exists.
        $next = NULL;
        for ($limit = (int) $arr['pos']; $limit > 0; --$limit) {
          $new = $cursor->addChild($arr['name']);
          if (count($cursor->qxpath($part)) > 0) {
            $next = $new;
            break;
          }
        }
        if ($next === NULL) {
          throw new \ErrorException("Unable to build XML part ($part) for path ($path).");
        }
      }
      else {
        throw new \InvalidArgumentException("Unable to init XML path ($prefix) containing complex filters");
      }
      if (!$next instanceof self) {
        throw new NotPermanentMemberException();
      }
      $cursor = $next;
    }

    return $cursor;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-return ($content is null|\QuipXml\Xml\QuipXmlFormatter ? string : static)
   */
  public function html(string|\SimpleXMLElement|\DOMNode|QuipXmlFormatter|null $content = NULL): string|static {
    if ($content === NULL) {
      $me = $this->dom();
      if ($me === FALSE) {
        return '';
      }
      if ($me instanceof \DOMAttr) {
        return (string) $this;
      }
      $str = trim((string) parent::asXML());
      // Drop the opening tag (after any XML declaration) and the closing tag.
      do {
        [$open, $str] = array_pad(explode('>', $str, 2), 2, '');
      } while (substr($open, -1) === '?');
      $tmp = explode('<', $str);
      array_pop($tmp);
      return strtr(trim(implode('<', $tmp)), [
        "\r" => '',
        '&#13;' => '',
        '&#xA0;' => '&nbsp;',
      ]);
    }
    if ($content instanceof QuipXmlFormatter) {
      return $content->getFormattedInner($this);
    }

    $me = $this->dom();
    if ($me instanceof \DOMAttr) {
      throw new \LogicException('html() cannot write markup into an attribute node; use text().');
    }
    if ($me === FALSE) {
      throw new NotPermanentMemberException();
    }
    $new = $this->contentToDom($content, TRUE);
    while ($me->firstChild !== NULL) {
      $me->removeChild($me->firstChild);
    }
    foreach ($new->childNodes as $child) {
      $me->appendChild($child->cloneNode(TRUE));
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function htmlOuter(?QuipXmlFormatter $formatter = NULL): string {
    if ($this->dom() === FALSE) {
      return '';
    }
    if ($formatter !== NULL) {
      return $formatter->getFormattedOuter($this);
    }
    $str = (string) preg_replace('@^<\?xml.*?\?>\s*@s', '', (string) parent::asXML());
    return trim(strtr($str, [
      "\r" => '',
      '&#13;' => '',
      '&#xA0;' => '&nbsp;',
    ]));
  }

  /**
   * {@inheritdoc}
   */
  public function remove(): bool {
    $me = $this->dom();
    if ($me instanceof \DOMAttr) {
      return $me->ownerElement !== NULL && $me->ownerElement->removeAttributeNode($me) !== FALSE;
    }
    if ($me === FALSE || $me->parentNode === NULL) {
      return FALSE;
    }
    $me->parentNode->removeChild($me);
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function setTag(string $tag): QuipXmlElement|QuipXmlElementIterator {
    $me = $this->elementNode('setTag');
    if ($me === NULL || $me->parentNode === NULL || $me->ownerDocument === NULL) {
      return $this;
    }
    if (preg_match('@^[a-z_][a-z0-9_.:-]*$@si', $tag)) {
      $new = $me->ownerDocument->createElement($tag);
      foreach ($me->attributes as $attribute) {
        $copy = $attribute->cloneNode();
        if ($copy instanceof \DOMAttr) {
          $new->setAttributeNode($copy);
        }
      }
    }
    else {
      // An opening tag on its own is not well formed; close it.
      $new = $this->contentToDom((string) preg_replace('@^(<[^<>/]+?)\s*/?>$@s', '$1/>', $tag));
    }
    if (!$new instanceof \DOMElement) {
      throw new \InvalidArgumentException("setTag() needs a tag name or an opening tag, not ($tag).");
    }
    while ($me->firstChild !== NULL) {
      $new->appendChild($me->firstChild);
    }
    $me->parentNode->replaceChild($new, $me);
    $quip = simplexml_import_dom($new, static::class);
    if (!$quip instanceof self) {
      throw new \LogicException('SimpleXML could not import the new element.');
    }
    return $quip;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-return ($content is null ? string : static)
   */
  public function text(string|int|float|null $content = NULL): string|static {
    $me = $this->dom();
    if ($content === NULL) {
      return $me instanceof \DOMAttr ? (string) $this : strip_tags((string) $this->html());
    }
    if ($me instanceof \DOMAttr) {
      $me->value = (string) $content;
      return $this;
    }
    if ($me === FALSE) {
      throw new NotPermanentMemberException();
    }
    // DOM reads entities in an element's nodeValue, so escape bare ampersands
    // and leave existing entities alone.
    $me->nodeValue = CharacterEncoding::toHtml((string) $content, [
      'escape_ampersand_selective' => TRUE,
      'entities_prefer_numeric' => TRUE,
    ]);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function unwrap(): QuipXmlElement|QuipXmlElementIterator {
    $parent = $this->elementNode('unwrap')?->parentNode;
    // The root has no element to hand its children to.
    if (!$parent instanceof \DOMElement || !$parent->parentNode instanceof \DOMElement) {
      return $this->getEmptyElement();
    }
    while ($parent->firstChild !== NULL) {
      $parent->parentNode->insertBefore($parent->firstChild, $parent);
    }
    $parent->parentNode->removeChild($parent);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function wrap(string|\SimpleXMLElement|\DOMNode $content): static {
    $me = $this->elementNode('wrap');
    if ($me !== NULL && $me->parentNode !== NULL) {
      $wrapper = $this->contentToDom($content);
      $me->parentNode->replaceChild($wrapper, $me);
      $wrapper->appendChild($me);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function wrapInner(string|\SimpleXMLElement|\DOMNode $content): static {
    $me = $this->elementNode('wrapInner');
    if ($me !== NULL) {
      $wrapper = $this->contentToDom($content);
      while ($me->firstChild !== NULL) {
        $wrapper->appendChild($me->firstChild);
      }
      $me->appendChild($wrapper);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   *
   * The prefix also reaches every node Quip returns from this one: query
   * results, empty results, and the results of children(), attributes() and
   * addChild(). Nodes SimpleXML makes itself, by property access or by
   * iterating a SimpleXML list, start without it.
   */
  // phpcs:ignore Drupal.NamingConventions.ValidFunctionName.ScopeNotCamelCaps -- SimpleXMLElement::registerXPathNamespace() names it.
  public function registerXPathNamespace(string $prefix, string $namespace): bool {
    if (!parent::registerXPathNamespace($prefix, $namespace)) {
      return FALSE;
    }
    self::$xpathNamespaces ??= new \WeakMap();
    $registered = self::$xpathNamespaces[$this] ?? [];
    $registered[$prefix] = $namespace;
    self::$xpathNamespaces[$this] = $registered;
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function qxparent(): QuipXmlElement|QuipXmlElementIterator {
    return $this->qxpath('..');
  }

  /**
   * {@inheritdoc}
   */
  public function qxprev(): QuipXmlElement|QuipXmlElementIterator {
    return $this->qxpath('preceding-sibling::*[1]');
  }

  /**
   * {@inheritdoc}
   */
  public function qxpath(string $path): QuipXmlElement|QuipXmlElementIterator {
    $results = parent::xpath($path);
    if (!is_array($results)) {
      // SimpleXML cannot query from a missing node; an empty result this
      // class made still knows its document.
      $root = self::$emptyRoots[$this] ?? NULL;
      if ($root !== NULL && str_starts_with($path, '/')) {
        return $this->passNamespaces($root)->qxpath($path);
      }
      return $this->getEmptyElement();
    }
    if ($results === []) {
      return $this->getEmptyElement();
    }
    foreach ($results as $result) {
      $this->passNamespaces($result);
    }
    return new QuipXmlElementIterator(new \ArrayIterator($results));
  }

  /**
   * Retired: SimpleXMLElement::xpath() cannot be overridden to return a set.
   *
   * @param string $expression
   *   Ignored.
   *
   * @throws \RuntimeException
   *   Always.
   */
  public function xpath(string $expression): never {
    throw new \RuntimeException('QuipXml 1.0 retired xpath(); call qxpath(), which returns a QuipXmlElementIterator.');
  }

  /**
   * Retired: renamed qxparent() alongside qxpath().
   *
   * @throws \RuntimeException
   *   Always.
   */
  public function xparent(): never {
    throw new \RuntimeException('QuipXml 1.0 retired xparent(); call qxparent().');
  }

  /**
   * Retired: renamed qxprev() alongside qxpath().
   *
   * @throws \RuntimeException
   *   Always.
   */
  public function xprev(): never {
    throw new \RuntimeException('QuipXml 1.0 retired xprev(); call qxprev().');
  }

}
