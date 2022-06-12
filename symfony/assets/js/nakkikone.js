document.addEventListener('DOMContentLoaded',function(){
    var tabElms = document.querySelectorAll('a[data-bs-toggle="list"]')
    tabElms.forEach(function(tabElm) {
        tabElm.addEventListener('shown.bs.tab', function (event) {
            var nakki = $(this).data('index');
            $('.card-hide').addClass('d-none').filter('#card-'+nakki).removeClass('d-none');
            $('.show-on-click').removeClass('d-none');
        })
    })
});
