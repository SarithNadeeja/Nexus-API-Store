document.addEventListener('DOMContentLoaded', () => {
  const root = document.getElementById('coin-packages-page');
  if (!root) return;

  const form = document.getElementById('coin-package-form');
  const idInput = document.getElementById('coin-package-id');
  const nameInput = document.getElementById('coin-package-name');
  const amountInput = document.getElementById('coin-package-amount');
  const priceInput = document.getElementById('coin-package-price');
  const toneInput = document.getElementById('coin-package-tone');
  const sortInput = document.getElementById('coin-package-sort');
  const activeInput = document.getElementById('coin-package-active');
  const previewCard = document.getElementById('coin-package-preview');
  const previewCoins = document.getElementById('coin-package-preview-coins');
  const previewPrice = document.getElementById('coin-package-preview-price');
  const previewName = document.getElementById('coin-package-preview-name');
  const resetBtn = document.getElementById('coin-package-reset-btn');
  const addBtn = document.getElementById('coin-package-add-btn');
  const formTitle = document.getElementById('coin-package-form-title');
  const tableSearch = document.getElementById('coin-package-table-search');
  const tableBody = document.getElementById('coin-package-table-body');
  const pagination = document.getElementById('coin-package-pagination');
  const perPageSelect = document.getElementById('coin-package-per-page');
  const summary = document.getElementById('coin-package-table-summary');

  let currentPage = 1;

  function updatePreview() {
    const tone = toneInput?.value || 'package-blue';
    const coins = Number(amountInput?.value || 100);
    const price = Number(priceInput?.value || 1);

    if (previewCard) {
      previewCard.className = `wallet-package-card preview-package ${tone}`;
    }
    if (previewCoins) previewCoins.textContent = `${coins.toLocaleString()} Coins`;
    if (previewPrice) previewPrice.textContent = window.NexusCurrency?.formatLkr(price) ?? `LKR ${price.toFixed(2)}`;
    if (previewName) previewName.textContent = nameInput?.value.trim() || 'Package Name';
  }

  function resetForm() {
    form?.reset();
    if (idInput) idInput.value = '';
    if (activeInput) activeInput.checked = true;
    if (formTitle) formTitle.textContent = 'Add / Update Coin Package';
    updatePreview();
  }

  [nameInput, amountInput, priceInput, toneInput].forEach((input) => {
    input?.addEventListener('input', updatePreview);
    input?.addEventListener('change', updatePreview);
  });

  resetBtn?.addEventListener('click', (event) => {
    event.preventDefault();
    resetForm();
  });

  addBtn?.addEventListener('click', () => {
    resetForm();
    form?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    nameInput?.focus();
  });

  document.querySelectorAll('[data-edit-coin-package]').forEach((button) => {
    button.addEventListener('click', () => {
      const row = button.closest('tr[data-package-row]');
      if (!row) return;

      idInput.value = row.dataset.id || '';
      nameInput.value = row.dataset.name || '';
      amountInput.value = row.dataset.coinAmount || '';
      priceInput.value = row.dataset.priceUsd || '';
      toneInput.value = row.dataset.tone || 'package-blue';
      sortInput.value = row.dataset.sortOrder || '0';
      activeInput.checked = row.dataset.isActive === '1';

      if (formTitle) formTitle.textContent = 'Update Coin Package';
      updatePreview();
      form?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      nameInput?.focus();
    });
  });

  function visibleRows() {
    const query = (tableSearch?.value || '').trim().toLowerCase();
    return Array.from(tableBody?.querySelectorAll('tr[data-package-row]') || []).filter((row) => {
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
        ? `Showing ${start} to ${end} of ${rows.length} package${rows.length === 1 ? '' : 's'}`
        : 'Showing 0 packages';
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

  updatePreview();
  renderPagination();
});
