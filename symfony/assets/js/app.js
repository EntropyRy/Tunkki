import '../css/custom.scss';
import '../css/polaroid.scss';
import '../css/app.css';
import '../css/dark.scss';

import 'bootstrap';
import bsCustomFileInput from 'bs-custom-file-input';
import a2lix_lib from '@a2lix/symfony-collection/src/a2lix_sf_collection';

require('@fortawesome/fontawesome-free/css/all.min.css');
//require('@fortawesome/fontawesome-free/js/brands.js');
//require('@fortawesome/fontawesome-free/js/solid.js');
//require('@fortawesome/fontawesome-free/js/fontawesome.js');
require('typeface-spacegrotesk')
require('jq-signature')

$(document).ready(function() {
    $('.js-signature').jqSignature({
        height: 250,
        autoFit: true
    });
    $('.clearCanvas').click(function() {
        $(this).hide();
        $('.saveCanvas').show();
        $('#signature').animate({height: "0px"}, 400, function() {
            $('#signature').empty();
            $('.js-signature').jqSignature('clearCanvas');
            $('.js-signature').animate({height: "250px"});
            $('#booking_consent_Agree').prop('disabled', true);
        });
        //$('#saveBtn').attr('disabled', true);
    });
    $('.saveCanvas').click(function() {
        $('.saveCanvas').hide();
        $('.clearCanvas').show();
        $('.js-signature').animate({height: "0px"}, 400, function(){
            $('#signature').empty();
            var dataUrl = $('.js-signature').jqSignature('getDataURL');
            var img = $('<img>').attr('src', dataUrl);
            $('#signature').append(img);
            $('#signature').animate({height: "250px"});
            $('#booking_consent_renterSignature').val(dataUrl);
            $('#booking_consent_Agree').prop('disabled', false);
        });

    });
    bsCustomFileInput.init();
    a2lix_lib.sfCollection.init({
        lang: {
            add: 'Add/Lisää',
            remove: 'Remove/Poista'
        }
    });
    $('#clubroomSwitch').click(function() {
        $('.post').each(function() {
            if ($(this).data('type') == 'clubroom'){
                $(this).animate({
                    height: "toggle"
                });
            }
        });
    });
    $('a[data-toggle="list"]').on('shown.bs.tab', function (e) {
        /*if(e.relatedTarget){
          var oldNakki = $(e.relatedTarget).attr('aria-controls')
        }*/
        var nakki = $(this).data('index');
        $('.card-hide').addClass('d-none').filter('#card-'+nakki).removeClass('d-none');
        $('.show-on-click').removeClass('d-none');
    })
});
$(document).on('click','.icopy', function(event) {
    event.preventDefault();
    var iconclass = $(this).data('i');
    $(this).parent().prev().val(iconclass);
    return false;
});


//console.log('Hello Webpack Encore');
