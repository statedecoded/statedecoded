# The State Decoded

A free, open source, PHP- and MySQL-based application to parse and display laws. This project can be seen in action on the under-development [Virginia](http://vacode.org/), [Maryland](http://marylandcode.org/), or [Florida](http://www.sunshinestatutes.com/) sites.

## Notes
This is a *pre-1.0 release*, which is to say that it is an incompete product. A capable PHP developer who is familiar and comfortable with her state legal code should be able to wrangle their laws into this release with a few hours’ work. All others should wait for v1.0.

See the [installation instructions](https://github.com/statedecoded/statedecoded/wiki/Installation-Instructions) for details.

The State Decoded benefits from [APC](http://php.net/manual/en/book.apc.php), which accelerates substantially its operations. It is not required, however. Likewise, it also benefits from [mod_pagespeed](https://developers.google.com/speed/pagespeed/mod) to streamline the HTML, CSS, and JavaScript.

## How to Help
* Use State Decoded sites (e.g., [Virginia Decoded](http://vacode.org/), [Maryland Decoded](http://www.marylandcode.org/), [Sunshine Statutes](http://www.sunshinestatutes.com/)) and share your feedback in the form of [filing issues](https://github.com/statedecoded/statedecoded/issues/new)—suggestions for new features, notifications of bugs, etc.
* Write or edit documentation on [the wiki](https://github.com/statedecoded/statedecoded/wiki).
* Read through [unresolved issues](https://github.com/statedecoded/statedecoded/issues) and comment on those on which you have something to add, to help resolve them.
* Contribute code to [fix bugs or add features](https://github.com/statedecoded/statedecoded/issues).
* Comb through [existing code](https://github.com/statedecoded/statedecoded) to clean it up—standardizing code formatting, adding docblocks, or editing/adding comments.

## More
Follow along on Twitter [@StateDecoded](http://twitter.com/statedecoded), or on the project website at [StateDecoded.com](http://www.statedecoded.com/).

Development of The State Decoded is funded by the John S. and James L. Knight Foundation’s News Challenge.

# ReadMe for The State Decoded design

This is the static breakout of The State Decoded design created in the fall of 2012. This is a combination of boilerplate as well as other functions that come from a variety of resources online, including the Ruby/Sass community. Any questions can be directed at myself (John Athayde) or Waldo Jaquith.

Cheers,
John Athayde
jmpa@meticulous.com
November 2012

## Setup

### Favicons, Apple Touch Icons

Each of these icons should somehow correlate to your State or Commonwealth. With Virginia, we used the seal icon that was created for the site. The larger touch icons have bitmapped 0 and 1s fading across, representing digital bits being "decoded" as it were. Feel free to edit the images in the source_psd folder to suit your needs. We recommend running any images through ImageOptim (http://imageoptim.com) to squeeze out excess file size.

### Sass, Compass, etc.

This site was built with future flexibility and reuse in mind. To that end, the site basically is a styleguide as well as a design. We use Sass/SCSS to allow the power of variables in CSS. This lets other groups who may be implementing this on their own to rapidly change colors and fonts without having to do massive find and replace or worry about color math variations in Photoshop.

This does create a layer of complexity, but one that I feel is worth having. You, as an end user, can write regular CSS in an SCSS file and it will compile normally.

To run this, there are a few setups you need. You need to either run CodeKit, Scout, CompassApp, or do a manual setup with Ruby on your system. This is documented on the Compass site (http://compass-style.org/install/).

## Media Queries

The site is defined mainly as three breakpoints:

* Handheld/iPhone
* Tablet/iPad (Portrait)
* Everything else

We'll probably add a desktop layout for windows larger than 1224px (see the scss/base/_media_queries.css.scss file for all breakpoints) but for right now, we're starting with the basics.
