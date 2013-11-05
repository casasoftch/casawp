
var $ = jQuery.noConflict();

$(document).ready(function($) {

    //google maps
    if ($('.casasync-map').length && google) {
        $('.casasync-map').each(function(){
            var $mapwraper = $(this);
            if ($mapwraper.data('address')) {
                geocoder = new google.maps.Geocoder();
                geocoder.geocode( { 'address': $mapwraper.data('address')}, function(results, status) {
                    if (status == google.maps.GeocoderStatus.OK) {
                        var map;
                        function initialize() {
                            var mapOptions = {
                              zoom: 12,
                              center: results[0].geometry.location,
                              mapTypeId: google.maps.MapTypeId.ROADMAP
                            };
                            $mapwraper.show();
                            map = new google.maps.Map(document.getElementById('map-canvas'),
                              mapOptions);


                            map.fitBounds(results[0].geometry.bounds);

                            var marker = new google.maps.Marker({
                                map: map,
                                position: results[0].geometry.location
                            });
                        }

                        google.maps.event.addDomListener(window, 'load', initialize);

                        //$('#map-canvas').animate({'height' : '400px'}, 500);
                    }
                });
            };
        });

        
    };





    var isMobile = navigator.userAgent.match(/(iPhone|iPod|iPad|Android|BlackBerry)/);
    if (!isMobile) {
        $('.multiselect').each(function(){
            var that = $(this);
            that.multiselect({
                buttonClass: 'btn',
                buttonWidth: 'auto',
                buttonContainer: '<div class="multiselect-item btn-group" />',
                maxHeight: false,
                buttonText: function(options) {
                  if (options.length == 0) {
                    if (that.data('empty')) {
                        return that.data('empty')+' <b class="caret"></b>';
                    } else {
                        return 'Bitte auswählen <b class="caret"></b>';
                    }
                  }
                  else if (options.length > 2) {
                    return options.length + ' ausgewählt  <b class="caret"></b>';
                  }
                  else {
                    var selected = '';
                    options.each(function() {
                      selected += $(this).text() + ', ';
                    });
                    return selected.substr(0, selected.length -2) + ' <b class="caret"></b>';
                  }
                }
            });

        });
    };
    

    $('#casasyncCarousel').carousel({
        interval: false
    });
 
    $('#carousel-text').html($('#slide-content-0').html());

    //Handles the carousel thumbnails
    $('[id^=carousel-selector-]').click( function(event){
    		event.preventDefault();
            
            var id_selector = $(this).attr("id");
            var id = id_selector.substr(id_selector.length -1);
            var id = parseInt(id);
            $('#casasyncCarousel').carousel(id);


            $('#slider-thumbs .thumbnail-pane li').removeClass('active');
            $(this).parent().addClass('active');

    });


    // When the carousel slides, auto update the text
    $('#casasyncCarousel').on('slid', function (e) {
            var id = $('.item.active').data('slide-number');
            $('#carousel-text').html($('#slide-content-'+id).html());

            $('#slider-thumbs .thumbnail-pane li').removeClass('active');
            $('#carousel-selector-'+id).parent().addClass('active');

    });

    function scrollToAnchor(id){
        var destinationTag = $('#'+id);
        $('html,body').animate({scrollTop: destinationTag.offset().top},'slow');
    }


    $('#casasyncKontactAnchor').click(function(event){
        event.preventDefault();

        $('html, body').animate({
            scrollTop: $( $.attr(this, 'href') ).parent().offset().top - 100
        }, 500);

        $('.casasync-property-contact-form').parent().addClass('casasync-highlight');
        $('.casasync-property-contact-form').delay(500).find(' input[name="firstname"]').focus();

        //scrollToAnchor($(this).attr('href').split('#')[1]);

        
        return false;

    });

    $('.casasync-fancybox').fancybox();

});