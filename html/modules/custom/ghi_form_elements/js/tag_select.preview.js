/**
 * @file
 *
 */

(function ($, Drupal, drupalSettings) {

  Drupal.TagSelectPreview = {};

  Drupal.TagSelectPreview.updateSummary = function(key, $wrapper) {

    // Get the tag select configuration.
    let tag_select = drupalSettings.tag_select[key];
    let ids_by_tag = tag_select.ids_by_tag;
    let tag_op = $wrapper.find('[data-drupal-selector="tag_op"] input[type="checkbox"]').is(':checked') ? 'and' : 'or';
    let all_ids = [...new Set([].concat.apply([], Object.values(tag_select.ids_by_tag).map(items => Object.values(items))))];

    if ($wrapper.find('.preview-summary').length == 0) {
      $summary = $('<span></span>');
      $summary.addClass('preview-summary');
      $wrapper.find('legend').append($summary);
    }

    var checked = [];
    $('fieldset input[type="checkbox"]:checked', $wrapper).each(function (i, checkbox) {
      checked.push($(checkbox).val());
    });

    var ids = tag_op == 'or' ? [] : all_ids;
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
    $wrapper.find('.preview-summary').html(' (' + Drupal.formatPlural(ids.length, tag_select.labels.singular, tag_select.labels.plural) + ')');
  }

  Drupal.behaviors.TagSelectPreview = {
    attach: function(context, settings) {
      if (typeof settings.tag_select == 'undefined') {
        return;
      }

      for (key in settings.tag_select) {
        $wrapper = $('[data-drupal-selector="' + key + '"]');
        Drupal.TagSelectPreview.updateSummary(key, $wrapper);
        $('fieldset input[type="checkbox"]', $wrapper).once().bind('change', function() {
          Drupal.TagSelectPreview.updateSummary(key, $wrapper);
        });
        $('[data-drupal-selector="tag_op"] input[type="checkbox"]', $wrapper).once().bind('change', function() {
          Drupal.TagSelectPreview.updateSummary(key, $wrapper);
        });
      }

    }
  }

})(jQuery, Drupal, drupalSettings);
