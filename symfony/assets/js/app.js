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

$(document).ready(function() {
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
