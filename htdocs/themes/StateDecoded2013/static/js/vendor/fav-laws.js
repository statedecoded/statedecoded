/**
 * favlaws class
 *
 * Allows users to save favorite laws.
 */
var favlaws = function () {
  this.pinned = [];
  this.pinLink = null;
  this.countBadge = null;

  // Counts above this are shown as "99+", so that the badge stays narrow.
  this.maxDisplayedCount = 99;

  var self = this;

  this.init = function () {
    this.initInterface();
    this.pinned = this.getPinned();
    this.updateCount();
    if(typeof section_number != 'undefined' && section_number) {
      this.law = this.getLaw();
      this.initLawPin();

      if(Mousetrap) {
        Mousetrap.bind(['p'], function(e) {
          self.toggleLaw();
        });
      }
    }
  };

  this.initInterface = function () {
    var pinnedLink = $('<li class="pinned-laws"><a href="#"><span class="icon"></span>Pinned Laws<span class="pin-count" aria-hidden="true"></span></a></li>');
    pinnedLink.click(function(e) {
      e.preventDefault();
      self.showPinned();
    });

    $('#main_navigation ul').append(pinnedLink);

    this.countBadge = pinnedLink.find('.pin-count');
  };

  /**
   * Show how many laws are pinned, as a badge on the menu item.
   *
   * The badge is hidden entirely at zero, rather than showing "0": an empty
   * badge is noise. Counts above maxDisplayedCount are shown as "99+" so that
   * the badge cannot grow wide enough to disturb the navigation.
   *
   * The badge is aria-hidden, since the count is decorative -- the link text
   * already says what it links to. The link's own title carries the count for
   * anyone who wants it.
   */
  this.updateCount = function () {
    if(!this.countBadge || !this.countBadge.length) {
      return;
    }

    var count = this.pinned.length;

    if(count < 1) {
      this.countBadge.text('').removeClass('has-pins');
      this.countBadge.closest('a').removeAttr('title');
      return;
    }

    var label = (count > this.maxDisplayedCount)
      ? this.maxDisplayedCount + '+'
      : String(count);

    this.countBadge.text(label).addClass('has-pins');
    this.countBadge.closest('a').attr('title',
      count + (count === 1 ? ' pinned law' : ' pinned laws'));
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
      token: this.lawToken(section_number, edition_id),
      url: window.location.href
    };
    return law;
  };

  /**
   * The identifier that distinguishes one pinned law from another.
   *
   * This used to be the Disqus identifier, but that is only emitted when
   * DISQUS_SHORTNAME is configured. Without it every law's token was the empty
   * string, so pinning any one law marked all of them as pinned.
   *
   * A section number is unique only within an edition, so both are needed.
   */
  this.lawToken = function (sectionNumber, editionId) {
    return editionId + ':' + sectionNumber;
  };

  this.showPinned = function () {
    var defaultContent = $('<p>No laws pinned yet.  Click the <span class="fa fa-thumb-tack"></span> icon to pin one.</p>');
    var lawList = defaultContent;
    var modal;

    if(this.pinned.length) {
      lawList = $('<ul class="pinned-law-list"></ul>');
      for(i in this.pinned) {
        var law = this.pinned[i];
        var listItem = $('<li></li>');
        var lawLink = $('<a href="' + law.url + '">§' + law.section_number + '</a>');
        var lawText = ' ' + law.catch_line;
        var unpin = $('<a class="pin-law pinned" title="Unpin law" data-token="' + law.token + '"><span class="label">Unpin</span></a>');
        unpin.click(function(e) {
          e.preventDefault();
          self.unpinLaw($(this).data('token'));
          $(this).parent().remove();
          self.checkActive();

          if(!self.pinned.length) {
            modal.html(defaultContent);
          }

        });
        listItem.append(unpin);
        listItem.append(lawLink);
        listItem.append(lawText);
        lawList.append(listItem);
      }
    }

    modal = $("<div></div>")
      .attr({
        'id': 'pinnedModal',
        'title': 'Pinned Laws'
      })
      .append(lawList)
      .dialog({
        modal: true,
        draggable: false,
        /*
         * "auto" leaves the width to the stylesheet, where it belongs. Passing
         * an empty string here (as this once did) is not a valid dimension:
         * jQuery UI writes it into the element's inline style, the browser
         * discards it, and the dialog collapses to fit its content.
         *
         * jQuery UI generates the wrapper element around this dialog, so the
         * "classes" option is how we get a hook to style it by.
         */
        width: 'auto',
        classes: {
          'ui-dialog': 'pinned-laws-dialog'
        },
        open: function(e, ui) {
          $('#content').addClass('behind');
        },
        beforeClose: function(e, ui) {
          $('#content').removeClass('behind');
        }
      });
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
    return this.repairTokens(pinned);
  };

  /**
   * Give a usable token to any law saved before tokens were derived from the
   * section number and edition.
   *
   * Laws pinned on a site without Disqus configured were all stored with an
   * empty token, which made them indistinguishable. Rebuilding the token from
   * the section number and edition -- both of which were always stored --
   * repairs them in place, so nobody loses their pinned laws.
   */
  this.repairTokens = function (pinned) {
    var self = this;
    var seen = {};
    var repaired = [];

    $.each(pinned, function (i, law) {
      if(!law || typeof law.section_number === 'undefined') {
        return;
      }

      if(!law.token) {
        law.token = self.lawToken(law.section_number, law.edition_id);
      }

      /*
       * Colliding tokens may have let the same law be pinned more than once.
       */
      if(seen[law.token]) {
        return;
      }

      seen[law.token] = true;
      repaired.push(law);
    });

    return repaired;
  };

  this.savePinned = function (pinned) {
    localStorage.setItem( 'pinned-laws', JSON.stringify(pinned) );

    /*
     * Every change to the pinned laws passes through here -- pinning from a
     * law page, the "p" shortcut, and unpinning from within the modal -- so
     * this is the one place the badge needs refreshing.
     */
    this.updateCount();
  };

  this.init();
}

$(document).ready(favlaws);
