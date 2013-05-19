// Avoid `console` errors in browsers that lack a console.
(function() {
    var method;
    var noop = function () {};
    var methods = [
        'assert', 'clear', 'count', 'debug', 'dir', 'dirxml', 'error',
        'exception', 'group', 'groupCollapsed', 'groupEnd', 'info', 'log',
        'markTimeline', 'profile', 'profileEnd', 'table', 'time', 'timeEnd',
        'timeStamp', 'trace', 'warn'
    ];
    var length = methods.length;
    var console = (window.console = window.console || {});

    while (length--) {
        method = methods[length];

        // Only stub undefined methods.
        if (!console[method]) {
            console[method] = noop;
        }
    }
}());


jQuery(function($) {

  //console.log("Ready Loaded");
  // Remove Preload so that transitions work after page loads
  $("body").removeClass("preload");

    Mousetrap.bind('?', function() {
     //console.log("Question Mark Pressed");
     $('#keyboard-shortcuts').modal("show");
    });

  // Table Column Highlgihting
  // --------------------------------------------------------

  $(".table-highlighting").delegate('td','mouseover mouseleave', function(e) {
    var whichCol = $(this).index();
    if (e.type == 'mouseover') {
      $(this).parent().addClass("is-active");
      $(this).parent().parent().siblings("colgroup").eq(whichCol).addClass("is-active");
    }
    else {
      $(this).parent().removeClass("is-active");
      $(this).parent().parent().siblings("colgroup").eq(whichCol).removeClass("is-active");
    }
  });

  // Open Lists
  // --------------------------------------------------------

  $(".collapse-list").delegate('li','click', function(e) {
    //console.log("Clicked");
    $(this).toggleClass("is-open");
  });



  // Handler for .ready() called.
});