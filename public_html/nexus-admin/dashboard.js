let overviewChart = null;

function badgeClass(status) {
  const value = String(status).toLowerCase();
  if (value === 'online' || value === 'healthy') return 'status-badge status-good';
  if (value.includes('slow')) return 'status-badge status-warn';
  return 'status-badge status-info';
}

function statCard(label, value, sublabel, iconClass, tone, accent) {
  return `
    <article class="dash-stat-card ${accent}">
      <div class="dash-stat-icon ${tone}">${iconClass}</div>
      <div class="dash-stat-body">
        <div class="dash-stat-label">${label}</div>
        <div class="dash-stat-value" data-stat="${label.toLowerCase().replace(/\s+/g, '-')}">${value}</div>
        <div class="dash-stat-sub">${sublabel}</div>
      </div>
    </article>`;
}

function renderStats(counts) {
  const el = document.getElementById('dashboard-stats');
  if (!el) return;
  el.innerHTML = [
    statCard('Categories', counts.categories, 'Total categories', '▦', 'tone-purple', 'accent-purple'),
    statCard('API Listings', counts.apis, 'Total API listings', '{ }', 'tone-green', 'accent-green'),
    statCard('Users', counts.users, 'Total registered users', '👥', 'tone-blue', 'accent-blue'),
    statCard('Admins', counts.admins, 'Total admin accounts', '🛡', 'tone-orange', 'accent-orange'),
  ].join('');
}

function renderChart(chart) {
  const canvas = document.getElementById('overview-chart');
  if (!canvas) return;

  const draw = () => {
    if (!window.Chart) {
      setTimeout(draw, 50);
      return;
    }

    if (overviewChart) {
      overviewChart.data.labels = chart.labels;
      overviewChart.data.datasets[0].data = chart.users;
      overviewChart.data.datasets[1].data = chart.apis;
      overviewChart.data.datasets[2].data = chart.keys;
      overviewChart.update();
      return;
    }

    overviewChart = new Chart(canvas, {
    type: 'line',
    data: {
      labels: chart.labels,
      datasets: [
        {
          label: 'Users',
          data: chart.users,
          borderColor: '#3b82f6',
          backgroundColor: 'rgba(59, 130, 246, 0.12)',
          tension: 0.42,
          fill: true,
          borderWidth: 2,
          pointRadius: 3,
          pointHoverRadius: 5,
        },
        {
          label: 'API Listings',
          data: chart.apis,
          borderColor: '#10b981',
          backgroundColor: 'rgba(16, 185, 129, 0.08)',
          tension: 0.42,
          fill: true,
          borderWidth: 2,
          pointRadius: 3,
          pointHoverRadius: 5,
        },
        {
          label: 'API Keys Generated',
          data: chart.keys,
          borderColor: '#f59e0b',
          backgroundColor: 'rgba(245, 158, 11, 0.08)',
          tension: 0.42,
          fill: true,
          borderWidth: 2,
          pointRadius: 3,
          pointHoverRadius: 5,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          labels: { color: '#94a3b8', boxWidth: 12 },
        },
      },
      scales: {
        x: {
          ticks: { color: '#64748b' },
          grid: { color: 'rgba(148, 163, 184, 0.08)' },
        },
        y: {
          beginAtZero: true,
          ticks: { color: '#64748b', precision: 0 },
          grid: { color: 'rgba(148, 163, 184, 0.08)' },
        },
      },
    },
  });
  };

  draw();
}

function activityIcon(type) {
  if (type === 'user') return '👤';
  if (type === 'key') return '🔑';
  if (type === 'api') return '{ }';
  return '🛡';
}

function renderActivity(items) {
  const el = document.getElementById('recent-activity');
  if (!el) return;

  if (!items.length) {
    el.innerHTML = '<p class="empty-copy">No recent activity yet.</p>';
    return;
  }

  el.innerHTML = items.map((item) => `
    <div class="activity-item">
      <div class="activity-icon">${activityIcon(item.type)}</div>
      <div class="activity-copy">
        <strong>${escapeHtml(item.title)}</strong>
        <span>${escapeHtml(item.detail)}</span>
      </div>
      <time>${escapeHtml(item.timeAgo)}</time>
    </div>
  `).join('');
}

function renderTopCategories(items) {
  const el = document.getElementById('top-categories');
  if (!el) return;

  if (!items.length) {
    el.innerHTML = `
      <div class="empty-state">
        <div class="empty-icon">📁</div>
        <p>No categories yet</p>
        <span>Create your first category to get started.</span>
        <a class="primary-btn" href="?section=categories">Create Category</a>
      </div>`;
    return;
  }

  el.innerHTML = items.map((item) => `
    <div class="rank-row">
      <span>${escapeHtml(item.name)}</span>
      <strong>${item.api_count} APIs</strong>
    </div>
  `).join('');
}

function renderTopApis(items) {
  const el = document.getElementById('top-apis');
  if (!el) return;

  if (!items.length) {
    el.innerHTML = `
      <div class="empty-state">
        <div class="empty-icon">{ }</div>
        <p>No API listings yet</p>
        <span>Add your first API listing to get started.</span>
        <a class="primary-btn" href="?section=apis">Add API Listing</a>
      </div>`;
    return;
  }

  el.innerHTML = items.map((item) => `
    <div class="rank-row">
      <span>${escapeHtml(item.name)}</span>
      <strong>${item.purchase_count} purchases</strong>
    </div>
  `).join('');
}

function renderSystem(system) {
  const map = {
    'system-server': system.server,
    'system-database': system.database,
    'system-storage': system.storageLabel,
    'system-api': `${system.apiResponseMs}ms`,
  };

  Object.entries(map).forEach(([id, value]) => {
    const el = document.getElementById(id);
    if (el) {
      el.textContent = value;
      el.className = badgeClass(value);
    }
  });

  const updated = document.getElementById('dashboard-updated');
  if (updated) {
    updated.textContent = 'Updated ' + new Date().toLocaleTimeString();
  }
}

function renderNotifications(count) {
  const badge = document.getElementById('notification-count');
  if (!badge) return;
  badge.textContent = count > 0 ? '' : '0';
  badge.hidden = count <= 0;
  badge.classList.toggle('has-alert', count > 0);
}

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (ch) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  })[ch]);
}

async function loadDashboard() {
  const periodSelect = document.getElementById('chart-period');
  const days = Number(periodSelect?.value || 7);
  const response = await fetch(`/nexus-admin/dashboard-data.php?days=${days}`, { credentials: 'same-origin' });
  if (!response.ok) throw new Error('Failed to load dashboard data');
  const data = await response.json();

  renderStats(data.counts);
  renderChart(data.chart);
  renderActivity(data.recentActivity);
  renderTopCategories(data.topCategories);
  renderTopApis(data.topApis);
  renderSystem(data.system);
  renderNotifications(data.unverifiedUsers);
}

document.addEventListener('DOMContentLoaded', () => {
  if (!document.getElementById('dashboard-root')) return;

  const searchInput = document.getElementById('dashboard-search');
  if (searchInput) {
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
  }

  const periodSelect = document.getElementById('chart-period');
  periodSelect?.addEventListener('change', () => {
    loadDashboard().catch(() => {});
  });

  loadDashboard().catch(() => {
    const el = document.getElementById('dashboard-root');
    if (el) {
      el.insertAdjacentHTML('beforeend', '<div class="alert error">Unable to load live dashboard data.</div>');
    }
  });

  setInterval(() => {
    loadDashboard().catch(() => {});
  }, 30000);
});
