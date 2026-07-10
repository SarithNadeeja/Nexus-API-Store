function renderNavbar() {
  const el = document.getElementById('navbar');
  if (!el) return;

  const path = location.pathname.replace(/\/$/, '') || '/index.html';
  const user = NexusAuth.user;

  el.innerHTML = `
    <div class="container navbar-inner">
      <a href="/index.html" class="gradient-text" style="font-weight:700;font-size:1.1rem;">⚡ Nexus API Store</a>
      <div class="nav-links">
        <a href="/index.html" class="${path.endsWith('index.html') || path === '/' ? 'active' : ''}">Home</a>
        <a href="/api-keys.html" class="${path.includes('api-keys') ? 'active' : ''}">API Keys</a>
        <a href="/about-us.html" class="${path.includes('about-us') ? 'active' : ''}">About Us</a>
        ${user ? `
          <span class="coin-badge">${user.coinBalance} coins</span>
          <a href="/dashboard.html">Dashboard</a>
          <button class="btn btn-secondary" id="logout-btn" type="button">Logout</button>
        ` : `
          <a href="/login.html">Sign In</a>
          <a href="/api-keys.html" class="btn btn-primary">Get API Access</a>
        `}
      </div>
    </div>`;

  const logoutBtn = document.getElementById('logout-btn');
  if (logoutBtn) {
    logoutBtn.addEventListener('click', async () => {
      await NexusAuth.logout();
      location.href = '/index.html';
    });
  }
}

document.addEventListener('DOMContentLoaded', renderNavbar);
document.addEventListener('nexus-auth-changed', renderNavbar);
