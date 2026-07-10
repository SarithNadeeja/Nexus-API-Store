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
          <span class="mini-tag mini-tag-amber">${api.priceCoins} coins</span>
        </div>
        <div class="endpoint-box">
          <div class="endpoint-title">◎ Buy with your account balance</div>
          <div class="endpoint-url">Endpoint: <span>${escapeHtml(api.endpointUrl)}</span></div>
        </div>
        <div class="card-actions">
          <a class="btn btn-secondary btn-sm" href="${escapeHtml(api.endpointUrl)}" target="_blank" rel="noreferrer">View Docs</a>
          ${api.purchased
            ? '<a class="btn btn-primary btn-sm" href="/dashboard.html">View Purchased Key</a>'
            : `<button class="btn btn-primary btn-sm buy-api-btn" data-api-id="${api.id}" type="button">Buy with Coins</button>`}
        </div>
      </article>
    `).join('');

    footer.innerHTML = user
      ? `Signed in as <strong>${escapeHtml(user.fullName)}</strong> with <span class="text-cyan">${user.coinBalance} coins</span>.`
      : 'Sign in to recharge coins and instantly purchase API keys.';

    grid.querySelectorAll('.buy-api-btn').forEach((btn) => {
      btn.addEventListener('click', async () => {
        if (!NexusAuth.user) {
          location.href = '/login.html';
          return;
        }
        btn.disabled = true;
        btn.textContent = 'Processing...';
        try {
          await NexusApi.purchase(Number(btn.dataset.apiId));
          await NexusAuth.refresh();
          await renderMarketplace(containerId, options);
        } catch (err) {
          alert(err.message);
          btn.disabled = false;
          btn.textContent = 'Buy with Coins';
        }
      });
    });

    if (window.NexusEffects) window.NexusEffects.initScrollReveal();
  } catch (err) {
    grid.innerHTML = `<div class="alert error center-text">${escapeHtml(err.message)}</div>`;
  }
}

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (ch) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  })[ch]);
}

window.renderMarketplace = renderMarketplace;
