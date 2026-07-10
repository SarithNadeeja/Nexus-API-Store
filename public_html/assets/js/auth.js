window.NexusAuth = {
  user: null,
  loading: true,

  async refresh() {
    this.loading = true;
    try {
      const session = await NexusApi.me();
      this.user = session.authenticated ? session : null;
    } catch (_) {
      this.user = null;
    }
    this.loading = false;
    document.dispatchEvent(new CustomEvent('nexus-auth-changed'));
    return this.user;
  },

  async login(email, password) {
    const session = await NexusApi.login({ email, password });
    this.user = session;
    document.dispatchEvent(new CustomEvent('nexus-auth-changed'));
    return session;
  },

  async register(fullName, email, password) {
    return NexusApi.register({ fullName, email, password });
  },

  async logout() {
    await NexusApi.logout();
    this.user = null;
    document.dispatchEvent(new CustomEvent('nexus-auth-changed'));
  },
};

document.addEventListener('DOMContentLoaded', () => NexusAuth.refresh());
