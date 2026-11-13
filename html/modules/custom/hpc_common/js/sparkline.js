(function ($, Drupal) {

  // Attach behaviors.
  Drupal.behaviors.hpc_sparkline = {
    attach: function(context, settings) {
      // Read the theme tokens so generated SVG strokes stay in sync with CSS.
      const rootStyles = window.getComputedStyle(document.documentElement);
      const color = rootStyles.getPropertyValue('--ghi-yellow').trim() || 'rgb(255, 200, 0)';
      const colorBaseline = rootStyles.getPropertyValue('--ghi-yellow--light-chart').trim() || 'rgb(250, 240, 187)';

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
          color: color,
          color_baseline: colorBaseline,
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
