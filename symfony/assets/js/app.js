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
    a2lix_lib.sfCollection.init();
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

//console.log('Hello Webpack Encore');
