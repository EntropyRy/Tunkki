import a2lix_lib from '@a2lix/symfony-collection/src/a2lix_sf_collection';
document.addEventListener('DOMContentLoaded',function(){
    a2lix_lib.sfCollection.init({
        entry: {
            add: {
                label:'Add/Lisää',
            },
            remove: {
                label:'Remove/Poista',
            }
        }
    });
});
