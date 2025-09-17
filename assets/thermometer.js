(function(){
  function animateIn(el){
    var fill = el.querySelector('.sh-thermo-fill');
    var percent = parseFloat(el.getAttribute('data-percent')) || 0;
    requestAnimationFrame(function(){
      fill.style.width = percent + '%';
    });
  }

  function updateMetMarkers(el){
    var current = parseFloat(el.getAttribute('data-current')) || 0;
    var target = parseFloat(el.getAttribute('data-target')) || 1;
    var markers = el.querySelectorAll('.sh-thermo-marker');
    markers.forEach(function(m){
      var label = m.querySelector('.sh-thermo-marker-label');
      var value = label ? parseFloat(label.textContent.replace(/[^\d.\-]/g, '')) : 0;
      if ((value || 0) <= current) { m.classList.add('met'); }
      else { m.classList.remove('met'); }
    });
  }

  function onIntersect(entries, observer){
    entries.forEach(function(entry){
      if (entry.isIntersecting) {
        animateIn(entry.target);
        observer.unobserve(entry.target);
      }
    });
  }

  function init(){
    var els = document.querySelectorAll('.sh-thermo');
    if (!('IntersectionObserver' in window)) {
      els.forEach(function(el){ animateIn(el); updateMetMarkers(el); });
      return;
    }
    var io = new IntersectionObserver(onIntersect, { threshold: 0.2 });
    els.forEach(function(el){ io.observe(el); updateMetMarkers(el); });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();