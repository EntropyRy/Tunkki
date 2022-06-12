import '../css/bs.scss';
import '../css/polaroid.scss';
import '../css/app.css';
import '../css/dark.scss';
import '../css/animations.scss';

//import 'bootstrap';
import 'bootstrap/js/dist/alert';
import 'bootstrap/js/dist/button';
// import 'bootstrap/js/dist/carousel';
import 'bootstrap/js/dist/collapse';
import 'bootstrap/js/dist/dropdown';
// import 'bootstrap/js/dist/modal';
// import 'bootstrap/js/dist/popover';
// import 'bootstrap/js/dist/scrollspy';
import 'bootstrap/js/dist/tab';
// import 'bootstrap/js/dist/toast';
// import 'bootstrap/js/dist/tooltip';

//import bsCustomFileInput from 'bs-custom-file-input';
//import a2lix_lib from '@a2lix/symfony-collection/src/a2lix_sf_collection';

require('@fortawesome/fontawesome-free/css/all.min.css');
//require('@fortawesome/fontawesome-free/js/brands.js');
//require('@fortawesome/fontawesome-free/js/solid.js');
//require('@fortawesome/fontawesome-free/js/fontawesome.js');
import '@fontsource/space-grotesk/400.css';
import '@fontsource/space-grotesk/700.css';
import '@fontsource/space-grotesk/500.css';

document.addEventListener('DOMContentLoaded',function(){
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

