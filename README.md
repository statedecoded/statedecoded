# The State Decoded

## What is The State Decoded?
The State Decoded is a free, open source, web-based application to display laws online. Although it's meant for laws, it'll basically work for any structured legal text. It makes legal text searchable, interconnected, and machine-readable, adding an API, bulk downloads, and powerful semantic analysis tools. With The State Decoded, legal codes become vastly easier to read, more useful, and more open. Here's an actual before-and-after from the Code of Virginia:

![Before and After](https://s3.amazonaws.com/statedecoded.com/comparison.jpg)

## Can I try it out?
Sure! This project can be seen in action on sites for [Virginia](http://vacode.org/), [Chicago](http://chicagocode.org/), [San Francisco](http://sanfranciscocode.org/), and [a growing list of others](http://americadecoded.org/). If you want to install it, you can also [download a Vagrant image from GitMachines](https://github.com/GitMachines/statedecoded-gm-centos6), or just [download and install it from scratch](https://github.com/statedecoded/statedecoded/releases).


## Is this ready for prime time?
Quite nearly! The current release is being used in production on a half-dozen different sites, with [no serious bugs](https://github.com/statedecoded/statedecoded/issues?direction=desc&labels=Bug&milestone=2&state=open), and is certainly in good enough shape to be used on websites that aren't official, government-run repositories of the law. The only catch is that, until v1.0 is released (the next major release due out), there's no built-in upgrade path to new releases. That said, it's easy enough to install a new version and just re-import your site's legal code.

This is a pre-v1.0 release, which is to say that it isn't quite done. A capable developer who is comfortable with legal terminology should be able to wrangle her laws into this release with a couple of hours of work.

## How do get my legal code into The State Decoded?
There are two ways.

1. Natively, The State Decoded imports XML in [The State Decoded XML format](http://docs.statedecoded.com/xml-format.html). If you have your legal code as XML, you can adapt [the provided XSLT](https://github.com/statedecoded/statedecoded/blob/master/sample.xsl) to transform it into the proper format. Or if you don't have your legal code as XML, you can convert it into XML.
1. Skip XML entirely and [modify the included parser](http://docs.statedecoded.com/parser.html) to import it in the format in which you have it.

## Project documentation
Project documentation can be found at [docs.statedecoded.com](http://docs.statedecoded.com/), which explains how to install the software, configure it, customize it, use the API, and more. The documentation is stored [as a GitHub project](http://github.com/statedecoded/documentation/), with its content automatically published via [Jekyll](http://jekyllrb.com/), so in addition to reading the documentation, you are welcome to make improvements to it!

## How to help
* Use State Decoded sites and share your feedback in the form of [filing issues](https://github.com/statedecoded/statedecoded/issues/new)—suggestions for new features, notifications of bugs, etc.
* Write or edit documentation on [the wiki](https://github.com/statedecoded/statedecoded/wiki).
* Read through [unresolved issues](https://github.com/statedecoded/statedecoded/issues) and comment on those on which you have something to add, to help resolve them.
* Contribute code to [fix bugs or add features](https://github.com/statedecoded/statedecoded/issues).
* Comb through [existing code](https://github.com/statedecoded/statedecoded) to clean it up—standardizing code formatting, adding docblocks, or editing/adding comments.

## Keep up to date
Follow along on Twitter [@StateDecoded](http://twitter.com/statedecoded), or on the project website at [StateDecoded.com](http://www.statedecoded.com/).

## Supported by
Development of The State Decoded was funded by [the John S. and James L. Knight Foundation’s News Challenge](http://www.knightfoundation.org/grants/20110158/).
