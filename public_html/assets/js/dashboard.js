let rechargePackages = [];
let customRecharge = null;
let whatsappNumber = '';
let selectedPackageId = null;
let rechargeMode = 'package';
let customCoinAmount = '';
let showAllActivity = false;
let packagesLoadError = false;

function formatLkr(amount, decimals = 2) {
  return window.NexusCurrency?.formatLkr(amount, decimals) ?? `LKR ${Number(amount || 0).toFixed(decimals)}`;
}

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

async function loadRechargePackages() {
  packagesLoadError = false;

  try {
    const data = await NexusApi.getCoinPackages();
    if (Array.isArray(data)) {
      rechargePackages = data;
      customRecharge = null;
    } else {
      rechargePackages = Array.isArray(data.packages) ? data.packages : [];
      customRecharge = data.customRecharge || null;
      whatsappNumber = data.whatsappNumber || '';
    }
  } catch (_) {
    rechargePackages = [];
    customRecharge = null;
    whatsappNumber = '';
    packagesLoadError = true;
  }

  if (rechargeMode === 'package' && !rechargePackages.some((pkg) => pkg.id === selectedPackageId)) {
    selectedPackageId = rechargePackages[0]?.id ?? null;
    if (!selectedPackageId && customRecharge?.enabled) {
      rechargeMode = 'custom';
    }
  }

  if (rechargeMode === 'package' && !selectedPackageId && !customRecharge?.enabled) {
    rechargeMode = 'package';
  }
}

function getSelectedPackage() {
  return rechargePackages.find((pkg) => pkg.id === selectedPackageId) || null;
}

function getCustomCoinsValue() {
  const value = Number(customCoinAmount);
  return Number.isFinite(value) ? Math.floor(value) : 0;
}

function getCustomPrice(coins) {
  if (!customRecharge?.enabled || !coins) return 0;
  return Math.round(coins * Number(customRecharge.pricePerCoin) * 100) / 100;
}

function isCustomAmountValid() {
  if (!customRecharge?.enabled) return false;
  const coins = getCustomCoinsValue();
  return coins >= customRecharge.minCoins && coins <= customRecharge.maxCoins;
}

function getRechargeButtonLabel() {
  if (rechargeMode === 'custom') {
    const coins = getCustomCoinsValue();
    if (!isCustomAmountValid()) return 'Enter a valid custom amount';
    return `Request ${coins.toLocaleString()} Coins via WhatsApp (${formatLkr(getCustomPrice(coins))})`;
  }

  const selectedPackage = getSelectedPackage();
  if (!selectedPackage) return 'No package selected';
  return `Request ${selectedPackage.coins.toLocaleString()} Coins via WhatsApp (${formatLkr(selectedPackage.price)})`;
}

function buildWhatsAppMessage(user) {
  const lines = [
    'Hello Nexus API Store,',
    '',
    'I would like to request a coin wallet recharge.',
    '',
    '*Account Details*',
    `User ID: ${user.userId ?? '—'}`,
    `Name: ${user.fullName || '—'}`,
    `Email: ${user.email || '—'}`,
    `Current Balance: ${Number(user.coinBalance).toLocaleString()} coins`,
    '',
    '*Request*',
  ];

  if (rechargeMode === 'custom') {
    const coins = getCustomCoinsValue();
    lines.push(
      'Type: Custom Coin Amount',
      `Coins Requested: ${coins.toLocaleString()}`,
      `Estimated Price: ${formatLkr(getCustomPrice(coins))}`,
    );
  } else {
    const selected = getSelectedPackage();
    lines.push(
      'Type: Coin Package',
      `Package: ${selected?.name || '—'}`,
      `Coins: ${selected?.coins?.toLocaleString() ?? '—'}`,
      `Price: ${formatLkr(selected?.price ?? 0)}`,
    );
  }

  lines.push('', 'Thank you.');
  return lines.join('\n');
}

function openWhatsAppRequest(user) {
  const number = String(whatsappNumber).replace(/\D+/g, '');
  if (!number) {
    alert('WhatsApp contact is not configured yet. Please contact support.');
    return;
  }

  const message = buildWhatsAppMessage(user);
  const url = `https://wa.me/${number}?text=${encodeURIComponent(message)}`;
  window.open(url, '_blank', 'noopener,noreferrer');
}

