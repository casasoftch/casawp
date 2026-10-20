/* Notice fallback checks: node tests/settings-notices.js */
'use strict';
const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const source = fs.readFileSync(path.join(__dirname, '..', 'plugin-assets/js/settings-notices.js'), 'utf8');
function notice(own = false) {
    let display = 'block', priority = 'important', writes = 0;
    return {own, style: {
        getPropertyValue() { return display; },
        getPropertyPriority() { return priority; },
        setProperty(key, value, importance) { assert.strictEqual(key, 'display'); display = value; priority = importance; writes++; }
    }, get writes() { return writes; }};
}
function environment(settingsPage = true, readyState = 'complete') {
    const notices = [notice(), notice(true)];
    let observer, ready, observed;
    const body = {classList: {contains(value) { assert.strictEqual(value, 'toplevel_page_casawp'); return settingsPage; }}};
    const document = {body, readyState,
        addEventListener(event, callback) { assert.strictEqual(event, 'DOMContentLoaded'); ready = callback; },
        querySelectorAll(selector) {
            ['notice', 'updated', 'error', 'update-nag'].forEach(name => assert(selector.includes('div.' + name + ':not(.casawp-notice)')));
            return notices.filter(item => !item.own);
        }};
    vm.runInNewContext(source, {document, MutationObserver: function (callback) {
        observer = callback;
        this.observe = (target, options) => { assert.strictEqual(target, body); observed = options; };
    }});
    return {notices, runReady() { ready(); }, notify() { observer(); }, get observed() { return observed; }};
}
const current = environment();
assert.strictEqual(current.notices[0].style.getPropertyValue('display'), 'none', 'Inline !important cannot keep an external notice visible.');
assert.strictEqual(current.notices[1].style.getPropertyValue('display'), 'block', 'CASAWP notices must stay visible.');
assert.strictEqual(current.observed.subtree, true);
assert.strictEqual(current.observed.childList, true);
assert.strictEqual(current.observed.attributes, true);
assert.deepStrictEqual(Array.from(current.observed.attributeFilter), ['class', 'style']);
const writes = current.notices[0].writes;
current.notify();
assert.strictEqual(current.notices[0].writes, writes, 'Unchanged display styles must not trigger an observer loop.');
current.notices[0].style.setProperty('display', 'block', 'important');
current.notify();
assert.strictEqual(current.notices[0].style.getPropertyValue('display'), 'none', 'Plugins cannot restore an existing banner with inline !important.');
current.notices.push(notice());
current.notify();
assert.strictEqual(current.notices[2].style.getPropertyValue('display'), 'none', 'Late notices must also be hidden.');
const other = environment(false);
assert.strictEqual(other.observed, undefined);
assert.strictEqual(other.notices[0].style.getPropertyValue('display'), 'block', 'Other admin pages must not be changed.');
const deferred = environment(true, 'loading');
assert.strictEqual(deferred.observed, undefined);
deferred.runReady();
assert.strictEqual(deferred.notices[0].style.getPropertyValue('display'), 'none');
console.log('Inline-important notices, late notices, CASAWP exceptions, observer loops and page scoping checks passed.');
