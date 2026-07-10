document.addEventListener('DOMContentLoaded', () => {
  const root = document.getElementById('admins-page');
  if (!root) return;

  const adminSelect = document.getElementById('admin-password-user-id');
  const tableSearch = document.getElementById('admins-table-search');
  const tableBody = document.getElementById('admins-table-body');
  const pagination = document.getElementById('admins-pagination');
  const perPageSelect = document.getElementById('admins-per-page');
  const summary = document.getElementById('admins-table-summary');
  const exportBtn = document.getElementById('admins-export-btn');
  const filterToggle = document.getElementById('admins-filter-toggle');
  const filterMenu = document.getElementById('admins-filter-menu');

  let currentPage = 1;
  let statusFilter = 'all';

  document.querySelectorAll('[data-password-target]').forEach((button) => {
    button.addEventListener('click', () => {
      const input = document.getElementById(button.dataset.passwordTarget || '');
      if (!input) return;
      const isPassword = input.type === 'password';
      input.type = isPassword ? 'text' : 'password';
      button.textContent = isPassword ? '🙈' : '👁';
    });
  });

  function visibleRows() {
    const query = (tableSearch?.value || '').trim().toLowerCase();
    return Array.from(tableBody?.querySelectorAll('tr[data-admin-row]') || []).filter((row) => {
      if (statusFilter !== 'all' && row.dataset.status !== statusFilter) {
        return false;
      }
      if (!query) return true;
      return row.textContent.toLowerCase().includes(query);
    });
  }

  function renderPagination() {
    const rows = visibleRows();
    const perPage = Number(perPageSelect?.value || 10);
    const totalPages = Math.max(1, Math.ceil(rows.length / perPage));
    currentPage = Math.min(currentPage, totalPages);

    rows.forEach((row, index) => {
      const page = Math.floor(index / perPage) + 1;
      row.hidden = page !== currentPage;
    });

    const start = rows.length ? (currentPage - 1) * perPage + 1 : 0;
    const end = Math.min(currentPage * perPage, rows.length);
    if (summary) {
      summary.textContent = rows.length
        ? `Showing ${start} to ${end} of ${rows.length} admin${rows.length === 1 ? '' : 's'}`
        : 'Showing 0 admins';
    }

    if (!pagination) return;

    pagination.innerHTML = '';
    const prev = document.createElement('button');
    prev.type = 'button';
    prev.className = 'page-btn';
    prev.textContent = '‹';
    prev.disabled = currentPage === 1;
    prev.addEventListener('click', () => {
      currentPage -= 1;
      renderPagination();
    });
    pagination.appendChild(prev);

    for (let page = 1; page <= totalPages; page += 1) {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'page-btn' + (page === currentPage ? ' active' : '');
      btn.textContent = String(page);
      btn.addEventListener('click', () => {
        currentPage = page;
        renderPagination();
      });
      pagination.appendChild(btn);
    }

    const next = document.createElement('button');
    next.type = 'button';
    next.className = 'page-btn';
    next.textContent = '›';
    next.disabled = currentPage === totalPages;
    next.addEventListener('click', () => {
      currentPage += 1;
      renderPagination();
    });
    pagination.appendChild(next);
  }

  tableSearch?.addEventListener('input', () => {
    currentPage = 1;
    renderPagination();
  });

  perPageSelect?.addEventListener('change', () => {
    currentPage = 1;
    renderPagination();
  });

  filterToggle?.addEventListener('click', (event) => {
    event.stopPropagation();
    filterMenu?.classList.toggle('is-open');
  });

  filterMenu?.querySelectorAll('[data-admin-filter]').forEach((button) => {
    button.addEventListener('click', () => {
      statusFilter = button.dataset.adminFilter || 'all';
      filterMenu.querySelectorAll('[data-admin-filter]').forEach((item) => item.classList.remove('active'));
      button.classList.add('active');
      filterMenu.classList.remove('is-open');
      currentPage = 1;
      renderPagination();
    });
  });

  document.querySelectorAll('[data-select-admin-password]').forEach((button) => {
    button.addEventListener('click', () => {
      if (adminSelect) {
        adminSelect.value = button.dataset.adminId || '';
      }
      document.getElementById('update-admin-password-form')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      document.getElementById('update-admin-password')?.focus();
      document.querySelectorAll('.admin-menu').forEach((menu) => menu.classList.remove('is-open'));
    });
  });

  document.querySelectorAll('[data-admin-menu-toggle]').forEach((button) => {
    button.addEventListener('click', (event) => {
      event.stopPropagation();
      const menu = button.parentElement?.querySelector('.admin-menu');
      document.querySelectorAll('.admin-menu').forEach((item) => {
        if (item !== menu) item.classList.remove('is-open');
      });
      menu?.classList.toggle('is-open');
    });
  });

  document.addEventListener('click', () => {
    document.querySelectorAll('.admin-menu, .filter-menu').forEach((menu) => menu.classList.remove('is-open'));
  });

  exportBtn?.addEventListener('click', () => {
    const rows = visibleRows();
    const lines = ['ID,Username,Created At,Last Login,Status'];
    rows.forEach((row) => {
      const statusLabel = row.dataset.status === 'pending' ? 'Setup Required' : 'Active';
      lines.push([
        row.dataset.adminId || '',
        row.dataset.username || '',
        row.dataset.created || '',
        row.dataset.lastLogin || '',
        statusLabel,
      ].map((value) => `"${String(value).replace(/"/g, '""')}"`).join(','));
    });

    const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'admin-accounts.csv';
    link.click();
    URL.revokeObjectURL(url);
  });

  renderPagination();
});
