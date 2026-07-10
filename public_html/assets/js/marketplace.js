async function renderMarketplace(containerId, options = {}) {
  const container = document.getElementById(containerId);
  if (!container) return;

  container.innerHTML = '<p class="muted">Loading APIs...</p>';
  try {
    const apis = await NexusApi.getApis();
    if (!apis.length) {
      container.innerHTML = '<p class="muted">No APIs listed yet. Check back soon.</p>';
      return;
    }

    const user = NexusAuth.user;
    container.innerHTML = `<div class="grid-3">${apis.map(api => `
      <article class="glass-card card api-card">
        <div class="api-meta">
          <span class="badge">${escapeHtml(api.category)}</span>
          <span class="tag">${escapeHtml(api.status)}</span>
          <span class="tag">${api.priceCoins} coins</span>
        </div>
        <h3>${escapeHtml(api.name)}</h3>
        <p class="muted">${escapeHtml(api.description || '')}</p>
        <div class="code">${escapeHtml(api.endpointUrl)}</div>
        <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
          <a class="btn btn-secondary" href="${escapeHtml(api.endpointUrl)}" target="_blank" rel="noreferrer">View Docs</a>
          ${api.purchased
            ? '<a class="btn btn-primary" href="/dashboard.html">View Purchased Key</a>'
            : `<button class="btn btn-primary buy-api-btn" data-api-id="${api.id}" type="button">Buy with Coins</button>`}
        </div>
      </article>
    `).join('')}</div>
    <p class="muted" style="margin-top:1rem;">${user ? `Signed in as ${escapeHtml(user.fullName)} • ${user.coinBalance} coins` : 'Sign in to recharge your wallet and buy API keys.'}</p>`;

    container.querySelectorAll('.buy-api-btn').forEach(btn => {
      btn.addEventListener('click', async () => {
        if (!NexusAuth.user) {
          location.href = '/login.html';
          return;
        }
        try {
          await NexusApi.purchase(Number(btn.dataset.apiId));
          await NexusAuth.refresh();
          await renderMarketplace(containerId, options);
          alert('API purchased successfully!');
        } catch (err) {
          alert(err.message);
        }
      });
    });
  } catch (err) {
    container.innerHTML = `<p class="alert error">${escapeHtml(err.message)}</p>`;
  }
}

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (ch) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  })[ch]);
}

window.renderMarketplace = renderMarketplace;
