/**
 * @file
 *
 */

(function ($, Drupal) {

  Drupal.TagSelectPreview = {
    checkboxSelector: 'fieldset input[type="checkbox"]:not(:disabled)',
    logicToggleSelector: '[data-drupal-selector="tag_op"] input[type="checkbox"]',
  };

  Drupal.TagSelectPreview.updateSelectedPreviewItems = function($wrapper) {
    var selected = [];
    $wrapper.find('.preview-content [data-content-id].selected:visible').each(function (item) {
      selected.push($(this).attr('data-content-id'));
    });
    $wrapper.find('input.selected-items').val(selected.join());
  };

  Drupal.TagSelectPreview.updateSummary = function(tag_select, $wrapper) {

    // Get the tag select configuration.
    let ids_by_tag = tag_select.ids_by_tag;
    let previews = tag_select.previews;
    let select_preview_items = tag_select.select_preview_items;
    let tag_op = $wrapper.find(Drupal.TagSelectPreview.logicToggleSelector).is(':checked') ? 'and' : 'or';
    let all_ids = [...new Set([].concat.apply([], Object.values(tag_select.ids_by_tag).map(items => Object.values(items))))];

    if ($wrapper.find('.preview-summary').length == 0) {
      $summary = $('<span></span>');
      $summary.addClass('preview-summary');
      $wrapper.find('legend').append($summary);
    }

    if (previews && $wrapper.find('.preview-content').length == 0) {
      $preview_label = $('<div class="label">' + Drupal.t('Content preview') + '</div>');
      $preview_wrapper = $('<div></div>');
      $preview_wrapper.addClass('preview-wrapper');
      $preview_wrapper.prepend($preview_label);
      $preview = $('<div></div>');
      $preview.addClass('preview-content');
      $preview.addClass('ghi-grid');
      $preview.addClass('cols-5');
      let selected = select_preview_items ? $wrapper.find('input.selected-items').val().split(',') : false;

      for (nid in previews) {
        let $node_view = $(previews[nid]);
        $node_view.attr('data-content-id', nid);
        $node_view.attr('tabindex', 0);

        if (select_preview_items && selected.indexOf(nid) > -1) {
          $node_view.addClass('selected');
        }
        $preview.append($node_view);
      }
      $preview_wrapper.append($preview);
      $wrapper.append($preview_wrapper);
      $wrapper.find('.preview-content [data-content-id] a').each(function () {
        $(this).removeAttr('href');
      });
      if (select_preview_items) {
        $wrapper.find('.preview-content [data-content-id]').on('click', function (e) {
          $(this).toggleClass('selected');
          Drupal.TagSelectPreview.updateSelectedPreviewItems($wrapper);
        });
        $wrapper.find('.preview-content [data-content-id]').on('keypress', function (e) {
          if (e.which == 13) {
            $(this).toggleClass('selected');
            Drupal.TagSelectPreview.updateSelectedPreviewItems($wrapper);
          }
        });
      }
    }

    var checked = [];
    $(Drupal.TagSelectPreview.checkboxSelector + ':checked', $wrapper).each(function (i, checkbox) {
      checked.push($(checkbox).val());
    });

    var ids = all_ids;
    if (checked.length > 0) {
      ids = tag_op == 'or' ? [] : all_ids;
      for (index in checked) {
        let tag_id = checked[index];
        let tag_ids = ids_by_tag.hasOwnProperty(tag_id) ? Object.values(ids_by_tag[tag_id]) : [];
        if (tag_op == 'or') {
          // OR concatenation.
          ids = ids.concat(tag_ids.filter((item) => ids.indexOf(item) < 0));
        }
        else {
          // AND concatenation.
          ids = ids.filter(value => tag_ids.includes(value));
        }
      }
    }

    // Update the preview summary (number of matched items).
    $wrapper.find('.preview-summary').html(' (' + Drupal.formatPlural(ids.length, tag_select.labels.singular, tag_select.labels.plural) + ')');

    // Update the content preview (preview of the actual rendered items).
    if (previews) {
      $wrapper.find('.preview-content [data-content-id]').hide();
      for (i in ids) {
        $wrapper.find('.preview-content [data-content-id=' + ids[i] + ']').show();
      }
      if (select_preview_items) {
        Drupal.TagSelectPreview.updateSelectedPreviewItems($wrapper);
      }
    }
  }

  Drupal.behaviors.TagSelectPreview = {
    attach: function(context, settings) {
      if (typeof settings.tag_select == 'undefined') {
        return;
      }

      for (key in settings.tag_select) {
        $wrapper = $('[data-drupal-selector="' + key + '"]');
        let tag_select = settings.tag_select[key];
        Drupal.TagSelectPreview.updateSummary(tag_select, $wrapper);
        once('tag-select-checkbox-selector', $wrapper.find(Drupal.TagSelectPreview.checkboxSelector)).forEach(element => {
          element.addEventListener('change', e => {
            Drupal.TagSelectPreview.updateSummary(tag_select, $wrapper);
          });
        });
        once('logic-toggle-selector', $wrapper.find(Drupal.TagSelectPreview.logicToggleSelector)).forEach(element => {
          element.addEventListener('change', e => {
            Drupal.TagSelectPreview.updateSummary(tag_select, $wrapper);
          });
        });
      }

    }
  }

})(jQuery, Drupal);
