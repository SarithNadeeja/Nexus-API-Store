let rechargePackages = [];
let customRecharge = null;
let whatsappNumber = '';
let selectedPackageId = null;
let rechargeMode = 'package';
let customCoinAmount = '';
let showAllActivity = false;
let showAllPurchases = false;
let packagesLoadError = false;
let purchasedApis = [];
let purchasesLoading = false;
let purchasesLoadError = '';

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

function maskApiKey(value, visible) {
  if (!value) return '••••••••••••••••';
  if (visible) return value;
  if (value.length <= 16) return '•'.repeat(value.length);
  return `${value.slice(0, 8)}${'•'.repeat(Math.min(value.length - 12, 24))}${value.slice(-4)}`;
}

function formatExpiryLabel(purchase) {
  const expired = Boolean(purchase.isExpired);
  const expiresAt = purchase.expiresAt ? new Date(purchase.expiresAt) : null;

  if (!expiresAt || Number.isNaN(expiresAt.getTime())) {
    const months = purchase.expirationMonths || 1;
    return {
      primary: `${months} month${months === 1 ? '' : 's'} from purchase`,
      secondary: expired ? 'Expired' : 'Active',
    };
  }

  const now = Date.now();
  const diffMs = expiresAt.getTime() - now;
  const daysLeft = Math.ceil(diffMs / (1000 * 60 * 60 * 24));

  return {
    primary: formatActivityDate(purchase.expiresAt),
    secondary: expired
      ? 'Expired on this date'
      : daysLeft <= 0
        ? 'Expires today'
        : `${daysLeft} day${daysLeft === 1 ? '' : 's'} remaining`,
  };
}

function renderPurchaseStatusBadge(purchase) {
  const expired = Boolean(purchase.isExpired);
  if (expired) {
    return '<span class="wallet-key-status wallet-key-status-expired">Expired</span>';
  }
  return '<span class="wallet-key-status wallet-key-status-active">Active</span>';
}

function renderPurchasedApis(purchases) {
  if (purchasesLoading) {
    return '<p class="wallet-loading-inline">Loading your purchased API keys...</p>';
  }

  if (purchasesLoadError) {
    return `
      <div class="wallet-activity-empty">
        <span>⚠</span>
        <p>${escapeHtml(purchasesLoadError)}</p>
        <button class="btn btn-secondary btn-sm" type="button" id="refresh-api-keys-btn">Try Again</button>
      </div>
    `;
  }

  if (!purchases.length) {
    return `
      <div class="wallet-activity-empty">
        <span>🔑</span>
        <p>No API keys yet. Buy an API from the marketplace to unlock your private access link.</p>
        <a class="btn btn-secondary btn-sm" href="/api-keys.html">Browse APIs</a>
      </div>
    `;
  }

  const visiblePurchases = showAllPurchases ? purchases : purchases.slice(0, 5);

  return `
    <div class="wallet-api-keys-table-wrap">
      <table class="wallet-api-keys-table">
        <thead>
          <tr>
            <th>API Name</th>
            <th>Purchased</th>
            <th>Status</th>
            <th>Expires</th>
            <th>Coins</th>
            <th>Your API Link</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          ${visiblePurchases.map((purchase, index) => {
            const apiLink = purchase.purchasedKey || purchase.accessLink || '';
            const expired = Boolean(purchase.isExpired);
            const expiry = formatExpiryLabel(purchase);
            return `
              <tr data-api-key-row="${index}" class="${expired ? 'is-expired' : 'is-active'}">
                <td data-label="API Name">
                  <strong>${escapeHtml(purchase.apiName)}</strong>
                </td>
                <td data-label="Purchased">${formatActivityDate(purchase.purchasedAt)}</td>
                <td data-label="Status">${renderPurchaseStatusBadge(purchase)}</td>
                <td data-label="Expires">
                  <strong>${escapeHtml(expiry.primary)}</strong>
                  <span class="wallet-expiry-meta">${escapeHtml(expiry.secondary)}</span>
                </td>
                <td data-label="Coins">${Number(purchase.coinsSpent).toLocaleString()}</td>
                <td data-label="Your API Link">
                  <div class="key-field wallet-api-key-field" data-api-key-value="${escapeHtml(apiLink)}">${escapeHtml(maskApiKey(apiLink, false))}</div>
                </td>
                <td data-label="Actions">
                  <div class="wallet-api-key-actions">
                    <button class="btn btn-secondary btn-sm" type="button" data-toggle-api-key="${index}" ${expired ? 'disabled' : ''}>Show</button>
                    <button class="btn btn-primary btn-sm" type="button" data-copy-api-key="${index}" ${expired ? 'disabled' : ''}>Copy</button>
                    ${apiLink && !expired ? `<a class="btn btn-secondary btn-sm" href="${escapeHtml(apiLink)}" target="_blank" rel="noreferrer">Open</a>` : ''}
                  </div>
                </td>
              </tr>
            `;
          }).join('')}
        </tbody>
      </table>
    </div>
    ${purchases.length > 5 ? `
      <div class="wallet-api-keys-footer">
        <button class="wallet-view-all" id="wallet-view-all-keys" type="button">
          ${showAllPurchases ? 'Show Less' : `View All ${purchases.length} API Keys`} <span>→</span>
        </button>
      </div>
    ` : ''}
  `;
}

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (ch) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  })[ch]);
}

