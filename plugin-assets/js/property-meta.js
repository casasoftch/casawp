(function ($) {
    'use strict';

    $(function () {
        var config = window.casawpPropertyMeta || {};

        $('.casawp-property-meta').each(function () {
            var root = $(this);
            var form = root.closest('form');
            var status = root.find('.casawp-meta-status');
            var dirty = false;
            var busy = false;
            var submittedEvent = null;

            root.find('[data-casawp-meta-enable]').prop('disabled', false);

            function snapshot() {
                return $.param(root.find(':input[name^="casawp_meta["]').serializeArray());
            }

            var confirmedValues = snapshot();

            function setStatus(message, isError) {
                status.text(typeof message === 'string' ? message : '')
                    .toggleClass('is-error', !!isError);
            }

            function updateDirty() {
                if (busy) { return; }
                dirty = snapshot() !== confirmedValues;
                submittedEvent = null;
                root.toggleClass('has-unsaved-changes', dirty);
                setStatus(dirty ? config.unsaved : '', false);
            }

            function lockControls() {
                root.attr('aria-busy', busy ? 'true' : 'false');
                root.find('input, select, textarea, button').each(function () {
                    var control = $(this);
                    if (busy) {
                        if (typeof control.data('casawpMetaDisabled') === 'undefined') {
                            control.data('casawpMetaDisabled', control.prop('disabled'));
                        }
                        control.prop('disabled', true);
                    } else if (typeof control.data('casawpMetaDisabled') !== 'undefined') {
                        control.prop('disabled', control.data('casawpMetaDisabled'));
                        control.removeData('casawpMetaDisabled');
                    }
                });
                if (!busy) { root.find('[data-casawp-meta-enable]').prop('disabled', false); }
            }

            function isMetaBoxSave(options) {
                if (!window._wpMetaBoxUrl || !options || String(options.method || '').toUpperCase() !== 'POST' ||
                    !window.FormData || !(options.body instanceof window.FormData)) { return false; }
                try {
                    if (new window.URL(options.url, window.location.href).href !==
                        new window.URL(window._wpMetaBoxUrl, window.location.href).href) { return false; }
                } catch (error) { return false; }
                return String(options.body.get('post_ID')) === root.attr('data-post-id') &&
                    options.body.get('casawp_meta_nonce') === root.find('[name="casawp_meta_nonce"]').val() &&
                    options.body.get('casawp_meta[complete]') === '1';
            }

            function errorMessage(error) {
                if (error && typeof error.message === 'string') { return Promise.resolve(error.message); }
                if (error && typeof error.clone === 'function') {
                    return error.clone().json().then(function (body) {
                        return body && typeof body.message === 'string' ? body.message : config.error;
                    }).catch(function () { return config.error; });
                }
                return Promise.resolve(config.error);
            }

            if (window.wp && wp.apiFetch && typeof wp.apiFetch.use === 'function') {
                wp.apiFetch.use(function (options, next) {
                    if (!isMetaBoxSave(options)) { return next(options); }
                    if (busy) { return Promise.reject(new Error(config.error)); }
                    // WordPress already captured FormData. Locking now cannot omit submitted fields.
                    busy = true;
                    lockControls();
                    var pending;
                    try { pending = next(options); }
                    catch (error) { pending = Promise.reject(error); }

                    return Promise.resolve(pending).then(function (response) {
                        if (response && response.ok === false) { throw response; }
                        if (!response || typeof response.clone !== 'function') { throw new Error(config.error); }
                        return response.clone().text().then(function (html) {
                            var parsed = new window.DOMParser().parseFromString(html, 'text/html');
                            var fresh = $(parsed).find('.casawp-property-meta').filter(function () {
                                return $(this).attr('data-post-id') === root.attr('data-post-id');
                            }).first();
                            if (!fresh.length || !fresh.find('[name="casawp_meta_nonce"]').val() ||
                                !fresh.find('[name="casawp_meta_revision"]').val() ||
                                fresh.find('[name="casawp_meta[complete]"]').val() !== '1') {
                                throw new Error(config.error);
                            }
                            var expanded = root.find('details').map(function () { return this.open; }).get();
                            // Native innerHTML leaves scripts in the parsed response inert.
                            root[0].innerHTML = fresh[0].innerHTML;
                            root.find('[data-casawp-meta-enable]').prop('disabled', false);
                            root.find('details').each(function (index) { this.open = !!expanded[index]; });
                            status = root.find('.casawp-meta-status');
                            confirmedValues = snapshot();
                            dirty = false;
                            submittedEvent = null;
                            root.removeClass('has-unsaved-changes');
                            setStatus('', false);
                            lockControls();
                            return response;
                        });
                    }).catch(function (error) {
                        return errorMessage(error).then(function (message) {
                            setStatus(message, true);
                            throw error;
                        });
                    }).then(function (response) {
                        busy = false;
                        lockControls();
                        return response;
                    }, function (error) {
                        busy = false;
                        lockControls();
                        return Promise.reject(error);
                    });
                });
            }

            root.on('input change', ':input[name^="casawp_meta["]', updateDirty);

            root.on('click', '.casawp-meta-add', function (event) {
                event.preventDefault();
                if (busy) { return; }
                var repeater = $(this).closest('.casawp-meta-repeater');
                var limit = Number(config.maxEntries) || 50;
                if (repeater.find('.casawp-meta-entry').length >= limit) {
                    setStatus(config.limitMessage, true);
                    return;
                }
                var template = repeater.find('template').first();
                if (!template.length) { return; }
                var next = Number(repeater.attr('data-next-index'));
                if (!isFinite(next) || next < 0) { next = 0; }
                repeater.find('.casawp-meta-entry[data-index]').each(function () {
                    var index = Number($(this).attr('data-index'));
                    if (isFinite(index) && index >= next) { next = index + 1; }
                });
                repeater.attr('data-next-index', next + 1);
                // The template is plugin-owned; only its numeric index is substituted.
                var entry = $(template.html().replace(/__INDEX__/g, String(next)));
                var entries = repeater.find('.casawp-meta-entries').first();
                if (entries.length) { entry.appendTo(entries); }
                else { entry.insertBefore(template); }
                entry.find('[data-casawp-meta-enable]').prop('disabled', false);
                entry.find('input:not([type="hidden"]), select, textarea').first().trigger('focus');
                updateDirty();
            });

            root.on('click', '.casawp-meta-remove', function (event) {
                event.preventDefault();
                if (busy) { return; }
                var entry = $(this).closest('.casawp-meta-entry');
                var repeater = entry.closest('.casawp-meta-repeater');
                var next = entry.next('.casawp-meta-entry');
                var previous = entry.prev('.casawp-meta-entry');
                entry.remove();
                var focus = next.find('input:not([type="hidden"]), select, textarea').first();
                if (!focus.length) { focus = previous.find('.casawp-meta-remove').first(); }
                if (!focus.length) { focus = repeater.find('.casawp-meta-add').first(); }
                focus.trigger('focus');
                updateDirty();
            });

            form.on('submit.casawpPropertyMeta', function (event) {
                // Keep all fields enabled so WordPress can serialize the complete metabox.
                submittedEvent = event;
            });

            window.addEventListener('beforeunload', function (event) {
                // Gutenberg manages navigation warnings for its legacy metabox forms.
                if (!dirty || form.attr('id') !== 'post' || !$.contains(document, root[0])) { return; }
                var submission = submittedEvent;
                submittedEvent = null;
                if (submission && !submission.isDefaultPrevented() &&
                    (!submission.originalEvent || !submission.originalEvent.defaultPrevented)) { return; }
                event.preventDefault();
                event.returnValue = '';
            });

            $(window).on('pageshow', function () { submittedEvent = null; });
        });
    });
})(jQuery);
