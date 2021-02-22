(function($){

    /* formular requests
    --------------------------------------------- */

    /* --- set defaults --- */
    // 1. input mail, 2. input radio
    var myElements = {
        "#casawp_remCat_email" : "casawp_request_per_remcat",
        "#casawp_request_per_mail_fallback_value" : "casawp_request_per_mail_fallback"
    };
    $.each(myElements, function(i, val) {
        if($('[name="' + val + '"]:checked').attr('value') == 0) {
            $(i).prop('readonly', true);
            $(i).prop('disabled', true);
        }
    });

    /* --- set or remove attributes --- */
    $.each(myElements, function(i, val) {
        $('[name="' + val + '"]').on('click', function() {
            if($(this).attr('value') != 0) {
                $(i).removeAttr('readonly');
                $(i).removeAttr('disabled');
            } else {
                 $(i).prop('readonly', true);
                 $(i).prop('disabled', true);
            }
        });
    });

}(jQuery));