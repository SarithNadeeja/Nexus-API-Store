const RECHARGE_PACKAGES = [
  { coins: 100, price: 1.0, tone: 'package-blue' },
  { coins: 250, price: 2.0, tone: 'package-purple' },
  { coins: 500, price: 4.0, tone: 'package-orange' },
  { coins: 1000, price: 7.0, tone: 'package-green' },
];

let selectedRechargeCoins = 100;
let showAllActivity = false;

function formatActivityDate(value) {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '—';
  return date.toLocaleString('en-US', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  });
}

function formatActivity(tx) {
  const amount = tx.coinAmount;
  const absAmount = Math.abs(amount);

  if (tx.transactionType === 'RECHARGE') {
    return {
      icon: 'wallet',
      tone: 'green',
      title: 'Coin Wallet Loaded',
      description: `${absAmount} coins added to your wallet`,
      amountLabel: `+${absAmount} Coins`,
      positive: true,
    };
  }

  if (tx.transactionType === 'PURCHASE') {
    return {
      icon: 'key',
      tone: 'amber',
      title: 'API Key Purchased',
      description: tx.description || 'API purchase completed',
      amountLabel: `${amount} Coins`,
      positive: false,
    };
  }

  if (tx.transactionType === 'ADMIN_CREDIT') {
    return {
      icon: 'wallet',
      tone: 'green',
      title: 'Coins Credited',
      description: tx.description || 'Admin wallet adjustment',
      amountLabel: `+${absAmount} Coins`,
      positive: true,
    };
  }

  if (tx.transactionType === 'ADMIN_DEBIT') {
    return {
      icon: 'wallet',
      tone: 'amber',
      title: 'Coins Debited',
      description: tx.description || 'Admin wallet adjustment',
      amountLabel: `-${absAmount} Coins`,
      positive: false,
    };
  }

  return {
    icon: 'wallet',
    tone: amount >= 0 ? 'green' : 'amber',
    title: tx.description || 'Wallet Activity',
    description: 'Account transaction',
    amountLabel: `${amount >= 0 ? '+' : ''}${amount} Coins`,
    positive: amount >= 0,
  };
}

function activityIcon(type) {
  if (type === 'key') return '🔑';
  return '👛';
}

