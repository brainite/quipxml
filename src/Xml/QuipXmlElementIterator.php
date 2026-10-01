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

/**
 * The set of nodes a Quip query matched, with the same verbs as one node.
 *
 * A verb that changes the document applies to every node in the set, and a
 * query or traversal (qxpath, qxparent, children, attributes, get, addChild,
 * setTag) collects every node's result into a new set. A getter reads the
 * first node (html, htmlOuter, text, asXML, getName), except the ones that
 * combine every node: casting to string concatenates each node's text, and
 * getNamespaces() and getDocNamespaces() merge each node's namespaces.
 *
 * The set holds each DOM node once, in the order first seen, and never holds
 * an empty result. A query that matches nothing returns an empty
 * QuipXmlElement rather than an empty set, so it casts to FALSE.
 *
 * Reading a property (`$set->item`) returns that child of the single node,
 * or every node's children of that name as one set; writing one writes it on
 * every node.
 *
 * @extends \IteratorIterator<mixed, \QuipXml\Xml\QuipXmlElement, \ArrayIterator<int, \QuipXml\Xml\QuipXmlElement>>
 * @mixin \QuipXml\Xml\QuipXmlElement
 */
class QuipXmlElementIterator extends \IteratorIterator implements QuipXmlElementInterface {

  /**
   * The nodes in the set.
   *
   * @var list<\QuipXml\Xml\QuipXmlElement>
   */
  private array $nodes = [];

  /**
   * Builds a set from nodes, dropping empty results and repeated nodes.
   *
   * @param iterable<mixed, mixed> $iterator
   *   QuipXmlElement nodes; anything else is skipped.
   */
  public function __construct(iterable $iterator) {
    $doms = [];
    foreach ($iterator as $node) {
      if (!$node instanceof QuipXmlElement) {
        continue;
      }
      $dom = $node->dom();
      if ($dom === FALSE) {
        continue;
      }
      foreach ($doms as $seen) {
        if ($dom->isSameNode($seen)) {
          continue 2;
        }
      }
      $this->nodes[] = $node;
      $doms[] = $dom;
    }
    parent::__construct(new \ArrayIterator($this->nodes));
    $this->rewind();
  }

  /**
   * Reads a child property of the nodes, as SimpleXML does for one node.
   *
   * @param string $name
   *   The child element name.
   *
   * @return \QuipXml\Xml\QuipXmlElement|\QuipXml\Xml\QuipXmlElementIterator|null
   *   The single node's children of that name, or every node's as one set.
   */
  public function __get(string $name): QuipXmlElement|QuipXmlElementIterator|null {
    if (count($this->nodes) === 1) {
      $children = $this->nodes[0]->{$name};
      return $children instanceof QuipXmlElement ? $children : NULL;
    }
    $children = [];
    foreach ($this->nodes as $node) {
      foreach ($node->{$name} ?? [] as $child) {
        $children[] = $child;
      }
    }
    $set = new self($children);
    return count($set) > 0 ? $set : $this->getEmptyElement();
  }

