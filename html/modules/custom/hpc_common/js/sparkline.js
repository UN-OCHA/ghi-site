(function ($, Drupal) {

  // Attach behaviors.
  Drupal.behaviors.hpc_sparkline = {
    attach: function(context, settings) {
      $('span.sparkline', context).once('sparkline').each(function(index) {
        let data = $(this).data('values').toString().split(',');
        let tooltip_content = $(this).data('tooltips').toString().split('|');
        let baseline = $(this).data('baseline');
        $(this).uniqueId();
        let options = {
          target: '#' + $(this).attr('id'),
          data: data.map(Number),
          baseline: baseline,
          size: 'parent',
          stroke_width: 3,
          stroke_width_baseline: 3,
          color: 'rgb(254, 216, 61)',
          color_baseline: 'rgb(248, 236, 180)',
          dasharray_baseline: '5, 10',
          point_radius: 2,
          tooltip: function(i) {
            return '<div class="tippy-box" data-state="visible" tabindex="-1" data-animation="fade" role="tooltip" data-placement="top"><div class="tippy-content" data-state="visible" style="transition-duration: 300ms;"><span>' + tooltip_content[i] + '</span></div><div class="tippy-arrow"></div></div>';
          }
        };
        var sparkchart = new SparkLine(options);
        sparkchart.render();
      });
    }
  }

})(jQuery, Drupal);
