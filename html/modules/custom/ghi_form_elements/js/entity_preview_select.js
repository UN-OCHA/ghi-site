/**
 * @file
 *
 */

 (function ($, Drupal, drupalSettings) {

  /**
   * Define our namespace.
   */
  Drupal.EntityPreviewSelect = {};

  /**
   * Get the form field that stores an optional limit.
   */
  Drupal.EntityPreviewSelect.getSelectLimitField = function($wrapper) {
    let limit_selector = $wrapper.data('config').limit_field;
    return limit_selector ? $wrapper.parents('form').find('[data-drupal-selector="' + limit_selector + '"]') : null;
  }

  /**
   * Get an optional limit.
   */
  Drupal.EntityPreviewSelect.getSelectLimit = function($wrapper) {
    let $limit_field = Drupal.EntityPreviewSelect.getSelectLimitField($wrapper);
    return $limit_field ? $limit_field.val() : $wrapper.data('config').allow_selected;
  }

  /**
   * Handle the selection of an entity preview item.
   */
  Drupal.EntityPreviewSelect.handleSelection = function(event, el, $wrapper) {
    if ($(event.target).hasClass('featured-checkbox') || $(event.target).parents('.featured-checkbox').length) {
      // Ignore the featured checkbox.
      return;
    }
    let limit = Drupal.EntityPreviewSelect.getSelectLimit($wrapper);
    if (limit && !$(el).hasClass('selected') && $wrapper.find('.preview-content [data-content-id].selected').length >= limit) {
      return;
    }
    $(el).toggleClass('selected');
    if (!$(el).hasClass('selected') && $(el).hasClass('featured')) {
      $(el).toggleClass('featured');
      Drupal.EntityPreviewSelect.updateFeatureSelectedPreviewItems($wrapper);
    }
    Drupal.EntityPreviewSelect.updateSelectedPreviewItems($wrapper);
  }

  /**
   * Update the selected preview items.
   */
  Drupal.EntityPreviewSelect.updateSelectedPreviewItems = function($wrapper) {
    var selected = [];
    let limit = Drupal.EntityPreviewSelect.getSelectLimit($wrapper);
    $wrapper.find('.preview-content [data-content-id].selected:visible').each(function (item) {
      if (limit && selected.length >= limit) {
        $(this).removeClass('selected');
        return;
      }
      selected.push($(this).attr('data-content-id'));
    });
    $wrapper.find('input.entities-selected').val(selected.join());
    Drupal.EntityPreviewSelect.updatePreviewSummary($wrapper);
  };

  /**
   * Handle the selection of an entity preview item as feature.
   */
   Drupal.EntityPreviewSelect.handleFeatureSelection = function(el, $wrapper) {
    let limit = $wrapper.data('config').allow_featured;
    if (limit && !$(el).hasClass('featured') && $wrapper.find('.preview-content [data-content-id].featured').length >= limit) {
      $(el).find('input[type="checkbox"').prop('checked', false);
      return;
    }
    $(el).toggleClass('featured');
    if ($(el).hasClass('featured') && !$(el).hasClass('selected')) {
      $(el).toggleClass('selected');
      Drupal.EntityPreviewSelect.updateSelectedPreviewItems($wrapper);
    }
    Drupal.EntityPreviewSelect.updateFeatureSelectedPreviewItems($wrapper);
  }

  /**
   * Update the selected preview items.
   */
   Drupal.EntityPreviewSelect.updateFeatureSelectedPreviewItems = function($wrapper) {
    var featured = [];
    let limit = $wrapper.data('config').allow_featured;
    $wrapper.find('.preview-content [data-content-id].featured:visible').each(function () {
      if (limit && featured.length >= limit) {
        $(this).removeClass('featured');
        return;
      }
      featured.push($(this).attr('data-content-id'));
    });
    $wrapper.find('.preview-content [data-content-id]:visible').each(function () {
      $(this).find('input[type="checkbox"').prop('checked', $(this).hasClass('featured'));
      let disabled = !$(this).hasClass('featured') && (limit && featured.length >= limit);
      $(this).find('input[type="checkbox"').attr('disabled', disabled);
      $(this).find('input[type="checkbox"').parent().toggleClass('disabled', disabled);
    });
    $wrapper.find('input.entities-featured').val(featured.join());
    Drupal.EntityPreviewSelect.updatePreviewSummary($wrapper);
  };

  /**
   * Update the preview summary.
   */
  Drupal.EntityPreviewSelect.updatePreviewSummary = function($wrapper) {
    let selected = Drupal.EntityPreviewSelect.getSelected($wrapper);
    let featured = Drupal.EntityPreviewSelect.getFeatured($wrapper);
    let limit_selected = Drupal.EntityPreviewSelect.getSelectLimit($wrapper);
    let limit_featured = $wrapper.data('config').allow_featured;
    var summary_selected = null;
    var summary_featured = null;
    if (limit_selected) {
      summary_selected = Drupal.t('!selected/!limit selected', {
        '!selected': selected.length,
        '!limit': limit_selected,
      });
    }
    else {
      summary_selected = Drupal.t('!selected selected', {
        '!selected': selected.length,
      });
    }
    if (limit_featured) {
      summary_featured = Drupal.t('!selected/!limit featured', {
        '!selected': featured.length,
        '!limit': limit_featured,
      });
    }
    if (summary_selected || summary_featured) {
      $wrapper.find('.preview-summary').html(' (' + [
        summary_selected,
        summary_featured
      ].filter((x) => x).join(', ') + ')');
    }
    else {
      $wrapper.find('.preview-summary').html();
    }
  }

  /**
   * Get the list of selected items.
   */
  Drupal.EntityPreviewSelect.getSelected = function($wrapper) {
    return $wrapper.find('input.entities-selected').val().split(',').filter((x) => x).map(Number);
  }

  /**
   * Get the list of featured items.
   */
  Drupal.EntityPreviewSelect.getFeatured = function($wrapper) {
    $input_featured = $wrapper.find('input.entities-featured');
    return $input_featured.length ? $input_featured.val().split(',').filter((x) => x).map(Number) : [];
  }

  /**
   * Initialize the element.
   */
  Drupal.EntityPreviewSelect.init = function(key, $wrapper) {

    // Get the previews configuration.
    let config = $wrapper.data('config');
    let previews = config.previews;
    let entity_ids = config.entity_ids || [];
    let selected = Drupal.EntityPreviewSelect.getSelected($wrapper);
    let allow_featured = config.allow_featured || 0;
    let show_filter = config.show_filter || false;
    let featured = allow_featured ? Drupal.EntityPreviewSelect.getFeatured($wrapper) : [];

    if ($wrapper.find('.preview-summary').length == 0) {
      $summary = $('<span></span>');
      $summary.addClass('preview-summary');
      $wrapper.children('label').append($summary);
    }

    if ($wrapper.find('.preview-content').length == 0) {
      $preview_wrapper = $('<div></div>');
      $preview_wrapper.addClass('preview-wrapper');
      $preview = $('<div></div>');
      $preview.addClass('preview-content');
      $preview.addClass('ghi-grid');
      $preview.addClass('cols-5');

      for (nid in previews) {
        nid = parseInt(nid);
        if (entity_ids.indexOf(nid) == -1) {
          continue;
        }
        let $entity_view = $(previews[nid]);
        $entity_view.attr('data-content-id', nid);

        // Make sure each preview item is accessible.
        $entity_view.attr('tabindex', 0);

        if (selected.indexOf(nid) > -1) {
          $entity_view.addClass('selected');
        }
        if (allow_featured > 0) {
          $featured_wrapper = $('<div class="featured-checkbox"><input type="checkbox" name="featured" value="1"><label for="featured">' + Drupal.t('Mark as featured') + '</label></div>');
          $entity_view.prepend($featured_wrapper);
          if (featured.indexOf(nid) > -1) {
            $entity_view.addClass('featured');
          }
        }
        $preview.append($entity_view);
      }
      $preview_wrapper.append($preview);
      $wrapper.append($preview_wrapper);

      // Disable all links.
      $wrapper.find('.preview-content [data-content-id] a').each(function () {
        $(this).removeAttr('href');
      });

      // Make the items sortable.
      Sortable.create($wrapper.find('.preview-content').get(0), {
        draggable: '[data-content-id]',
        ghostClass: 'ui-state-drop',
        group: 'entity-preview-selection',
        dataIdAttr: 'data-content-id',
        delay: 50,
        delayOnTouchOnly: true,
        store: {
          get: function (sortable) {
            return $wrapper.find('input.entities-order').val().split(',');
          },
          set: function (sortable) {
            var order = sortable.toArray();
            $wrapper.find('input.entities-order').val(order.join(','));
          }
        }
      });

      // Attach event handlers for click events and ENTER keypresses for item
      // selection.
      $wrapper.find('.preview-content [data-content-id]').on('click', function (e) {
        Drupal.EntityPreviewSelect.handleSelection(e, this, $wrapper);
      });
      $wrapper.find('.preview-content [data-content-id]').on('keypress', function (e) {
        if (e.which == 13) {
          Drupal.EntityPreviewSelect.handleSelection(e, this, $wrapper);
        }
      });

      // Attach event handlers for click events and ENTER keypresses for item
      // feature selection.
      if (allow_featured) {
        $wrapper.find('.featured-checkbox input').on('change', function (e) {
          e.stopPropagation();
          Drupal.EntityPreviewSelect.handleFeatureSelection($(this).parents('[data-content-id]'), $wrapper);
        });
        $wrapper.find('.featured-checkbox > *:not(input)').on('click', function (e) {
          e.stopPropagation();
          Drupal.EntityPreviewSelect.handleFeatureSelection($(this).parents('[data-content-id]'), $wrapper);
        });
      }

      if (show_filter) {
        $filter_field = $('<div class="filter-field-wrapper"><label for="filter-field" class="glb-form-item__label">' + Drupal.t('Filter by text') + '</label><input id="entity-preview-filter-field" name="filter-field" type="search" placeholder="' + Drupal.t('Type to filter') + '" aria-label="' + Drupal.t('Filter the available articles') + '"></div>');
        $wrapper.prepend($filter_field);
        $wrapper.find('input[name="filter-field"]').on('input', function() {
          let value = $(this).val();
          if(!value) {
            $wrapper.find('.preview-content [data-content-id]').show();
          }
          $wrapper.find('.preview-content [data-content-id]').each(function () {
            if (!($(this).html().toLowerCase().includes(value.toLowerCase()))) {
              $(this).hide();
            }
          });
        });
      }
    }

    Drupal.EntityPreviewSelect.updateSelectedPreviewItems($wrapper);
    Drupal.EntityPreviewSelect.updateFeatureSelectedPreviewItems($wrapper);
  }

  Drupal.behaviors.EntityPreviewSelect = {
    attach: function(context, settings) {
      if (typeof settings.entity_preview_select == 'undefined') {
        return;
      }
      for (key in settings.entity_preview_select) {
        $wrapper = $('[data-drupal-selector="' + key + '"]', context);
        if ($wrapper.length) {
          $wrapper.data('config', drupalSettings.entity_preview_select[key]);
          Drupal.EntityPreviewSelect.init(key, $wrapper);

          $limit_field = Drupal.EntityPreviewSelect.getSelectLimitField($wrapper);
          if ($limit_field) {
            $limit_field.on('input', function () {
              Drupal.EntityPreviewSelect.updateSelectedPreviewItems($wrapper);
            });
          }
        }
      }
    }
  }

})(jQuery, Drupal, drupalSettings);
