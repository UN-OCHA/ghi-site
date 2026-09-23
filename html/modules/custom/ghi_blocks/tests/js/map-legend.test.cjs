const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

function mockElement() {
  return {
    children: [], attributes: {},
    append(value) { this.children.push(value); return this; },
    attr(name, value) { this.attributes[name] = value; return this; },
    get() { return this; },
    addClass() { return this; },
    css() { return this; },
    find() { return { length: 0 }; },
  };
}

function createLegendContext(decimalFormat = 'point') {
  const jquery = () => mockElement();
  jquery.extend = Object.assign;
  const Drupal = {
    behaviors: {}, t: (text) => text,
    theme: (name, ...args) => Drupal.theme[name](...args),
  };
  const context = vm.createContext({
    window: {}, jQuery: jquery, Drupal, document: { documentElement: {}, body: {} },
    tippy: (element, options) => {
      const tooltip = { options, destroyed: false, destroy() { this.destroyed = true; } };
      element.tooltip = tooltip;
      return tooltip;
    },
    getComputedStyle: () => ({ getPropertyValue: () => '' }),
    drupalSettings: { plan_settings: { decimal_format: decimalFormat } },
  });
  for (const file of ['theme.js', 'map.gl/map.state.js', 'map.gl/map.plan_composite.js']) {
    vm.runInContext(fs.readFileSync(path.join(__dirname, '../../js', file), 'utf8'), context);
  }
  context.state = new context.window.ghi.mapState('map', {}, {}, { locations: [] }, {});
  return context;
}

function renderLegend(ranges, compact, decimalFormat) {
  const context = createLegendContext(decimalFormat);
  const legend = context.state.createRangeLegend(ranges, {}, compact);
  return legend.children.map((item) => ({ text: item.children[1], tooltip: item.tooltip?.options.content }));
}

test('other map legends retain full ranges unless compact is requested', () => {
  assert.deepEqual(renderLegend([0, 1, 1183450, 2366899]), [
    { text: '1 - 1,183,449', tooltip: undefined },
    { text: '1,183,450 - 2,366,898', tooltip: undefined },
    { text: '>= 2,366,899', tooltip: undefined },
  ]);
});

test('compact ranges preserve exact hover text and do not mutate thresholds', () => {
  const ranges = [0, 1, 1183450, 2366899];
  const original = [...ranges];
  assert.deepEqual(renderLegend(ranges, true), [
    { text: '≈ 1 - 1.2M', tooltip: '1 - 1,183,449' },
    { text: '≈ 1.2M - 2.4M', tooltip: '1,183,450 - 2,366,898' },
    { text: '≈ >= 2.4M', tooltip: '>= 2,366,899' },
  ]);
  assert.deepEqual(ranges, original);
});

test('small severity values remain exact', () => {
  assert.deepEqual(renderLegend([0, 1, 2, 3, 4, 5], true), renderLegend([0, 1, 2, 3, 4, 5], false));
});

test('thousands use the existing compact number formatter', () => {
  assert.deepEqual(renderLegend([0, 1200, 2400], true), [
    { text: '≈ 1.2k - 2.4k', tooltip: '1,200 - 2,399' },
    { text: '≈ >= 2.4k', tooltip: '>= 2,400' },
  ]);
});

test('narrow ranges do not collapse to identical rounded endpoints', () => {
  assert.equal(renderLegend([0, 1234000, 1234100], true)[0].text, '1,234,000 - 1,234,099');
});

test('compact labels respect the plan decimal separator', () => {
  assert.deepEqual(renderLegend([0, 1200000, 2400000], true, 'comma'), [
    { text: '≈ 1,2M - 2,4M', tooltip: '1 200 000 - 2 399 999' },
    { text: '≈ >= 2,4M', tooltip: '>= 2 400 000' },
  ]);
});

test('empty and zero-only legends remain valid', () => {
  assert.deepEqual(renderLegend([], true), []);
  assert.deepEqual(renderLegend([0], true), [{ text: '0', tooltip: undefined }]);
});

test('the block setting is passed into the map options', () => {
  const context = createLegendContext();
  let buildOptions;
  context.window.ghi.mapLazy = { attach: (key, element, settings, options) => { buildOptions = options.buildOptions; } };
  context.Drupal.behaviors.planCompositeMap.attach({}, {});
  assert.equal(buildOptions({}).compact_polygon_legend, true);
  assert.equal(buildOptions({ compact_polygon_legend: true }).compact_polygon_legend, true);
  assert.equal(buildOptions({ compact_polygon_legend: false }).compact_polygon_legend, false);
});


test('exact ranges use keyboard-accessible tooltips and are cleaned up with the legend', () => {
  const context = createLegendContext();
  const legend = context.state.createRangeLegend([0, 1200, 2400], {}, true);
  for (const item of legend.children) {
    assert.equal(item.attributes.title, undefined);
    assert.equal(item.attributes.tabindex, '0');
    assert.equal(item.tooltip.options.trigger, 'mouseenter focus');
    assert.equal(item.tooltip.options.hideOnClick, false);
    assert.equal(item.tooltip.options.allowHTML, false);
    assert.equal(item.tooltip.options.appendTo, context.document.body);
  }
  context.state.destroyRangeLegendTooltips();
  assert.ok(legend.children.every((item) => item.tooltip.destroyed));
  assert.equal(context.state.rangeLegendTooltips.length, 0);
});
