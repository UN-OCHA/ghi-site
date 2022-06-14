(function ($) {
  // Override the showAlert method so that we can define a callback as the error text.
  L.Control.Search.prototype.__showAlert = L.Control.Search.prototype.showAlert;
  L.Control.Search.prototype.showAlert = function(text) {
    var self = this;
    var error_text;
    text = text || this.options.textErr;
    if (typeof text == 'function') {
      error_text = text(self);
    }
    else {
      error_text = text;
    }
    // Hand over to the original method.
    return this.__showAlert(error_text);
  };
  // Override the cancel method to be able to close the error message if
  // it's present.
  L.Control.Search.prototype.__cancel = L.Control.Search.prototype.cancel;
  L.Control.Search.prototype.cancel = function() {
    this.hideAlert();
    return this.__cancel();
  }
  // Overrride the keypress handler, just to be able to search on backspace.
  L.Control.Search.prototype._handleKeypress = function (e) { //run _input keyup event
    var self = this;

    switch(e.keyCode)
    {
      case 27://Esc
        this.collapse();
      break;
      case 13://Enter
        if (this._countertips == 1 || (this.options.firstTipSubmit && this._countertips > 0)) {
          if (this._tooltip.currentSelection == -1) {
            this._handleArrowSelect(1);
          }
        }
        this._handleSubmit(); //do search
      break;
      case 38://Up
        this._handleArrowSelect(-1);
      break;
      case 40://Down
        this._handleArrowSelect(1);
      break;
      // case  8://Backspace
      // case 45://Insert
      // case 46://Delete
      //   this._autoTypeTmp = false;//disable temporarily autoType
      // break;
      case 37://Left
      case 39://Right
      case 16://Shift
      case 17://Ctrl
      case 35://End
      case 36://Home
      break;
      default://All keys
        if(this._input.value.length)
          this._cancel.style.display = 'block';
        else
          this._cancel.style.display = 'none';

        if(this._input.value.length >= this.options.minLength)
        {
          clearTimeout(this.timerKeypress); //cancel last search request while type in
          this.timerKeypress = setTimeout(function() {  //delay before request, for limit jsonp/ajax request

            self._fillRecordsCache();

          }, this.options.delayType);
        }
        else
          this._hideTooltip();
    }

    this._handleAutoresize();
  }
  // Override the _handleArrowSelect method to set the location name in the
  // input field.
  L.Control.Search.prototype.__handleArrowSelect = L.Control.Search.prototype._handleArrowSelect;
  L.Control.Search.prototype._handleArrowSelect = function(velocity) {
    this.__handleArrowSelect(velocity);
    let searchTips = this._tooltip.hasChildNodes() ? this._tooltip.childNodes : [];

    if (typeof searchTips[this._tooltip.currentSelection] == 'undefined') {
      return;
    }

    let map_id = this._map._container.id;
    let location_id = searchTips[this._tooltip.currentSelection]._text;

    let location_data = this.options.getLocationData(map_id, location_id);
    this._input.value = location_data.location_name;

    $('#' + map_id + ' .search-tip').removeClass('hover');
    $('#' + map_id + ' .search-tip[data-location-id=' + location_id + ']').addClass('hover');
  }
  // Override the submit handler to use the location id instead of the
  // title shown in the input.
  L.Control.Search.prototype.__handleSubmit = L.Control.Search.prototype._handleSubmit;
  L.Control.Search.prototype._handleSubmit = function() { //button and tooltip click and enter submit

    let searchTips = this._tooltip.hasChildNodes() ? this._tooltip.childNodes : [];
    var location_id;
    if (typeof searchTips[this._tooltip.currentSelection] != 'undefined') {
      location_id = searchTips[this._tooltip.currentSelection]._text;
    }
    else {
      location_id = this._input.value;
    }

    this._hideAutoType();
    this.hideAlert();
    this._hideTooltip();

    if (this._input.style.display == 'none') { //on first click show _input only
      this.expand();
    } else {
      if (this._input.value === '') { //hide _input only
        this.collapse();
      } else {
        var loc = this._getLocation(location_id);
        if (loc == false) {
          this._fillRecordsCache();
          if (!this._tooltip.hasChildNodes()) {
            this.showAlert();
          }
        } else {
          this.showLocation(loc, location_id);
          this.fire('search:locationfound', {
              latlng: loc,
              text: this._input.value,
              layer: loc.layer ? loc.layer : null
            });
        }
      }
    }
  }
})(jQuery, Drupal);