/**
 * Http - HTTP client with automatic JWT token handling
 *
 * Provides a fetch wrapper with the following features:
 * - Automatic Bearer token injection into requests
 * - Automatic token refresh when it expires (401)
 * - Centralized error handling
 * - Helper methods for GET and POST
 */
const Http = (function() {
  'use strict';

  /** @constant {string} Base URL for all API calls */
  const API_BASE_URL = '/api';

  /**
   * Executes an HTTP request with automatic authentication handling
   *
   * @async
   * @function request
   * @param {string} path Relative API path (e.g. '/auth/login')
   * @param {Object} [options={}] Fetch options
   * @param {Object} [options.headers={}] Additional HTTP headers
   * @param {string} [options.method='GET'] HTTP method
   * @param {string|Object} [options.body] Request body
   * @returns {Promise<any>} JSON response from the server
   * @throws {Error} If the request fails or the server responds with an error
   *
   * @description
   * This method:
   * 1. Automatically adds the Authorization header if a token is available
   * 2. Sets Content-Type: application/json by default
   * 3. Automatically refreshes the token on a 401 response
   * 4. Automatically parses the JSON response
   * 5. Throws an error if response.ok is false
   *
   * @example
   * // Simple GET
   * const data = await Http.request('/users/profile');
   *
   * // POST with body
   * const result = await Http.request('/auth/login', {
   *   method: 'POST',
   *   body: JSON.stringify({ email, password })
   * });
   */
  async function request(path, options = {}) {
    // Build headers with a default Content-Type
    const headers = {
      'Content-Type': 'application/json',
      ...(options.headers || {})
    };

    // Automatic Bearer token injection
    const accessToken = TokenStore.getAccessToken();
    if (accessToken) {
      headers.Authorization = `Bearer ${accessToken}`;
    }

    // Execute request
    const response = await fetch(`${API_BASE_URL}${path}`, {
      ...options,
      headers
    });

    // If we get a 401 Unauthorized, try to refresh the token and retry the request once
    if (response.status === 401 && TokenStore.getRefreshToken()) {
      const refreshed = await refreshAccessToken();
      if (refreshed) {
        // Retry the original request with the new token
        return request(path, options);
      }
    }

    // Parse JSON response
    const json = await response.json();

    // Handle HTTP errors
    if (!response.ok) {
      throw new Error(json.error || 'Request failed');
    }

    return json;
  }

  /**
   * Renews the access token using the refresh token
   *
   * @async
   * @function refreshAccessToken
   * @returns {Promise<boolean>} True if refresh succeeded, false otherwise
   * @description
   * This method is called automatically when a request
   * receives a 401 response. It sends the refresh token to the server and
   * updates TokenStore with the new tokens.
   *
   * If the refresh fails (e.g. refresh token expired or invalid),
   * it automatically clears tokens to force a new login.
   *
   * @example
   * const success = await Http.refreshAccessToken();
   * if (!success) {
   *   // Redirect to login
   *   window.location.href = '/login';
   * }
   */
  async function refreshAccessToken() {
    try {
      // Send refresh request to the server
      const res = await fetch(`${API_BASE_URL}/auth/refresh`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          refresh_token: TokenStore.getRefreshToken()
        })
      });

      // If the server rejects the refresh, clear tokens
      if (!res.ok) {
        TokenStore.clearTokens();
        return false;
      }

      // Update tokens from the response
      const json = await res.json();

      if (json.tokens) {
        TokenStore.setTokens(json.tokens);
        return true;
      }

      return false;
    } 
    catch (error) {
      // On network errors or other failures, clear tokens
      TokenStore.clearTokens();
      return false;
    }
  }

  /**
   * Executes a GET request
   *
   * @async
   * @function get
   * @param {string} path API path
   * @returns {Promise<any>} Response data
   * @example
   * const user = await Http.get('/users/me');
   * const items = await Http.get('/items?limit=10');
   */
  function get(path) {
    return request(path, { method: 'GET' });
  }

  /**
   * Executes a POST request
   *
   * @async
   * @function post
   * @param {string} path API path
   * @param {Object} body Data to send (will be JSON.stringify-ed)
   * @returns {Promise<any>} Response data
   * @example
   * const result = await Http.post('/auth/login', {
   *   email: 'user@example.com',
   *   password: 'secret'
   * });
   */
  function post(path, body) {
    return request(path, {
      method: 'POST',
      body: JSON.stringify(body)
    });
  }

  return {
    // Core method
    request,

    // HTTP helpers
    get,
    post,

    // Token management
    refreshAccessToken
  };
})();
