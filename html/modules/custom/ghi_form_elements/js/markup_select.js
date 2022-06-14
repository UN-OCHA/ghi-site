/**
 * @file
 *
 */

 (function ($, Drupal, drupalSettings) {

  /**
   * Define our namespace.
   */
  Drupal.MarkupSelect = {};

  /**
   * Get an optional limit.
   */
  Drupal.MarkupSelect.getSelectLimit = function($wrapper) {
    let config = $wrapper.data('config');
    return config.limit || null;
  }

  /**
   * Handle the selection of a markup item.
   */
  Drupal.MarkupSelect.handleSelection = function(event, el, $wrapper) {
    let limit = Drupal.MarkupSelect.getSelectLimit($wrapper);
    if (limit && !$(el).hasClass('selected') && $wrapper.find('.preview-content [data-content-id].selected').length >= limit) {
      if (limit == 1) {
        // Make this work as radio.
        $wrapper.find('.preview-content [data-content-id].selected').removeClass('selected');
      }
      else {
        return;
      }
    }
    $(el).toggleClass('selected');
    Drupal.MarkupSelect.updateSelectedPreviewItems($wrapper);
  }

  /**
   * Update the selected preview items.
   */
  Drupal.MarkupSelect.updateSelectedPreviewItems = function($wrapper) {
    var selected = [];
    let limit = Drupal.MarkupSelect.getSelectLimit($wrapper);
    $wrapper.find('.preview-content [data-content-id].selected:visible').each(function (item) {
      if (limit && selected.length >= limit) {
        $(this).removeClass('selected');
        return;
      }
      selected.push($(this).attr('data-content-id'));
    });
    $wrapper.find('input.items-selected').val(selected.join());
  };

  /**
   * Initialize the element.
   */
  Drupal.MarkupSelect.init = function(key, $wrapper) {

    // Get the previews configuration.
    let config = $wrapper.data('config');
    let previews = config.previews;
    let ids = config.ids || [];
    let cols = config.cols || 5;
    let selected = $wrapper.find('input.items-selected').val().split(',').map(Number);

    if ($wrapper.find('.preview-content').length == 0) {
      $preview_wrapper = $('<div></div>');
      $preview_wrapper.addClass('preview-wrapper');
      $preview = $('<div></div>');
      $preview.addClass('preview-content');
      $preview.addClass('ghi-grid');
      $preview.addClass('cols-' + cols);

      for (id in previews) {
        id = parseInt(id);
        if (ids.indexOf(id) == -1) {
          continue;
        }
        let $rendered = $(previews[id]);
        $rendered.attr('data-content-id', id);

        // Make sure each preview item is accessible and that contained focussable items don't interfere.
        $rendered.find('[tabindex],iframe,a,input').attr('tabindex', '-1');
        $rendered.attr('tabindex', 0);

        if (selected.indexOf(id) > -1) {
          $rendered.addClass('selected');
        }
        $preview.append($rendered);
      }
      $preview_wrapper.append($preview);
      $wrapper.append($preview_wrapper);

      // Disable all links.
      $wrapper.find('.preview-content [data-content-id] a').each(function () {
        $(this).removeAttr('href');
      });

      // Attach event handlers for click events and ENTER keypresses for item
      // selection.
      $wrapper.find('.preview-content [data-content-id]').on('click', function (e) {
        Drupal.MarkupSelect.handleSelection(e, this, $wrapper);
      });
      $wrapper.find('.preview-content [data-content-id]').on('keypress', function (e) {
        if (e.which == 13) {
          Drupal.MarkupSelect.handleSelection(e, this, $wrapper);
        }
      });
    }

    Drupal.MarkupSelect.updateSelectedPreviewItems($wrapper);
  }

  Drupal.behaviors.MarkupSelect = {
    attach: function(context, settings) {
      if (typeof settings.markup_select == 'undefined') {
        return;
      }
      for (key in settings.markup_select) {
        $wrapper = $('[data-drupal-selector="' + key + '"]', context);
        if ($wrapper.length) {
          $wrapper.data('config', drupalSettings.markup_select[key]);
          Drupal.MarkupSelect.init(key, $wrapper);
        }
      }
    }
  }

})(jQuery, Drupal, drupalSettings);
