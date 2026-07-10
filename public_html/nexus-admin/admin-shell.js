document.addEventListener('DOMContentLoaded', () => {
  const searchInput = document.getElementById('admin-shell-search');
  if (!searchInput) return;

  document.addEventListener('keydown', (event) => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
      event.preventDefault();
      searchInput.focus();
    }
  });

  searchInput.addEventListener('input', () => {
    const query = searchInput.value.trim().toLowerCase();
    document.querySelectorAll('.sidebar-nav .nav-link').forEach((link) => {
      const match = link.textContent.toLowerCase().includes(query);
      link.style.display = match || query === '' ? '' : 'none';
    });
  });
});
