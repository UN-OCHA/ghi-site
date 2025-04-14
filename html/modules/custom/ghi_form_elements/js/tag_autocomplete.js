/**
 * @file
 * JavaScript behaviors for tag autocomplete.
 */
(function ($, Drupal, once) {

  Drupal.behaviors.TagAutocomplete = {
    attach: function (context, settings) {
      // console.log($default_tags['tag_ids']);
      once("tag-autocomplete", '[disabled-tags]', context).forEach((element) => {
        let disabledTagIds = $(element).attr('disabled-tags').split('-');
        if (!disabledTagIds.length) {
          return;
        }
        disabledTagIds.forEach((tagId) => {
          $(element).find('tags.active-tags tag[value=' + tagId + ']').addClass('disabled');
        });

      });
    }

  }
})(jQuery, Drupal, once);
