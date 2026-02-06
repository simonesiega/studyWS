const Http = (function() {
  'use strict';

  const API_BASE_URL = '/api';

  /**
   * Make a JSON request to the API
   * @param {string} path - API path (e.g., '/auth/login')
   * @param {Object} [options={}] - Fetch options
   * @returns {Promise<any>}
   */
  async function request(path, options = {}) {
    const headers = {
      'Content-Type': 'application/json',
      ...(options.headers || {})
    };

    const accessToken = TokenStore.getAccessToken();
    if (accessToken) {
      headers.Authorization = `Bearer ${accessToken}`;
    }

    const response = await fetch(`${API_BASE_URL}${path}`, {
      ...options,
      headers
    });

    // Token scaduto → tenta refresh
    if (response.status === 401 && TokenStore.getRefreshToken()) {
      const refreshed = await refreshAccessToken();
      if (refreshed) {
        return request(path, options);
      }
    }

    const json = await response.json();

    if (!response.ok) {
      throw new Error(json.error || 'Request failed');
    }

    return json;
  }

  /**
   * Refresh access token
   * @returns {Promise<boolean>}
   */
  async function refreshAccessToken() {
    try {
      const res = await fetch(`${API_BASE_URL}/auth/refresh`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          refresh_token: TokenStore.getRefreshToken()
        })
      });

      if (!res.ok) {
        TokenStore.clearTokens();
        return false;
      }

      const json = await res.json();
      
      if (json.tokens) {
        TokenStore.setTokens(json.tokens);
        return true;
      }

      return false;
    } catch (error) {
      TokenStore.clearTokens();
      return false;
    }
  }

  /**
   * GET request
   * @param {string} path
   * @returns {Promise<any>}
   */
  function get(path) {
    return request(path, { method: 'GET' });
  }

  /**
   * POST request
   * @param {string} path
   * @param {Object} body
   * @returns {Promise<any>}
   */
  function post(path, body) {
    return request(path, {
      method: 'POST',
      body: JSON.stringify(body)
    });
  }

  // Public API
  return {
    request,
    get,
    post,
    refreshAccessToken
  };
})();
