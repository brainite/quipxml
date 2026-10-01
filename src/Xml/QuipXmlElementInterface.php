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
 * The chainable API shared by a single Quip node and a set of matched nodes.
 *
 * QuipXmlElement (one node, a SimpleXMLElement) and QuipXmlElementIterator
 * (the nodes a query matched) both implement it, so a caller can chain verbs
 * without knowing how many nodes a query returned. On a set, a verb that
 * changes the document applies to every node; a getter reads the first node,
 * except where a method says it combines all of them.
 *
 * A query that matches nothing returns an empty QuipXmlElement: it casts to
 * FALSE, iterates nothing, and every verb on it is a no-op, so a chain over a
 * missing node does not need a guard at each step.
 *
 * Iterating yields QuipXmlElement nodes: the matched nodes of a set, or the
 * child elements of a single node (SimpleXML's own iteration).
 *
 * @extends \Traversable<mixed, \QuipXml\Xml\QuipXmlElement>
 */
interface QuipXmlElementInterface extends \Countable, \Stringable, \Traversable {

  /**
   * Adds an attribute to each node.
   *
   * @param string $qualifiedName
   *   The attribute name, optionally prefixed.
   * @param string $value
   *   The attribute value.
   * @param string|null $namespace
   *   The namespace URI the attribute belongs to, if any.
   */
  public function addAttribute(string $qualifiedName, string $value, ?string $namespace = NULL): void;

  /**
   * Appends a new child element to each node.
   *
   * @param string $qualifiedName
   *   The child element name, optionally prefixed.
   * @param string|null $value
   *   The child's text content, if any.
   * @param string|null $namespace
   *   The namespace URI the child belongs to, if any.
   *
   * @return \QuipXml\Xml\QuipXmlElement|\QuipXml\Xml\QuipXmlElementIterator|null
   *   The added child, or every added child for a set.
   */
  public function addChild(string $qualifiedName, ?string $value = NULL, ?string $namespace = NULL): QuipXmlElement|QuipXmlElementIterator|null;

  /**
   * Inserts content as the next sibling of each node.
   *
   * @param string|\SimpleXMLElement|\DOMNode $content
   *   Markup, or a node to copy in.
   *
   * @return static
   *   The same node or set, for chaining.
   *
   * @throws \LogicException
   *   When a node is an attribute, which has no siblings.
   */
  public function after(string|\SimpleXMLElement|\DOMNode $content): static;

  /**
   * Inserts content as the last child of each node.
   *
   * @param string|\SimpleXMLElement|\DOMNode $content
   *   Markup, or a node to copy in.
   *
   * @return static
   *   The same node or set, for chaining.
   *
   * @throws \LogicException
   *   When a node is an attribute, which has no children.
   */
  public function append(string|\SimpleXMLElement|\DOMNode $content): static;

  /**
   * Serializes the first node as XML, or writes it to a file.
   *
   * @param string|null $filename
   *   A path to write to instead of returning the XML.
   *
   * @return string|bool
   *   The XML, or whether the file was written; FALSE on failure or when
   *   there is no node.
   */
  // phpcs:ignore Drupal.NamingConventions.ValidFunctionName.ScopeNotCamelCaps -- SimpleXMLElement::asXML() names it.
  public function asXML(?string $filename = NULL): string|bool;

  /**
   * Lists the attributes of each node.
   *
   * @param string|null $namespaceOrPrefix
   *   Only attributes in this namespace URI, or with this prefix.
   * @param bool $isPrefix
   *   Whether $namespaceOrPrefix is a prefix rather than a URI.
   *
   * @return \QuipXml\Xml\QuipXmlElement|\QuipXml\Xml\QuipXmlElementIterator|null
   *   The attributes; for a set, every node's attributes flattened into one
   *   set of attribute nodes.
   */
  public function attributes(?string $namespaceOrPrefix = NULL, bool $isPrefix = FALSE): QuipXmlElement|QuipXmlElementIterator|null;

  /**
   * Inserts content as the previous sibling of each node.
   *
   * @param string|\SimpleXMLElement|\DOMNode $content
   *   Markup, or a node to copy in.
   *
   * @return static
   *   The same node or set, for chaining.
   *
   * @throws \LogicException
   *   When a node is an attribute, which has no siblings.
   */
  public function before(string|\SimpleXMLElement|\DOMNode $content): static;

  /**
   * Lists the child elements of each node.
   *
   * @param string|null $namespaceOrPrefix
   *   Only children in this namespace URI, or with this prefix.
   * @param bool $isPrefix
   *   Whether $namespaceOrPrefix is a prefix rather than a URI.
   *
   * @return \QuipXml\Xml\QuipXmlElement|\QuipXml\Xml\QuipXmlElementIterator|null
   *   The children; for a set, every node's children flattened into one set.
   */
  public function children(?string $namespaceOrPrefix = NULL, bool $isPrefix = FALSE): QuipXmlElement|QuipXmlElementIterator|null;

  /**
   * Returns the DOM node behind one node.
   *
   * @param int $index
   *   The position of the node in a set; a single node answers only 0.
   *
   * @return \DOMNode|false
   *   The DOMElement (or DOMAttr for an attribute node), or FALSE when there
   *   is no such node.
   */
  public function dom(int $index = 0): \DOMNode|false;

  /**
   * Returns one node.
   *
   * @param int $index
   *   The position of the node in a set; a single node returns itself.
   *
   * @return \QuipXml\Xml\QuipXmlElement
   *   The node, or an empty element when there is none at that position.
   */
  public function eq(int $index = 0): QuipXmlElement;

  /**
   * Finds a descendant by a simple path, creating the missing elements.
   *
   * @param string $path
   *   A slash-separated path; the existing prefix may be any XPath, and the
   *   missing remainder may use only names and positional filters (`item[2]`).
   *
   * @return \QuipXml\Xml\QuipXmlElement|\QuipXml\Xml\QuipXmlElementIterator
   *   The found or created element, or one per node for a set.
   *
   * @throws \InvalidArgumentException
   *   When the missing remainder holds an axis, a wildcard or a complex filter.
   * @throws \ErrorException
   *   When a positional filter cannot be satisfied.
   */
  public function get(string $path): QuipXmlElement|QuipXmlElementIterator;

  /**
   * Returns the namespaces declared in the document.
   *
   * @param bool $recursive
   *   Whether to include declarations on descendants.
   * @param bool $fromRoot
   *   Whether to start at the document root rather than at the node.
   *
   * @return array<string, string>|false
   *   Namespace URIs keyed by prefix; for a set, every node's declarations
   *   merged, the first node's winning a shared prefix.
   */
  public function getDocNamespaces(bool $recursive = FALSE, bool $fromRoot = TRUE): array|false;

  /**
   * Returns the name of the first node.
   *
   * @return string
   *   The element or attribute name, or an empty string when there is none.
   */
  public function getName(): string;

  /**
   * Returns the namespaces in use.
   *
   * @param bool $recursive
   *   Whether to include namespaces used by descendants.
   *
   * @return array<string, string>
   *   Namespace URIs keyed by prefix; for a set, every node's namespaces
   *   merged, the first node's winning a shared prefix.
   */
  public function getNamespaces(bool $recursive = FALSE): array;

  /**
   * Reads or replaces the inner markup.
   *
   * @param string|\SimpleXMLElement|\DOMNode|\QuipXml\Xml\QuipXmlFormatter|null $content
   *   NULL to read the first node's inner markup; a formatter to read it
   *   formatted; otherwise the markup or node that replaces each node's
   *   children.
   *
   * @return string|static
   *   The markup when reading (an attribute's value for an attribute node),
   *   or the same node or set when writing.
   *
   * @throws \LogicException
   *   When writing markup into an attribute node.
   *
   * @phpstan-return ($content is null|\QuipXml\Xml\QuipXmlFormatter ? string : static)
   */
  public function html(string|\SimpleXMLElement|\DOMNode|QuipXmlFormatter|null $content = NULL): string|static;

  /**
   * Reads the outer markup of the first node.
   *
   * @param \QuipXml\Xml\QuipXmlFormatter|null $formatter
   *   A formatter to apply, if any.
   *
   * @return string
   *   The node's markup; `name="value"` for an attribute node; an empty
   *   string when there is no node.
   */
  public function htmlOuter(?QuipXmlFormatter $formatter = NULL): string;

  /**
   * Runs a relative or absolute XPath query.
   *
   * @param string $path
   *   The XPath expression.
   *
   * @return \QuipXml\Xml\QuipXmlElement|\QuipXml\Xml\QuipXmlElementIterator
   *   The matched nodes as a set, or an empty element when nothing matched.
   */
  public function qxpath(string $path): QuipXmlElement|QuipXmlElementIterator;

  /**
   * Returns the parent of each node.
   *
   * @return \QuipXml\Xml\QuipXmlElement|\QuipXml\Xml\QuipXmlElementIterator
   *   The parents as a set, or an empty element when there is none.
   */
  public function qxparent(): QuipXmlElement|QuipXmlElementIterator;

  /**
   * Returns the previous sibling element of each node.
   *
   * @return \QuipXml\Xml\QuipXmlElement|\QuipXml\Xml\QuipXmlElementIterator
   *   The siblings as a set, or an empty element when there is none.
   */
  public function qxprev(): QuipXmlElement|QuipXmlElementIterator;

  /**
   * Registers a namespace prefix for later qxpath() queries on each node.
   *
   * @param string $prefix
   *   The prefix to use in queries.
   * @param string $namespace
   *   The namespace URI it stands for.
   *
   * @return bool
   *   Whether every node registered it.
   */
  // phpcs:ignore Drupal.NamingConventions.ValidFunctionName.ScopeNotCamelCaps -- SimpleXMLElement::registerXPathNamespace() names it.
  public function registerXPathNamespace(string $prefix, string $namespace): bool;

  /**
   * Removes each node from the document.
   *
   * @return bool
   *   Whether any node was removed.
   */
  public function remove(): bool;

  /**
   * Serializes the first node as XML; an alias of asXML().
   *
   * @param string|null $filename
   *   A path to write to instead of returning the XML.
   *
   * @return string|bool
   *   The XML, or whether the file was written; FALSE on failure.
   */
  // phpcs:ignore Drupal.NamingConventions.ValidFunctionName.ScopeNotCamelCaps -- SimpleXMLElement::saveXML() names it.
  public function saveXML(?string $filename = NULL): string|bool;

  /**
   * Replaces each node with an element of another name.
   *
   * @param string $tag
   *   A bare name (`th`), which keeps the node's attributes, or an opening
   *   tag (`<th scope="col">`), whose attributes replace them. The children
   *   move into the new element either way.
   *
   * @return \QuipXml\Xml\QuipXmlElement|\QuipXml\Xml\QuipXmlElementIterator
   *   The new element, or every new element for a set.
   *
   * @throws \LogicException
   *   When a node is an attribute.
   */
  public function setTag(string $tag): QuipXmlElement|QuipXmlElementIterator;

  /**
   * Reads or replaces the text content.
   *
   * @param string|int|float|null $content
   *   NULL to read the first node's text; otherwise the text that replaces
   *   each node's content (an attribute node's value).
   *
   * @return string|static
   *   The text, with markup removed and entities kept, when reading; the
   *   same node or set when writing.
   *
   * @phpstan-return ($content is null ? string : static)
   */
  public function text(string|int|float|null $content = NULL): string|static;

  /**
   * Removes the parent of each node, keeping the parent's children in place.
   *
   * @return \QuipXml\Xml\QuipXmlElement|\QuipXml\Xml\QuipXmlElementIterator
   *   The same node or set, or an empty element when there was no parent.
   *
   * @throws \LogicException
   *   When a node is an attribute.
   */
  public function unwrap(): QuipXmlElement|QuipXmlElementIterator;

  /**
   * Wraps each node in new markup.
   *
   * @param string|\SimpleXMLElement|\DOMNode $content
   *   The wrapping element's markup, or a node to copy in.
   *
   * @return static
   *   The same node or set, for chaining.
   *
   * @throws \LogicException
   *   When a node is an attribute.
   */
  public function wrap(string|\SimpleXMLElement|\DOMNode $content): static;

  /**
   * Wraps the children of each node in new markup.
   *
   * @param string|\SimpleXMLElement|\DOMNode $content
   *   The wrapping element's markup, or a node to copy in.
   *
   * @return static
   *   The same node or set, for chaining.
   *
   * @throws \LogicException
   *   When a node is an attribute.
   */
  public function wrapInner(string|\SimpleXMLElement|\DOMNode $content): static;

}
