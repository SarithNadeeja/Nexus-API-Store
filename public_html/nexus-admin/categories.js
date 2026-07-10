document.addEventListener('DOMContentLoaded', () => {
  const root = document.getElementById('categories-page');
  if (!root) return;

  const form = document.getElementById('category-form');
  const idInput = document.getElementById('category-id');
  const nameInput = document.getElementById('category-name');
  const descriptionInput = document.getElementById('category-description');
  const previewName = document.getElementById('category-preview-name');
  const previewDescription = document.getElementById('category-preview-description');
  const resetBtn = document.getElementById('category-reset-btn');
  const addBtn = document.getElementById('category-add-btn');
  const tableSearch = document.getElementById('category-table-search');
  const tableBody = document.getElementById('category-table-body');
  const pagination = document.getElementById('category-pagination');
  const perPageSelect = document.getElementById('category-per-page');
  const formTitle = document.getElementById('category-form-title');

  let currentPage = 1;

  function updatePreview() {
    if (previewName) {
      previewName.textContent = nameInput.value.trim() || 'Category Name';
    }
    if (previewDescription) {
      previewDescription.textContent = descriptionInput.value.trim() || 'Category description preview';
    }
  }

  function resetForm() {
    form.reset();
    idInput.value = '';
    if (formTitle) formTitle.textContent = 'Add / Update Category';
    updatePreview();
  }

  nameInput?.addEventListener('input', updatePreview);
  descriptionInput?.addEventListener('input', updatePreview);

  resetBtn?.addEventListener('click', (event) => {
    event.preventDefault();
    resetForm();
  });

  addBtn?.addEventListener('click', () => {
    resetForm();
    form?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    nameInput?.focus();
  });

  document.querySelectorAll('[data-edit-category]').forEach((button) => {
    button.addEventListener('click', () => {
      idInput.value = button.dataset.id || '';
      nameInput.value = button.dataset.name || '';
      descriptionInput.value = button.dataset.description || '';
      if (formTitle) formTitle.textContent = 'Update Category';
      updatePreview();
      form?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      nameInput?.focus();
    });
  });

  function visibleRows() {
    const query = (tableSearch?.value || '').trim().toLowerCase();
    return Array.from(tableBody?.querySelectorAll('tr') || []).filter((row) => {
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
