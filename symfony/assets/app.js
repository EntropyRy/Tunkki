import './custom.scss';
import './css/app.css';

import 'bootstrap';

$(document).ready(function() {
    $('#clubroomSwitch').click(function() {
        $('.post').each(function() {
            if ($(this).data('type') == 'Clubroom'){
                $(this).animate({
                    height: "toggle"
                });
            }
        });
    });
    //$('nav').find('a[href="'+window.location.pathname+'"]').parent().addClass('active');
});

//console.log('Hello Webpack Encore');
