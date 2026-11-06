/**
 * @file
 * Applies shared backend styling without changing native tabledrag behavior.
 */

(function (Drupal, once) {
  'use strict';

  const observers = new WeakMap();

  /**
   * Returns registered tables in this backend form context, excluding previews.
   */
  const getTables = (context, settings) => Object.keys(settings.tableDrag || {})
  .map((id) => document.getElementById(id))
  .filter((table) => table && context.contains(table) && table.closest('form[data-ghi-tabledrag]') && !table.closest('[data-block-preview]'));

  /**
   * Decorates core's indentation lanes without changing their count or width.
   */
  const decorateHierarchy = (table) => {
    const records = [];
    Array.from(table.tBodies).forEach((body) => {
      let nextByDepth = [];
      const rows = Array.from(body.rows).map((row) => {
        if (!row.classList.contains('draggable') || !row.cells[0]) {
          return null;
        }
        const indents = Array.from(row.cells[0].querySelectorAll('.js-indentation')).filter((indent) => indent.closest('table') === table);
        return {row, indents, depth: indents.length};
      });
      // A shallower row or a non-draggable boundary ends the current sibling
      // set. This also distinguishes ancestor continuations in deeper trees.
      rows.slice().reverse().forEach((record) => {
        if (!record) {
          nextByDepth = [];
          return;
        }
        record.hasNextSibling = Boolean(nextByDepth[record.depth]);
        nextByDepth.length = record.depth + 1;
        nextByDepth[record.depth] = record;
      });
      let ancestors = [];
      rows.forEach((record, index) => {
        if (!record) {
          ancestors = [];
          return;
        }
        const next = rows[index + 1];
        record.row.classList.toggle('ghi-tabledrag__group', record.row.classList.contains('tabledrag-root') || Boolean(next && next.depth > record.depth));
        record.indents.forEach((indent, level) => {
          const branch = level === record.depth - 1;
          indent.classList.add('ghi-tabledrag__indent');
          indent.classList.toggle('ghi-tabledrag__indent--line', branch || Boolean(ancestors[level + 1] && ancestors[level + 1].hasNextSibling));
          indent.classList.toggle('ghi-tabledrag__indent--branch', branch);
          indent.classList.toggle('ghi-tabledrag__indent--end', branch && !record.hasNextSibling);
        });
        ancestors.length = record.depth + 1;
        ancestors[record.depth] = record;
        records.push(record);
      });
    });
    // Read geometry together, after decorating. Guides must span the whole row
    // even when another column contains a tall preview or wrapped form fields.
    const geometry = records.filter((record) => record.indents.length).map((record) => {
      const row = record.row.getBoundingClientRect();
      const indent = record.indents[0].getBoundingClientRect();
      return {row: record.row, top: row.top - indent.top, height: row.height};
    });
    geometry.forEach((record) => {
      record.row.style.setProperty('--ghi-tabledrag-guide-top', `${record.top}px`);
      record.row.style.setProperty('--ghi-tabledrag-guide-height', `${record.height}px`);
    });
  };

  /**
   * Follows native swaps, indentation and AJAX without replacing core callbacks.
   */
  const observeHierarchy = (table) => {
    const state = {frame: null};
    const schedule = () => {
      if (state.frame !== null) {
        return;
      }
      state.frame = requestAnimationFrame(() => {
        state.frame = null;
        decorateHierarchy(table);
      });
    };
    // Only structural changes matter. Observing attributes would also observe
    // our own classes/styles and create a feedback loop during decoration.
    state.mutations = new MutationObserver(schedule);
    state.mutations.observe(table, {childList: true, subtree: true});
    state.resize = new ResizeObserver(schedule);
    state.resize.observe(table);
    observers.set(table, state);
    schedule();
  };

  Drupal.behaviors.ghiTableDrag = {
    attach: (context, settings) => {
      // The registry also includes tables built late by field widgets and Views.
      // Looking up IDs directly avoids assumptions about their generated markup.
      once('ghi-tabledrag', getTables(context, settings)).forEach((table) => {
        table.classList.add('ghi-tabledrag');
        // Stacked mobile data-table cards break native row/column hit testing.
        // Keep the existing scroll wrapper, indentation and hidden weight cells.
        table.classList.remove('cd-table--responsive');
        // Core's globally numbered option keys can serialize as objects rather
        // than arrays, even for different groups on the same table.
        const hierarchy = Object.values(settings.tableDrag[table.id]).some((group) => Object.values(group).some((option) => option.relationship === 'parent'));
        if (hierarchy) {
          table.classList.add('ghi-tabledrag--hierarchy');
          observeHierarchy(table);
        }
      });
    },
    detach: (context, settings, trigger) => {
      if (trigger !== 'unload') {
        return;
      }
      once.remove('ghi-tabledrag', getTables(context, settings)).forEach((table) => {
        const state = observers.get(table);
        if (state) {
          state.mutations.disconnect();
          state.resize.disconnect();
          cancelAnimationFrame(state.frame);
          observers.delete(table);
        }
      });
    }
  };

})(Drupal, once);
