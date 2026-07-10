document.addEventListener('DOMContentLoaded', () => {
  const root = document.getElementById('apis-page');
  if (!root) return;

  const form = document.getElementById('api-form');
  const idInput = document.getElementById('api-id');
  const nameInput = document.getElementById('api-name');
  const statusInput = document.getElementById('api-status');
  const categoryInput = document.getElementById('api-category');
  const priceInput = document.getElementById('api-price');
  const keyInput = document.getElementById('api-key');
  const endpointInput = document.getElementById('api-endpoint');
  const accessInput = document.getElementById('api-access');
  const descriptionInput = document.getElementById('api-description');
  const resetBtn = document.getElementById('api-reset-btn');
  const generateBtn = document.getElementById('api-generate-key');
  const addBtn = document.getElementById('api-add-btn');
  const tableSearch = document.getElementById('api-table-search');
  const tableBody = document.getElementById('api-table-body');
  const pagination = document.getElementById('api-pagination');
  const perPageSelect = document.getElementById('api-per-page');
  const formTitle = document.getElementById('api-form-title');
  const breadcrumbAction = document.getElementById('api-breadcrumb-action');

  const previewName = document.getElementById('api-preview-name');
  const previewStatus = document.getElementById('api-preview-status');
  const previewCategory = document.getElementById('api-preview-category');
  const previewPrice = document.getElementById('api-preview-price');
  const previewEndpoint = document.getElementById('api-preview-endpoint');
  const previewAccess = document.getElementById('api-preview-access');
  const previewKey = document.getElementById('api-preview-key');
  const toggleKeyBtn = document.getElementById('api-preview-toggle-key');
  const copyKeyBtn = document.getElementById('api-preview-copy-key');

  let currentPage = 1;
  let keyVisible = false;

  function categoryLabel() {
    const option = categoryInput?.selectedOptions?.[0];
    return option?.textContent?.trim() || 'Category';
  }

  function maskKey(value) {
    if (!value) return '••••••••••••';
    if (keyVisible) return value;
    return '•'.repeat(Math.min(value.length, 12));
  }

  function updatePreview() {
    if (previewName) previewName.textContent = nameInput.value.trim() || 'API Name';
    if (previewStatus) {
      previewStatus.textContent = statusInput.value || 'ACTIVE';
      previewStatus.className = 'preview-status ' + (statusInput.value === 'ACTIVE' ? 'is-active' : 'is-muted');
    }
    if (previewCategory) previewCategory.textContent = categoryLabel();
    if (previewPrice) previewPrice.textContent = (priceInput.value || '50') + ' coins';
    if (previewEndpoint) previewEndpoint.textContent = endpointInput.value.trim() || 'https://api.example.com/v1/service';
    if (previewAccess) previewAccess.textContent = accessInput.value.trim() || 'https://docs.example.com/api';
    if (previewKey) previewKey.textContent = maskKey(keyInput.value.trim());
  }

  function generateApiKey() {
    const bytes = new Uint8Array(16);
    crypto.getRandomValues(bytes);
    const token = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
    keyInput.value = `nxk_live_${token}`;
    keyVisible = false;
    updatePreview();
  }

  function resetForm() {
    form.reset();
    idInput.value = '';
    if (priceInput) priceInput.value = '50';
    if (statusInput) statusInput.value = 'ACTIVE';
    keyVisible = false;
    if (formTitle) formTitle.textContent = 'Add / Update API Listing';
    if (breadcrumbAction) breadcrumbAction.textContent = 'Add New';
    updatePreview();
  }

  function bindField(el) {
    el?.addEventListener('input', updatePreview);
    el?.addEventListener('change', updatePreview);
  }

  [nameInput, statusInput, categoryInput, priceInput, keyInput, endpointInput, accessInput, descriptionInput]
    .forEach(bindField);

  resetBtn?.addEventListener('click', (event) => {
    event.preventDefault();
    resetForm();
  });

  generateBtn?.addEventListener('click', (event) => {
    event.preventDefault();
    generateApiKey();
  });

  addBtn?.addEventListener('click', () => {
    resetForm();
    form?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    nameInput?.focus();
  });

  toggleKeyBtn?.addEventListener('click', () => {
    keyVisible = !keyVisible;
    updatePreview();
  });

  copyKeyBtn?.addEventListener('click', async () => {
    const value = keyInput.value.trim();
    if (!value) return;
    try {
      await navigator.clipboard.writeText(value);
      copyKeyBtn.textContent = '✓';
      setTimeout(() => { copyKeyBtn.textContent = '⧉'; }, 1200);
    } catch (_) {}
  });

  document.querySelectorAll('[data-edit-api]').forEach((button) => {
    button.addEventListener('click', () => {
      idInput.value = button.dataset.id || '';
      nameInput.value = button.dataset.name || '';
      statusInput.value = button.dataset.status || 'ACTIVE';
      categoryInput.value = button.dataset.categoryId || '';
      priceInput.value = button.dataset.price || '50';
      keyInput.value = button.dataset.key || '';
      endpointInput.value = button.dataset.endpoint || '';
      accessInput.value = button.dataset.access || '';
      descriptionInput.value = button.dataset.description || '';
      keyVisible = false;
      if (formTitle) formTitle.textContent = 'Update API Listing';
      if (breadcrumbAction) breadcrumbAction.textContent = 'Edit';
      updatePreview();
      form?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      nameInput?.focus();
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

  if (!keyInput.value.trim()) {
    generateApiKey();
  } else {
    updatePreview();
  }
  renderPagination();
});