function renderDashboard() {
  const container = document.getElementById('dashboard-content');
  if (!container) return;

  if (NexusAuth.loading) {
    container.innerHTML = '<p class="wallet-loading">Loading dashboard...</p>';
    return;
  }

  if (!NexusAuth.user) {
    location.href = '/login.html';
    return;
  }

  const user = NexusAuth.user;
  const transactions = user.transactions || [];
  const visibleTransactions = showAllActivity ? transactions : transactions.slice(0, 5);

  container.innerHTML = `
    <div class="wallet-stats-grid">
      <article class="wallet-stat-card">
        <div class="wallet-stat-icon tone-blue">👛</div>
        <div class="wallet-stat-body">
          <div class="wallet-stat-value">${Number(user.coinBalance).toLocaleString()}</div>
          <div class="wallet-stat-meta">
            <span class="status-dot tone-green"></span>
            <span>Available Coins</span>
          </div>
        </div>
      </article>

      <article class="wallet-stat-card">
        <div class="wallet-stat-icon tone-purple">📄</div>
        <div class="wallet-stat-body">
          <div class="wallet-stat-value">${user.purchases.length}</div>
          <div class="wallet-stat-meta">
            <span class="status-dot tone-purple"></span>
            <span>Total Purchased</span>
          </div>
        </div>
      </article>

      <article class="wallet-stat-card wallet-stat-status">
        <div class="wallet-stat-icon tone-green">👤</div>
        <div class="wallet-stat-body">
          <span class="wallet-status-badge">Active</span>
          <div class="wallet-stat-meta">
            <span>Account Status</span>
          </div>
        </div>
        <button class="wallet-logout-btn" id="dash-logout" type="button">
          <span>↪</span> Logout
        </button>
      </article>
    </div>

    <section class="wallet-recharge-section glass-card">
      <div class="wallet-recharge-main">
        <div class="wallet-section-head">
          <h2>⚡ Recharge Coins</h2>
          <p>Choose a package that suits your needs.</p>
        </div>

        <div class="wallet-packages-grid">
          ${RECHARGE_PACKAGES.map((pkg) => `
            <button
              class="wallet-package-card tone-${pkg.tone}${selectedRechargeCoins === pkg.coins ? ' is-selected' : ''}"
              type="button"
              data-recharge-coins="${pkg.coins}"
            >
              ${selectedRechargeCoins === pkg.coins ? '<span class="wallet-package-check">✓</span>' : ''}
              <span class="wallet-package-icon">◎</span>
              <strong>${pkg.coins} Coins</strong>
              <span class="wallet-package-price">$${pkg.price.toFixed(2)}</span>
            </button>
          `).join('')}
        </div>

        <button class="btn btn-primary wallet-recharge-btn" id="wallet-recharge-btn" type="button">
          Recharge ${selectedRechargeCoins} Coins
        </button>
      </div>

      <aside class="wallet-recharge-sidebar">
        <div class="wallet-sidebar-shield" aria-hidden="true">🛡</div>
        <h3>Why Recharge?</h3>
        <ul class="wallet-benefits-list">
          <li><span>✓</span> Instant coin credit</li>
          <li><span>✓</span> Secure transactions</li>
          <li><span>✓</span> No hidden charges</li>
          <li><span>✓</span> Use across all APIs</li>
        </ul>
      </aside>
    </section>

    <section class="wallet-activity-section glass-card">
      <div class="wallet-activity-head">
        <div>
          <h2>🕒 Recent Activity</h2>
          <p>Your latest transactions and activities.</p>
        </div>
        ${transactions.length > 5 ? `
          <button class="wallet-view-all" id="wallet-view-all" type="button">
            ${showAllActivity ? 'Show Less' : 'View All'} <span>→</span>
          </button>
        ` : ''}
      </div>

      <div class="wallet-activity-list">
        ${visibleTransactions.length ? visibleTransactions.map((tx) => {
          const activity = formatActivity(tx);
          return `
            <article class="wallet-activity-item">
              <div class="wallet-activity-left">
                <span class="wallet-activity-icon tone-${activity.tone}">${activityIcon(activity.icon)}</span>
                <div>
                  <strong>${activity.title}</strong>
                  <p>${activity.description}</p>
                </div>
              </div>
              <div class="wallet-activity-right">
                <span class="wallet-activity-amount ${activity.positive ? 'positive' : 'negative'}">${activity.amountLabel}</span>
                <time>${formatActivityDate(tx.createdAt)}</time>
              </div>
            </article>
          `;
        }).join('') : `
          <div class="wallet-activity-empty">
            <span>🕒</span>
            <p>No coin activity yet. Recharge your wallet to get started.</p>
          </div>
        `}
      </div>
    </section>
  `;

  document.getElementById('dash-logout')?.addEventListener('click', async () => {
    await NexusAuth.logout();
    location.href = '/';
  });

  container.querySelectorAll('[data-recharge-coins]').forEach((button) => {
    button.addEventListener('click', () => {
      selectedRechargeCoins = Number(button.dataset.rechargeCoins || 100);
      renderDashboard();
    });
  });

  document.getElementById('wallet-recharge-btn')?.addEventListener('click', async () => {
    const button = document.getElementById('wallet-recharge-btn');
    if (!button) return;

    button.disabled = true;
    button.textContent = 'Processing...';

    try {
      const session = await NexusApi.recharge(selectedRechargeCoins);
      NexusAuth.user = session;
      document.dispatchEvent(new CustomEvent('nexus-auth-changed'));
      button.textContent = `Recharge ${selectedRechargeCoins} Coins`;
      button.disabled = false;
      renderDashboard();
    } catch (err) {
      alert(err.message);
      button.textContent = `Recharge ${selectedRechargeCoins} Coins`;
      button.disabled = false;
    }
  });

  document.getElementById('wallet-view-all')?.addEventListener('click', () => {
    showAllActivity = !showAllActivity;
    renderDashboard();
  });
}

document.addEventListener('DOMContentLoaded', renderDashboard);
document.addEventListener('nexus-auth-changed', renderDashboard);
