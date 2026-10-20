(function () {
    'use strict';

    // Run in the head, before the settings fields can be painted.
    document.documentElement.classList.add('casawp-dropdowns-pending');
    window.casawpDropdownsReady = function () {
        document.documentElement.classList.remove('casawp-dropdowns-pending');
    };
    // A missing or failed enhancement must never leave native fields hidden.
    window.setTimeout(window.casawpDropdownsReady, 2000);
})();
