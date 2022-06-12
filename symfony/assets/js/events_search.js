document.addEventListener('DOMContentLoaded',function(){
  // Nimi Haku
  function delay(fn, ms) {
    let timer = 0
    return function(...args) {
      clearTimeout(timer)
      timer = setTimeout(fn.bind(this, ...args), ms || 0)
    }
  }
  const input = document.getElementById('nameSearchInput');
  const posts = document.getElementsByClassName('post');
    
    
  input.addEventListener('keyup', delay(function (e) {
      //console.log('Time elapsed!', this.value);
      //console.log(posts);
      name = this.value;
      for (var i=0; i<posts.length; i++){
          if(posts[i].dataset.name.toLowerCase().indexOf(name) > -1 || name==''){
              //bootstrap.Carousel.getInstance(posts[i]).show();
              posts[i].classList.add('show');
          } else {
              posts[i].classList.remove('show');
            }
      }
  }, 500));

});

