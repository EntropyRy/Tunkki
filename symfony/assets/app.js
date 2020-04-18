import './custom.scss';
import './css/app.css';

import 'bootstrap';

$(document).ready(function() {
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
