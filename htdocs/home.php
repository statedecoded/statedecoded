<?php

/**
 * The "Home" page, displaying the front page of the site.
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.8
 * @link		http://www.statedecoded.com/
 * @since		0.8
 *
 */

/*
 * Fire up our templating engine.
 */
$content = new Content();

/*
 * Define some page elements.
 */
$content->set('browser_title', '');
$content->set('page_title', '');

$content->set('body',
	'<!-- UNCOMMENT TO DISPLAY AN INTRODUCTORY VIDEO HERE
	<div class="nest video">
		<div class="video-frame">
			<div class="video-container">
				<video width="" height="" controls="controls">
					<source src="" type="video/mp4">
					<source src="" type="video/webm">
				</video>
			</div>
		</div>
	</div>--> <!-- // .nest -->

	<section class="homepage" role="main">
		<div class="nest">
			<section class="feature-content">
				<hgroup>
					<h1>Discover the ' . LAWS_NAME . '</h1>
					<h2>The laws of ' . PLACE_NAME . ', for non-lawyers.</h2>
				</hgroup>

				<p>' . SITE_TITLE . ' provides the ' . LAWS_NAME . ' on one friendly website. Inline
				definitions, cross-references, bulk downloads, a modern API, and all of the niceties
				of modern website design. It’s like the expensive software lawyers use, but free and
				wonderful.</p>

				<p>This is a public beta test of ' . SITE_TITLE . ', which is to say that everything
				is under development. Things are funny looking, broken, and generally unreliable
				right now.</p>

				<p>This site is powered by <a href="http://www.statedecoded.com/">The State
				Decoded</a>.</p>

			</section> <!-- // .feature -->

			<section class="secondary-content">

				<article class="abstract">
					<h1>Inline Definitions</h1>
					<p>Throughout the ' . LAWS_NAME . ', very specific legal definitions are
					provided for terminology both specialized and mundane. If you don’t know which
					words have special definitions, and what those definitions are, then you can’t
					understand what a law <em>really</em> means. ' . SITE_TITLE . ' solves this
					problem neatly, by identifying every definition in the  ' . LAWS_NAME . ' and
					providing a pop-up definition every time that a defined word appears.</p>
				</article>

				<article class="abstract">
					<h1>Bulk Downloads</h1>
					<p>' . SITE_TITLE . ' isn’t just a pretty website—you can take the laws with
					you, too. On <a href="/downloads/">our downloads page</a> you can get copies
					of all of the laws of ' . PLACE_NAME . ' in any format that you like, to do
					whatever you like with. They’re available in formats meant for you to read
					and in formats meant for software to read, too. If you’re a software developer,
					you’ll love our API!</p>
				</article>

			</section> <!-- // .secondary-content -->

		</div> <!-- // .nest -->

	</section>');

/*
 * Put the shorthand $sidebar variable into its proper place.
 */
$content->set('sidebar', '');

/*
 * Add the custom classes to the body.
 */
$content->set('body_class', 'preload');

/*
 * Parse the template, which is a shortcut for a few steps that culminate in sending the content
 * to the browser.
 */
$template = Template::create();
$template->parse($content);
