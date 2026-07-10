function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function revealDelayClass(index) {
  return `reveal reveal-delay-${(index % 6) + 1}`;
}

async function renderHomeCategories(containerId = 'home-categories-grid') {
  const grid = document.getElementById(containerId);
  if (!grid) return;

  grid.innerHTML = '<p class="muted center-text">Loading categories...</p>';

  try {
    const categories = await NexusApi.getCategories();

    if (!categories.length) {
      grid.innerHTML = '<p class="muted center-text">No categories available yet. Check back soon.</p>';
      return;
    }

    grid.innerHTML = categories.map((category, index) => {
      const description = category.description?.trim()
        || 'Browse API services in this category.';
      const apiLabel = `${category.apiCount} API${category.apiCount === 1 ? '' : 's'}`;

      return `
        <a href="#featured-apis" class="glass-card glass-card-hover category-card ${revealDelayClass(index)}">
          <div class="category-icon ${escapeHtml(category.iconClass)}">${escapeHtml(category.icon)}</div>
          <div>
            <h3>${escapeHtml(category.name)}</h3>
            <p class="muted">${escapeHtml(description)}</p>
          </div>
          <div class="category-footer">
            <span class="category-count">${apiLabel}</span>
            <span class="category-arrow">→</span>
          </div>
        </a>
      `;
    }).join('');

    window.NexusEffects?.initScrollReveal?.();
  } catch (err) {
    grid.innerHTML = `<p class="muted center-text">${escapeHtml(err.message || 'Unable to load categories.')}</p>`;
  }
}

window.renderHomeCategories = renderHomeCategories;
