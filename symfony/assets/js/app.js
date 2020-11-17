import '../css/custom.scss';
import '../css/app.css';

import 'bootstrap';
import bsCustomFileInput from 'bs-custom-file-input'
import a2lix_lib from '@a2lix/symfony-collection/src/a2lix_sf_collection';

require('@fortawesome/fontawesome-free/css/all.min.css');
require('@fortawesome/fontawesome-free/js/all.js');
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
});
$(document).on('click','.icopy', function(event) {
    event.preventDefault();
    var iconclass = $(this).data('i');
    $(this).parent().prev().val(iconclass);
    return false;
});


//console.log('Hello Webpack Encore');
