QuipXml
=======

[![CI](https://github.com/brainite/quipxml/actions/workflows/ci.yml/badge.svg)](https://github.com/brainite/quipxml/actions/workflows/ci.yml)

QuipXml is chainable PHP objects for manipulating XML.
Unlike other libraries that attempt to replicate jQuery syntax throughout,
Quip attempts to provide lightweight extensions to SimpleXML to facilitate
cleaner code without imposing JavaScript conventions on a PHP project.
The end result is fast and easy to use.

Requires PHP 8.3 or later.

Basic Usage
-----------

```` php
use QuipXml\Quip;

// The 'load' factory method aligns with SimpleXml constructor arguments.
// Markup that is not well-formed XML is read with the HTML parser.
$quip = Quip::load($xml_path, 0, TRUE);
$quip = Quip::load($xml_string);

// jQuery method names are used where appropriate.
$html = $quip->html();

// Queries carry a 'q' prefix: SimpleXMLElement::xpath() returns an array,
// and an override returning anything else is not legal PHP.
$quip->qxparent();
$quip->qxprev();

// While jQuery syntax is wonderful, you also get PHP advantages:
//  1. xpath
//  2. access children like properties
//  3. use foreach loops
$ul = $quip->qxpath("//ul")->eq(0);
$ul->li->after('<li>New bullet.</li>');
foreach ($quip->qxpath("//li") as $li) {
}

// For advanced operations, just like in jQuery, you can access the DOMNode for a given XML node.
$el = $ul->dom();
````

Nodes and sets
--------------

`qxpath()` returns a `QuipXmlElementIterator` holding every match, or an
empty `QuipXmlElement` when nothing matched. Both implement
`QuipXmlElementInterface`, so a chain does not need to know how many nodes
it holds:

- Verbs that change the document (`after`, `before`, `append`, `wrap`,
  `wrapInner`, `unwrap`, `remove`, `setTag`, `addChild`, `addAttribute`, and
  `html()` or `text()` given content) apply to every node.
- Getters (`html()`, `text()`, `htmlOuter()`, `asXML()`, `getName()`) read
  the first node. Casting a set to a string concatenates every node's text,
  and `getNamespaces()` and `getDocNamespaces()` merge every node's.
- `children()` and `attributes()` flatten every node's children or
  attributes into one set.
- An empty result casts to `FALSE` and iterates nothing; getters on it return
  an empty string and the structural verbs do nothing, so a chain over a
  missing node needs no guard. Writing into it with `html()` or `text()`
  throws a `NotPermanentMemberException`, since there is nowhere to write.
- `before()` and `after()` on the root element throw a `\LogicException`: a
  document has one root.

A prefix registered with `registerXPathNamespace()` carries over to every
node Quip returns from that node (query results, `children()`,
`attributes()`, `addChild()`), so a nested query needs no second
registration. Nodes SimpleXML makes itself — property access such as
`$quip->child`, or `foreach` over a SimpleXML list — start without it.

An attribute node (from `attributes()` or `qxpath('//@name')`) reads and
writes its value through `text()` and is deleted by `remove()`; the
structural verbs throw a `\LogicException` on it.

Upgrading from 0.x
------------------

- PHP 8.3 or later.
- `xpath()`, `xparent()` and `xprev()` throw a `\RuntimeException`; call
  `qxpath()`, `qxparent()` and `qxprev()` (available since 0.4).
- Every parameter and return value is typed and every file declares
  `strict_types`. A caller without strict types still has scalars coerced,
  but `NULL` where a string or array is expected is now a `TypeError`.
- A subclass must match the typed signatures: an override without the
  parent's return type is a fatal error. The protected helpers lost their
  underscore: `contentToDom()`, `getEmptyElement()`, `eachGetIterator()`,
  `eachSetter()`, `singleGetter()`.
- `Quip::load()` refuses an empty document with an
  `\InvalidArgumentException`.
- `remove()` returns a bool on a set as well as on one node.
- `setTag()` on a set returns the renamed nodes rather than the original
  set; on one node it still returns a set of one.
- Reading a property on a set of several nodes (`$set->item`) returns every
  node's children of that name, not one per node.
- Attribute nodes: `html()` and `text()` return the value, `text()` writes
  it, and `remove()` deletes the attribute.
- `before()` and `after()` on the root throw rather than build a second
  root.
- Fixed: `before()`, `after()` and `append()` act on a queried element that
  has no children or attributes; `setTag()` keeps an empty element well
  formed and keeps namespaces; `unwrap()` on siblings removes their shared
  parent once.

Development
-----------

```` sh
composer install
composer lint      # PHP_CodeSniffer: strict_types in every file
composer analyse   # PHPStan level 8
composer test      # PHPUnit; `vendor/bin/phpunit --group benchmark` for the memory benchmark
````
