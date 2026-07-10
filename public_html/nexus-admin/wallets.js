document.addEventListener('DOMContentLoaded', () => {
  const root = document.getElementById('wallets-page');
  if (!root) return;

  const userSelect = document.getElementById('wallet-user-id');
  const tableSearch = document.getElementById('wallet-table-search');
  const tableBody = document.getElementById('wallet-table-body');
  const pagination = document.getElementById('wallet-pagination');
  const perPageSelect = document.getElementById('wallet-per-page');
  const summary = document.getElementById('wallet-table-summary');
  const exportBtn = document.getElementById('wallet-export-btn');

  let currentPage = 1;

  function visibleRows() {
    const query = (tableSearch?.value || '').trim().toLowerCase();
    return Array.from(tableBody?.querySelectorAll('tr[data-user-row]') || []).filter((row) => {
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
        ? `Showing ${start} to ${end} of ${rows.length} users`
        : 'Showing 0 users';
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

  document.querySelectorAll('[data-select-wallet-user]').forEach((button) => {
    button.addEventListener('click', () => {
      if (userSelect) {
        userSelect.value = button.dataset.userId || '';
      }
      document.getElementById('wallet-adjust-form')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      document.querySelectorAll('.wallet-menu').forEach((menu) => menu.classList.remove('is-open'));
    });
  });

  document.querySelectorAll('[data-wallet-menu-toggle]').forEach((button) => {
    button.addEventListener('click', (event) => {
      event.stopPropagation();
      const menu = button.parentElement?.querySelector('.wallet-menu');
      document.querySelectorAll('.wallet-menu').forEach((item) => {
        if (item !== menu) item.classList.remove('is-open');
      });
      menu?.classList.toggle('is-open');
    });
  });

  document.addEventListener('click', () => {
    document.querySelectorAll('.wallet-menu').forEach((menu) => menu.classList.remove('is-open'));
  });

  exportBtn?.addEventListener('click', () => {
    const rows = visibleRows();
    const lines = ['Name,Email,Balance,Verified'];
    rows.forEach((row) => {
      lines.push([
        row.dataset.name || '',
        row.dataset.email || '',
        row.dataset.balance || '0',
        row.dataset.verified || 'No',
      ].map((value) => `"${String(value).replace(/"/g, '""')}"`).join(','));
    });

    const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'user-wallets.csv';
    link.click();
    URL.revokeObjectURL(url);
  });

  renderPagination();
});
