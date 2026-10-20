(function ($) {
    'use strict';

    function initialize() {
        try {
            var $settings = $('.casawp-settings');
            if (!$settings.length || !$.fn.casawpSelect2) {
                return;
            }

            $settings.find('#options_form select').each(function () {
                var $select = $(this);
                if ($select.hasClass('select2-hidden-accessible')) {
                    return;
                }

                $select.casawpSelect2({
                    theme: 'casawp',
                    width: '100%',
                    dropdownParent: $settings,
                    language: window.casawpDropdowns ? window.casawpDropdowns.language : 'en',
                    minimumResultsForSearch: 10
                });

                // Preserve each field's accessible name on the replacement control.
                var label = this.getAttribute('aria-label');
                var labelledBy = this.getAttribute('aria-labelledby');
                if (!label && !labelledBy) {
                    var $label = this.labels && this.labels.length ? $(this.labels[0]) : $();
                    label = $label.length ? $label.text().trim() : $select.closest('tr').children('th').text().trim();
                }
                var $control = $select.next('.select2-container').find('[role="combobox"]');
                if (labelledBy) {
                    $control.attr('aria-labelledby', labelledBy);
                } else if (label) {
                    $control.removeAttr('aria-labelledby').attr('aria-label', label);
                }
            });
        } finally {
            if (window.casawpDropdownsReady) {
                window.casawpDropdownsReady();
            }
        }
    }

    // The footer already follows the form; avoid jQuery ready's extra delay.
    if (document.querySelector('.casawp-settings')) {
        initialize();
    } else {
        $(initialize);
    }
})(jQuery);
