<?php

# Include the PHP declarations that drive this page.
require $_SERVER['DOCUMENT_ROOT'].'/../includes/page-head.inc.php';

# Fire up our templating engine.
$template = new Page;

# Define some page elements.
$template->field->browser_title = 'About Virginia Decoded';
$template->field->page_title = 'About';

$body = '
<h2>Introduction</h2>
<p>Virginia Decoded is a private, non-governmental, non-partisan implementation of
<a href="http://www.statedecoded.com/">The State Decoded</a>, an open source project that provides a
platform to display state-level legal information in a friendly, accessible, modern fashion.
Virginia is the first state to deploy the software.</p>

<h2 id="beta-testing">Beta Testing</h2>
<p>Virginia Decoded is currently in <em>beta,</em> which is to say that the site is under active
development, with known shortcomings, but it has reached a point where it would benefit from being
used by the general public (who one hopes will likewise benefit from it.) While every effort is
made to ensure that the data provided on Virginia Decoded is accurate and up-to-date, it would
be gravely unwise to rely on it for any matter of importance while it is in this beta testing
phase.</p>

<p>Many more features are under development, including improvements to search, calculations of
the importance of given sections of the code, inclusion of attorney generals’ opinions, Supreme
Court of Virginia rulings, extensive explanatory text, social media integration, significant
navigation enhancements, a vastly expanded built-in glossary of legal terms, Code download options,
scholarly article citations, and much more.</p>

<h2 id="data-source">Data Sources</h2>
<p>The information that makes up Virginia Decoded comes entirely from public sources. All of the
sections of the code are straight from the <a
href="http://codecommission.dls.virginia.gov/">Virginia Code Commission</a>, who provides SGML of
the Code (via LexisNexis). Legislative data is from <a
href="http://www.richmondsunlight.com/">Richmond Sunlight</a>. Court decisions are scraped from
<a href="http://www.courts.state.va.us/wpcap.htm">the Court of Appeals’ decisions webpage</a>.
Term definitions are from within the state code itself. Throughout the site, links are provided to
original data sources, whenever possible.</p>

<h2 id="api">API</h2>
<p>The application programming interface for Virginia Decoded is currently in alpha testing. If
you are interested in trying it out, <a href="http://waldo.jaquith.org/contact/">e-mail me</a>.</p>

<h2 id="thanks">Thanks</h2>
<p>Virginia Decoded wouldn’t be possible without the contributions of the many dozens of people who
participated in private alpha and beta testing of the site over the course of a year and a half,
beginning in 2010. Specific thanks must be extended to John Athayde, James Alcorn, Josh Baugher,
Clarissa Berry, Mark Blacknell, Jane Chaffin, Aneesh Chopra, Jeff Cornejo, Hawkins Dale, Lucy
Dalglish, Max Fenton, Larry Gross, Alex Gulotta, Jay Landis, John Loy, Tom Moncure, Vivian Paige,
David Poole, Megan Rhyne, and the good people of the Virginia Code Commission. This platform on
which this site is based, The State Decoded, was expanded to function beyond Virginia thanks to a
generous grant by the John S. and James L. Knight Foundation.</p>

<h2 id="colophon">Colophon</h2>
<p>Hosted on <a href="http://www.centos.org/">CentOS</a>, driven by <a
href="http://httpd.apache.org/">Apache</a>, <a href="http://www.mysql.com/">MySQL</a>, and <a
href="http://www.php.net/">PHP</a>. Hosting by <a href="http://www.slicehost.com/">Slicehost</a>
(Rackspace). Search by <a href="http://sphinxsearch.com/">Sphinx</a>. Comments by <a
href="http://disqus.com/">Disqus</a>.</p>

<h2 id="disclaimer">Disclaimer</h2>
<p>This is not an official copy of the Code of Virginia. It is in no way authorized by the State of
Virginia. No information that is found on Virginia Decoded constitutes legal advice on any subject
matter. Do not take action (or fail to take action) on a legal matter without consulting proper
legal counsel. The contents of this website are provided as-is, with no warranty of any kind,
including merchantability, non-infringement, or fitness for a particular purpose. This website is
not your lawyer, and neither am I.</p>';

$sidebar = '<h1>Contact</h1>
		<section>
		<ul>
			<li><a href="http://twitter.com/openvirginia">Follow Open Virginia on Twitter</a></li>
			<li><a href="http://waldo.jaquith.org/contact/">E-mail Waldo Jaquith</a></li>
		</ul>
		</section>';

# Put the shorthand $body variable into its proper place.
$template->field->body = $body;
unset($body);

# Put the shorthand $sidebar variable into its proper place.
$template->field->sidebar = $sidebar;
unset($sidebar);

# Parse the template, which is a shortcut for a few steps that culminate in sending the content
# to the browser.
$template->parse();

?>