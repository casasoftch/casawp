/* Settings UI state checks without WordPress: node tests/import-progress.js */
'use strict';
const assert = require('assert');
const fs = require('fs');
const vm = require('vm');
const path = require('path');

function environment() {
    const elements = {}, intervals = [], requests = [], timers = new Map();
    let timerId = 0;
    function $(selector) {
        if (typeof selector === 'function') { selector($); return; }
        if (typeof selector === 'object') { return selector; }
        if (!elements[selector]) {
            const element = {attributes: {}, visible: false, handlers: {}};
            element.show = function () { this.visible = true; return this; };
            element.hide = function () { this.visible = false; return this; };
            element.toggle = function (visible) { this.visible = visible; return this; };
            element.attr = element.prop = element.css = function (key, value) { this.attributes[key] = value; return this; };
            element.removeAttr = function (key) { delete this.attributes[key]; return this; };
            element.text = function (value) { this.content = value; return this; };
            element.val = function (value) { this.attributes.value = value; return this; };
            element.toggleClass = function () { return this; };
            element.on = function (event, callback) { this.handlers[event] = callback; return this; };
            elements[selector] = element;
        }
        return elements[selector];
    }
    $.ajax = function (options) {
        requests.push(options);
        const chain = {};
        ['done', 'fail', 'always'].forEach(method => { chain[method] = callback => { options[method] = callback; return chain; }; });
        return chain;
    };
    const context = {jQuery: $, ajaxurl: '/ajax', console, alert() {}, confirm() { return true; },
        casawpImportI18n: {statusError: 'Retrying'},
        setInterval(callback) { intervals.push(callback); },
        setTimeout(callback, delay) { timers.set(++timerId, {callback, delay}); return timerId; },
        clearTimeout(id) { timers.delete(id); },
        casawpCleanup: {nonce: 'nonce', initial: {status: 'idle', can_start: true}}};
    context.window = context;
    return {$, context, intervals, requests, timers,
        finishTimers() { const pending = Array.from(timers.values()); timers.clear(); pending.forEach(timer => timer.callback()); }};
}

const root = path.dirname(__dirname);
const options = fs.readFileSync(path.join(root, 'options.php'), 'utf8');
const start = options.indexOf('jQuery(function($) {', options.indexOf('var casawpImportI18n'));
const script = options.slice(start, options.indexOf('</script>', start));
const test = environment();
vm.runInNewContext(script, test.context);
function reply(data, target = test) {
    const request = target.requests.filter(r => r.data.action === 'casawp_get_import_progress').pop();
    request.success({success: true, data});
    request.complete();
}
reply({status: 'cleanup', progress: 99, message: 'Database attachments'});
assert.strictEqual(test.$('#casawp-progress-percent').content, '99%');
assert.strictEqual(test.$('#casawp-import-cleanup-progress').visible, true);
assert.strictEqual(test.$('#casawp-import-button').attributes.disabled, true);
assert.strictEqual(test.$('#casawp-progress-bar-container').visible, true);
test.intervals[0]();
reply({status: 'completed', progress: 100, message: 'Finished', notice_seconds: 600});
assert.strictEqual(test.$('#casawp-progress-percent').content, '100%');
assert.strictEqual(test.$('#casawp-import-cleanup-progress').visible, false);
assert.strictEqual(test.$('#casawp-progress-bar-container').visible, false, 'The import bar disappears on completion.');
assert.strictEqual(test.$('#casawp-import-status').content, 'Finished');
test.intervals[0]();
reply({status: 'completed', progress: 100, message: 'Finished', notice_seconds: 595});
assert.strictEqual(test.timers.size, 1, 'Repeated status polling must not extend the completion notice.');
assert.strictEqual(Array.from(test.timers.values())[0].delay, 595000);
test.finishTimers();
assert.strictEqual(test.$('#casawp-progress-wrapper').visible, false);
assert.strictEqual(test.$('#casawp-import-status').content, '');
test.intervals[0]();
reply({status: 'completed', progress: 100, message: 'Finished', notice_seconds: 0});
assert.strictEqual(test.$('#casawp-progress-wrapper').visible, false, 'Polling must not resurrect dismissed results.');
assert.strictEqual(test.$('#casawp-import-button').attributes.disabled, false);
assert(!test.requests.some(r => r.data.action === 'casawp_reset_import_progress'), 'Polling must not erase progress belonging to a newer import.');
test.intervals[0]();
reply({status: 'running', progress: 0, message: 'Starting'});
assert.strictEqual(test.$('#casawp-progress-wrapper').visible, true);
assert.strictEqual(test.$('#casawp-import-button').attributes.disabled, true);
test.intervals[0]();
const failed = test.requests.filter(r => r.data.action === 'casawp_get_import_progress').pop();
failed.error(); failed.complete();
assert.strictEqual(test.$('#casawp-import-button').attributes.disabled, true, 'A polling failure must preserve the active state.');
test.intervals[0]();
reply({status: 'cleanup', progress: 99, message: 'Files'});
assert.strictEqual(test.$('#casawp-import-cleanup-progress').visible, true, 'Polling must recover after an error.');
const reopened = environment();
vm.runInNewContext(script, reopened.context);
reply({status: 'completed', progress: 100, message: 'Old completion'}, reopened);
assert.strictEqual(reopened.$('#casawp-progress-wrapper').visible, false, 'Reopening settings must not show an old completed import.');
assert.strictEqual(reopened.timers.size, 0);
reopened.intervals[0]();
reply({status: 'completed', progress: 100, message: 'Recent completion', notice_seconds: 540}, reopened);
assert.strictEqual(reopened.$('#casawp-progress-wrapper').visible, true, 'A recent result remains visible after reopening settings.');
assert.strictEqual(Array.from(reopened.timers.values())[0].delay, 540000);
reopened.$('#casawp-cleanup-button').handlers.click();
assert.strictEqual(reopened.$('#casawp-progress-wrapper').visible, false, 'Starting manual cleanup immediately hides the previous import result.');
assert.strictEqual(reopened.timers.size, 0);
reopened.intervals[0]();
reply({status: 'completed', progress: 100, message: 'Previous import', notice_seconds: 535}, reopened);
assert.strictEqual(reopened.$('#casawp-progress-wrapper').visible, false, 'Polling cannot restore the import completion message during manual cleanup.');
reopened.intervals[0]();
reply({status: 'running', progress: 0, message: 'New run'}, reopened);
assert.strictEqual(reopened.timers.size, 0, 'Starting another import clears the previous completion timer.');
assert.strictEqual(reopened.$('#casawp-import-status').content, 'New run');

