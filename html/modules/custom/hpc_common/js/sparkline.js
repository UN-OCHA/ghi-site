(function ($, Drupal) {

  // Attach behaviors.
  Drupal.behaviors.hpc_sparkline = {
    attach: function(context, settings) {
      once('sparkline', 'span.sparkline', context).forEach(element => {
        if (!$(element).data('values')) {
          return;
        }
        let data = $(element).data('values').toString().split(',').map(Number);
        if (data.filter(x => !!x).length == 0) {
          return;
        }
        let tooltip_content = $(element).data('tooltips').toString().split('|');
        let baseline = $(element).data('baseline');
        $(element).uniqueId();
        let options = {
          target: '#' + $(element).attr('id'),
          data: data,
          baseline: baseline,
          size: 'parent',
          stroke_width: 3,
          stroke_width_baseline: 3,
          color: 'rgb(254, 216, 61)',
          color_baseline: 'rgb(248, 236, 180)',
          dasharray_baseline: '5, 10',
          point_radius: 2,
          tooltip: function(i) {
            if (!tooltip_content[i]) {
              return null;
            }
            return '<div class="tippy-box" data-state="visible" tabindex="-1" data-animation="fade" role="tooltip" data-placement="top"><div class="tippy-content" data-state="visible" style="transition-duration: 300ms;"><span>' + tooltip_content[i] + '</span></div><div class="tippy-arrow"></div></div>';
          }
        };
        var sparkchart = new SparkLine(options);
        sparkchart.render();
      });
    }
  }

})(jQuery, Drupal);
