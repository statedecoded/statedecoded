The State Decoded
=================

A free, open source PHP- and MySQL-based application to parse and display state laws. This project is currently in beta testing [as a Virginia implementation](http://vacode.org/) and in alpha testing [as a Florida implementation](http://www.sunshinestatutes.com/).

#### Notes
This is the Virginia implementation, stripped down and normalized. Simply installing this would not yield useful results. That said, a few hours of work could well yield a useful, functioning website.

Initial configuration is done in `includes/config.inc.php`, with the heavy lifting coming with customizing `includes/parser.inc.php` to be able to import the legal code in question. Specifically,
Parser::iterate(), Parser::parse(), and Parser::store() will need to be overhauled to be able to iterate through the structure in which the laws are stored (a single SGML file, a series of XML files, scraped via HTTP, etc.), extract each datum from each law (section number, catch line, text, history, etc.), and then store them in the format expected by the State Decoded. The other methods
in the Parser class ought to work basically as already written.

A Google API key must be entered in includes/page.inc.php for the Google JS API call.

#### More
News at http://twitter.com/statedecoded

Website at http://www.statedecoded.com/

Development of The State Decoded is funded by the John S. and James L. Knight Foundation’s News Challenge.