const API_BASE = '/api';

async function apiRequest(path, options = {}) {
  const response = await fetch(`${API_BASE}${path}`, {
    credentials: 'include',
    headers: { 'Content-Type': 'application/json', ...(options.headers || {}) },
    ...options,
  });

  let data = {};
  try {
    data = await response.json();
  } catch (_) {}

  if (!response.ok) {
    throw new Error(data.message || 'Request failed');
  }
  return data;
}

window.NexusApi = {
  getApis: () => apiRequest('/public/apis'),
  getCategories: () => apiRequest('/public/categories'),
  getCoinPackages: () => apiRequest('/public/coin-packages'),
  register: (payload) => apiRequest('/auth/register', { method: 'POST', body: JSON.stringify(payload) }),
  resendVerification: (email) => apiRequest('/auth/resend-verification', { method: 'POST', body: JSON.stringify({ email }) }),
  login: (payload) => apiRequest('/auth/login', { method: 'POST', body: JSON.stringify(payload) }),
  me: () => apiRequest('/auth/me'),
  verifyEmail: (token) => apiRequest(`/auth/verify?token=${encodeURIComponent(token)}`),
  logout: () => apiRequest('/auth/logout', { method: 'POST', body: '{}' }),
  recharge: (coins, packageId = null, custom = false) => apiRequest('/user/recharge', {
    method: 'POST',
    body: JSON.stringify({ coins, packageId, custom }),
  }),
  purchase: (apiId) => apiRequest('/user/purchases', { method: 'POST', body: JSON.stringify({ apiId }) }),
};
