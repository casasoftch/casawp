jQuery.noConflict();
jQuery(document).ready(function($) {

    //archive jstorage persistance
    if (window.casawpParams) {
        $.jStorage.set('casawpParams', window.casawpParams);
    }

    $('#casawpContactAnchor').click(function(event){
        event.preventDefault();

        $('html, body').animate({
            scrollTop: $( $.attr(this, 'href') ).parent().offset().top - 100
        }, 500);

        $('.casawp-contactform-form').parent().addClass('casawp-highlight');
        $('.casawp-contactform-form').delay(500).find(' input[name="firstname"]').focus();

        return false;
    });

    if (window.casawpOptionParams && window.casawpOptionParams.featherlight == 1) {
        if($('.property-image-gallery').length){
            $('.property-image-gallery').featherlightGallery();
        }
    }

    if (window.casawpOptionParams && window.casawpOptionParams.chosen == 1) {
        $('.chosen-select').chosen({width:"100%"});
    }

    var casawpParams = $.jStorage.get('casawpParams', false);
    if (casawpParams && $('.casawp-single-pagination').length) {
        var post_id = $('.casawp-single-pagination').data('post');
        $('.casawp-single-archivelink').prop('href', casawpParams.archive_link);
        var query = casawpParams;
        delete query.archive_link;
        if (post_id) {
            $.ajax({
                type: 'GET',
                url: '',
                data: {
                    'ajax' : 'prevnext',
                    'base_id' : post_id,
                    'query' : casawpParams
                },
                success: function (json) {
                    if (json.nextlink !== 'no') {
                        $('.casawp-single-next').prop('href', json.nextlink);    
                    } else {
                        $('.casawp-single-next').addClass('disabled');
                    }
                    if (json.prevlink !== 'no') {
                        $('.casawp-single-prev').prop('href', json.prevlink);
                    } else {
                        $('.casawp-single-prev').addClass('disabled');
                    }
                    $('.casawp-single-pagination').css('display','none').removeClass('hidden').show('fast');

                    $('.casawp-single').trigger("casawp-pagination-update");
                }
            });
        }
    }

    //google maps
    function initializeMap($mapwraper) {
        var location = new google.maps.LatLng($mapwraper.data('lat'),$mapwraper.data('lng'));
        var mapOptions = {
          zoom: parseInt(window.casawpOptionParams.google_maps_zoomlevel),
          mapTypeId: google.maps.MapTypeId.ROADMAP,
          center: location
        };
        $mapwraper.show();
        map = new google.maps.Map(document.getElementById('map-canvas'), mapOptions);

        var marker = new google.maps.Marker({
            map: map,
            position: location
        });
    }
    if (window.casawpOptionParams && window.casawpOptionParams.google_maps == 1) {
        if ($('.casawp-map').length && google) {
            $('.casawp-map').each(function(){
                var $mapwraper = $(this);
                if ($mapwraper.data('lat') && $mapwraper.data('lng')) {
                    var map;
                    initializeMap($mapwraper);
                }
            });
        }
    }

    /*gallery thumbnails*/
    function setThumbnailColumns() {
        var that = '.casawp-gallery-thumbnails';
        var attachments = $(that).find('a');
        var width = $(that).width();
        var idealThumbnailWidth = window.casawpOptionParams.thumbnails_ideal_width;

        if ( width ) {
            var columns = Math.min( Math.round( width / idealThumbnailWidth ), 8 ) || 1;
            var imageSize = width / columns;
            $( attachments ).each(function( index ) {
                $(this).attr( 'data-col', columns );
            });
        }
        if (typeof ticker === 'undefined') {
            var windowWidth = $(window).width();
            var windowHeight = $(window).height();
            ticker = setInterval(function () {
                if ((width != $(window).width()) || (height != $(window).height())) {
                    windowWidth = $(window).width();
                    windowHeight = $(window).height();
                    setThumbnailColumns();
                }
            }, 300);
        }
    }
    if($('.casawp-gallery-thumbnails').length && window.casawpOptionParams){
        setThumbnailColumns();
    }
   
    // remove attr multiple (safari bug)
    var userAgent = window.navigator.userAgent;
    if (userAgent.match(/iPad/i) || userAgent.match(/iPhone/i)) {
        selector = '.casawp_multiselect';
        $(selector).removeAttr('multiple');
        $(selector).each(function( index ) {
            var hasSelectedItem = $(this).find(':selected');
            if(hasSelectedItem.length == 0) {
                var placeholder = $(this).attr('data-placeholder');
                $(this).append('<option value="" selected disabled style="display:none;">' + placeholder + '</option>');
            }
        });
    }
});