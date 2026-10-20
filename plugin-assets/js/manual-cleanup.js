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
        var completionTimer = null;
        var completionDismissed = false;
        var generation = 0;
        var lastData = casawpCleanup.initial;

        function dismissCompletion() {
            generation++;
            completionDismissed = true;
            window.clearTimeout(completionTimer);
            completionTimer = null;
            status.text('').hide();
        }

        $('#casawp-import-button').on('click', dismissCompletion);
        $('#casawp-single-import-button').on('click', dismissCompletion);

        function render(data) {
            lastData = data;
            var active = data.status === 'queued' || data.status === 'running' || data.status === 'stopping';
            if (active) { completionDismissed = false; }
            button.prop('disabled', pending || active || !data.can_start);
            resume.toggle(!!data.can_resume).prop('disabled', pending || !data.can_resume);
            stop.toggle(!!data.can_stop || data.status === 'stopping').prop('disabled', pending || !data.can_stop);
            deleting.attr('aria-disabled', active ? 'true' : 'false').toggleClass('disabled', active);
            progress.toggle(active && !data.can_resume);
            window.clearTimeout(completionTimer);
            completionTimer = null;
            if (data.status === 'completed') {
                if (data.notice_seconds > 0 && !completionDismissed) {
                    status.text(data.message || '').show();
                    completionTimer = window.setTimeout(function () {
                        status.text('').hide();
                        completionTimer = null;
                    }, data.notice_seconds * 1000);
                } else { status.text('').hide(); }
            } else {
                status.text(data.message || '').toggle(!!data.message);
            }
        }

        function request(action) {
            return $.ajax({url: ajaxurl, method: 'POST', data: {action: action, nonce: casawpCleanup.nonce, run_id: lastData.run_id}});
        }

        function errorMessage(xhr) {
            return xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                ? xhr.responseJSON.data.message : casawpCleanup.requestFailed;
        }

        function submit(action) {
            if (pending) { return; }
            pending = true;
            dismissCompletion();
            button.prop('disabled', true);
            resume.prop('disabled', true);
            stop.prop('disabled', true);
            request(action).done(function (response) {
                pending = false;
                if (response.success) { render(response.data); }
                else { render(lastData); status.text(response.data.message || casawpCleanup.requestFailed).show(); }
            }).fail(function (xhr) {
                pending = false;
                render(lastData);
                status.text(errorMessage(xhr)).show();
            });
        }

        button.on('click', function () { submit('casawp_start_cleanup'); });
        resume.on('click', function () { submit('casawp_resume_cleanup'); });
        stop.on('click', function () { submit('casawp_stop_cleanup'); });

        render(casawpCleanup.initial);
        var polling = false;
        window.setInterval(function () {
            if (pending || polling) { return; }
            polling = true;
            var requestGeneration = generation;
            request('casawp_cleanup_status').done(function (response) {
                if (response.success && requestGeneration === generation) { render(response.data); }
            }).fail(function (xhr) {
                status.text(errorMessage(xhr)).show();
            }).always(function () { polling = false; });
        }, 5000);
    });
}(jQuery));
