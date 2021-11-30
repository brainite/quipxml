QuipXml
=======

[![CI](https://github.com/brainite/quipxml/actions/workflows/ci.yml/badge.svg)](https://github.com/brainite/quipxml/actions/workflows/ci.yml)

QuipXml is chainable PHP objects for manipulating XML.
Unlike other libraries that attempt to replicate jQuery syntax throughout,
Quip attempts to provide lightweight extensions to SimpleXML to facilitate
cleaner code without imposing JavaScript conventions on a PHP project.
The end result is fast and easy to use.

Basic Usage
-----------

```` php
// The 'load' factory method aligns with SimpleXml constructor arguments.
$quip = Quip::load($xml_path, 0, TRUE);
$quip = Quip::load($xml_string);

// jQuery method names are used where appropriate.
$html = $quip->html();

// jQuery method names are adjusted when there is a keyword conflict.
$quip->xparent();
    
// While jQuery syntax is wonderful, you also get PHP advantages:
//  1. xpath
//  2. access children like properties
//  3. use foreach loops
$ul = $quip->xpath("//ul")->eq(0);
$ul->li->after('<li>New bullet.</li>');
foreach ($quip->xpath("//li")->eq(1) as $li) {
}

// For advanced operations, just like in jQuery, you can access the DOMNode for a given XML node.
$el = $ul->dom();
````

HHVM Limitations for SimpleXML
------------------------------

Effective 2014-03-24 (confirmed on travis-ci), you cannot use the magic self-reference on SimpleXml elements. This code will not work:
```` php
$sxml = simplexml_load_file('example.xml');
$sxml->original[0] = $expected;
````
Update: This was corrected on HHVM 3 (confirmed on travis-ci on 2014-05-30).