  /**
   * Whether any node has a child of that name, as SimpleXML answers isset().
   *
   * @param string $name
   *   The child element name.
   *
   * @return bool
   *   TRUE when at least one node has such a child.
   */
  public function __isset(string $name): bool {
    foreach ($this->nodes as $node) {
      if (isset($node->{$name})) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Writes a child property on every node, as SimpleXML does for one node.
   *
   * @param string $name
   *   The child element name.
   * @param mixed $value
   *   The value SimpleXML assigns.
   */
  public function __set(string $name, mixed $value): void {
    foreach ($this->nodes as $node) {
      $node->{$name} = $value;
    }
  }

  /**
   * Concatenates the text of every node.
   *
   * @return string
   *   Each node's string value (its own text, as SimpleXML casts it), joined
   *   with nothing between.
   */
  public function __toString(): string {
    return implode('', array_map(static fn (QuipXmlElement $node): string => (string) $node, $this->nodes));
  }

  /**
   * Collects each node's result into one set.
   *
   * @param \Closure(\QuipXml\Xml\QuipXmlElement): mixed $callback
   *   Returns a node, a set, or NULL for each node.
   * @param bool $flatten
   *   Whether a returned node stands for a list to iterate (children() and
   *   attributes() return one) rather than for itself.
   *
   * @return \QuipXml\Xml\QuipXmlElement|\QuipXml\Xml\QuipXmlElementIterator
   *   The collected set, or an empty element when it is empty.
   */
  protected function eachGetIterator(\Closure $callback, bool $flatten = FALSE): QuipXmlElement|QuipXmlElementIterator {
    $results = [];
    foreach ($this->nodes as $node) {
      $result = $callback($node);
      if ($result instanceof self) {
        foreach ($result as $item) {
          $results[] = $item;
        }
      }
      elseif ($flatten && $result instanceof QuipXmlElement) {
        // Iterating a SimpleXML list makes new nodes; give them the list's
        // registered prefixes.
        foreach ($result as $item) {
          QuipXmlElement::shareXpathNamespaces($result, $item);
          $results[] = $item;
        }
      }
      elseif ($result instanceof QuipXmlElement) {
        $results[] = $result;
      }
    }
    $set = new self($results);
    return count($set) > 0 ? $set : $this->getEmptyElement();
  }

  /**
   * Applies a verb to each node.
   *
   * @param \Closure(\QuipXml\Xml\QuipXmlElement): mixed $callback
   *   The verb, applied to one node.
   *
   * @return static
   *   The same set, for chaining.
   */
  protected function eachSetter(\Closure $callback): static {
    foreach ($this->nodes as $node) {
      $callback($node);
    }
    return $this;
  }

  /**
   * Returns an empty result tied to the set's document.
   *
   * @return \QuipXml\Xml\QuipXmlElement
   *   A missing child that casts to FALSE and iterates nothing.
   */
  protected function getEmptyElement(): QuipXmlElement {
    // A query that can never match returns the node's own empty result.
    $empty = ($this->nodes[0] ?? new QuipXmlElement('<empty/>'))->qxpath('self::node()[false()]');
    if (!$empty instanceof QuipXmlElement) {
      throw new \LogicException('An unmatchable query returned a set.');
    }
    return $empty;
  }

  /**
   * Reads a value from the first node.
   *
   * @param \Closure(\QuipXml\Xml\QuipXmlElement): mixed $callback
   *   The getter, applied to one node.
   *
   * @return mixed
   *   The first node's value, or NULL for an empty set.
   */
  protected function singleGetter(\Closure $callback): mixed {
    return isset($this->nodes[0]) ? $callback($this->nodes[0]) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function addAttribute(string $qualifiedName, string $value, ?string $namespace = NULL): void {
    $this->eachSetter(static fn (QuipXmlElement $node) => $node->addAttribute($qualifiedName, $value, $namespace));
  }

  /**
   * {@inheritdoc}
   */
  public function addChild(string $qualifiedName, ?string $value = NULL, ?string $namespace = NULL): QuipXmlElement|QuipXmlElementIterator {
    return $this->eachGetIterator(static fn (QuipXmlElement $node) => $node->addChild($qualifiedName, $value, $namespace));
  }

  /**
   * {@inheritdoc}
   */
  public function after(string|\SimpleXMLElement|\DOMNode $content): static {
    return $this->eachSetter(static fn (QuipXmlElement $node) => $node->after($content));
  }

  /**
   * {@inheritdoc}
   */
  public function append(string|\SimpleXMLElement|\DOMNode $content): static {
    return $this->eachSetter(static fn (QuipXmlElement $node) => $node->append($content));
  }

  /**
   * {@inheritdoc}
   */
  // phpcs:ignore Drupal.NamingConventions.ValidFunctionName.ScopeNotCamelCaps -- SimpleXMLElement::asXML() names it.
  public function asXML(?string $filename = NULL): string|bool {
    $xml = $this->singleGetter(static fn (QuipXmlElement $node) => $filename === NULL ? $node->asXML() : $node->asXML($filename));
    return is_string($xml) || is_bool($xml) ? $xml : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function attributes(?string $namespaceOrPrefix = NULL, bool $isPrefix = FALSE): QuipXmlElement|QuipXmlElementIterator {
    return $this->eachGetIterator(static fn (QuipXmlElement $node) => $node->attributes($namespaceOrPrefix, $isPrefix), TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function before(string|\SimpleXMLElement|\DOMNode $content): static {
    return $this->eachSetter(static fn (QuipXmlElement $node) => $node->before($content));
  }

  /**
   * {@inheritdoc}
   */
  public function children(?string $namespaceOrPrefix = NULL, bool $isPrefix = FALSE): QuipXmlElement|QuipXmlElementIterator {
    return $this->eachGetIterator(static fn (QuipXmlElement $node) => $node->children($namespaceOrPrefix, $isPrefix), TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function count(): int {
    return count($this->nodes);
  }

  /**
   * {@inheritdoc}
   */
  public function dom(int $index = 0): \DOMNode|false {
    return $this->eq($index)->dom();
  }

  /**
   * {@inheritdoc}
   */
  public function eq(int $index = 0): QuipXmlElement {
    return $this->nodes[$index] ?? $this->getEmptyElement();
  }

  /**
   * {@inheritdoc}
   */
  public function get(string $path): QuipXmlElement|QuipXmlElementIterator {
    return $this->eachGetIterator(static fn (QuipXmlElement $node) => $node->get($path));
  }

  /**
   * {@inheritdoc}
   */
  public function getDocNamespaces(bool $recursive = FALSE, bool $fromRoot = TRUE): array {
    $namespaces = [];
    foreach ($this->nodes as $node) {
      $namespaces += $node->getDocNamespaces($recursive, $fromRoot) ?: [];
    }
    return $namespaces;
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return isset($this->nodes[0]) ? $this->nodes[0]->getName() : '';
  }

  /**
   * {@inheritdoc}
   */
  public function getNamespaces(bool $recursive = FALSE): array {
    $namespaces = [];
    foreach ($this->nodes as $node) {
      $namespaces += $node->getNamespaces($recursive);
    }
    return $namespaces;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-return ($content is null|\QuipXml\Xml\QuipXmlFormatter ? string : static)
   */
  public function html(string|\SimpleXMLElement|\DOMNode|QuipXmlFormatter|null $content = NULL): string|static {
    if ($content === NULL || $content instanceof QuipXmlFormatter) {
      $html = $this->singleGetter(static fn (QuipXmlElement $node) => $node->html($content));
      return is_string($html) ? $html : '';
    }
    return $this->eachSetter(static fn (QuipXmlElement $node) => $node->html($content));
  }

  /**
   * {@inheritdoc}
   */
  public function htmlOuter(?QuipXmlFormatter $formatter = NULL): string {
    $html = $this->singleGetter(static fn (QuipXmlElement $node) => $node->htmlOuter($formatter));
    return is_string($html) ? $html : '';
  }

  /**
   * {@inheritdoc}
   */
  // phpcs:ignore Drupal.NamingConventions.ValidFunctionName.ScopeNotCamelCaps -- SimpleXMLElement::registerXPathNamespace() names it.
  public function registerXPathNamespace(string $prefix, string $namespace): bool {
    $registered = TRUE;
    foreach ($this->nodes as $node) {
      $registered = $node->registerXPathNamespace($prefix, $namespace) && $registered;
    }
    return $registered;
  }

  /**
   * {@inheritdoc}
   */
  public function remove(): bool {
    $removed = FALSE;
    foreach ($this->nodes as $node) {
      $removed = $node->remove() || $removed;
    }
    return $removed;
  }

  /**
   * {@inheritdoc}
   */
  // phpcs:ignore Drupal.NamingConventions.ValidFunctionName.ScopeNotCamelCaps -- SimpleXMLElement::saveXML() names it.
  public function saveXML(?string $filename = NULL): string|bool {
    return $this->asXML($filename);
  }

  /**
   * {@inheritdoc}
   */
  public function setTag(string $tag): QuipXmlElement|QuipXmlElementIterator {
    return $this->eachGetIterator(static fn (QuipXmlElement $node) => $node->setTag($tag));
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-return ($content is null ? string : static)
   */
  public function text(string|int|float|null $content = NULL): string|static {
    if ($content === NULL) {
      $text = $this->singleGetter(static fn (QuipXmlElement $node) => $node->text());
      return is_string($text) ? $text : '';
    }
    return $this->eachSetter(static fn (QuipXmlElement $node) => $node->text($content));
  }

  /**
   * {@inheritdoc}
   */
  public function unwrap(): QuipXmlElement|QuipXmlElementIterator {
    // Siblings share a parent, which is removed once; every parent is read
    // before any is removed, as removing one moves its children up a level.
    $parents = [];
    $unwrap = [];
    foreach ($this->nodes as $node) {
      $parent = $node->dom()->parentNode ?? NULL;
      foreach ($parents as $seen) {
        if ($parent !== NULL && $parent->isSameNode($seen)) {
          continue 2;
        }
      }
      if ($parent !== NULL) {
        $parents[] = $parent;
      }
      $unwrap[] = $node;
    }
    foreach ($unwrap as $node) {
      $node->unwrap();
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function wrap(string|\SimpleXMLElement|\DOMNode $content): static {
    return $this->eachSetter(static fn (QuipXmlElement $node) => $node->wrap($content));
  }

  /**
   * {@inheritdoc}
   */
  public function wrapInner(string|\SimpleXMLElement|\DOMNode $content): static {
    return $this->eachSetter(static fn (QuipXmlElement $node) => $node->wrapInner($content));
  }

  /**
   * {@inheritdoc}
   */
  public function qxparent(): QuipXmlElement|QuipXmlElementIterator {
    return $this->eachGetIterator(static fn (QuipXmlElement $node) => $node->qxparent());
  }

  /**
   * {@inheritdoc}
   */
  public function qxprev(): QuipXmlElement|QuipXmlElementIterator {
    return $this->eachGetIterator(static fn (QuipXmlElement $node) => $node->qxprev());
  }

  /**
   * {@inheritdoc}
   *
   * An absolute path runs once, from the first node's document.
   */
  public function qxpath(string $path): QuipXmlElement|QuipXmlElementIterator {
    if (str_starts_with($path, '/')) {
      return isset($this->nodes[0]) ? $this->nodes[0]->qxpath($path) : $this->getEmptyElement();
    }
    return $this->eachGetIterator(static fn (QuipXmlElement $node) => $node->qxpath($path));
  }

  /**
   * Retired: renamed qxpath() so the element's override stays legal.
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
