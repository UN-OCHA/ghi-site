const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

function mockMap(cached = true) {
  const frames = new Map();
  const timers = new Map();
  const idle = new Set();
  const requests = [];
  const writes = [];
  let taskId = 0;
  let pending = 0;
  let level = 1;
  let metric = 'need';
  const location = () => ({ object_id: level, metrics: { 10: { need: 12, target: 24 } } });
  const controller = {
    showThrobber: () => pending++,
    hideThrobber: () => pending--,
    loadFeaturesAsync(locations, callback) {
      const respond = () => callback(locations.map((item) => ({ properties: { location_id: item.object_id } })));
      if (cached) respond();
      else requests.push(respond);
    },
  };
  const map = {
    once: (event, callback) => idle.add(callback),
    off: (event, callback) => idle.delete(callback),
  };
  const state = {
    getMapId: () => 'map',
    getMap: () => map,
    getMapController: () => controller,
    getLocationsKeyed: () => ({ [level]: location() }),
    updateMapData: (source, features) => writes.push({ source, features }),
    querySourceFeatures: () => { throw new Error('Do not restore stale Mapbox source features'); },
  };
  const context = vm.createContext({
    window: {}, jQuery: {}, document: { documentElement: {} },
    getComputedStyle: () => ({ getPropertyValue: () => '' }),
    requestAnimationFrame: (callback) => { frames.set(++taskId, callback); return taskId; },
    cancelAnimationFrame: (id) => frames.delete(id),
    setTimeout: (callback) => { timers.set(++taskId, callback); return taskId; },
    clearTimeout: (id) => timers.delete(id),
  });
  vm.runInContext(fs.readFileSync(path.join(__dirname, '../../js/map.gl/styles/map.composite.js'), 'utf8'), context);
  const style = new context.window.ghi.compositeMap(controller, state, {});
  style.loaded = true;
  style.getPolygonData = () => ({ attachment: { id: 10 }, metric_index: metric });
  style.getFullPieLocations = () => [location()];
  style.updateFeatures = () => [{ id: level }];
  style.updateMarkers = (features) => writes.push({ source: 'markers', features });
  style.addAdminAreaLayers = () => {};
  function flush(queue) {
    const callbacks = [...queue.values()];
    queue.clear();
    callbacks.forEach((callback) => callback());
  }
  return {
    style, state, writes, requests,
    setLevel: (value) => { level = value; },
    setMetric: (value) => { metric = value; },
    pending: () => pending,
    paint: () => flush(frames),
    render: () => flush(timers),
    idle: () => [...idle].forEach((callback) => callback()),
    polygons: () => writes.filter((write) => write.source === style.adminAreaSourceId),
  };
}

test('cached geometry switches both ways without restoring old polygons', () => {
  const fixture = mockMap();
  for (const level of [1, 2, 1, 2]) {
    fixture.setLevel(level);
    fixture.style.renderLocations(null, true);
    assert.equal(fixture.pending(), 1);
    fixture.paint();
    fixture.render();
    const polygon = fixture.polygons().at(-1).features[0];
    assert.equal(polygon.properties.location_id, level);
    assert.equal(polygon.properties.value, 12);
    assert.equal(fixture.pending(), 1, 'spinner remains until Mapbox finishes rendering');
    fixture.idle();
    assert.equal(fixture.pending(), 0);
  }
  assert.equal(fixture.polygons().length, 4, 'one source update per level change');
});

test('tab changes refresh polygon metrics even without full_reload', () => {
  const fixture = mockMap();
  for (const metric of ['need', 'target']) {
    fixture.setMetric(metric);
    fixture.style.renderLocations();
    fixture.paint();
    fixture.render();
    fixture.idle();
  }
  assert.equal(fixture.polygons()[0].features[0].properties.value, 12);
  assert.equal(fixture.polygons()[1].features[0].properties.value, 24);
});

test('rendering yields a paint before rebuilding markers and coalesces rapid changes', () => {
  const fixture = mockMap();
  fixture.style.renderLocations();
  fixture.paint();
  assert.equal(fixture.writes.length, 0);
  fixture.setLevel(2);
  fixture.style.renderLocations();
  fixture.render();
  assert.equal(fixture.writes.length, 0, 'superseded render timer was cancelled');
  fixture.paint();
  fixture.render();
  assert.equal(fixture.polygons().length, 1);
  assert.equal(fixture.polygons()[0].features[0].properties.location_id, 2);
});

test('late geometry from an earlier level cannot overwrite the selected level', () => {
  const fixture = mockMap(false);
  fixture.style.renderLocations();
  fixture.paint();
  fixture.render();
  fixture.setLevel(2);
  fixture.style.renderLocations();
  fixture.paint();
  fixture.render();
  fixture.requests[1]();
  fixture.requests[0]();
  assert.equal(fixture.polygons().length, 1);
  assert.equal(fixture.polygons()[0].features[0].properties.location_id, 2);
  fixture.idle();
  assert.equal(fixture.pending(), 0);
});

test('destroy cancels queued renders and ignores geometry still in flight', () => {
  const fixture = mockMap(false);
  fixture.style.renderLocations();
  fixture.paint();
  fixture.render();
  fixture.style.destroy();
  fixture.requests[0]();
  assert.equal(fixture.polygons().length, 0);
  assert.equal(fixture.pending(), 0);
});

test('donut creation uses feature metrics without sorting the location collection again', () => {
  const fixture = mockMap();
  fixture.state.getLocationById = () => { throw new Error('Repeated full collection lookup'); };
  const properties = { object_id: 1, total: 100, metrics: { 10: { need: 50 } }, radius: 12 };
  fixture.style.createDonutChart = (object, radius) => {
    assert.equal(object, properties);
    assert.equal(radius, 12);
    return 'donut';
  };
  assert.equal(fixture.style.createDonutChartForFeature({ properties }), 'donut');
});

test('overlapping data loads cannot hide a render that is still busy', () => {
  let visible = false;
  const container = {
    length: 1,
    parent() { return this; },
    find() { return this; },
    is: () => visible,
    show: () => { visible = true; },
    hide: () => { visible = false; },
  };
  const context = vm.createContext({ window: {}, jQuery: (element) => element });
  vm.runInContext(fs.readFileSync(path.join(__dirname, '../../js/map.gl/map.throbber.js'), 'utf8'), context);
  const throbber = new context.window.ghi.throbber({ getCanvasContainer: () => container });
  throbber.show();
  throbber.show();
  throbber.hide();
  assert.equal(visible, true);
  throbber.hide();
  assert.equal(visible, false);
});
