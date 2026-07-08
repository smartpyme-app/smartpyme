@if(!isset($esPdf))
<script>
  if (window.self === window.top) {
    window.onload = function() { window.print(); }
  }
</script>
@endif
