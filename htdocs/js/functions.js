$(document).ready(function () {
	
	/* Provide the ability to navigate with arrow keys. */
	Mousetrap.bind('left', function(e) {
		var url = $('a.prev').attr('href');
		if (url) {
			window.location = url;
		}
	});
	
	Mousetrap.bind('right', function(e) {
		var url = $('a.next').attr('href');
		if (url) {
			window.location = url;
		}
	});
	
	/* Highlight a section chosen in an anchor (URL fragment). The first stanza is for externally
	originating traffic, the second is for when clicking on an anchor link within a page. */
	if (document.location.hash) {
		$(document.location.hash).slideto({
			highlight_color: 'yellow',
			highlight_duration: 5000,
			slide_duration: 500
		});

	}
	$('a[href*=#]').click(function(){
		var elemId = '#' + $(this).attr('href').split('#')[1];
		$(elemId).slideto({
			highlight_color: 'yellow',
			highlight_duration: 5000,
			slide_duration: 500
		});
	});
	
		
	/* Display a tooltip for permalinks. */
	$('a.section-permalink').qtip({
		content: "Permanent link to this subsection",
		show: {
			event: "mouseover"
		},
		hide: {
			event: "mouseout"
		},
		position: {
			at: "top center",
			my: "bottom center"
		}
	})
	
	/* Mentions of other sections of the code. */
	$("a.law").each(function() {
		var section_number = $(this).text();
		$(this).qtip({
			tip: true,
			hide: {
				when: 'mouseout',
				fixed: true,
				delay: 100
			},
			position: {
				at: "top center",
				my: "bottom left"
			},
			style: {
				width: 300,
				tip: "bottom left"
			},
			content: {
				text: 'Loading .&thinsp;.&thinsp;.',
				ajax: {
					url: '/api/0.1/law/'+section_number,
					type: 'GET',
					data: { fields: 'catch_line,ancestry', key: api_key },
					dataType: 'json',
					success: function(section, status) {
						if( section.ancestry instanceof Object ) {
							var content = '';
							for (key in section.ancestry) {
								var content = section.ancestry[key].name + ' → ' + content;
							}
						}
						var content = content + section.catch_line;
						this.set('content.text', content);
					}
				}
			}
		})
	});

	/* Truncate text at 250 characters of length. Written by "c_harm" and posted to Stack Overflow
	at http://stackoverflow.com/a/1199627/955342 */
	String.prototype.truncate = function(){
		var re = this.match(/^.{0,500}[\S]*/);
		var l = re[0].length;
		var re = re[0].replace(/\s$/,'');
		if(l < this.length)
			re = re + "&nbsp;.&thinsp;.&thinsp;.&thinsp;";
		return re;
	}
	
	/* Words for which we have dictionary terms.*/
	$("span.dictionary").each(function() {
		var term = $(this).text();
		$(this).qtip({
			tip: true,
			hide: {
				when: 'mouseout',
				fixed: true,
				delay: 100
			},
			position: {
				at: "top center",
				my: "bottom left"
			},
			style: {
				width: 300,
				tip: "bottom left"
			},
			content: {
				text: 'Loading .&thinsp;.&thinsp;.',
				ajax: {
					url: '/api/0.1/dictionary',
					type: 'GET',
					data: { term: term, section: section_number, key: api_key },
					dataType: 'json',
					success: function(data, status) {
						var content = data.definition.truncate();
						if (data.section != null) {
							content = content + ' (<a href="' + data.url + '">§&nbsp;' + data.section + '</a>)';
						}
						else if (data.source) {
							content = content + ' (Source: <a href="' + data.url + '">' + data.source + '</a>)';
						}
						this.set('content.text', content);
					}
				}
			}
		})
	});
});