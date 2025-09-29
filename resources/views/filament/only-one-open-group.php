<script>
document.addEventListener('alpine:init', () => {
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-sidebar-group-toggle]');
    if (!btn) return;
    const sidebar = btn.closest('[data-sidebar]') || document;
    // zárjon be minden más csoportot
    sidebar.querySelectorAll('[data-sidebar-group][aria-expanded="true"]').forEach(el => {
      if (!btn.closest('[data-sidebar-group]').isSameNode(el)) {
        el.__x?.$data?.open && (el.__x.$data.open = false);
      }
    });
  });
});
</script>
