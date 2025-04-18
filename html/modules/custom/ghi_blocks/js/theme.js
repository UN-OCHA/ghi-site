(function ($) {

  // Add regex support to the states API, see
  // https://evolvingweb.ca/blog/extending-form-api-states-regular-expressions
  Drupal.hpc_content_panes_states_extension = function(reference, value) {
    if ('regex' in reference) {
      return (new RegExp(reference.regex, reference.flags)).test(value);
    } else {
      return reference.indexOf(value) !== false;
    }
  }
  Drupal.behaviors.statesModification = {
    attach: function(context, settings) {
      if (Drupal.states) {
        Drupal.states.Dependent.comparisons.Object = Drupal.hpc_content_panes_states_extension;
      }
    }
  }

  $.extend(Drupal.theme, {
    table: function (header, rows, attributes) {
      // Creates table.
      var table = $('<table></table>')

      if (typeof attributes != 'undefined') {
        if (typeof attributes.classes != 'undefined') {
          table.addClass(attributes.classes);
        }
      }

      var tr = $('<tr></tr>') // Creates row.
      var th = $('<th></th>') // Creates table header cells.
      var td = $('<td></td>') // Creates table cells.

      var thead = tr.clone() // Creates header row.

      // Fills header row.
      header.forEach(function(d) {
        thead.append(th.clone().html(d));
      })

      // Attaches header row.
      table.append($('<thead></thead>').append(thead));

      //creates
      var tbody = $('<tbody></tbody>')

      // Fills out the table body.
      rows.forEach(function(d) {
        var row = tr.clone(); // Creates a row.
        d.forEach(function(e,j) {
          row.append(td.clone().html(e)) // Fills in the row.
        });
        tbody.append(row); // Puts row on the tbody.
      })
      table.append(tbody);
      var container = $('<div></div>').append(table);
      return container.html();
    },

    mapPlanCard: function(vars) {
      var container = $('<div></div>').addClass('map-plan-card-container');
      var header = $('<div></div>').addClass('modal-header');
      var content = $('<div></div>').addClass('modal-content');

      if (vars.next || vars.previous) {
        // Add a navigation footer.
        let navigation = $('<div></div>').addClass('navigation');

        let previous_link = $('<span tabindex="0"></span>')
          .addClass('link')
          .addClass('previous')
          .html('<i class="material-icons">keyboard_arrow_left</i>');
        if (vars.previous) {
          previous_link
            .attr('data-object-id', vars.previous.object_id)
            .attr('title', vars.previous.location_name);
        }
        else {
          previous_link.addClass('disabled');
        }

        let next_link = $('<span tabindex="0"></span>')
          .addClass('link')
          .addClass('next')
          .html('<i class="material-icons">keyboard_arrow_right</i>');
        if (vars.next) {
          next_link
            .attr('data-object-id', vars.next.object_id)
            .attr('title', vars.next.location_name);
        }
        else {
          next_link.addClass('disabled');
        }

        navigation.append(previous_link);
        navigation.append(next_link);

        if (vars.current_index != null && vars.total_count != null) {
          let counter = $('<span></span>')
            .addClass('counter')
            .html(Drupal.t('!current / !total', {
              '!current': vars.current_index,
              '!total': vars.total_count
            }));
          navigation.append(counter);
        }

        header.append(navigation);
      }

      // Add the title.
      if (vars.title) {
        header.append($('<div></div>').addClass('title').html(vars.title)); // Creates title div.
      }
      // Add the title.
      if (vars.tag_line) {
        header.append($('<div></div>').addClass('tag-line').html(vars.tag_line)); // Creates tag line div.
      }
      // Add the admin area.
      if (vars.location_data && typeof vars.location_data.admin_level != 'undefined') {

        var location_details = [];
        location_details.push(Drupal.t('Admin level !admin_level', {
          '!admin_level': vars.location_data.admin_level
        }));
        if (typeof vars.pcodes_enabled != 'undefined' && vars.pcodes_enabled == true && vars.location_data.hasOwnProperty('pcode') && vars.location_data.pcode && vars.location_data.pcode.length) {
          location_details.push(vars.location_data.pcode);
        }
        if (vars.location_data.object_count_label) {
          location_details.push(vars.location_data.object_count_label);
        }
        header.append($('<div></div>').addClass('admin-area').html(location_details.join(' | ')));
      }
      // Add the content.
      if (vars.content) {
        content.append($('<div></div>').addClass('content').html(vars.content)); // Creates content div.
      }
      // Add the subcontent.
      if (vars.subcontent) {
        content.append($('<div></div>').addClass('subcontent').html(vars.subcontent)); // Creates subcontent div.
      }

      $(container).append(header);
      $(container).append(content);
      return container[0].outerHTML;
    },

    number: function(amount, short) {
      var num = parseInt(amount);
      if (short) {
        return number_format_si(num, 1);
      }
      return number_format(num, 0);
    },

    amount: function(amount, include_prefix) {
      var num = parseInt(amount);
      var prefix = include_prefix ? ' USD' : '';
      return number_format(num, 0) + prefix;
    },

    percent: function(ratio) {
      return number_format(ratio * 100, 1) + '%';
    },
  });

  number_format = function(number, decimals) {
    var decimal_format = 'point';
    let settings = drupalSettings;
    let plan_settings = settings.hasOwnProperty('plan_settings') ? settings.plan_settings : null;
    if (plan_settings && plan_settings.hasOwnProperty('decimal_format') && plan_settings.decimal_format) {
      decimal_format = plan_settings.decimal_format;
    }
    dec_point = decimal_format == 'point' ? '.' : ',';
    thousands_sep = decimal_format == 'point' ? ',' : ' ';

    var n = !isFinite(+number) ? 0 : +number,
        prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
        sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
        dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
        toFixedFix = function (n, prec) {
            // Fix for IE parseFloat(0.55).toFixed(0) = 0;
            var k = Math.pow(10, prec);
            return Math.round(n * k) / k;
        },
        s = (prec ? toFixedFix(n, prec) : Math.round(n)).toString().split('.');
    if (s[0].length > 3) {
        s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
    }
    if ((s[1] || '').length < prec) {
        s[1] = s[1] || '';
        s[1] += new Array(prec - s[1].length + 1).join('0');
    }
    return s.join(dec);
  };

  number_format_si = function(num, decimals) {
    var si = [
      { value: 1, symbol: "" },
      { value: 1E3, symbol: "k" },
      { value: 1E6, symbol: "M" },
      { value: 1E9, symbol: "G" },
      { value: 1E12, symbol: "T" },
      { value: 1E15, symbol: "P" },
      { value: 1E18, symbol: "E" }
    ];
    var rx = /\.0+$|(\.[0-9]*[1-9])0+$/;
    var i;
    for (i = si.length - 1; i > 0; i--) {
      if (num >= si[i].value) {
        break;
      }
    }
    return number_format(num / si[i].value, decimals).replace(rx, "$1") + si[i].symbol;
  }

})(jQuery, Drupal);
