/**
 * @file
 * JavaScript behaviors for tag autocomplete.
 */
(function ($, Drupal, once, window) {

  Drupal.behaviors.TagAutocomplete = {
    attach: function (context, settings) {
      if (window.activeTags) {
        // Disable the use of the backspace to remove a tag.
        window.activeTags.settings.backspace = false;
      }
      once('tag-autocomplete-disabled', '[disabled-tags]', context).forEach((element) => {
        let disabledTagIds = $(element).attr('disabled-tags').split('-');
        if (!disabledTagIds.length) {
          return;
        }
        disabledTagIds.forEach((tagId) => {
          $(element).find('tags.active-tags tag[value=' + tagId + ']').addClass('disabled');
        });
      });
      once('tag-autocomplete-preview', '.form-type--tag_autocomplete', context).forEach((element) => {
        $(element).on('change', function (event) {
          let entityIds = event.target.__activeTags.value.map((d) => d.entity_id);
          console.log(entityIds);
        });
      });
    }

  }
})(jQuery, Drupal, once, window);
