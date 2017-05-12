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
                $(this).attr( 'data-col', (columns*2) );
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


    //<option value="" disabled selected hidden>Please Choose...</option>



    var userAgent = window.navigator.userAgent;
    console.log(userAgent);
    if (userAgent.match(/iPad/i) || userAgent.match(/iPhone/i)) {
        $('.chosen-select').each(function(index, el) {
            $(el).removeAttr('multiple')
            .prop('placeholder', $(this).data('placeholder'))
            .prepend("<option value='' disabled selected hidden>"+($(el).data('placeholder'))+"</option>");
        });
    }


    //replace radios with a controllable one
    if($('.casawp-gender-radios').length){
        $('.casawp-gender-radios .form-group label:not(.control-label)').append($('<span class="checkreplacer"></span>'));
    }



    // remove attr multiple (safari bug)
    /*var userAgent = window.navigator.userAgent;
    console.log(userAgent);
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
    }*/

    //ajax based archive filter

    function addParameterAndReturn(url, paramName, paramValue)
    {
        if (url.indexOf(paramName + "=") >= 0)
        {
            var prefix = url.substring(0, url.indexOf(paramName));
            var suffix = url.substring(url.indexOf(paramName));
            suffix = suffix.substring(suffix.indexOf("=") + 1);
            suffix = (suffix.indexOf("&") >= 0) ? suffix.substring(suffix.indexOf("&")) : "";
            url = prefix + paramName + "=" + paramValue + suffix;
        }
        else
        {
        if (url.indexOf("?") < 0)
            url += "?" + paramName + "=" + paramValue;
        else
            url += "&" + paramName + "=" + paramValue;
        }
        return url;
    }

    if (window.casawpOptionParams && window.casawpOptionParams.ajaxify_archive == 1) {
      var $archiveList = $('.casawp-ajax-archive-list');
      var $archiveForm = $('.casawp-filterform');
      if ($archiveList.length || $archiveForm.length) {
        if ($archiveList.length ) {
            $('.casawp-filterform-button').hide();
        }

        $archiveForm.change(function(event){
          $(this).trigger( "casawp-ajaxfilter:filter-change" );
          $(this).submit();
        });

        $archiveForm.submit(function(event){

          var $form = $(this);
          event.preventDefault();
          var filteredParams = $form.serialize();
          var filteredParamsArray = $form.serializeArray();
          var url = $form.prop('action');
          url = url.replace(window.location.protocol + "//" + window.location.host, '');

          //update filter form
          $form.addClass('casawp-filterform-loading');

          if($(this).find("input[type=submit]:focus").length){
            window.location = url + '?' + filteredParams;
            return;
          }
          var filteredUrl = addParameterAndReturn(url + '?' + filteredParams, 'ajax', 'archive-filter');

          

          $.ajax({
            url: filteredUrl,
            type: 'GET',
            dataType: 'html',
            success: function (data) {
                $form.removeClass('casawp-filterform-loading');
                $form.html($(data).find('form').html());
                if (window.casawpOptionParams && window.casawpOptionParams.chosen == 1) {
                  $form.find('.chosen-select').chosen({width:"100%"});
                }
                if ($archiveList.length ) {
                    $('.casawp-filterform-button').hide();
                }
                $form.trigger( "casawp-filterform:after-filter-reload", [url, filteredUrl]  );

            }
          });

          //update property list
          $archiveList.addClass('casawp-archive-list-loading');
          var filteredArchiveUrl = addParameterAndReturn(url + '?' + filteredParams, 'ajax', 'archive');
          $.ajax({
            url: filteredArchiveUrl,
            type: 'GET',
            dataType: 'html',
            success: function (data) {
              $archiveList.removeClass('casawp-archive-list-loading');
              $archiveList.html($(data).html());
              $archiveList.trigger( "casawp-ajaxfilter:after-list-reload", [url, filteredUrl] );
              if ($(data).data('archivelink')) {
                casawpParams.archive_link = $(data).data('archivelink');
                $.jStorage.set('casawpParams', casawpParams);
              }


            //
            // console.log(casawpParams);
            //
            // $.each(filteredParamsArray, function(index, elem){
            //   switch (elem.name) {
            //     case 'locations':
            //       if (casawpParams[elem.name] === undefined) {
            //         casawpParams[elem.name] = [];
            //       }
            //       casawpParams[elem.name].push(elem.value);
            //       break;
            //     default:
            //     casawpParams[elem.name] = elem.value;
            //   }
            //
            // });
            //
            // $.jStorage.set('casawpParams', casawpParams);
            // console.log('stored');
            // console.log(casawpParams);
            //




            },
            always: function() {
              //console.log('HEEELLLLOOO??????');
            }
          });

          $form.trigger( "casawp-ajaxfilter:filter-submit", [url, filteredUrl] );

        });
      }
    }

    /*
      Triggers to use:

      $('.casawp-filterform').on( "casawp-ajaxfilter:filter-change", function(event){

      });

      $('.casawp-filterform').on( "casawp-ajaxfilter:filter-submit", function(event, url, filteredUrl){

      });

      $('.casawp-filterform').on( "casawp-ajaxfilter:after-filter-reload", function(event, url, filteredUrl){

      });

      $('.casawp-ajax-archive-list').on( "casawp-ajaxfilter:after-list-reload", function(event, url, filteredUrl){

      });
    */

});