const manual = environment();
vm.runInNewContext(fs.readFileSync(path.join(root, 'plugin-assets/js/manual-cleanup.js'), 'utf8'), manual.context);
function manualReply(status, noticeSeconds = 0) {
    manual.intervals[0]();
    const request = manual.requests[manual.requests.length - 1];
    request.done({success: true, data: {status, can_start: status === 'completed', message: status, notice_seconds: noticeSeconds}});
    request.always();
}
manualReply('running');
assert.strictEqual(manual.$('#casawp-cleanup-progress').visible, true);
assert.strictEqual(manual.$('#casawp-cleanup-progress').attributes.value, undefined, 'Active cleanup has no invented percentage.');
assert.strictEqual(manual.$('#casawp-cleanup-button').attributes.disabled, true);
manualReply('completed', 600);
assert.strictEqual(manual.$('#casawp-cleanup-progress').visible, false);
assert.strictEqual(manual.$('#casawp-cleanup-status').visible, true);
assert.strictEqual(manual.$('#casawp-cleanup-button').attributes.disabled, false);
manualReply('completed', 595);
assert.strictEqual(manual.timers.size, 1);
assert.strictEqual(Array.from(manual.timers.values())[0].delay, 595000);
manual.finishTimers();
assert.strictEqual(manual.$('#casawp-cleanup-status').visible, false);
assert.strictEqual(manual.$('#casawp-cleanup-status').content, '');
manualReply('completed');
assert.strictEqual(manual.$('#casawp-cleanup-status').visible, false, 'Polling must not restore a dismissed manual result.');
manualReply('running');
assert.strictEqual(manual.$('#casawp-cleanup-progress').attributes.value, undefined, 'A new cleanup resets the completed bar.');
manualReply('failed');
assert.strictEqual(manual.$('#casawp-cleanup-progress').visible, false);
const reopenedManual = environment();
reopenedManual.context.casawpCleanup.initial = {status: 'completed', can_start: true, message: 'Old completion'};
vm.runInNewContext(fs.readFileSync(path.join(root, 'plugin-assets/js/manual-cleanup.js'), 'utf8'), reopenedManual.context);
assert.strictEqual(reopenedManual.$('#casawp-cleanup-progress').visible, false);
assert.strictEqual(reopenedManual.$('#casawp-cleanup-status').visible, false, 'Initial manual status must not show an old completion.');
const recentManual = environment();
recentManual.context.casawpCleanup.initial = {status: 'completed', can_start: true, message: 'Recent completion', notice_seconds: 540};
vm.runInNewContext(fs.readFileSync(path.join(root, 'plugin-assets/js/manual-cleanup.js'), 'utf8'), recentManual.context);
assert.strictEqual(recentManual.$('#casawp-cleanup-status').visible, true);
assert.strictEqual(recentManual.$('#casawp-cleanup-progress').visible, false);
assert.strictEqual(Array.from(recentManual.timers.values())[0].delay, 540000);
recentManual.$('#casawp-import-button').handlers.click();
assert.strictEqual(recentManual.$('#casawp-cleanup-status').visible, false, 'Starting an import must immediately hide the manual completion notice.');
assert.strictEqual(recentManual.timers.size, 0);
recentManual.intervals[0]();
let staleNotice = recentManual.requests[recentManual.requests.length - 1];
staleNotice.done({success: true, data: {status: 'completed', can_start: false, message: 'Previous result', notice_seconds: 530}});
staleNotice.always();
assert.strictEqual(recentManual.$('#casawp-cleanup-status').visible, false, 'An in-flight status response must not restore a dismissed notice.');
recentManual.intervals[0]();
let nextCleanup = recentManual.requests[recentManual.requests.length - 1];
nextCleanup.done({success: true, data: {status: 'queued', can_start: false, message: 'New cleanup'}});
nextCleanup.always();
recentManual.intervals[0]();
nextCleanup = recentManual.requests[recentManual.requests.length - 1];
nextCleanup.done({success: true, data: {status: 'completed', can_start: true, message: 'New result', notice_seconds: 600}});
nextCleanup.always();
assert.strictEqual(recentManual.$('#casawp-cleanup-status').visible, true, 'A new manual cleanup can show its own result.');
recentManual.intervals[0]();
const oldManualPoll = recentManual.requests[recentManual.requests.length - 1];
recentManual.$('#casawp-cleanup-button').handlers.click();
oldManualPoll.done({success: true, data: {status: 'completed', can_start: true, message: 'Old completion', notice_seconds: 530}});
oldManualPoll.always();
assert.strictEqual(recentManual.$('#casawp-cleanup-status').visible, false, 'Starting cleanup clears the previous result immediately.');
assert.strictEqual(recentManual.timers.size, 0);

