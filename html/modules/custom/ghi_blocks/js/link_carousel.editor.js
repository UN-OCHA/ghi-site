/**
 * @file
 * Keeps editorial placements and nonblocking donation feedback up to date.
 */

/* global once */

(function (Drupal, once, $) {
  'use strict';

  const donationFeedbackCleanups = new WeakMap();
  const placementCleanups = new WeakMap();

  function attachDonationFeedback(trigger) {
    const form = trigger.closest('form');
    const queue = new Set();
    const refresh = () => {
      if (!trigger.isConnected) {
        cleanup();
        return;
      }
      const ajax = Drupal.ajax.instances.find((instance) => instance && instance.element === trigger);
      if (!ajax || ajax.ajaxing || !queue.size) {
        return;
      }
      // A tab switch or final Update can replace this form. Let that action
      // finish before issuing feedback for any remaining queued fields.
      if (Drupal.ajax.instances.some((instance) => instance && instance !== ajax && instance.$form && instance.$form[0] === form && instance.ajaxing)) {
        return;
      }
      const key = queue.values().next().value;
      queue.delete(key);
      trigger.value = key;
      // Core disables only this hidden trigger, keeping visible draft values
      // present in an immediate Update or tab-navigation submission.
      $(trigger).trigger('linkCarouselDonationFeedback');
    };
    const change = (event) => {
      const wrapper = event.target.closest('[data-link-carousel-donation-field]');
      if (!wrapper || !event.target.matches('input, textarea')) {
        return;
      }
      queue.add(wrapper.getAttribute('data-link-carousel-donation-field'));
      refresh();
    };
    const complete = () => refresh();
    const cleanup = () => {
      form.removeEventListener('change', change);
      $(document).off('ajaxComplete', complete);
      donationFeedbackCleanups.delete(trigger);
    };
    form.addEventListener('change', change);
    // Core emits this only after its commands finish and ajaxing is cleared.
    $(document).on('ajaxComplete', complete);
    donationFeedbackCleanups.set(trigger, cleanup);
  }

  function placementRows(table) {
    return Array.from(table.querySelectorAll('tbody > tr.draggable')).filter((row) => row.closest('table') === table);
  }

  function availableRow(row) {
    return row.getAttribute('data-link-carousel-available') === '1';
  }

  function placementLimit(table) {
    return Number(table.getAttribute('data-link-carousel-max-items'));
  }

  /**
   * Measures how far the cutoff is outside the allowed valid-item range.
   *
   * Invalid rows do not count. An entirely invalid draft list has no limits;
   * distance lets keyboard moves repair an existing out-of-range boundary.
   */
  function displayBounds(table, rows = placementRows(table)) {
    const maxItems = placementLimit(table);
    const end = rows.findIndex((row) => row.hasAttribute('data-link-carousel-display-end'));
    const total = rows.filter(availableRow).length;
    const count = rows.slice(0, end).filter(availableRow).length;
    return {end: end, total: total, count: count, maxItems: maxItems, distance: total ? Math.max(1 - count, count - maxItems, 0) : 0};
  }

  function placementFeedback(table, message) {
    const form = table.closest('form');
    const feedback = form && form.querySelector('[data-link-carousel-placement-feedback]');
    if (feedback && feedback.textContent !== message) {
      feedback.textContent = message;
    }
  }

  function boundaryMessage(bounds) {
    if (!bounds.total || !bounds.distance) {
      return '';
    }
    return bounds.count < 1 ? Drupal.t('Keep at least one valid news item above the display boundary.') : Drupal.t('Only Main news and up to @count Featured items can be displayed above the boundary.', {'@count': bounds.maxItems - 1});
  }

  function clampedBoundaryMessage(table) {
    return Drupal.t('The display boundary was adjusted to keep one to @count valid news items above it.', {'@count': placementLimit(table)});
  }

  function updatePlacements(table) {
    const rows = placementRows(table);
    const bounds = displayBounds(table, rows);
    if (bounds.end < 0) {
      return;
    }
    let index = 0;
    rows.forEach((row, position) => {
      if (position === bounds.end) {
        return;
      }
      let role = 'unavailable';
      let label = Drupal.t('Not available');
      if (availableRow(row)) {
        role = 'hidden';
        label = Drupal.t('Not displayed');
        if (position < bounds.end && index < bounds.maxItems) {
          role = index === 0 ? 'main' : 'featured';
          label = index === 0 ? Drupal.t('Main news') : Drupal.t('Featured @number', {'@number': index});
        }
        if (position < bounds.end) {
          index++;
        }
      }
      row.classList.remove('link-carousel-editor__row--main', 'link-carousel-editor__row--featured', 'link-carousel-editor__row--hidden', 'link-carousel-editor__row--unavailable');
      row.classList.add('link-carousel-editor__row--' + role);
      row.querySelectorAll('[data-link-carousel-placement]').forEach((placement) => {
        placement.textContent = label;
      });
    });
    table.setAttribute('data-link-carousel-featured-count', Math.max(0, Math.min(bounds.maxItems - 1, bounds.count - 1)));
  }

  function renumberWeights(rows) {
    rows.forEach((row, index) => {
      const weight = row.querySelector('.row-weight');
      if (weight) {
        weight.value = String(index);
      }
    });
  }

  function clampDisplayEnd(table, rows) {
    const bounds = displayBounds(table, rows);
    if (bounds.end < 0 || !bounds.distance) {
      return false;
    }
    const end = rows.splice(bounds.end, 1)[0];
    const available = rows.filter(availableRow);
    const anchor = available[bounds.count < 1 ? 0 : bounds.maxItems - 1];
    rows.splice(rows.indexOf(anchor) + 1, 0, end);
    return true;
  }

  function applyRowOrder(rows, tableDrag) {
    const focused = document.activeElement;
    const restoreFocus = rows.some((row) => row.contains(focused));
    const safeBlur = tableDrag && tableDrag.safeBlur;
    if (tableDrag) {
      // Moving a focused handle must not recursively run core's drop handler.
      tableDrag.safeBlur = false;
    }
    try {
      rows.forEach((row) => row.parentNode.appendChild(row));
      renumberWeights(rows);
      if (restoreFocus) {
        focused.focus({preventScroll: true});
      }
    }
    finally {
      if (tableDrag) {
        tableDrag.safeBlur = safeBlur;
      }
    }
  }

  function attachPlacements(table) {
    updatePlacements(table);
    placementFeedback(table, boundaryMessage(displayBounds(table)));
    const tableDrag = Drupal.tableDrag[table.id];
    if (tableDrag) {
      const OriginalRow = tableDrag.row;
      const onDrop = tableDrag.onDrop;
      const allowedSwap = (dragged, position, target) => {
        const current = placementRows(table);
        const proposed = current.filter((row) => !dragged.group.includes(row));
        const targetIndex = proposed.indexOf(target);
        if (targetIndex < 0) {
          return true;
        }
        proposed.splice(targetIndex + (position === 'after' ? 1 : 0), 0, ...dragged.group);
        const before = displayBounds(table, current);
        const after = displayBounds(table, proposed);
        // Changed availability can leave a saved boundary outside the valid
        // range. Permit non-worsening steps across invalid rows to repair it
        // with the keyboard, but never introduce a new invalid boundary.
        if (!after.distance || (before.distance && after.distance <= before.distance)) {
          return true;
        }
        placementFeedback(table, boundaryMessage(after));
        return false;
      };
      // Core's keyboard path calls swap() directly, bypassing isValidSwap().
      // Override this table's row constructor, not the shared row prototype.
      tableDrag.row = class extends OriginalRow {
        isValidSwap(row) {
          return super.isValidSwap(row) && allowedSwap(this, this.direction === 'down' ? 'after' : 'before', row);
        }

        swap(position, row) {
          if (allowedSwap(this, position, row)) {
            super.swap(position, row);
          }
        }

        onSwap(row) {
          super.onSwap(row);
          updatePlacements(table);
          placementFeedback(table, boundaryMessage(displayBounds(table)));
        }
      };
      tableDrag.onDrop = function () {
        onDrop.apply(this, arguments);
        if (this.rowObject && this.rowObject.changed) {
          const rows = placementRows(table);
          if (clampDisplayEnd(table, rows)) {
            applyRowOrder(rows, this);
            placementFeedback(table, clampedBoundaryMessage(table));
          }
          else {
            renumberWeights(rows);
          }
        }
        updatePlacements(table);
      };
      placementCleanups.set(table, () => {
        tableDrag.row = OriginalRow;
        tableDrag.onDrop = onDrop;
        placementCleanups.delete(table);
      });
    }
    table.addEventListener('change', (event) => {
      if (!event.target.matches('.row-weight')) {
        return;
      }
      const rows = placementRows(table);
      const positions = new Map(rows.map((row, index) => [row, index]));
      const weight = (row) => Number(row.querySelector('.row-weight').value);
      rows.sort((first, second) => weight(first) - weight(second) || positions.get(first) - positions.get(second));
      const clamped = clampDisplayEnd(table, rows);
      applyRowOrder(rows, tableDrag);
      updatePlacements(table);
      placementFeedback(table, clamped ? clampedBoundaryMessage(table) : '');
    });
  }

  Drupal.behaviors.linkCarouselPlacements = {
    attach: function (context) {
      const selector = 'table[data-link-carousel-placements]';
      const tables = Array.from(context.querySelectorAll(selector));
      if (context.matches && context.matches(selector)) {
        tables.unshift(context);
      }
      once('link-carousel-placements', tables).forEach(attachPlacements);
    },
    detach: function (context, settings, trigger) {
      if (trigger === 'unload') {
        once.remove('link-carousel-placements', 'table[data-link-carousel-placements]', context).forEach((table) => {
          const cleanup = placementCleanups.get(table);
          if (cleanup) {
            cleanup();
          }
        });
      }
    }
  };

  Drupal.behaviors.linkCarouselDonationFeedback = {
    attach: function (context) {
      once('link-carousel-donation-feedback', '[data-link-carousel-donation-refresh]', context).forEach(attachDonationFeedback);
    },
    detach: function (context, settings, trigger) {
      if (trigger === 'unload') {
        once.remove('link-carousel-donation-feedback', '[data-link-carousel-donation-refresh]', context).forEach((element) => {
          const cleanup = donationFeedbackCleanups.get(element);
          if (cleanup) {
            cleanup();
          }
        });
      }
    }
  };
})(Drupal, once, jQuery);
