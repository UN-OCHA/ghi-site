(function ($) {

  // Attach behaviors.
  Drupal.behaviors.hpc_plan_ajax_switcher = {
    attach: function(context, settings) {

      $('.ajax-switcher', context).change(function(e) {
        var value = $(this).val();
        let url = $(this).data('url');
        let query_key = $(this).data('query-key');
        let args = $(this).data('args');
        let pane = $(this).parents('.pane-content');
        var pane_id = $(this).parents('.panel-pane').data('pid');
        if (!pane_id && $(this).parents('form.hpc-widget-form')) {
          // It seems we are in a configuration context.
          pane_id = $(this).parents('form.hpc-widget-form').data('pid');
        }
        $(this).addClass('loading');
        $(pane).addClass('loading');
        $(pane).append('<div class="loading throbber"></div>');

        var data = {
          pane_id: pane_id,
        };
        data[query_key] = value;

        if (typeof args == 'object') {
          $.extend(data, args);
        }

        // Prevent duplicate HTML ids in the returned markup.
        // @see drupal_html_id()
        data['ajax_html_ids[]'] = [];
        $('[id]').each(function () {
          data['ajax_html_ids[]'].push(this.id);
        });

        // Allow Drupal to return new JavaScript and CSS files to load without
        // returning the ones already loaded.
        // @see ajax_base_page_theme()
        // @see drupal_get_css()
        // @see drupal_get_js()
        data['ajax_page_state[theme]'] = Drupal.settings.ajaxPageState.theme;
        data['ajax_page_state[theme_token]'] = Drupal.settings.ajaxPageState.theme_token;
        for (var key in Drupal.settings.ajaxPageState.css) {
          data['ajax_page_state[css][' + key + ']'] = 1;
        }
        for (var key in Drupal.settings.ajaxPageState.js) {
          data['ajax_page_state[js][' + key + ']'] = 1;
        }

        $.ajax({
          url: url,
          data: data,
          method: 'post',
          success: function (response, status, jqXHR) {
            new Drupal.ajax(null, $(document.body), {url: ''}).success(response, status);
          },
          complete: function() {
            $(this).removeClass('loading');
            $(pane).removeClass('loading');
            $(pane).find('.loading.throbber').remove();
          }
        })
      });
    }
  }

})(jQuery, Drupal);
