// User-customizable site configurations.

function confManage() {
  this.conf;

  var self = this;

  this.init = function() {

    this.conf = JSON.parse(localStorage.getItem('conf'));

    if(this.conf === null || typeof(this.conf) === 'undefined') {
      this.conf = {
        'breadcrumbs': 0
      };
    }
    // Temporarily disable animations on page load.
    $('.breadcrumbs').addClass('no-animation');
    this.updateState();
    $('.breadcrumbs').removeClass('no-animation');

    if(Mousetrap) {
      Mousetrap.bind(['b'], this.handleBreadcrumbToggle.bind(this));
    }

    if($('.law').length || $('.structure').length) {
      var confButton = $('<a class="settings-button">Settings</a>')
        .click(this.showConf);
      $('#main_content .nest').prepend(confButton);
    }
  };

  this.updateState = function() {

    // Breadcrumbs.  We use a binary operation to make changing state easier.
    // State 0 (0b00) => Show Label, Show Title
    // State 1 (0b01) => Hide Label, Show Title
    // State 2 (0b10) => Show Label, Hide Title
    // State 3 (0b11) => Hide Label, Hide Title
    // State 4 (0b100) => Resets to 0

    // Label (0b#1)
    if(this.conf['breadcrumbs'] & 1) {
      $('.breadcrumb-structure-label').addClass('hide');
    }
    else {
      $('.breadcrumb-structure-label').removeClass('hide');
    }

    // Title (0b1#)
    if(this.conf['breadcrumbs'] & 2) {
      $('.breadcrumb-structure-title').addClass('hide');
      $('.breadcrumb-id-title').addClass('hide');
    }
    else {
      $('.breadcrumb-structure-title').removeClass('hide');
      $('.breadcrumb-id-title').removeClass('hide');
    }
  };

  // Step through each of the visual states in turn.
  this.handleBreadcrumbToggle = function() {
    this.conf['breadcrumbs'] += 1;
    if(this.conf['breadcrumbs'] >= 4) {
      this.conf['breadcrumbs'] = 0;
    }

    this.storeConf();
    this.updateState();
  };

  this.storeConf = function() {
    localStorage.setItem('conf', JSON.stringify(this.conf));
  };

  this.setConfBit = function(field, n, val) {
    // Binary representation of the bit in question.
    // E.g. 3 -> 00000100 (shift twice)
    var mask = 1 << (n-1);

    if(val) {
        // Set the nth bit.
        self.conf[field] |= mask;
      }
      else {
        // Clear the nth bit.
        // Invert the mask then AND it.
        self.conf[field] &= ~mask;
      }
  };

  this.getConfBit = function(field, n) {
    var mask = 1 << (n-1);
    // Test the mask against the number.
    return ((self.conf[field] & mask) != 0);
  };

  this.showConf = function(e) {
    e.preventDefault();

    var confForm = $('<form class="settings-form"></form>');

    var bcFieldset = $('<fieldset><legend>Breadcrumb Navigation</legend></fieldset>');

    // Hide Labels
    var labelBtn = $('<input type="checkbox" id="bc-label-check" value="1">');
    labelBtn.click( function() {
      // Label, 1st bit
      self.setConfBit('breadcrumbs', 1, $(this).prop('checked'));
      self.storeConf();
      self.updateState();
    });
    labelBtn.prop('checked', self.getConfBit('breadcrumbs', 1))

    var labelBtnLabel = $('<label for="bc-label-check">Hide Labels</label>');
    labelBtnLabel.prepend(labelBtn);
    bcFieldset.append(labelBtnLabel);

    // Hide Titles
    var titleBtn = $('<input type="checkbox" id="bc-title-check" value="1">');
    titleBtn.click( function() {
      // Title, 2nd bit
      self.setConfBit('breadcrumbs', 2, $(this).prop('checked'));
      self.storeConf();
      self.updateState();
    });
    titleBtn.prop('checked', self.getConfBit('breadcrumbs', 2))

    var titleBtntitle = $('<label for="bc-title-check">Hide Titles</label>');
    titleBtntitle.prepend(titleBtn);
    bcFieldset.append(titleBtntitle);

    confForm.append(bcFieldset);

    $("<div></div>")
      .attr({
        'id': 'confModal',
        'title': 'Settings'
      })
      .append(confForm)
      .dialog({
        modal: true,
        draggable: false,
        width: '',
        open: function(e, ui) {
          $('#content').addClass('behind');
        },
        beforeClose: function(e, ui) {
          $('#content').removeClass('behind');
        }
      });
  };

  this.init();
}

$(document).ready(new confManage());
