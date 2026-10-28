(function ($) {
    'use strict';

    $(function () {
        var config = window.casawpPropertyMedia;
        if (!config || !config.ajaxUrl) { return; }

        $('.casawp-property-media').each(function () {
            var root = $(this);
            var content = root.find('.casawp-property-media-content');
            var status = root.find('.casawp-property-media-status');
            var confirmedHtml = content.html();
            var busy = false;
            var choosing = false;
            var dragging = false;
            var alive = true;
            var interrupted = false;
            var serial = 0;
            var request = null;
            var thumbnailRequest = null;
            var thumbnailSerial = 0;
            var mediaFrame = null;

            function isCurrent(token) {
                return alive && token === serial && $.contains(document, root[0]);
            }

            function setStatus(message, isError) {
                status.text(typeof message === 'string' ? message : '')
                    .toggleClass('is-error', !!isError);
            }

            function lockControls() {
                var locked = busy || choosing || dragging;
                root.attr('aria-busy', busy ? 'true' : 'false').toggleClass('is-saving', busy);
                root.find('button, select').each(function () {
                    var control = $(this);
                    // Disabling a dragged button can swallow mouseup before sortable receives it.
                    if (dragging && control.hasClass('casawp-image-drag')) { return; }
                    if (locked) {
                        if (typeof control.data('casawpMediaDisabled') === 'undefined') {
                            control.data('casawpMediaDisabled', control.prop('disabled'));
                        }
                        control.prop('disabled', true);
                    } else if (typeof control.data('casawpMediaDisabled') !== 'undefined') {
                        control.prop('disabled', control.data('casawpMediaDisabled'));
                        control.removeData('casawpMediaDisabled');
                    }
                });
                root.find('.casawp-property-images').each(function () {
                    var list = $(this);
                    // A drag already in progress must finish before sortable is disabled.
                    if (list.hasClass('ui-sortable') && !dragging) {
                        list.sortable('option', 'disabled', busy || choosing);
                    }
                });
            }

            function imageIds() {
                return root.find('.casawp-property-images > [data-id]').map(function () {
                    return $(this).attr('data-id');
                }).get();
            }

            function focusControl(focus) {
                var target;
                if (!focus) { return; }
                if (focus.kind === 'add') {
                    target = root.find('.casawp-property-media-add').first();
                } else {
                    var item = root.find('.casawp-property-images > [data-id]').filter(function () {
                        return $(this).attr('data-id') === String(focus.id);
                    });
                    target = item.find('.casawp-image-' + focus.direction + ':enabled').first();
                    if (!target.length) {
                        target = item.find('.casawp-image-up:enabled, .casawp-image-down:enabled, .casawp-image-drag:enabled').first();
                    }
                }
                if (target && target.length) { target.trigger('focus'); }
            }

            function initialiseSortable() {
                if (!$.fn.sortable) { return; }
                root.find('.casawp-property-images').each(function () {
                    $(this).find('img').attr('draggable', 'false');
                    $(this).sortable({
                        items: '> [data-id]',
                        handle: '.casawp-image-drag, img',
                        cancel: 'a, input, select, textarea, .casawp-image-up, .casawp-image-down',
                        placeholder: 'casawp-image-placeholder',
                        forcePlaceholderSize: true,
                        tolerance: 'pointer',
                        start: function () {
                            dragging = true;
                            lockControls();
                        },
                        update: function () {
                            dragging = false;
                            save('order', imageIds(), null, null);
                        },
                        stop: function () {
                            dragging = false;
                            lockControls();
                        }
                    });
                });
            }

            function replaceContent(html) {
                var selectedType = root.find('.casawp-property-media-type').val();
                if ($.fn.sortable) { root.find('.casawp-property-images.ui-sortable').sortable('destroy'); }
                content.html(html);
                if (selectedType) { root.find('.casawp-property-media-type').val(selectedType); }
                initialiseSortable();
            }

            function messageFrom(response) {
                if (response && response.data && typeof response.data.message === 'string') {
                    return response.data.message;
                }
                if (response && typeof response.data === 'string') { return response.data; }
                return config.error;
            }

            function updateFeaturedImage(data) {
                var id = Number(data.thumbnail_id);
                if (!Object.prototype.hasOwnProperty.call(data, 'thumbnail_id') || !isFinite(id) || id < 0) { return; }
                if (window.wp && wp.data && typeof wp.data.select === 'function' && typeof wp.data.dispatch === 'function') {
                    try {
                        var editor = wp.data.select('core/editor');
                        var editorActions = wp.data.dispatch('core/editor');
                        if (editor && typeof editor.getCurrentPostId === 'function' &&
                            Number(editor.getCurrentPostId()) === Number(root.attr('data-post-id')) &&
                            editorActions && typeof editorActions.editPost === 'function') {
                            editorActions.editPost({featured_media: id});
                        }
                    } catch (error) {
                        // The classic editor may load wp.data without registering the block-editor store.
                    }
                }
                if (window.wp && wp.media && wp.media.featuredImage &&
                    wp.media.view && wp.media.view.settings && wp.media.view.settings.post &&
                    Number(wp.media.view.settings.post.id) === Number(root.attr('data-post-id')) &&
                    $('#postimagediv').length) {
                    var settings = wp.media.view.settings.post;
                    var desired = id || -1;
                    var metabox = $('#postimagediv .inside');
                    var field = metabox.find('#_thumbnail_id');
                    if (Number(wp.media.featuredImage.get()) === desired && field.length && Number(field.val()) === desired) { return; }
                    var token = ++thumbnailSerial;
                    if (thumbnailRequest && thumbnailRequest.abort) { thumbnailRequest.abort(); }
                    // Update the form immediately; an older metabox response must not undo a newer order.
                    settings.featuredImageId = desired;
                    if (!field.length) {
                        field = $('<input>', {type: 'hidden', id: '_thumbnail_id', name: '_thumbnail_id'}).appendTo(metabox);
                    }
                    field.val(desired);
                    if (typeof wp.media.post !== 'function') { return; }
                    thumbnailRequest = wp.media.post('get-post-thumbnail-html', {
                        post_id: settings.id,
                        thumbnail_id: desired,
                        _wpnonce: settings.nonce
                    }).done(function (html) {
                        if (alive && token === thumbnailSerial && $.contains(document, root[0]) &&
                            Number(wp.media.featuredImage.get()) === desired && typeof html === 'string' && html !== '0') {
                            metabox.html(html);
                            metabox.find('#_thumbnail_id').val(desired);
                        }
                    }).always(function () {
                        if (token === thumbnailSerial) { thumbnailRequest = null; }
                    });
                }
            }

            function save(operation, ids, type, focus) {
                if (busy || choosing || dragging || !alive || !ids.length) { return; }
                busy = true;
                var token = ++serial;
                setStatus(config.saving, false);
                lockControls();
                var data = {
                    action: 'casawp_property_media',
                    nonce: root.attr('data-nonce'),
                    post_id: root.attr('data-post-id'),
                    operation: operation,
                    ids: ids
                };
                if (type) { data.type = type; }

                function failed(response) {
                    if (response && response.data && typeof response.data.html === 'string') {
                        confirmedHtml = response.data.html;
                        updateFeaturedImage(response.data);
                    }
                    replaceContent(confirmedHtml);
                    setStatus(messageFrom(response), true);
                }

                request = $.ajax({url: config.ajaxUrl, method: 'POST', dataType: 'json', data: data, timeout: 60000})
                    .done(function (response) {
                        if (!isCurrent(token)) { return; }
                        if (!response || !response.success || !response.data || typeof response.data.html !== 'string') {
                            failed(response);
                            return;
                        }
                        confirmedHtml = response.data.html;
                        replaceContent(confirmedHtml);
                        setStatus(response.data.message || config.saved, false);
                        updateFeaturedImage(response.data);
                    })
                    .fail(function (xhr) {
                        if (isCurrent(token)) { failed(xhr.responseJSON); }
                    })
                    .always(function () {
                        if (!isCurrent(token)) { return; }
                        request = null;
                        busy = false;
                        lockControls();
                        focusControl(focus);
                    });
            }

            root.on('click', '.casawp-image-drag', function (event) {
                event.preventDefault();
            });

            root.on('click', '.casawp-image-up, .casawp-image-down', function (event) {
                event.preventDefault();
                if (busy || choosing || dragging) { return; }
                var button = $(this);
                var item = button.closest('[data-id]');
                var direction = button.hasClass('casawp-image-up') ? 'up' : 'down';
                var neighbour = direction === 'up' ? item.prev('[data-id]') : item.next('[data-id]');
                if (!neighbour.length) { return; }
                if (direction === 'up') { item.insertBefore(neighbour); }
                else { item.insertAfter(neighbour); }
                save('order', imageIds(), null, {id: item.attr('data-id'), direction: direction});
            });

            root.on('click', '.casawp-property-media-add', function (event) {
                event.preventDefault();
                if (busy || choosing || dragging || !window.wp || !wp.media) { return; }
                var type = root.find('.casawp-property-media-type').val();
                if (!type) { return; }
                var options = {title: config.addTitle, button: {text: config.addButton}, multiple: true};
                if (type === 'image' || type === 'offer-logo') { options.library = {type: 'image'}; }
                // Each opening starts with an empty selection; other media workflows keep their own state.
                var frame = wp.media(options);
                mediaFrame = frame;
                choosing = true;
                lockControls();
                function keepUploadsUnattached() {
                    var uploader = frame.uploader && frame.uploader.uploader;
                    if (uploader && typeof uploader.param === 'function') {
                        // Only the verified Attach request may associate uploads with this property.
                        uploader.param('post_id', 0);
                    }
                }
                frame.on('uploader:ready', keepUploadsUnattached);
                frame.on('select', function () {
                    if (!alive || !$.contains(document, root[0])) { return; }
                    var ids = frame.state().get('selection').map(function (attachment) { return attachment.id; });
                    choosing = false;
                    lockControls();
                    save('attach', ids, type, {kind: 'add'});
                });
                frame.on('close', function () {
                    choosing = false;
                    lockControls();
                    // The select event follows close in WordPress; remove the frame afterwards.
                    window.setTimeout(function () {
                        frame.remove();
                        if (mediaFrame === frame) { mediaFrame = null; }
                    }, 0);
                });
                frame.open();
                keepUploadsUnattached();
            });

            $(window).on('pagehide', function () {
                interrupted = busy;
                alive = false;
                serial++;
                thumbnailSerial++;
                if (request && request.abort) { request.abort(); }
                if (thumbnailRequest && thumbnailRequest.abort) { thumbnailRequest.abort(); }
                if (mediaFrame) { mediaFrame.close(); }
            }).on('pageshow', function () {
                if (alive) { return; }
                alive = true;
                request = null;
                busy = false;
                choosing = false;
                dragging = false;
                replaceContent(confirmedHtml);
                lockControls();
                if (interrupted) { setStatus(config.error, true); }
            });

            initialiseSortable();
        });
    });
})(jQuery);
