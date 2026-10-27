(function ($) {
    'use strict';
    $(function () {
        var button = $('#casawp-cleanup-button');
        var resume = $('#casawp-resume-cleanup-button');
        var stop = $('#casawp-stop-cleanup-button');
        var status = $('#casawp-cleanup-status');
        var progress = $('#casawp-cleanup-progress');
        var deleting = $('#casawp-delete-all-button');
        var pending = false;
        var pendingAction = '';
        var initialImport = window.casawpImportInitial || {};
        var importBusy = initialImport.status === 'running' || initialImport.status === 'cleanup';
        var completionTimer = null;
        var completionDeadline = 0;
        var completionRun = 0;
        var completionDismissed = false;
        var generation = 0;
        var polling = false;
        var pollSerial = 0;
        var pollRequest = null;
        var latestRun = 0;
        var publishedState = '';
        var lastData = casawpCleanup.initial;

        function invalidateRequests() {
            generation++;
            pollSerial++;
            polling = false;
            if (pollRequest && pollRequest.abort) { pollRequest.abort(); }
            pollRequest = null;
        }

        function dismissCompletion() {
            completionDismissed = true;
            completionDeadline = 0;
            window.clearTimeout(completionTimer);
            completionTimer = null;
            status.text('').hide();
        }

        function publishState(active) {
            var state = {active: active, pending: pending, status: lastData.status, run_id: lastData.run_id || 0};
            var signature = JSON.stringify(state);
            if (signature !== publishedState) {
                publishedState = signature;
                $(document).trigger('casawp:cleanup-state', [state]);
            }
        }

        function render() {
            var active = lastData.status === 'queued' || lastData.status === 'running' || lastData.status === 'stopping';
            button.prop('disabled', pending || importBusy || active || !lastData.can_start);
            resume.toggle(!!lastData.can_resume).prop('disabled', pending || !lastData.can_resume);
            stop.toggle(!!lastData.can_stop || lastData.status === 'stopping').prop('disabled', pending || !lastData.can_stop);
            var busy = pending || active || importBusy;
            deleting.attr('aria-disabled', busy ? 'true' : 'false').toggleClass('disabled', busy);
            progress.toggle((pending && pendingAction !== 'casawp_stop_cleanup') || (active && !lastData.can_resume));
            window.clearTimeout(completionTimer);
            completionTimer = null;
            if (pending) {
                status.text(pendingAction === 'casawp_stop_cleanup' ? casawpCleanup.stopping : casawpCleanup.queued).show();
            } else if (lastData.status === 'completed') {
                if (completionDeadline > Date.now() && !completionDismissed && !importBusy) {
                    status.text(lastData.message || '').show();
                    completionTimer = window.setTimeout(function () {
                        completionDeadline = 0;
                        completionDismissed = true;
                        render();
                    }, Math.max(0, completionDeadline - Date.now()));
                } else { status.text('').hide(); }
            } else {
                status.text(lastData.message || '').toggle(!!lastData.message);
            }
            publishState(active);
        }

        function acceptStatus(data) {
            if (!data || typeof data.status !== 'string') { return; }
            var run = Number(data.run_id) || 0;
            if (latestRun && run < latestRun) { return; }
            if (run > latestRun) { latestRun = run; completionDismissed = false; }
            if (data.status === 'queued' || data.status === 'running' || data.status === 'stopping') { completionDismissed = false; }
            lastData = data;
            if (data.status === 'completed' && Number(data.notice_seconds) > 0 && !completionDismissed) {
                var deadline = Date.now() + Math.min(600, Number(data.notice_seconds)) * 1000;
                completionDeadline = completionRun === run && completionDeadline ? Math.min(completionDeadline, deadline) : deadline;
                completionRun = run;
            } else { completionDeadline = 0; }
            render();
        }

        function request(action) {
            return $.ajax({url: ajaxurl, method: 'POST', timeout: action === 'casawp_cleanup_status' ? 15000 : 90000,
                data: {action: action, nonce: casawpCleanup.nonce, run_id: lastData.run_id}});
        }

        function errorMessage(xhr) {
            return xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                ? xhr.responseJSON.data.message : casawpCleanup.requestFailed;
        }

        function pollStatus() {
            if (pending || polling) { return; }
            polling = true;
            var requestGeneration = generation;
            var serial = ++pollSerial;
            pollRequest = request('casawp_cleanup_status').done(function (response) {
                if (requestGeneration !== generation || pending) { return; }
                if (response && response.success) { acceptStatus(response.data); }
                else { status.text(errorMessage({responseJSON: response})).show(); }
            }).fail(function (xhr) {
                if (requestGeneration !== generation || pending) { return; }
                status.text(errorMessage(xhr)).show();
            }).always(function () {
                if (serial === pollSerial) { polling = false; pollRequest = null; }
            });
        }

        function submit(action) {
            if (pending || (action === 'casawp_start_cleanup' && (importBusy || !lastData.can_start))) { return; }
            invalidateRequests();
            pending = true;
            pendingAction = action;
            dismissCompletion();
            var requestGeneration = generation;
            $(document).trigger('casawp:cleanup-action', [{action: action}]);
            render();
            request(action).done(function (response) {
                if (requestGeneration !== generation) { return; }
                pending = false;
                pendingAction = '';
                if (response && response.success) { acceptStatus(response.data); }
                else { render(); status.text(errorMessage({responseJSON: response})).show(); }
                pollStatus();
            }).fail(function (xhr) {
                if (requestGeneration !== generation) { return; }
                pending = false;
                pendingAction = '';
                render();
                status.text(errorMessage(xhr)).show();
                pollStatus();
            });
        }

        button.on('click', function () { submit('casawp_start_cleanup'); });
        resume.on('click', function () { submit('casawp_resume_cleanup'); });
        stop.on('click', function () { submit('casawp_stop_cleanup'); });

        $(document).on('casawp:import-action', function () {
            invalidateRequests();
            dismissCompletion();
            importBusy = true;
            render();
        });
        $(document).on('casawp:import-state', function (event, state) {
            var busy = !!(state && (state.active || state.pending));
            if (busy !== importBusy) {
                importBusy = busy;
                render();
                if (!busy) { pollStatus(); }
            }
        });

        acceptStatus(casawpCleanup.initial);
        pollStatus();
        window.setInterval(pollStatus, 5000);
        $(window).on('focus', pollStatus);
        $(document).on('visibilitychange', function () { if (!document.hidden) { pollStatus(); } });
    });
}(jQuery));
