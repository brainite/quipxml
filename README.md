QuipXml
=======

[![Build Status](https://travis-ci.org/wittiws/quipxml.png?branch=master)](https://travis-ci.org/wittiws/quipxml)

QuipXml is chainable PHP objects for manipulating XML modeled after jQuery.

Basic Usage
-----------

```` php
// The 'load' factory method aligns with SimpleXml constructor arguments.
$quip = Quip::load($xml_path, 0, TRUE);
$quip = Quip::load($xml_string);

// jQuery method names are used where appropriate.
$html = $quip->html();

// jQuery method names are adjusted when there is a keyword conflict.
$quip->parent_();
    
// While jQuery syntax is wonderful, you also get PHP advantages:
//  1. xpath
//  2. access children like properties
//  3. use foreach loops
$ul = $quip->xpath("//ul")->eq(0);
$ul->li->after('<li>New bullet.</li>');
foreach ($quip->xpath("//li")->eq(1) as $li) {
}

// For advanced operations, just like in jQuery, you can access the DOMNode for a given XML node.
$el = $ul->get();
````
