/* Standalone settings interaction checks: node tests/settings-controls.js */
'use strict';
const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const source = fs.readFileSync(path.join(__dirname, '..', 'options.php'), 'utf8');

const elements = {};
['ckCDN', 'ckRef', 'ckTrans', 'ckForceImportContent'].forEach(id => {
    elements[id] = {checked: false, disabled: false};
});
elements['force-import-content-wrap'] = {style: {display: ''}};
const dependencies = {document: {getElementById(id) { return elements[id]; }}};
const start = source.indexOf('function ckChange() {');
assert(start !== -1, 'The import option dependency handler must exist.');
vm.runInNewContext(source.slice(start, source.indexOf('window.onload =', start)), dependencies);
dependencies.ckChange();
assert.strictEqual(elements.ckCDN.disabled, false);
assert.strictEqual(elements.ckRef.disabled, false);
assert.strictEqual(elements['force-import-content-wrap'].style.display, 'none');
elements.ckCDN.checked = true;
dependencies.ckChange();
assert.strictEqual(elements.ckRef.disabled, true, 'CDN must prevent reference-image limiting.');
elements.ckCDN.checked = false;
elements.ckRef.checked = true;
dependencies.ckChange();
assert.strictEqual(elements.ckCDN.disabled, true, 'Reference-image limiting must prevent CDN.');
elements.ckRef.checked = false;
elements.ckTrans.checked = true;
dependencies.ckChange();
assert.strictEqual(elements['force-import-content-wrap'].style.display, '');
elements.ckForceImportContent.checked = true;
elements.ckTrans.checked = false;
dependencies.ckChange();
assert.strictEqual(elements['force-import-content-wrap'].style.display, 'none');
assert.strictEqual(elements.ckForceImportContent.checked, false, 'Disabling dynamic translations clears its dependent override.');
assert.strictEqual(elements.ckCDN.disabled, false);
assert.strictEqual(elements.ckRef.disabled, false);

const items = ['street', 'location', 'rooms'].map(id => ({id, order: '99'}));
let sortable;
function wrap(item) {
    return {find(selector) {
        assert.strictEqual(selector, '.small-text');
        return {val(value) { item.order = value; }};
    }};
}
const list = {
    children() { return {each(callback) { items.forEach((item, index) => callback(index, item)); }}; },
    sortable(argument) {
        if (argument === 'toArray') { return items.map(item => item.id); }
        sortable = argument;
    }
};
function $(target) {
    if (typeof target === 'object') {
        return target.id ? wrap(target) : {ready(callback) { callback($); }};
    }
    return target === '#draggableList' ? list : wrap(items.find(item => '#' + item.id === target));
}
const sortingStart = source.indexOf('jQuery(document).ready(function($)');
assert(sortingStart !== -1, 'The archive sorting handler must exist.');
vm.runInNewContext(source.slice(sortingStart, source.indexOf('</script>', sortingStart)), {jQuery: $, document: {}});
assert.deepStrictEqual(items.map(item => item.order), [0, 1, 2], 'Initial order inputs must match the displayed order.');
items.unshift(items.pop());
sortable.stop();
assert.deepStrictEqual(items.map(item => item.id), ['rooms', 'street', 'location']);
assert.deepStrictEqual(items.map(item => item.order), [0, 1, 2], 'Dragging fields must update their submitted order inputs.');
console.log('Settings dependency and archive drag-order checks passed.');
