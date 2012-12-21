<!DOCTYPE html>
<!-- paulirish.com/2008/conditional-stylesheets-vs-css-hacks-answer-neither/ -->
<!--[if lt IE 7 ]> <html lang="en" class="ie ie6"> <![endif]-->
<!--[if IE 7 ]>    <html lang="en" class="ie ie7"> <![endif]-->
<!--[if IE 8 ]>    <html lang="en" class="ie ie8"> <![endif]-->
<!--[if IE 9 ]>    <html lang="en" class="ie ie9"> <![endif]-->
<!--[if (gt IE 9)|!(IE)]><!--> <html lang="en"> <!--<![endif]-->
<head>
	<meta charset="utf-8"/>
	<title>{{browser_title}}</title>
	<meta name="viewport" content="width=device-width; initial-scale=1.0; maximum-scale=1.0;">
	<link rel="stylesheet" href="/css/reset.css" type="text/css" media="screen">
	<link rel="stylesheet" href="/css/master.css" type="text/css" media="screen">
	<link rel="stylesheet" href="/css/jquery.qtip.css" type="text/css" media="screen">
	<link rel="home" title="Home" href="/" />
	<link rel="search" title="Search" href="/search/" />
	{{link_rel}}
	{{css}}
	{{inline_css}}
	<!-- CSS: Generic print styles -->
	<!--<link rel="stylesheet" media="print" href="/css/print.css"/>-->
	
	<!-- For the less-enabled mobile browsers like Opera Mini -->
	<!--<link rel="stylesheet" media="handheld" href="/css/handheld.css"/>-->
	
	<!-- Make MSIE play nice with HTML5 & Media Queries -->
	<script src="/js/modernizr.custom.23612.js"></script>
	<script src="/js/respond.min.js"></script>
	<!--[if lt IE 9]>
	<script src="http://ie7-js.googlecode.com/svn/version/2.1(beta4)/IE9.js"></script>
	<![endif]-->

	<script src="https://www.google.com/jsapi?key=YOURKEY"></script>
	<script>
		google.load("jquery", "1.4.3");
		google.load("jqueryui", "1.8.11");
	</script>
</head>
<body>
	<div id="corner-banner">
		<span><a href="/about/">Beta</a></span>
	</div>
	<header id="masthead">
		<hgroup>
			<h1><a href="/">The State Decoded</a></h1>
		</hgroup>
		<nav id="main_navigation">
			<div id="search">
				<form method="get" action="/search/">
					<input type="search" size="20" name="q" placeholder="Search the Code"/>
					<input type="submit" value="Search" />
				</form>
			</div> <!-- // #search -->
			<ul>
				<li><a href="/" class="ir" id="home">Home</a></li>
				<li><a href="/about/" class="ir" id="about">About</a></li>
			</ul>
		</nav> <!-- // #main_navigation -->
	</header> <!-- // #masthead -->

	<section id="page">
		<nav id="breadcrumbs">
			{{breadcrumbs}}
		</nav>
		
		<nav id="intercode">
			{{intercode}}
		</nav> <!-- // #intercode -->

		<h1>{{page_title}}</h1>
    	
    	<section id="sidebar">
		{{sidebar}}
		</section>
		
		{{body}}
		
	</section> <!-- // #page -->
  
	<footer id="page_footer">
		<p>Powered by <a href="http://www.statedecoded.com/">The State Decoded</a>.</p>
	</footer>
	{{javascript_files}}
	<script>
		{{javascript}}
	</script>
	<script src="/js/jquery.qtip.min.js"></script>
	<script src="/js/jquery.color.js"></script>
</body>
</html>