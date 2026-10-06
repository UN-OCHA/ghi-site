const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

// Exercise the real snapshot controller with a loaded Mapbox map and DOM doubles.
function mockCapture(composite = false) {
  const timers = [];
  const attributes = new Set();
  const classes = new Set();
  const listeners = new Map();
  const container = { style: {}, offsetHeight: 460 };
  const canvas = { style: {}, toDataURL: () => 'data:image/png;base64,test' };
  const images = [];
  const canvasContainer = {
    querySelector: () => images[0] ?? null,
    insertBefore: (image) => images.push(image),
  };
  canvas.parentElement = canvasContainer;
  const element = {
    scrollIntoView() {},
    closest: () => ({ classList: { add: (name) => classes.add(name) } }),
    setAttribute: (name) => attributes.add(name),
    querySelector: (selector) => ({
      '.mapboxgl-canvas': canvas,
      '.mapboxgl-map': container,
    })[selector] ?? null,
    appendChild: (image) => images.push(image),
  };
  const document = {
    documentElement: {},
    getElementById: () => element,
    createElement: () => ({ style: {}, setAttribute() {} }),
  };
  const map = {
    resize() {},
    isStyleLoaded: () => true,
    areTilesLoaded: () => true,
    getLayer: (id) => id === (composite ? 'test-map-geojson' : 'test-map-circle'),
    queryRenderedFeatures: () => [{}],
    on: (event, callback) => listeners.set(event, callback),
    off: (event) => listeners.delete(event),
  };
  const context = vm.createContext({
    window: {}, document, jQuery: {}, Drupal: { t: (value) => value },
    getComputedStyle: () => ({ getPropertyValue: () => '' }),
    setTimeout: (callback) => timers.push(callback),
    requestAnimationFrame: (callback) => callback(),
  });
  for (const file of ['styles/map.composite.js', 'map.js']) {
    vm.runInContext(fs.readFileSync(path.join(__dirname, '../../js/map.gl', file), 'utf8'), context);
  }
  const state = {
    getMap: () => map,
    getMapId: () => 'test-map',
    getLocations: () => [{}],
  };
  state.style = composite
    ? new context.window.ghi.compositeMap({}, state, {})
    : { getFeatureLayerId: () => 'test-map-circle' };
  state.style.loaded = true;
  return {
    attributes, classes, container, canvas, images, map, state,
    capture: () => context.window.ghi.map.preparePngCaptureSnapshot(state),
    tick: () => timers.shift()?.(),
    flush: () => {
      for (let count = 0; timers.length && count < 100; count++) {
        timers.shift()();
      }
    },
  };
}

test('composite export finishes using its rendered polygon layer', () => {
  const capture = mockCapture(true);
  capture.capture();
  capture.flush();
  assert.equal(capture.attributes.has('data-map-snapshot-error'), false);
  assert.equal(capture.classes.has('map-image-loaded'), true);
});

test('snapshot preserves the map container holding HTML pies and controls', () => {
  const capture = mockCapture();
  capture.capture();
  capture.flush();
  assert.equal(capture.classes.has('map-image-loaded'), true);
  assert.notEqual(capture.container.style.display, 'none');
  assert.equal(capture.canvas.style.visibility, 'hidden');
  assert.equal(capture.images[0].style.position, 'absolute');
});

test('export waits until polygon features have rendered', () => {
  const capture = mockCapture(true);
  capture.map.queryRenderedFeatures = () => [];
  capture.capture();
  capture.tick();
  assert.equal(capture.classes.has('map-image-loaded'), false);
  capture.map.queryRenderedFeatures = () => [{}];
  capture.flush();
  assert.equal(capture.classes.has('map-image-loaded'), true);
});

test('a map that never becomes ready fails without marking the export ready', () => {
  const capture = mockCapture();
  capture.map.areTilesLoaded = () => false;
  capture.capture();
  capture.flush();
  assert.equal(capture.attributes.has('data-map-snapshot-error'), true);
  assert.equal(capture.classes.has('map-image-loaded'), false);
});
