import '../css/custom.scss';
import '../css/app.css';

import 'bootstrap';
import bsCustomFileInput from 'bs-custom-file-input'

require('@fortawesome/fontawesome-free/css/all.min.css');
require('@fortawesome/fontawesome-free/js/all.js');
require('typeface-spacegrotesk')

$(document).ready(function() {
    bsCustomFileInput.init();
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
