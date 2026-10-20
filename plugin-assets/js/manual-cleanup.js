(function ($) {
    'use strict';
    $(function () {
        var button = $('#casawp-cleanup-button');
        var status = $('#casawp-cleanup-status');
        var progress = $('#casawp-cleanup-progress');
        var deleting = $('#casawp-delete-all-button');
        var pending = false;
        var completionTimer = null;
        var completionDismissed = false;
        var generation = 0;

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
            var active = data.status === 'queued' || data.status === 'running';
            if (active) { completionDismissed = false; }
            button.prop('disabled', pending || active || !data.can_start);
            deleting.attr('aria-disabled', active ? 'true' : 'false').toggleClass('disabled', active);
            progress.toggle(active);
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
            return $.ajax({url: ajaxurl, method: 'POST', data: {action: action, nonce: casawpCleanup.nonce}});
        }

        function errorMessage(xhr) {
            return xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                ? xhr.responseJSON.data.message : casawpCleanup.requestFailed;
        }

        button.on('click', function () {
            pending = true;
            dismissCompletion();
            button.prop('disabled', true);
            request('casawp_start_cleanup').done(function (response) {
                pending = false;
                if (response.success) { render(response.data); }
                else { button.prop('disabled', false); status.text(response.data.message || casawpCleanup.requestFailed).show(); }
            }).fail(function (xhr) {
                pending = false;
                button.prop('disabled', false);
                status.text(errorMessage(xhr)).show();
            });
        });

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
