document.addEventListener('DOMContentLoaded', () => {
  const root = document.getElementById('wallets-page');
  if (!root) return;

  const tableSearch = document.getElementById('wallet-table-search');
  const tableBody = document.getElementById('wallet-table-body');
  const pagination = document.getElementById('wallet-pagination');
  const perPageSelect = document.getElementById('wallet-per-page');
  const summary = document.getElementById('wallet-table-summary');
  const exportBtn = document.getElementById('wallet-export-btn');
  const adjustForm = document.getElementById('wallet-adjust-form');

  let currentPage = 1;

  function initUserCombobox() {
    const combobox = document.getElementById('wallet-user-combobox');
    const hiddenInput = document.getElementById('wallet-user-id');
    const searchInput = document.getElementById('wallet-user-search');
    const listEl = document.getElementById('wallet-user-list');
    const clearBtn = document.getElementById('wallet-user-clear');
    const dataEl = document.getElementById('wallet-users-data');

    if (!combobox || !hiddenInput || !searchInput || !listEl || !dataEl) {
      return null;
    }

    let users = [];
    try {
      users = JSON.parse(dataEl.textContent || '[]');
    } catch (_) {
      users = [];
    }

    let activeIndex = -1;
    let selectedUser = null;

    function formatLabel(user) {
      return `${user.name} (${user.email})`;
    }

    function setSelected(user, updateInput = true) {
      selectedUser = user;
      hiddenInput.value = user ? String(user.id) : '';
      combobox.classList.toggle('is-invalid', false);
      clearBtn.hidden = !user;

      if (updateInput) {
        searchInput.value = user ? formatLabel(user) : '';
      }
    }

    function clearSelection() {
      setSelected(null);
      searchInput.value = '';
      closeList();
      searchInput.focus();
    }

    function closeList() {
      listEl.hidden = true;
      listEl.innerHTML = '';
      activeIndex = -1;
    }

    function openList() {
      listEl.hidden = false;
    }

    function scoreUser(user, query) {
      const name = String(user.name || '').toLowerCase();
      const email = String(user.email || '').toLowerCase();
      const q = query.toLowerCase();

      if (name === q || email === q) return 0;
      if (name.startsWith(q) || email.startsWith(q)) return 1;
      if (name.includes(q) || email.includes(q)) return 2;
      return 99;
    }

    function filterUsers(query) {
      const trimmed = query.trim();
      if (!trimmed) {
        return users.slice(0, 12);
      }

      return users
        .map((user) => ({ user, score: scoreUser(user, trimmed) }))
        .filter((entry) => entry.score < 99)
        .sort((a, b) => {
          if (a.score !== b.score) return a.score - b.score;
          return String(a.user.name).localeCompare(String(b.user.name));
        })
        .slice(0, 12)
        .map((entry) => entry.user);
    }

    function renderList(items) {
      listEl.innerHTML = '';

      if (!items.length) {
        const empty = document.createElement('li');
        empty.className = 'user-combobox-empty';
        empty.textContent = 'No matching users found.';
        listEl.appendChild(empty);
        openList();
        return;
      }

      items.forEach((user, index) => {
        const item = document.createElement('li');
        item.dataset.index = String(index);
        item.dataset.userId = String(user.id);
        item.innerHTML = `<strong>${escapeHtml(user.name)}</strong><span>${escapeHtml(user.email)} · ◎ ${Number(user.balance || 0).toLocaleString()} coins</span>`;
        item.addEventListener('mousedown', (event) => {
          event.preventDefault();
          setSelected(user);
          closeList();
        });
        listEl.appendChild(item);
      });

      openList();
    }

    function highlightActive() {
      const items = Array.from(listEl.querySelectorAll('li:not(.user-combobox-empty)'));
      items.forEach((item, index) => {
        item.classList.toggle('is-active', index === activeIndex);
      });

      const activeItem = items[activeIndex];
      if (activeItem) {
        activeItem.scrollIntoView({ block: 'nearest' });
      }
    }

    function showSuggestions() {
      if (selectedUser && searchInput.value.trim() === formatLabel(selectedUser)) {
        renderList(users.slice(0, 12));
        return;
      }

      selectedUser = null;
      hiddenInput.value = '';
      renderList(filterUsers(searchInput.value));
    }

    searchInput.addEventListener('focus', showSuggestions);
    searchInput.addEventListener('input', () => {
      selectedUser = null;
      hiddenInput.value = '';
      clearBtn.hidden = true;
      showSuggestions();
    });

    searchInput.addEventListener('keydown', (event) => {
      const items = Array.from(listEl.querySelectorAll('li:not(.user-combobox-empty)'));

      if (event.key === 'ArrowDown') {
        event.preventDefault();
        if (listEl.hidden) showSuggestions();
        activeIndex = Math.min(activeIndex + 1, items.length - 1);
        highlightActive();
        return;
      }

      if (event.key === 'ArrowUp') {
        event.preventDefault();
        activeIndex = Math.max(activeIndex - 1, 0);
        highlightActive();
        return;
      }

      if (event.key === 'Enter' && !listEl.hidden && activeIndex >= 0 && items[activeIndex]) {
        event.preventDefault();
        const userId = Number(items[activeIndex].dataset.userId);
        const user = users.find((entry) => entry.id === userId);
        if (user) {
          setSelected(user);
          closeList();
        }
        return;
      }

      if (event.key === 'Escape') {
        closeList();
      }
    });

    clearBtn?.addEventListener('click', clearSelection);

    document.addEventListener('click', (event) => {
      if (!combobox.contains(event.target)) {
        closeList();
        if (selectedUser) {
          searchInput.value = formatLabel(selectedUser);
        }
      }
    });

    adjustForm?.addEventListener('submit', (event) => {
      if (!hiddenInput.value) {
        event.preventDefault();
        combobox.classList.add('is-invalid');
        searchInput.focus();
        showSuggestions();
      }
    });

    return {
      selectById(userId, name = '', email = '') {
        const user = users.find((entry) => entry.id === Number(userId));
        if (user) {
          setSelected(user);
          return;
        }
        if (userId) {
          setSelected({
            id: Number(userId),
            name: name || `User #${userId}`,
            email: email || '',
            balance: 0,
          });
        }
      },
    };
  }

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (ch) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[ch]);
  }

  const userCombobox = initUserCombobox();

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
      userCombobox?.selectById(
        button.dataset.userId,
        button.dataset.userName || '',
        button.dataset.userEmail || ''
      );
      adjustForm?.scrollIntoView({ behavior: 'smooth', block: 'start' });
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
