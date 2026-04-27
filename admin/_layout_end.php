<?php // admin/_layout_end.php ?>
</div><!-- /.page -->
</main><!-- /.main -->
<script>
// Close sidebar on outside click (mobile)
document.addEventListener('click', function(e){
  const sb = document.getElementById('sidebar');
  if(sb && sb.classList.contains('open') && !sb.contains(e.target) && !e.target.closest('.topbar-menu-btn')){
    sb.classList.remove('open');
  }
});

// Auto-dismiss alerts
document.querySelectorAll('.alert').forEach(function(el){
  setTimeout(function(){ el.style.transition='opacity .5s'; el.style.opacity='0'; setTimeout(function(){el.remove()},500); }, 4000);
});

// Confirm dangerous actions
document.querySelectorAll('[data-confirm]').forEach(function(el){
  el.addEventListener('click',function(e){
    if(!confirm(el.getAttribute('data-confirm'))){ e.preventDefault(); }
  });
});
</script>
</body>
</html>