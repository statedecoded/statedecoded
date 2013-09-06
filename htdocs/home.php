<?php

/**
 * The "Home" page, displaying the front page of the site.
 *
 * PHP version 5
 *
 * @author		Waldo Jaquith <waldo at jaquith.org>
 * @copyright	2010-2013 Waldo Jaquith
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

//body class="preload"?

$content->set('body',
	'<div class="nest video">
		<div class="video-frame">
			<div class="video-container">
				<iframe width="560" height="315" src="http://www.youtube.com/embed/4HPxQHBFjcg" frameborder="0" allowfullscreen></iframe>
				<!-- <video width="" height="" controls="controls">
					<source src="" type="video/mp4">
					<source src="" type="video/webm">
				</video> -->
			</div>
		</div>
	</div> <!-- // .nest -->

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

				<p>This is a public beta test of Virginia Decoded, which is to say that everything
				is under development. Things are funny looking, broken, and generally unreliable
				right now.</p>

				<p>This site is powered by <a href="http://www.statedecoded.com/">The State
				Decoded</a></p>

			</section> <!-- // .feature -->

			<section class="secondary-content">

				<article class="abstract">
					<h1>Feature Code Callout</h1>
					<p>Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. Mauris in erat justo. Nullam ac urna eu felis dapibus condimentum sit amet a augue. Sed non neque elit. Sed ut imperdiet nisi. Proin condimentum fermentum nunc. Etiam pharetra, erat sed fermentum feugiat, velit mauris egestas quam, ut aliquam massa nisl quis neque.</p>
				</article>

				<article class="abstract">
					<h1>Feature Code Callout</h1>
					<p>Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. Mauris in erat justo. Nullam ac urna eu felis dapibus condimentum sit amet a augue. Sed non neque elit. Sed ut imperdiet nisi. Proin condimentum fermentum nunc. Etiam pharetra, erat sed fermentum feugiat, velit mauris egestas quam, ut aliquam massa nisl quis neque.</p>
				</article>

				<article class="abstract">

					<figure>
						<img src="/public/images/richmond_capitol.jpg" alt="The Richmond Capitol Building">
					</figure>

					<h1>The Capitol File</h1>
					<p>Want to know more about what’s going on in the State Legislature? Check our our sister site, <a href="http://www.richmondsunlight.com">Richmond Sunlight</a>, to find out about the changes that are being proposed to the Commonwealth’s code.</p>

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
