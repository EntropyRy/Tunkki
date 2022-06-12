document.addEventListener('DOMContentLoaded',function(){
  // Nimi Haku
  function delay(fn, ms) {
    let timer = 0
    return function(...args) {
      clearTimeout(timer)
      timer = setTimeout(fn.bind(this, ...args), ms || 0)
    }
  }
  $('#nameSearchInput').keyup(delay(function (e) {
      console.log('Time elapsed!', this.value);
      name = this.value;
      $(".post").filter(function (){
          if($(this).data('name').toLowerCase().indexOf(name) > -1 || name==''){
              $(this).addClass('show');
          } else {
              $(this).removeClass('show');
            }
      });
  }, 500));

});