const recovery = environment();
recovery.context.casawpCleanup.initial = {status: 'running', run_id: 123, can_start: false, can_resume: true, can_stop: true, message: 'Stalled'};
vm.runInNewContext(fs.readFileSync(path.join(root, 'plugin-assets/js/manual-cleanup.js'), 'utf8'), recovery.context);
assert.strictEqual(recovery.$('#casawp-resume-cleanup-button').visible, true);
assert.strictEqual(recovery.$('#casawp-stop-cleanup-button').visible, true);
assert.strictEqual(recovery.$('#casawp-cleanup-progress').visible, false, 'A stalled cleanup must not display an activity animation.');
recovery.intervals[0]();
const oldRecoveryPoll = recovery.requests[recovery.requests.length - 1];
recovery.$('#casawp-resume-cleanup-button').handlers.click();
let recoveryRequest = recovery.requests[recovery.requests.length - 1];
assert.strictEqual(recoveryRequest.data.action, 'casawp_resume_cleanup');
assert.strictEqual(recoveryRequest.data.run_id, 123, 'Recovery controls must target the run displayed in the settings tab.');
assert.strictEqual(recovery.$('#casawp-resume-cleanup-button').attributes.disabled, true);
const resumeRequests = recovery.requests.length;
recovery.$('#casawp-resume-cleanup-button').handlers.click();
assert.strictEqual(recovery.requests.length, resumeRequests, 'Double clicks must not send duplicate recovery requests.');
recoveryRequest.done({success: true, data: {status: 'queued', run_id: 123, can_start: false, can_resume: false, can_stop: true}});
oldRecoveryPoll.done({success: true, data: {status: 'running', run_id: 123, can_resume: true, can_stop: true}});
oldRecoveryPoll.always();
assert.strictEqual(recovery.$('#casawp-resume-cleanup-button').visible, false, 'An old poll must not restore the resume button after recovery.');
assert.strictEqual(recovery.$('#casawp-cleanup-progress').visible, true);
recovery.$('#casawp-stop-cleanup-button').handlers.click();
recoveryRequest = recovery.requests[recovery.requests.length - 1];
assert.strictEqual(recoveryRequest.data.action, 'casawp_stop_cleanup');
recoveryRequest.fail({});
assert.strictEqual(recovery.$('#casawp-stop-cleanup-button').attributes.disabled, false, 'A failed stop request must be retryable.');
recovery.$('#casawp-stop-cleanup-button').handlers.click();
recoveryRequest = recovery.requests[recovery.requests.length - 1];
recoveryRequest.done({success: true, data: {status: 'stopping', run_id: 123, can_start: false, can_resume: false, can_stop: false, message: 'Stopping'}});
assert.strictEqual(recovery.$('#casawp-stop-cleanup-button').visible, true);
assert.strictEqual(recovery.$('#casawp-stop-cleanup-button').attributes.disabled, true);
assert.strictEqual(recovery.$('#casawp-cleanup-button').attributes.disabled, true, 'A new cleanup must wait for the stopped worker to exit.');
recovery.intervals[0]();
recoveryRequest = recovery.requests[recovery.requests.length - 1];
recoveryRequest.done({success: true, data: {status: 'stopped', can_start: true, can_resume: false, can_stop: false, message: 'Stopped'}});
recoveryRequest.always();
assert.strictEqual(recovery.$('#casawp-cleanup-progress').visible, false);
assert.strictEqual(recovery.$('#casawp-stop-cleanup-button').visible, false);
assert.strictEqual(recovery.$('#casawp-cleanup-button').attributes.disabled, false);
assert.strictEqual(recovery.$('#casawp-cleanup-status').content, 'Stopped');
console.log('Import and manual cleanup UI checks passed.');