function bindPurchasedApiKeys(purchases) {
  const visibility = new Map();

  document.querySelectorAll('[data-toggle-api-key]').forEach((button) => {
    button.addEventListener('click', () => {
      const index = button.dataset.toggleApiKey;
      const row = document.querySelector(`[data-api-key-row="${index}"]`);
      const field = row?.querySelector('[data-api-key-value]');
      const value = field?.dataset.apiKeyValue || '';
      const nextVisible = !visibility.get(index);
      visibility.set(index, nextVisible);
      if (field) field.textContent = maskApiKey(value, nextVisible);
      button.textContent = nextVisible ? 'Hide' : 'Show';
    });
  });

  document.querySelectorAll('[data-copy-api-key]').forEach((button) => {
    button.addEventListener('click', async () => {
      const index = button.dataset.copyApiKey;
      const row = document.querySelector(`[data-api-key-row="${index}"]`);
      const value = row?.querySelector('[data-api-key-value]')?.dataset.apiKeyValue || '';
      if (!value) return;
      try {
        await navigator.clipboard.writeText(value);
        button.textContent = 'Copied';
        setTimeout(() => { button.textContent = 'Copy'; }, 1200);
      } catch (_) {
        alert('Could not copy the API link. Please copy it manually.');
      }
    });
  });
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
  const purchases = purchasedApis.length ? purchasedApis : (user.purchases || []);
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

      <article class="wallet-stat-card wallet-stat-link" id="wallet-purchased-stat" role="button" tabindex="0">
        <div class="wallet-stat-icon tone-purple">🔑</div>
        <div class="wallet-stat-body">
          <div class="wallet-stat-value">${purchases.length}</div>
          <div class="wallet-stat-meta">
            <span class="status-dot tone-purple"></span>
            <span>Purchased API Keys</span>
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

    <section class="wallet-activity-section glass-card" id="my-api-keys">
      <div class="wallet-activity-head">
        <div>
          <h2>🔑 My Purchased API Keys</h2>
          <p>View your bought API history with expiry time and active or expired status.</p>
        </div>
        <button class="wallet-view-all" id="refresh-api-keys-btn" type="button" ${purchasesLoading ? 'disabled' : ''}>
          ${purchasesLoading ? 'Refreshing...' : 'Refresh'} <span>↻</span>
        </button>
      </div>
      <div class="wallet-api-keys-list">
        ${renderPurchasedApis(purchases)}
      </div>
    </section>

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

  bindPurchasedApiKeys(purchases);

  const scrollToApiKeys = () => {
    document.getElementById('my-api-keys')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };

  document.getElementById('wallet-purchased-stat')?.addEventListener('click', scrollToApiKeys);
  document.getElementById('wallet-purchased-stat')?.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      scrollToApiKeys();
    }
  });

  document.getElementById('refresh-api-keys-btn')?.addEventListener('click', async () => {
    await loadPurchasedApis(true);
    renderDashboard();
  });

  document.getElementById('wallet-view-all-keys')?.addEventListener('click', () => {
    showAllPurchases = !showAllPurchases;
    renderDashboard();
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

async function loadPurchasedApis(force = false) {
  if (!NexusAuth.user) {
    purchasedApis = [];
    return;
  }

  if (!force && purchasedApis.length) {
    return;
  }

  purchasesLoading = true;
  purchasesLoadError = '';

  try {
    const data = await NexusApi.getPurchases();
    purchasedApis = Array.isArray(data.purchases) ? data.purchases : [];
  } catch (err) {
    purchasedApis = NexusAuth.user?.purchases || [];
    purchasesLoadError = err.message || 'Unable to load purchased API keys.';
  } finally {
    purchasesLoading = false;
  }
}

async function initDashboard() {
  if (NexusAuth.loading) {
    renderDashboard();
    return;
  }

  await Promise.all([loadRechargePackages(), loadPurchasedApis()]);
  renderDashboard();

  if (window.location.hash === '#my-api-keys') {
    document.getElementById('my-api-keys')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}

document.addEventListener('DOMContentLoaded', initDashboard);
document.addEventListener('nexus-auth-changed', initDashboard);