function canRecharge() {
  if (rechargeMode === 'custom') return isCustomAmountValid();
  return Boolean(getSelectedPackage());
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
  const customCoins = getCustomCoinsValue();
  const customPrice = getCustomPrice(customCoins);

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
          <p>Choose a package or enter a custom coin amount.</p>
        </div>

        <div class="wallet-packages-grid">
          ${rechargePackages.length ? rechargePackages.map((pkg) => `
            <button
              class="wallet-package-card ${pkg.tone}${rechargeMode === 'package' && selectedPackageId === pkg.id ? ' is-selected' : ''}"
              type="button"
              data-package-id="${pkg.id}"
            >
              ${rechargeMode === 'package' && selectedPackageId === pkg.id ? '<span class="wallet-package-check">✓</span>' : ''}
              <span class="wallet-package-icon">◎</span>
              <strong>${pkg.coins.toLocaleString()} Coins</strong>
              <span class="wallet-package-price">${formatLkr(pkg.price)}</span>
            </button>
          `).join('') : `
            <div class="wallet-packages-empty">
              <p>${packagesLoadError ? 'Unable to load coin packages right now.' : 'No active coin packages are available.'}</p>
            </div>
          `}
        </div>

        ${customRecharge?.enabled ? `
          <div class="wallet-custom-recharge ${rechargeMode === 'custom' ? 'is-selected' : ''}" id="wallet-custom-recharge">
            <div class="wallet-custom-head">
              <h3>Custom Amount</h3>
              <p>Enter between ${customRecharge.minCoins.toLocaleString()} and ${customRecharge.maxCoins.toLocaleString()} coins</p>
            </div>
            <div class="wallet-custom-input-row">
              <div class="input-with-icon">
                <span class="input-icon">◎</span>
                <input
                  type="number"
                  id="custom-coin-input"
                  min="${customRecharge.minCoins}"
                  max="${customRecharge.maxCoins}"
                  step="1"
                  value="${customCoinAmount}"
                  placeholder="Enter coin amount"
                >
              </div>
              <div class="wallet-custom-price-box">
                <span>Estimated price</span>
                <strong id="custom-coin-price-display">${formatLkr(customPrice)}</strong>
              </div>
            </div>
            <p class="wallet-custom-hint">Rate: ${formatLkr(customRecharge.pricePerCoin, 2)} per coin</p>
          </div>
        ` : ''}

        <button class="btn btn-primary wallet-recharge-btn" id="wallet-recharge-btn" type="button" ${!canRecharge() ? 'disabled' : ''}>
          📱 ${getRechargeButtonLabel()}
        </button>
      </div>

      <aside class="wallet-recharge-sidebar">
        <div class="wallet-sidebar-shield" aria-hidden="true">🛡</div>
        <h3>Why Recharge?</h3>
        <ul class="wallet-benefits-list">
          <li><span>✓</span> Request via WhatsApp</li>
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

  container.querySelectorAll('[data-package-id]').forEach((button) => {
    button.addEventListener('click', () => {
      rechargeMode = 'package';
      selectedPackageId = Number(button.dataset.packageId) || null;
      renderDashboard();
    });
  });

  const customSection = document.getElementById('wallet-custom-recharge');
  const customInput = document.getElementById('custom-coin-input');

  customSection?.addEventListener('click', () => {
    rechargeMode = 'custom';
    renderDashboard();
    document.getElementById('custom-coin-input')?.focus();
  });

  customInput?.addEventListener('click', (event) => {
    event.stopPropagation();
    rechargeMode = 'custom';
  });

  customInput?.addEventListener('input', () => {
    rechargeMode = 'custom';
    customCoinAmount = customInput.value;
    const priceDisplay = document.getElementById('custom-coin-price-display');
    const button = document.getElementById('wallet-recharge-btn');
    const coins = getCustomCoinsValue();
    const price = getCustomPrice(coins);

    if (priceDisplay) priceDisplay.textContent = formatLkr(price);
    if (button) {
      button.disabled = !isCustomAmountValid();
      button.textContent = `📱 ${getRechargeButtonLabel()}`;
    }

    customSection?.classList.toggle('is-selected', true);
    container.querySelectorAll('.wallet-package-card.is-selected').forEach((card) => {
      card.classList.remove('is-selected');
      card.querySelector('.wallet-package-check')?.remove();
    });
  });

  customInput?.addEventListener('focus', () => {
    rechargeMode = 'custom';
    customSection?.classList.add('is-selected');
  });

  document.getElementById('wallet-recharge-btn')?.addEventListener('click', () => {
    if (!canRecharge() || !NexusAuth.user) return;
    openWhatsAppRequest(NexusAuth.user);
  });

  document.getElementById('wallet-view-all')?.addEventListener('click', () => {
    showAllActivity = !showAllActivity;
    renderDashboard();
  });
}

async function initDashboard() {
  if (NexusAuth.loading) {
    renderDashboard();
    return;
  }

  await loadRechargePackages();
  renderDashboard();
}

document.addEventListener('DOMContentLoaded', initDashboard);
document.addEventListener('nexus-auth-changed', initDashboard);
