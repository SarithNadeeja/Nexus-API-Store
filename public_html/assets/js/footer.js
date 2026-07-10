function renderFooter() {
  const el = document.getElementById('site-footer');
  if (!el) return;

  const columns = {
    Platform: [
      { label: 'Home', to: '/index.html' },
      { label: 'API Keys', to: '/api-keys.html' },
      { label: 'Dashboard', to: '/dashboard.html' },
      { label: 'Status', to: '#' },
    ],
    Company: [
      { label: 'About Us', to: '/about-us.html' },
      { label: 'Contact', to: '#' },
      { label: 'Terms', to: '#' },
      { label: 'Privacy', to: '#' },
    ],
    Developers: [
      { label: 'Authentication', to: '/login.html' },
      { label: 'Coin Wallet', to: '/dashboard.html' },
      { label: 'API Marketplace', to: '/api-keys.html' },
      { label: 'Support', to: '#' },
    ],
    Security: [
      { label: 'Responsible Use', to: '#' },
      { label: 'Security Policy', to: '/index.html#security' },
      { label: 'Report Issue', to: '#' },
    ],
  };

  el.innerHTML = `
    <div class="container footer-inner">
      <div class="footer-grid">
        <div class="footer-brand">
          <a href="/index.html" class="nav-brand">
            <span class="nav-logo">⚡</span>
            <span>Nexus API Store</span>
          </a>
          <p class="footer-copy">A premium API marketplace where users recharge coins and unlock secure API access.</p>
        </div>
        ${Object.entries(columns).map(([title, links]) => `
          <div>
            <h4 class="footer-title">${title}</h4>
            <ul class="footer-links">
              ${links.map((link) => `<li><a href="${link.to}">${link.label}</a></li>`).join('')}
            </ul>
          </div>
        `).join('')}
      </div>
      <div class="footer-bottom">
        <p>&copy; 2026 Nexus API Store. All rights reserved.</p>
        <div class="footer-bottom-links">
          <a href="#">Terms</a>
          <a href="#">Privacy</a>
          <a href="/index.html#security">Security</a>
        </div>
      </div>
    </div>`;
}

document.addEventListener('DOMContentLoaded', renderFooter);
