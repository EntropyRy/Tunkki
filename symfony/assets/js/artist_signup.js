import a2lix_lib from '@a2lix/symfony-collection/src/a2lix_sf_collection';
document.addEventListener('DOMContentLoaded',function(){
    a2lix_lib.sfCollection.init({
        lang: {
            add: 'Add/Lisää',
            remove: 'Remove/Poista'
        }
    });
/*    const el = [].slice.call(document.getElementsByClassName('icopy'));
    el.forEach(function(el) {
        el.addEventListener('click', function (event) {
            event.preventDefault();
            let iconclass = this.dataset.i;
console.log(this.parentElement.previousElementSibling);
            this.parentElement.previousElementSibling.setAttribute('value', iconclass);
            return false;
        });
    });
    */
});
$(document).on('click','.icopy', function(event) {
    event.preventDefault();
    var iconclass = $(this).data('i');
    $(this).parent().prev().val(iconclass);
    return false;
});
