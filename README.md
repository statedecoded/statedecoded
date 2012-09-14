The State Decoded
=================

A free, open source PHP- and MySQL-based application to parse and display state laws. This project is currently in beta testing [as a Virginia implementation](http://vacode.org/) and in alpha testing [as a Florida implementation](http://www.sunshinestatutes.com/).

#### Notes
This is a *pre-1.0 release*, which is to say that it is an incompete product. A capable PHP developer who is familiar and comfortable with her state legal code should be able to wrangle their laws into this release with a few hours’ work. All others should wait for v1.0.

Initial configuration is done in `includes/config.inc.php` (using `config-sample.inc.php` as a template), with the heavy lifting coming with customizing `includes/parser.inc.php` to be able to import the legal code in question. Specifically, Parser::iterate(), Parser::parse(), and Parser::store() will need to be overhauled to be able to iterate through the structure in which the laws are stored (a single SGML file, a series of XML files, scraped via HTTP, etc.), extract each datum from each law (section number, catch line, text, history, etc.), and then store them in the format expected by the State Decoded. The other methods in the Parser class ought to work basically as already written. This is all documented within [How the Parser Works](https://github.com/waldoj/statedecoded/wiki/How-the-Parser-Works).

One particularly important task in the configuration process is devising a Perl-compatible regular expression to pluck out every reference to an individual law from within bodies of text. This is stored in `config.inc.php`. By way of example, Virginia’s PCRE is `([[0-9]{1,})([0-9A-Za-z\-\.]{0,3})-([0-9A-Za-z\-\.:]*)([0-9A-Za-z]{1,})`.

The `.htaccess` file will need to be customized, with regular expressions devised to support the [URL rewrites](http://httpd.apache.org/docs/current/mod/mod_rewrite.html) that direct site visitors the appropriate law or structural element.

The State Decoded benefits from [APC](http://php.net/manual/en/book.apc.php), which accelerates substantially its operations. It is not required, however. Likewise, it also benefits from [mod_pagespeed](https://developers.google.com/speed/pagespeed/mod) to streamline HTML and JavaScript, which are produced by The State Decoded without formatting that is pleasing to the eye or optimization of execution by the client.

A Google API key must be entered in includes/page.inc.php for the Google JS API call.

#### More
News at [@StateDecoded](http://twitter.com/statedecoded). Website at [StateDecoded.com](http://www.statedecoded.com/).

Development of The State Decoded is funded by the John S. and James L. Knight Foundation’s News Challenge.