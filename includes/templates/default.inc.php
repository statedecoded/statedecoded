<!DOCTYPE html>
<!--[if lt IE 7]> <html class="no-js lt-ie9 lt-ie8 lt-ie7"> <![endif]-->
<!--[if IEMobile 7 ]><html class="no-js iem7"><![endif]-->
<!--[if IE 7]>	<html class="no-js lt-ie9 lt-ie8 ie7"> <![endif]-->
<!--[if IE 8]>	<html class="no-js lt-ie9 ie8"> <![endif]-->
<!--[if (gt IE 8)|(gt IEMobile 7)|!(IEMobile)|!(IE)]><!--><html class="" lang="en"> <!--<![endif]-->
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
	<title>{{browser_title}}</title>
	<meta name="description" content="">
	<meta name="viewport" content="width=device-width">
	{{meta_tags}}
	<link rel="home" title="Home" href="/" />
	{{link_rel}}


	<!-- Place favicon.ico and apple-touch-icon.png in the root directory -->

	<link rel="stylesheet" href="/static/css/application.css">
	{{css}}
	{{inline_css}}

	<script src="/static/js/vendor/modernizr.min.js"></script>

	<!-- TypeKit Font loading -->
	<script type="text/javascript" src="//use.typekit.net/djv6ymt.js"></script>
	<script type="text/javascript">try{Typekit.load();}catch(e){}</script>

	<script src="//ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
	<script src="//ajax.googleapis.com/ajax/libs/jqueryui/1.9.2/jquery-ui.min.js"></script>
	
	<script src="/js/jquery.qtip.min.js"></script>

</head>
<body class="preload {{body_class}}">
	<!--[if lt IE 7]>
		<p class="chromeframe">You are using an <strong>outdated</strong> browser. Please <a href="http://browsehappy.com/">upgrade your browser</a> or <a href="http://www.google.com/chromeframe/?redirect=true">activate Google Chrome Frame</a> to improve your experience.</p>
	<![endif]-->
	<header id="page_header">
		<div class="nest">
			<hgroup id="virginia_logo">
				<h1>Virginia</h1>
				<h2>Decoded</h2>
			</hgroup>
			<section id="search">
				<form id="search_form">
					<label for="search">Search the code by title name, common phrase, or assocaited court cases</label>
					<input type="search" name="search" value="" id="search" placeholder="Search the code...">
					<input type="submit" name="submit" value="Search" id="submit" class="btn btn-success">
					<a class="advanced" href="#">Advanced</a>
				</form>
			</section> <!-- // #search -->
		</div> <!-- // .nest -->
		<nav id="main_navigation" role="navigation">
			<div class="nest">
				<ul>
					<li>
						<a href="" id="the_code">The Code</a>
					</li>
					<li>
						<a href="" id="court_cases">Court Cases</a>
					</li>
					<li>
						<a href="" id="about_us">About Us</a>
					</li>
					<li>
						<a href="" id="use_the_api">Use The API</a>
					</li>
				</ul>
			</div> <!-- // .nest -->
		</nav> <!-- // #main_navigation -->
	</header> <!-- // #page_header -->

	<section id="main_content" role="main">
		<div class="{{content_class}}">
			<nav class="breadcrumbs">
				{{breadcrumbs}}
			</nav>
	
			<nav id="intercode">
				{{intercode}}
			</nav> <!-- // #intercode -->

			<h1>{{page_title}}</h1>
	
			{{body}}

			<aside id="sidebar" class="secondary-content">
			{{sidebar}}
			</aside>
		</div>
	
	</section> <!-- // #page -->

	<footer id="page_footer">
		<p>Powered by <a href="http://www.statedecoded.com/">The State Decoded</a>.</p>
	</footer>

	{{javascript_files}}
	<script>
		{{javascript}}
	</script>
	<script src="/js/jquery.qtip.min.js"></script>
</body>
</html>
