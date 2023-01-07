document.addEventListener('DOMContentLoaded',function(){
    var tabElms = document.querySelectorAll('a[data-bs-toggle="list"]')
    var cards = document.querySelectorAll('.card-hide')
    var list = document.querySelectorAll('.show-on-click')

    tabElms.forEach(function(tabElm) {
        tabElm.addEventListener('shown.bs.tab', function (event) {
            var nakki = event.target.dataset.index;
            cards.forEach(function(card) {
                card.classList.add('d-none')
                //console.log(card.id);
                if(card.id == 'card-'+nakki){
                    card.classList.remove('d-none')
                }
                list[0].classList.remove('d-none')
            });
        })
    })
});
