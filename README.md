The State Decoded
=================

A free, open source PHP- and MySQL-based application to parse and display state laws. This project is currently in beta testing [as a Virginia implementation](http://vacode.org/) and in alpha testing [as a Florida implementation](http://www.sunshinestatutes.com/).

#### Notes
This is the Virginia implementation, stripped down and normalized. Simply installing this would not yield useful results. That said, a few hours of work could well yield a useful, functioning website.

Initial configuration is done in `includes/config.inc.php`, with the heavy lifting coming with customizing `includes/parser.inc.php` to be able to import the legal code in question. Specifically,
Parser::iterate(), Parser::parse(), and Parser::store() will need to be overhauled to be able to iterate through the structure in which the laws are stored (a single SGML file, a series of XML files, scraped via HTTP, etc.), extract each datum from each law (section number, catch line, text, history, etc.), and then store them in the format expected by the State Decoded. The other methods
in the Parser class ought to work basically as already written.

One particularly important task in the configuration process is devising a Perl-compatible regular expression to pluck out every reference to an individual law from within bodies of text. This is stored in `config.inc.php`. By way of example, Virginia’s PCRE is `([[0-9]{1,})([0-9A-Za-z\-\.]{0,3})-([0-9A-Za-z\-\.:]*)([0-9A-Za-z]{1,})`.

The `.htaccess` file will need to be customized, with regular expressions devised to support the [URL rewrites](http://httpd.apache.org/docs/current/mod/mod_rewrite.html) that direct site visitors the appropriate law or structural element.

The State Decoded benefits from [APC](http://php.net/manual/en/book.apc.php), which accelerates substantially its operations. It is not required, however. Likewise, it also benefits from [mod_pagespeed](https://developers.google.com/speed/pagespeed/mod) to streamline HTML and JavaScript.

A Google API key must be entered in includes/page.inc.php for the Google JS API call.

#### More
News at http://twitter.com/statedecoded

Website at http://www.statedecoded.com/

Development of The State Decoded is funded by the John S. and James L. Knight Foundation’s News Challenge.