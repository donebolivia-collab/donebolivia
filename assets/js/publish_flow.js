// publish_flow.js — evita pantalla en blanco tras "Publicar" y lleva a vista previa o inicio.
(function(){
  document.addEventListener('submit', function(e){
    var f = e.target;
    var a = (f.getAttribute('action')||'').toLowerCase();
    if (a.indexOf('public')>-1 || a.indexOf('anuncio')>-1 || a.indexOf('crear')>-1) {
      try{ sessionStorage.setItem('__justPublished','1'); }catch(err){}
    }
  }, true);

  function isContentPresent(){
    var main = document.querySelector('main, .main, #main, #content, .content, .container');
    if (!main) return false;
    if (main.textContent && main.textContent.trim().length > 30) return true;
    if (main.querySelector('img, .card, .alert, .row, .col, form')) return true;
    return false;
  }

  window.addEventListener('DOMContentLoaded', function(){
    try{
      var flag = sessionStorage.getItem('__justPublished');
      if (flag === '1'){
        sessionStorage.removeItem('__justPublished');
        if (!isContentPresent()){
          var m = location.search.match(/[?&](id|ad|anuncio_id)=(\d+)/);
          if (m && m[2]) {
            location.replace('/anuncio.php?id=' + m[2]);
          } else {
            location.replace('/index.php');
          }
        }
      }
    }catch(err){}
  });
})();