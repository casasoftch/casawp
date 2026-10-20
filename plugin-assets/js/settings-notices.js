(function () {
    'use strict';

    function start() {
        if (!document.body.classList.contains('toplevel_page_casawp')) { return; }

        var selector = 'div.notice:not(.casawp-notice), div.updated:not(.casawp-notice), '
            + 'div.error:not(.casawp-notice), div.update-nag:not(.casawp-notice)';

        function hideNotices() {
            document.querySelectorAll(selector).forEach(function (notice) {
                // Inline !important rules outrank the stylesheet. Update only
                // when needed so observing style changes cannot cause a loop.
                if (notice.style.getPropertyValue('display') !== 'none'
                    || notice.style.getPropertyPriority('display') !== 'important') {
                    notice.style.setProperty('display', 'none', 'important');
                }
            });
        }

        hideNotices();
        new MutationObserver(hideNotices).observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['class', 'style']
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
}());
