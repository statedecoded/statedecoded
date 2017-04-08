/**
 * favlaws class
 *
 * Allows users to save favorite laws.
 */
var favlaws = function () {
  this.pinned = [];
  this.pinLink = null;

  var self = this;

  this.init = function () {
    this.initInterface();
    this.pinned = this.getPinned();
    if(section_number) {
      this.law = this.getLaw();
      this.initLawPin();
    }
  };

  this.initInterface = function () {
    var pinnedLink = $('<li class="pinned-laws"><a href="#"><span class="icon"></span>Pinned Laws</a></li>');
    pinnedLink.click(function(e) {
      e.preventDefault();
      self.showPinned();
    });

    $('#main_navigation ul').append(pinnedLink);
  };

  this.initLawPin = function () {
    var pinLink = $('<a class="pin-law" href="#" title="Pin law"><span class="label">Pin</span></a>');
    pinLink.click(function(e) {
      e.preventDefault();
      self.toggleLaw();
    });
    $('.law .law-contents h1').prepend(pinLink);

    this.pinLink = $('.pin-law');
    this.checkActive();

  };

  this.checkActive = function () {
    if(this.law) {
      if(this.lawPinned(this.law.token)) {
        self.pinLink.addClass('pinned');
      }
      else {
        self.pinLink.removeClass('pinned');
      }
    }
  };

  this.getLaw = function () {
    // If we're on a law page, we've already got most of our data in the page.
    var law = {
      section_number: section_number,
      catch_line: $('.catch_line').first().text(),
      edition_id: edition_id,
      token: disqus_identifier,
      url: window.location.href
    };
    return law;
  };

  this.showPinned = function () {
    var lawList = $('<p>No laws pinned yet.  Click the <span class="fa fa-thumb-tack"></span> icon to pin one.</p>');
    if(this.pinned.length) {
      lawList = $('<ul class="pinned-law-list"></ul>');
      for(i in this.pinned) {
        var law = this.pinned[i];
        var listItem = $('<li><a href="' + law.url + '">§' + law.section_number + '</a> ' + law.catch_line + '</li>');
        var unpin = $('<a class="pin-law pinned" title="Unpin law" data-token="' + law.token + '"><span class="label">Unpin</span></a>');
        unpin.click(function(e) {
          e.preventDefault();
          self.unpinLaw($(this).data('token'));
          $(this).parent().remove();
          self.checkActive();
        });
        listItem.append(unpin);
        lawList.append(listItem);
      }
    }


    var closeModal = $('<a href="#" class="close" title="Close"><span class="label">close</span></a>');
    closeModal.click(function(e) {
      e.preventDefault();
      $('.modal-background').remove();
    });

    var modal = $('<div class="pinned-modal modal-background"></div>')
      .append($('<div class="modal in"></div>').append(closeModal).append('<h3>Pinned Laws</h3>').append(lawList));

    $('body').append(modal);
  };

  this.toggleLaw = function () {
    if(this.law) {
      if(this.lawPinned(this.law.token)) {
        this.unpinLaw(this.law.token);
        this.pinLink.removeClass('pinned');
      }
      else {
        this.pinLaw(this.law);
        this.pinLink.addClass('pinned');
      }
    }
  }

  this.pinLaw = function (law) {
    this.pinned.push(law);
    this.savePinned(this.pinned);
  };

  this.unpinLaw = function (token) {
    this.pinned = $.grep(this.pinned, function(elm, i) {
      return (elm.token !== token);
    });
    this.savePinned(this.pinned);
  };

  this.lawPinned = function (token) {
    var found = $.grep(this.pinned, function(elm, i) {
      return (elm.token === token);
    });
    if(found && found.length) {
      return true;
    }
    return false;
  }

  this.getPinned = function () {
    var pinned = [];
    var pinnedText = localStorage.getItem('pinned-laws');
    if(pinnedText) {
      pinned = JSON.parse(pinnedText);
    }
    return pinned;
  };

  this.savePinned = function (pinned) {
    localStorage.setItem( 'pinned-laws', JSON.stringify(pinned) );
  };

  this.init();
}

$(document).ready(favlaws);
