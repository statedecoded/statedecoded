/**
 * Truncate text at 500 characters of length.
 */
function truncate(str){
	var re = str.match(/^.{0,500}[\S]*/);
	var new_str = re[0];
	var new_str = new_str.replace(/\s$/,'');
	if(new_str.length < str.length)
		new_str = new_str + "&nbsp;&#8230;";
	console.log(str, new_str);
	return new_str;
}

/**
 * Escape special characters in selectors
 *
 * Examples:
 * console.log(escapeSelector('^')); // returns '\^'
 * console.log(escapeSelector('#(a)')); // returns '#\(a\)'
 */
function escapeSelector(str) {
	// Find everything but # which we want to leave intact!
	var find = /([!"$%&'()*+,.\/:;<=>?@\[\]\\^`\{|\}~])/g;
	var replace = '\\$1';
	return str.replace(find, replace);
}


/**
 * Get help via AJAX callback
 */
var help = {};
function getHelp(section, callback) {
	if(!help.length) {
		$.getJSON('/content/help.json', {}, function(data, textStatus, jqXHR) {
			if(data) {
				help = data;
				callback(section, help[section]);
			}
			else {
				console.log('No help returned from /content/help.json');
			}
		});
	}
	else {
		callback(section, help[section]);
	}
}

/**
 * Show help via jQuery UI dialog
 */
function displayHelp(section, help_section) {
	$("<div></div>")
		.attr({
			'id': section,
			'title': help_section['title']
		})
		.append(help_section['content'])
		.dialog({
			modal: true,
			draggable: false,
			open: function(e, ui) {
				$('#content').addClass('behind');
			},
			beforeClose: function(e, ui) {
				$('#content').removeClass('behind');
			}
		});
}

/**
 * Wrapper to both get and show help.
 */
function showHelp(section) {
	getHelp(section, displayHelp);
}

$(document).ready(function () {

	/* Provide the ability to navigate with arrow keys. */
	Mousetrap.bind(['ctrl+left', 'left', 'j', 'a'], function(e) {
		var url = $('link[rel=prev]').attr('href');
		if (url) {
			window.location = url;
		}
	});

	Mousetrap.bind(['ctrl+right', 'right', 'k', 'd'], function(e) {
		var url = $('link[rel=next]').attr('href');
		if (url) {
			window.location = url;
		}
	});

	Mousetrap.bind(['ctrl+up', 'w'], function(e) {
		var url = $('link[rel=up]').attr('href');
		if (url) {
			window.location = url;
		}
	});

	Mousetrap.bind(['ctrl+down', 's'],  function(e) {
		var url = $('link[rel=down]').attr('href');
		if (url) {
			window.location = url;
		}
	});

	Mousetrap.bind(['/', 'ctrl+/'], function(e) {
		if(!$('#search-input:focus').length) {
			e.preventDefault();
			$('#search-input').focus();
		}
	});

	Mousetrap.bind(['?', 'h'], function(e) {
		if($("#keyhelp").length) {
			$("#keyhelp").click();
		}
	});

	/* Highlight a section chosen in an anchor (URL fragment). The first stanza is for externally
	originating traffic, the second is for when clicking on an anchor link within a page. */
	if (document.location.hash) {
		var id = escapeSelector(document.location.hash);
		$(id).slideto({
			slide_duration: 500
		});

		$(id).show('highlight', {color: '#ffff00'}, 'fast');
	}

	$('a[href*=#]').click(function(){

		var elemId = '#' + escapeSelector($(this).attr('href').split('#')[1]);

		$(elemId).slideto({
			slide_duration: 500
		});

		var id = escapeSelector(document.location.hash);
		$(id).show('highlight', {color: '#ffff00'}, 'fast');

	});


	/* Display a tooltip for permalinks. */
	$('a.section-permalink').qtip({
		content: "Copy permanent link to this subsection",
		show: {
			event: "mouseover"
		},
		hide: {
			event: "mouseout",
			fixed: true,
			delay: 100
		},
		position: {
			at: "top center",
			my: "bottom center",
			viewport: $(window)
		}
	})

    /*
     * Set the base URL once for use later.
     * Note that document.location.origin is a Chrome-only thing so far, so we can't use it.
     */
    var base_url;
    if ('https:' == document.location.protocol) {
        base_url = 'https://';
    }
    else {
        base_url = 'http://';
    }
    /* We want the port if it is set, so don't use hostname */
    base_url += document.location.host;

	/* Get each permalink and add a copy function on it when visible */
	$('a.section-permalink').bind('inview', function(event, visible, topOrBottomOrBoth) {
		if(!visible) return false;

		var elm = $(this);
		var id = escapeSelector(elm.attr('id'));

		if(!elm.data('copyable')){
			/* Permit copying URLs to the clipboard. */
			elm.zclip({
				path: zclip_swf_file,
				copy: function() { return base_url + $(this).attr('href'); }
			});

			/* Ensure zclip only run once per element */
			elm.data('copyable', true);
		}
	});

	/* Mentions of other sections of the code. */
	$("a.law").each(function(i, elm) {
		elm = $(elm);
		var section_number = $(this).text();
		var content = {};

		// If we have multiple references, use that list in the popup.
		if(elm.hasClass('multiple-references')) {
			var laws = elm.data('popup-content');

			var popup_content = section_number + ' may refer to the following sections: ';
			popup_content += '<ul>';

			for(i in laws)
			{
				var law = laws[i];
				popup_content += '<li><a href="' + law.url + '">' + law.catch_line + '</a></li>';
			}

			popup_content += '</ul>';

			content = {
				text: popup_content
			};

			// Since this is activated on hover, we need something for screen readers.
			// This will be hidden from normal browsers.

			var footnote = $('.footnote');
			if(footnote.length === 0) {
				footnote = $('<section class="footnote"></section>');
				footnote.append('<h2>Footnotes</h2>');
			}

			// Create a wrapper to link to.
			var wrapper = $('<article><h3>' + elm.data('ref-count') + '</h3></article>');
			wrapper.attr('id', 'ref-' +  elm.data('ref-count'));
			wrapper.append(popup_content);

			footnote.append(wrapper);

			$('#law').append(footnote);

			elm.after('<sup class="footnote-link"><a href="#ref-' + elm.data('ref-count') + '">' + elm.data('ref-count') + '</a></sup>');


		}

		// Otherwise, Ajax in the list.
		else {
			console.log('/api/law' + elm.attr('href'));
			content = {
				text: 'Loading . . .', // Those are U+2009, not regular spaces.
				ajax: {
					url: '/api/law' + elm.attr('href'),
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
			};
		}


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
			content: content
		})
	});

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
				text: 'Loading . . .', // Those are U+2009, not regular spaces.
				ajax: {
					url: '/api/dictionary/' + encodeURI(term) + '/',
					type: 'GET',
					data: {
						law_id: law_id,
						edition_id: edition_id,
						key: api_key
					},
					dataType: 'json',
					success: function(data, status) {
						var content = truncate(data.definition);
						if (data.section_number != null) {
							content = content + ' (<a href="' + data.url + '">§&nbsp;' + data.section_number + '</a>)';
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

	/* Modal dialog overlay. */
	$("#keyhelp").click(function() {
		showHelp('keyboard');
	});



	/* Search Autocomplete */
	$(function() {
	  function split( val ) {
		return val.split( /\s+/ );
	  }
	  function extractLast( term ) {
		return split( term ).pop();
	  }

	  $( "#q" ).autocomplete({
		source: function( request, response ) {
		  if(request.term[request.term.length-1]==" ") {
			response()
			return;
		  }
		  $.ajax({
			url: "/api/suggest/" + request.term,
			dataType: "jsonp",
			cache: true,
			data: {
			  key: api_key
			},
			success: function( data ) {
			  response( $.map(data.terms, function(item) {
				return {
					label: item.term,
					value: item.term
				}
			  }));
			}
		  });
		},
		minLength: 2,
		focus: function() {
		  // prevent value inserted on focus
		  return false;
		},
		select: function( event, ui ) {
		  var terms = split( this.value );
		  // remove the current input
		  terms.pop();
		  // add the selected item
		  terms.push( ui.item.value );
		  // add placeholder to get the comma-and-space at the end
		  terms.push( "" );
		  this.value = terms.join( " " );
		  return false;
		},
		open: function() {
		  $( this ).removeClass( "ui-corner-all" ).addClass( "ui-corner-top" );
		},
		close: function() {
		  $( this ).removeClass( "ui-corner-top" ).addClass( "ui-corner-all" );
		}
	  });
	});

	// We need to make sure every row in our table has the same number of columns.
	// Otherwise dataTable doesn't work.
	$('.primary-content table').each(function(i, elm) {
		elm = $(elm);

		// Get the longest row length.
		var maxsize = 0;
		var trs = elm.find('tr');
		trs.each(function(i, tr) {
			if($(tr).find('td,th').length > maxsize) {
				maxsize = $(tr).find('td,th').length;
			}
		});

		// Repeat the loop, this time padding out our rows
		trs.each(function(i, tr) {
			var tr = $(tr);
			var missing = maxsize - tr.find('td,th').length;
			if(missing > 0) {
				var extra_elms = '<td></td>';
				if(tr.parent().prop('tagName').toLowerCase() === 'thead') {
					extra_elms = '<th></th>';
				}

				for(i=0; i<missing; i++) {
					tr.append(extra_elms);
				}
			}
		});
	});

	$('.primary-content table').dataTable({
        'scrollY': '400px',
        'scrollX': true,
        'scrollCollapse': true,
        'paging': false,
        'ordering': false,
        'info': false
    });

});
