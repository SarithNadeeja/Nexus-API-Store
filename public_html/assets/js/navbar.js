function isActive(path, to) {
  if (to === '/index.html' || to === '/') {
    return path.endsWith('index.html') || path === '/' || path === '';
  }
  return path.includes(to.replace('.html', ''));
}

function renderNavbar() {
  const el = document.getElementById('navbar');
  if (!el) return;

  const path = location.pathname.replace(/\/$/, '') || '/index.html';
  const user = NexusAuth.user;
  const navLinks = [
    { label: 'Home', to: '/index.html' },
    { label: 'API Keys', to: '/api-keys.html' },
    { label: 'About Us', to: '/about-us.html' },
  ];

  el.innerHTML = `
    <div class="container navbar-inner">
      <a href="/index.html" class="nav-brand">
        <span class="nav-logo">⚡</span>
        <span>Nexus API Store</span>
      </a>

      <div class="nav-links nav-links-desktop">
        ${navLinks.map((link) => `
          <a href="${link.to}" class="nav-link ${isActive(path, link.to) ? 'active' : ''}">${link.label}</a>
        `).join('')}
      </div>

      <div class="nav-actions nav-links-desktop">
        ${user ? `
          <a href="/dashboard.html" class="coin-badge coin-badge-link">
            <span class="coin-icon">◎</span> ${user.coinBalance} Coins
          </a>
          <div class="magnetic-wrap"><a href="/dashboard.html" class="btn btn-secondary btn-nav">Dashboard</a></div>
          <div class="magnetic-wrap"><button class="btn btn-primary btn-nav" id="logout-btn" type="button">Logout</button></div>
        ` : `
          <div class="magnetic-wrap"><a href="/login.html" class="btn btn-secondary btn-nav">Sign In</a></div>
          <div class="magnetic-wrap"><a href="/api-keys.html" class="btn btn-primary btn-nav">Get API Access</a></div>
        `}
      </div>

      <button class="mobile-menu-btn" id="mobile-menu-btn" type="button" aria-label="Open menu" aria-expanded="false">
        <span class="menu-icon-open">☰</span>
        <span class="menu-icon-close">✕</span>
      </button>
    </div>`;

  let overlay = document.getElementById('mobile-overlay');
  let drawer = document.getElementById('mobile-drawer');

  if (!overlay) {
    overlay = document.createElement('div');
    overlay.id = 'mobile-overlay';
    overlay.className = 'mobile-overlay';
    overlay.hidden = true;
    document.body.appendChild(overlay);
  }

  if (!drawer) {
    drawer = document.createElement('aside');
    drawer.id = 'mobile-drawer';
    drawer.className = 'mobile-drawer';
    drawer.hidden = true;
    document.body.appendChild(drawer);
  }

  drawer.innerHTML = `
      <div class="mobile-drawer-header">
        <span class="mobile-drawer-title">Menu</span>
        <button class="mobile-close-btn" id="mobile-close-btn" type="button" aria-label="Close menu">✕</button>
      </div>
      <nav class="mobile-drawer-nav">
        ${navLinks.map((link) => `
          <a href="${link.to}" class="mobile-nav-link ${isActive(path, link.to) ? 'active' : ''}">${link.label}</a>
        `).join('')}
      </nav>
      <div class="mobile-drawer-footer">
        ${user ? `
          <div class="coin-badge coin-badge-block">${user.coinBalance} Coins Available</div>
          <a href="/dashboard.html" class="btn btn-secondary btn-block">Dashboard</a>
          <button class="btn btn-primary btn-block" id="mobile-logout-btn" type="button">Logout</button>
        ` : `
          <a href="/login.html" class="btn btn-secondary btn-block">Sign In</a>
          <a href="/api-keys.html" class="btn btn-primary btn-block">Get API Access</a>
        `}
      </div>`;

  const menuBtn = document.getElementById('mobile-menu-btn');
  const closeBtn = document.getElementById('mobile-close-btn');

  function setMobileOpen(open) {
    const isDesktop = window.matchMedia('(min-width: 1024px)').matches;
    if (isDesktop) open = false;
    document.body.classList.toggle('mobile-menu-open', open);
    if (menuBtn) menuBtn.setAttribute('aria-expanded', String(open));
    if (overlay) overlay.hidden = !open;
    if (drawer) drawer.hidden = !open;
  }

  menuBtn?.addEventListener('click', () => setMobileOpen(!document.body.classList.contains('mobile-menu-open')));
  closeBtn?.addEventListener('click', () => setMobileOpen(false));
  overlay?.addEventListener('click', () => setMobileOpen(false));
  drawer?.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => setMobileOpen(false));
  });

  if (!window.__nexusMobileResizeBound) {
    window.__nexusMobileResizeBound = true;
    window.addEventListener('resize', () => {
      if (window.matchMedia('(min-width: 1024px)').matches) {
        setMobileOpen(false);
      }
    });
  }

  document.getElementById('logout-btn')?.addEventListener('click', async () => {
    await NexusAuth.logout();
    location.href = '/index.html';
  });

  document.getElementById('mobile-logout-btn')?.addEventListener('click', async () => {
    await NexusAuth.logout();
    setMobileOpen(false);
    location.href = '/index.html';
  });

  if (window.NexusEffects) {
    window.NexusEffects.initMagneticButtons();
  }

  setMobileOpen(false);
}

document.addEventListener('DOMContentLoaded', renderNavbar);
document.addEventListener('nexus-auth-changed', renderNavbar);
