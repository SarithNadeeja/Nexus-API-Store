document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('api-form');
  if (!form) return;

  const idInput = document.getElementById('api-id');
  const nameInput = document.getElementById('api-name');
  const statusInput = document.getElementById('api-status');
  const categoryInput = document.getElementById('api-category');
  const priceInput = document.getElementById('api-price');
  const accessInput = document.getElementById('api-access');
  const descriptionInput = document.getElementById('api-description');
  const bulkInput = document.getElementById('api-bulk-keys');
  const bulkCount = document.getElementById('api-bulk-count');
  const resetBtn = document.getElementById('api-reset-btn');
  const formTitle = document.getElementById('api-form-title');
  const tableSearch = document.getElementById('api-table-search');
  const tableBody = document.getElementById('api-table-body');
  const pagination = document.getElementById('api-pagination');
  const perPageSelect = document.getElementById('api-per-page');

  let currentPage = 1;

  function countBulkLinks(value) {
    return value
      .split(/\r?\n/)
      .map((line) => line.trim())
      .filter((line) => line && !line.startsWith('#')).length;
  }

  function updateBulkCount() {
    if (!bulkCount || !bulkInput) return;
    const count = countBulkLinks(bulkInput.value);
    bulkCount.textContent = count === 1 ? '1 link ready to upload' : `${count} links ready to upload`;
  }

  function resetForm() {
    window.location.href = '?section=apis';
  }

  bulkInput?.addEventListener('input', updateBulkCount);
  updateBulkCount();

  resetBtn?.addEventListener('click', (event) => {
    event.preventDefault();
    resetForm();
  });

  document.querySelectorAll('[data-edit-api]').forEach((button) => {
    button.addEventListener('click', () => {
      const id = button.dataset.id || '';
      if (id) {
        window.location.href = `?section=apis&edit=${encodeURIComponent(id)}`;
      }
    });
  });

  function visibleRows() {
    const query = (tableSearch?.value || '').trim().toLowerCase();
    return Array.from(tableBody?.querySelectorAll('tr[data-row]') || []).filter((row) => {
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

    if (!pagination) return;
    pagination.innerHTML = '';

    const prev = document.createElement('button');
    prev.type = 'button';
    prev.className = 'page-btn';
    prev.textContent = '‹';
    prev.disabled = currentPage === 1;
    prev.addEventListener('click', () => { currentPage -= 1; renderPagination(); });
    pagination.appendChild(prev);

    for (let page = 1; page <= totalPages; page += 1) {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'page-btn' + (page === currentPage ? ' active' : '');
      btn.textContent = String(page);
      btn.addEventListener('click', () => { currentPage = page; renderPagination(); });
      pagination.appendChild(btn);
    }

    const next = document.createElement('button');
    next.type = 'button';
    next.className = 'page-btn';
    next.textContent = '›';
    next.disabled = currentPage === totalPages;
    next.addEventListener('click', () => { currentPage += 1; renderPagination(); });
    pagination.appendChild(next);
  }

  tableSearch?.addEventListener('input', () => { currentPage = 1; renderPagination(); });
  perPageSelect?.addEventListener('change', () => { currentPage = 1; renderPagination(); });
  renderPagination();

  form.addEventListener('submit', (event) => {
    const isEdit = Boolean(idInput?.value);
    const bulkCountValue = countBulkLinks(bulkInput?.value || '');
    if (!isEdit && bulkCountValue === 0) {
      event.preventDefault();
      alert('Paste at least one API key link (one per line).');
      bulkInput?.focus();
    }
  });
});
