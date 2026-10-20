/* Loading and failure recovery: node tests/settings-dropdowns.js */
'use strict';
const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const root = path.join(__dirname, '..', 'plugin-assets', 'js');
const preload = fs.readFileSync(path.join(root, 'settings-dropdowns-preload.js'), 'utf8');
const initialize = fs.readFileSync(path.join(root, 'settings-dropdowns.js'), 'utf8');

function page() {
    const classes = new Set();
    const timers = [];
    const context = {
        document: {
            documentElement: {classList: {add: name => classes.add(name), remove: name => classes.delete(name)}},
            querySelector: () => ({})
        },
        window: {setTimeout: callback => timers.push(callback)}
    };
    vm.runInNewContext(preload, context);
    assert(classes.has('casawp-dropdowns-pending'), 'Mask native controls before rendering.');
    return {classes, timers, context};
}

const missingScript = page();
missingScript.timers[0]();
assert(!missingScript.classes.has('casawp-dropdowns-pending'), 'Restore native controls if the footer scripts never load.');

const missingLibrary = page();
missingLibrary.context.jQuery = () => ({length: 1});
missingLibrary.context.jQuery.fn = {};
vm.runInNewContext(initialize, missingLibrary.context);
assert(!missingLibrary.classes.has('casawp-dropdowns-pending'), 'A missing library must reveal native controls immediately.');

const failedInitialization = page();
failedInitialization.context.jQuery = () => ({length: 1, find: () => {throw new Error('Initialization failed');}});
failedInitialization.context.jQuery.fn = {casawpSelect2() {}};
assert.throws(() => vm.runInNewContext(initialize, failedInitialization.context), /Initialization failed/);
assert(!failedInitialization.classes.has('casawp-dropdowns-pending'), 'An initialization error must not leave fields hidden.');

console.log('Dropdown loading, missing-script, missing-library and initialization-failure recovery checks passed.');
