function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (ch) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  })[ch]);
}

function formatCoins(value) {
  return Number(value || 0).toLocaleString();
}

function downloadCodeSnippetsTxt(apiName, snippets) {
  const safeName = String(apiName || 'api_snippet').replace(/[^a-z0-9_-]+/gi, '_').replace(/_+/g, '_');
  const uniqueSnippets = [...new Set((snippets || []).map((snippet) => String(snippet || '').trim()).filter(Boolean))];
  const snippet = uniqueSnippets[0] || '';
  const header = [
    `# ${apiName}`,
    `# Purchased: ${new Date().toLocaleString()}`,
    '',
  ].join('\n');
  const content = `${header}${snippet}\n`;
  const blob = new Blob([content], { type: 'text/plain;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = `${safeName}_code_snippet.txt`;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}

function closeBulkPurchaseModal() {
  document.getElementById('bulk-purchase-modal')?.remove();
}

function showBulkPurchaseSuccess(result) {
  const keys = Array.isArray(result.purchasedKeys) ? result.purchasedKeys : [];
  const quantity = result.quantity || keys.length || 1;
  const overlay = document.createElement('div');
  overlay.className = 'bulk-modal-overlay';
  overlay.id = 'bulk-purchase-success-modal';
  overlay.innerHTML = `
    <div class="bulk-modal-card" role="dialog" aria-modal="true" aria-labelledby="bulk-success-title">
      <div class="bulk-modal-header">
        <h3 id="bulk-success-title">Purchase Successful</h3>
        <button class="bulk-modal-close" type="button" aria-label="Close">×</button>
      </div>
      <div class="bulk-modal-body">
        <p>You purchased access to <strong>${escapeHtml(result.apiName || 'this API')}</strong>. Every customer receives the same shared code snippet.</p>
        <p class="bulk-modal-summary">
          <span>Total spent: <strong>${formatCoins(result.coinsSpent)} coins</strong></span>
          <span>Expires: <strong>${result.expirationMonths || 1} month${(result.expirationMonths || 1) === 1 ? '' : 's'} from purchase</strong></span>
        </p>
        <button class="btn btn-primary btn-sm bulk-download-btn" type="button" id="bulk-download-keys-btn">
          Download Code Snippet (.txt)
        </button>
        <a class="btn btn-secondary btn-sm" href="/dashboard.html#my-api-keys">View in Dashboard</a>
      </div>
    </div>
  `;

  document.body.appendChild(overlay);
  overlay.querySelector('.bulk-modal-close')?.addEventListener('click', () => overlay.remove());
  overlay.addEventListener('click', (event) => {
    if (event.target === overlay) overlay.remove();
  });

  overlay.querySelector('#bulk-download-keys-btn')?.addEventListener('click', () => {
    downloadCodeSnippetsTxt(result.apiName, keys);
  });
}

function openBulkPurchaseModal(api, onConfirm) {
  closeBulkPurchaseModal();

  const user = NexusAuth.user;
  const balance = Number(user?.coinBalance || 0);
  const maxByStock = Number(api.availableKeys || 1);
  const maxByCoins = api.priceCoins > 0 ? Math.floor(balance / api.priceCoins) : 0;
  const maxQuantity = Math.max(0, Math.min(maxByStock, maxByCoins, 100));
  const defaultQty = maxQuantity > 0 ? 1 : 0;

  const overlay = document.createElement('div');
  overlay.className = 'bulk-modal-overlay';
  overlay.id = 'bulk-purchase-modal';
  overlay.innerHTML = `
    <div class="bulk-modal-card" role="dialog" aria-modal="true" aria-labelledby="bulk-purchase-title">
      <div class="bulk-modal-header">
        <h3 id="bulk-purchase-title">Buy API Access</h3>
        <button class="bulk-modal-close" type="button" aria-label="Close">×</button>
      </div>
      <div class="bulk-modal-body">
        <p class="bulk-modal-api-name">${escapeHtml(api.name)}</p>
        <div class="bulk-modal-meta">
          <span>${formatCoins(api.priceCoins)} coins each</span>
          <span>Shared code included</span>
          <span>Your balance: ${formatCoins(balance)} coins</span>
        </div>
        <label class="bulk-modal-field">
          <span>How many access periods do you want?</span>
          <input
            type="number"
            id="bulk-purchase-qty"
            min="1"
            max="${Math.max(maxQuantity, 1)}"
            value="${defaultQty || 1}"
            ${maxQuantity === 0 ? 'disabled' : ''}
          >
        </label>
        <div class="bulk-modal-total" id="bulk-purchase-total">
          Total: <strong>0 coins</strong>
        </div>
        <p class="bulk-modal-error" id="bulk-purchase-error" hidden></p>
        <div class="bulk-modal-actions">
          <button class="btn btn-secondary btn-sm" type="button" id="bulk-purchase-cancel">Cancel</button>
          <button class="btn btn-primary btn-sm" type="button" id="bulk-purchase-confirm" ${maxQuantity === 0 ? 'disabled' : ''}>
            Confirm Purchase
          </button>
        </div>
      </div>
    </div>
  `;

  document.body.appendChild(overlay);

  const qtyInput = overlay.querySelector('#bulk-purchase-qty');
  const totalEl = overlay.querySelector('#bulk-purchase-total');
  const errorEl = overlay.querySelector('#bulk-purchase-error');
  const confirmBtn = overlay.querySelector('#bulk-purchase-confirm');

  const updateSummary = () => {
    const qty = Math.max(1, Math.floor(Number(qtyInput.value) || 1));
    const total = qty * api.priceCoins;
    const validStock = qty <= maxByStock;
    const validCoins = total <= balance;
    const validMax = qty <= 100;

    totalEl.innerHTML = `Total: <strong>${formatCoins(total)} coins</strong>`;

    if (!validStock) {
      errorEl.hidden = false;
      errorEl.textContent = `Maximum ${formatCoins(maxByStock)} per purchase.`;
      confirmBtn.disabled = true;
      return;
    }
    if (!validCoins) {
      errorEl.hidden = false;
      errorEl.textContent = `You need ${formatCoins(total)} coins but only have ${formatCoins(balance)}.`;
      confirmBtn.disabled = true;
      return;
    }
    if (!validMax) {
      errorEl.hidden = false;
      errorEl.textContent = 'Maximum 100 purchases at once.';
      confirmBtn.disabled = true;
      return;
    }

    errorEl.hidden = true;
    errorEl.textContent = '';
    confirmBtn.disabled = false;
  };

  qtyInput?.addEventListener('input', updateSummary);
  updateSummary();

  overlay.querySelector('.bulk-modal-close')?.addEventListener('click', closeBulkPurchaseModal);
  overlay.querySelector('#bulk-purchase-cancel')?.addEventListener('click', closeBulkPurchaseModal);
  overlay.addEventListener('click', (event) => {
    if (event.target === overlay) closeBulkPurchaseModal();
  });

  confirmBtn?.addEventListener('click', async () => {
    const quantity = Math.max(1, Math.floor(Number(qtyInput.value) || 1));
    confirmBtn.disabled = true;
    confirmBtn.textContent = 'Processing...';
    errorEl.hidden = true;

    try {
      await onConfirm(quantity);
      closeBulkPurchaseModal();
    } catch (err) {
      errorEl.hidden = false;
      errorEl.textContent = err.message || 'Purchase failed.';
      confirmBtn.disabled = false;
      confirmBtn.textContent = 'Confirm Purchase';
    }
  });
}

function renderMarketplaceActions(api) {
  const parts = [];

  if (api.availableKeys > 0) {
    parts.push(`
      <button
        class="btn btn-primary btn-sm buy-api-btn"
        data-api-id="${api.id}"
        data-api-name="${escapeHtml(api.name)}"
        data-price-coins="${api.priceCoins}"
        data-available-keys="${api.availableKeys}"
        data-expiration-months="${api.expirationMonths}"
        type="button"
      >${api.purchased ? 'Extend Access' : 'Buy with Coins'}</button>
    `);
  }

  if (api.purchased) {
    parts.push('<a class="btn btn-secondary btn-sm" href="/dashboard.html#my-api-keys">View Your Keys</a>');
  }

  if (!api.availableKeys && !api.purchased) {
    parts.push('<button class="btn btn-secondary btn-sm" type="button" disabled>Sold Out</button>');
  }

  return parts.join('');
}

async function renderMarketplace(containerId, options = {}) {
  const container = document.getElementById(containerId);
  if (!container) return;

  const title = options.title || 'Featured API Services';
  const description = options.description || 'Production-ready services with clear documentation, authentication requirements, and coin-based access plans.';
  const showHeader = options.showHeader !== false;

  if (showHeader) {
    const parts = title.split(' ');
    const accent = parts.length > 2 ? parts.slice(-2).join(' ') : '';
    const plain = parts.length > 2 ? parts.slice(0, -2).join(' ') : title;
    container.innerHTML = `
      <div class="section-intro reveal">
        <h2 class="section-heading">${plain}${accent ? ` <span class="gradient-text">${accent}</span>` : ''}</h2>
        <p class="section-subheading">${description}</p>
      </div>
      <div id="${containerId}-grid" class="marketplace-grid"><p class="muted center-text">Loading API marketplace...</p></div>
      <div id="${containerId}-footer" class="marketplace-footer muted center-text"></div>`;
  } else {
    container.innerHTML = `
      <div id="${containerId}-grid" class="marketplace-grid"><p class="muted center-text">Loading API marketplace...</p></div>
      <div id="${containerId}-footer" class="marketplace-footer muted center-text"></div>`;
  }

  const grid = document.getElementById(`${containerId}-grid`);
  const footer = document.getElementById(`${containerId}-footer`);

  try {
    const apis = await NexusApi.getApis();
    if (!apis.length) {
      grid.innerHTML = '<p class="muted center-text">No APIs listed yet. Check back soon.</p>';
      return;
    }

    const user = NexusAuth.user;
    grid.innerHTML = apis.map((api, index) => `
      <article class="glass-card glass-card-hover marketplace-card reveal reveal-delay-${(index % 6) + 1}">
        <div class="api-badges">
          <span class="pill pill-cyan">${escapeHtml(api.category)}</span>
          <span class="pill pill-emerald">${escapeHtml(api.status)}</span>
        </div>
        <h3 class="card-title">${escapeHtml(api.name)}</h3>
        <p class="card-copy">${escapeHtml(api.description || '')}</p>
        <div class="tag-row">
          <span class="mini-tag">Auth: API Key</span>
          <span class="mini-tag">Format: JSON</span>
          <span class="mini-tag mini-tag-amber">${api.priceCoins} coins each</span>
          <span class="mini-tag">${api.expirationMonths} month${api.expirationMonths === 1 ? '' : 's'} access</span>
          ${api.availableKeys > 0 ? '<span class="mini-tag mini-tag-cyan">Shared code</span>' : '<span class="mini-tag">Unavailable</span>'}
          ${api.purchased ? `<span class="mini-tag">You own ${api.purchasedCount || 1}</span>` : ''}
        </div>
        <div class="endpoint-box">
          <div class="endpoint-title">◎ Secure delivery after purchase</div>
          <div class="endpoint-url">Purchase once and receive the full shared code snippet. Every customer gets the same code.</div>
        </div>
        <div class="card-actions">
          ${renderMarketplaceActions(api)}
        </div>
      </article>
    `).join('');

    footer.innerHTML = user
      ? `Signed in as <strong>${escapeHtml(user.fullName)}</strong> with <span class="text-cyan">${formatCoins(user.coinBalance)} coins</span>.`
      : 'Sign in to recharge coins and instantly purchase API access.';

    grid.querySelectorAll('.buy-api-btn').forEach((btn) => {
      btn.addEventListener('click', async () => {
        if (!NexusAuth.user) {
          location.href = '/login.html';
          return;
        }

        const api = {
          id: Number(btn.dataset.apiId),
          name: btn.dataset.apiName || 'API',
          priceCoins: Number(btn.dataset.priceCoins) || 0,
          availableKeys: Number(btn.dataset.availableKeys) || 0,
          expirationMonths: Number(btn.dataset.expirationMonths) || 1,
        };

        openBulkPurchaseModal(api, async (quantity) => {
          const result = await NexusApi.purchase(api.id, quantity);
          await NexusAuth.refresh();
          showBulkPurchaseSuccess(result);
          await renderMarketplace(containerId, options);
        });
      });
    });

    if (window.NexusEffects) window.NexusEffects.initScrollReveal();
  } catch (err) {
    grid.innerHTML = `<div class="alert error center-text">${escapeHtml(err.message)}</div>`;
  }
}

window.renderMarketplace = renderMarketplace;
window.downloadCodeSnippetsTxt = downloadCodeSnippetsTxt;
window.downloadApiKeysTxt = downloadCodeSnippetsTxt;
