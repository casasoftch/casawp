
jQuery.noConflict();
jQuery(function($) {

    //archive jstorage persistance
    if (window.casawpParams) {
        $.jStorage.set('casawpParams', window.casawpParams);
    }

    $('#casawpContactAnchor').on('click', function(event){
        event.preventDefault();

        $('html, body').animate({
            scrollTop: $( $.attr(this, 'href') ).parent().offset().top - 100
        }, 500);

        $('.casawp-contactform-form').parent().addClass('casawp-highlight');
        $('.casawp-contactform-form').delay(500).find(' input[name="firstname"]').focus();

        //scrollToAnchor($(this).attr('href').split('#')[1]);
        return false;
    });

    
    if (window.casawpOptionParams && window.casawpOptionParams.featherlight == 1) {
        if($('.property-image-gallery').length){
            $('.property-image-gallery').featherlightGallery();
        }
    }
    /*if (window.casawpOptionParams && window.casawpOptionParams.fancybox == 1) {
        if($('.casawp-fancybox').length){
            $('.casawp-fancybox').fancybox({
                helpers     : {
                    title   : { type : 'inside' },
                }
            });
        }
    }*/

    if (window.casawpOptionParams && window.casawpOptionParams.chosen == 1) {
        var config = {
          '.chosen-select' : {width:"100%"}
        }
        for (var selector in config) {
          $(selector).chosen(config[selector]);
        }
    }

    if ($('.casawp-basic-box:visible').length){
        if (window.casawpOptionParams && window.casawpOptionParams.load_css == 'bootstrapv3') {
            var selector = '.casawp-basic-box';
        }
    }

    var casawpParams = $.jStorage.get('casawpParams', false);
    if (casawpParams && $('.casawp-single-pagination').length) {
        $('.casawp-single-archivelink').prop('href', casawpParams.archive_link);

        $.ajax({
            type: 'GET',
            url: '',
            data: {
                'ajax' : 'prevnext',
                'p' : casawpParams.p,
                'query' : casawpParams,
                'post_type' : 'casawp_property'
            },
            success: function (json) {
                if (jQuery.parseJSON(json).nextlink !== 'no') {
                    $('.casawp-single-next').prop('href', jQuery.parseJSON(json).nextlink);    
                } else {
                    $('.casawp-single-next').addClass('disabled');
                }
                if (jQuery.parseJSON(json).prevlink !== 'no') {
                    $('.casawp-single-prev').prop('href', jQuery.parseJSON(json).prevlink);
                } else {
                    $('.casawp-single-prev').addClass('disabled');
                }
                $('.casawp-single-pagination').css('display','none').removeClass('hidden').show('fast');

                $('.casawp-single').trigger("casawp-pagination-update");
            }
        });
    };

    //google maps
    if (window.casawpOptionParams && window.casawpOptionParams.google_maps == 1) {
        if ($('.casawp-map').length && google) {
            $('.casawp-map').each(function(){
                var $mapwraper = $(this);
                if ($mapwraper.data('lat') && $mapwraper.data('lng')) {
                    
                    var map;
                    function initialize() {
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
                    initialize();
                };
            });
        }
    }

    /**/
    if($('.casawp-gallery-thumbnails').length && window.casawpOptionParams){
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
        setThumbnailColumns();
    }
    /*
    var prev = $attachments.columns,
    width = $attachments.width();
    if ( width ) {
        $attachments.columns = Math.min( Math.round( width / 150 ), 12 ) || 1;

        if ( ! prev || prev !== $attachments.columns ) {
            $attachments.closest( '.media-frame-content' ).attr( 'data-columns', $attachments.columns );
        }
    }
    if (!that.ticker) {
        var $window = $(window);
        that.windowWidth = $window.width();
        that.windowHeight = $window.height();
        that.ticker = setInterval(function () {
            if ((width != $window.width()) || (height != $window.height())) {
                that.windowWidth = $window.width();
                that.windowHeight = $window.height();

                that['setColumns'](_relatedTarget);
            }
        }, 300);
    }*/
    /**/

    if ((window.casawpOptionParams && window.casawpOptionParams.load_css == 'bootstrapv2') && (window.casawpOptionParams && window.casawpOptionParams.load_bootstrap_js == 1)) {
        // Bootstrap 2 Scripts
        $('#casawpCarousel').carousel({
            interval: false
        });
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