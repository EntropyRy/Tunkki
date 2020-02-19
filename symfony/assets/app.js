import './custom.scss';
import './css/app.css';

import 'bootstrap';

$(document).ready(function() {
    $('nav').find('a[href="'+window.location.pathname+'"]').parent().addClass('active');
});

//console.log('Hello Webpack Encore');
