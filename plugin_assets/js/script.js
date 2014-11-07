
jQuery.noConflict();
jQuery(document).ready(function($) {

    //archive jstorage persistance
    if (window.casasyncParams) {
        $.jStorage.set('casasyncParams', window.casasyncParams);
    }

    $('#casasyncContactAnchor').click(function(event){
        event.preventDefault();

        $('html, body').animate({
            scrollTop: $( $.attr(this, 'href') ).parent().offset().top - 100
        }, 500);

        $('.casasync-contactform-form').parent().addClass('casasync-highlight');
        $('.casasync-contactform-form').delay(500).find(' input[name="firstname"]').focus();

        //scrollToAnchor($(this).attr('href').split('#')[1]);
        return false;
    });

    
    if (window.casasyncOptionParams && window.casasyncOptionParams.featherlight == 1) {
        if($('.property-image-gallery').length){
            $('.property-image-gallery').featherlightGallery();
        }
    }
    /*if (window.casasyncOptionParams && window.casasyncOptionParams.fancybox == 1) {
        if($('.casasync-fancybox').length){
            $('.casasync-fancybox').fancybox({
                helpers     : {
                    title   : { type : 'inside' },
                }
            });
        }
    }*/

    if (window.casasyncOptionParams && window.casasyncOptionParams.chosen == 1) {
        var config = {
          '.chosen-select' : {width:"100%"}
        }
        for (var selector in config) {
          $(selector).chosen(config[selector]);
        }
    }

    if ($('.casasync-basic-box').length){
        if (window.casasyncOptionParams && window.casasyncOptionParams.load_css == 'bootstrapv3') {
            var selector = '.casasync-basic-box';
            $(selector).equalHeightColumns({
                speed : 500
            });
        }
    }

    var casasyncParams = $.jStorage.get('casasyncParams', false);
    if (casasyncParams && $('.casasync-single-pagination').length) {
        $('.casasync-single-archivelink').prop('href', casasyncParams.archive_link);

        $.ajax({
            type: 'GET',
            url: '',
            data: {
                'ajax' : 'prevnext',
                'p' : casasyncParams.p,
                'query' : casasyncParams,
                'post_type' : 'casasync_property'
            },
            success: function (json) {
                if (jQuery.parseJSON(json).nextlink !== 'no') {
                    $('.casasync-single-next').prop('href', jQuery.parseJSON(json).nextlink);    
                } else {
                    $('.casasync-single-next').addClass('disabled');
                }
                if (jQuery.parseJSON(json).prevlink !== 'no') {
                    $('.casasync-single-prev').prop('href', jQuery.parseJSON(json).prevlink);
                } else {
                    $('.casasync-single-prev').addClass('disabled');
                }
                $('.casasync-single-pagination').css('display','none').removeClass('hidden').show('fast');

                $('.casasync-single').trigger("casasync-pagination-update");
            }
        });
    };

    //google maps
    if (window.casasyncOptionParams && window.casasyncOptionParams.google_maps == 1) {
        if ($('.casasync-map').length && google) {
            $('.casasync-map').each(function(){
                var $mapwraper = $(this);
                if ($mapwraper.data('address')) {
                    geocoder = new google.maps.Geocoder();
                    geocoder.geocode( { 'address': $mapwraper.data('address')}, function(results, status) {
                        if (status == google.maps.GeocoderStatus.OK) {
                            var map;
                            function initialize() {
                                if (results[0]) {
                                    var location = results[0].geometry.location;
                                    var bounds = results[0].geometry.bounds;
                                } else {
                                    var location = null;
                                    var bounds = null;
                                }
                                var mapOptions = {
                                  zoom: parseInt(window.casasyncOptionParams.google_maps_zoomlevel),
                                  mapTypeId: google.maps.MapTypeId.ROADMAP,
                                  center: location
                                };
                                $mapwraper.show();
                                map = new google.maps.Map(document.getElementById('map-canvas'),
                                  mapOptions);

                                if (bounds) {
                                    map.fitBounds(bounds);
                                }
                                var marker = new google.maps.Marker({
                                    map: map,
                                    position: location
                                });
                            }
                            google.maps.event.addDomListener(window, 'load', initialize);
                            //$('#map-canvas').animate({'height' : '400px'}, 500);
                        }
                    });
                };
            });
        }
    }

    /**/
    if($('.casasync-gallery-thumbnails').length && window.casasyncOptionParams){
        function setThumbnailColumns() {
            var that = '.casasync-gallery-thumbnails';
            var attachments = $(that).find('a');
            var width = $(that).width();
            var idealThumbnailWidth = window.casasyncOptionParams.thumbnails_ideal_width;

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


    if ((window.casasyncOptionParams && window.casasyncOptionParams.load_css == 'bootstrapv2') && (window.casasyncOptionParams && window.casasyncOptionParams.load_bootstrap_js == 1)) {
        // Bootstrap 2 Scripts
        $('#casasyncCarousel').carousel({
            interval: false
        });
    }

    // remove attr multiple (safari bug)
    var userAgent = window.navigator.userAgent;
    if (userAgent.match(/iPad/i) || userAgent.match(/iPhone/i)) {
        selector = '.casasync_multiselect';
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