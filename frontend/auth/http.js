/**
 * HTTP helper module for auth-related requests.
 *
 * Provides a small wrapper around fetch to:
 * - attach access tokens from `tokenStore`
 * - automatically attempt refresh when a 401 occurs
 * - parse JSON responses and throw on HTTP errors
 *
 * @module http
 */
import { tokenStore } from './tokenStore';

const API_BASE_URL = 'http://localhost:8080';

/**
 * Make a JSON request to the API.
 *
 * Attaches Authorization header when an access token is present and
 * automatically retries the request once if a 401 is returned and refresh succeeds.
 *
 * @param {string} path - The path on the API (e.g. '/auth/login')
 * @param {RequestInit} [options={}] - Fetch options; body must be a JSON string when sending data
 * @returns {Promise<any>} The parsed JSON response
 * @throws {Error} When the response is not ok. Error message is taken from response JSON `error`.
 */
async function request(path, options = {}) {
  const headers = {
    'Content-Type': 'application/json',
    ...(options.headers || {})
  };

  const accessToken = tokenStore.getAccessToken();
  if (accessToken) {
    headers.Authorization = `Bearer ${accessToken}`;
  }

  const response = await fetch(`${API_BASE_URL}${path}`, {
    ...options,
    headers
  });

  // Access token scaduto → tenta refresh
  if (response.status === 401 && tokenStore.getRefreshToken()) {
    const refreshed = await refreshToken();
    if (refreshed) {
      return request(path, options); // retry
    }
  }

  const json = await response.json();

  if (!response.ok) {
    throw new Error(json.error || 'Request failed');
  }

  return json;
}

/**
 * Attempt to refresh the access token using the stored refresh token.
 *
 * On success updates tokens in `tokenStore`.
 * On failure clears stored tokens.
 *
 * @returns {Promise<boolean>} True if refresh succeeded, false otherwise.
 */
async function refreshToken() {
  try {
    const res = await fetch(`${API_BASE_URL}/auth/refresh`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        refresh_token: tokenStore.getRefreshToken()
      })
    });

    if (!res.ok) return false;

    const json = await res.json();

    tokenStore.setAccessToken(json.data.access_token);
    tokenStore.setRefreshToken(json.data.refresh_token);

    return true;
  } catch {
    tokenStore.clear();
    return false;
  }
}

/**
 * HTTP helper methods
 * @type {{get:(path:string)=>Promise<any>, post:(path:string, body:object)=>Promise<any>}}
 */
export const http = {
  /**
   * GET request helper
   * @param {string} path
   * @returns {Promise<any>}
   */
  get: (path) => request(path),
  /**
   * POST request helper that sends JSON
   * @param {string} path
   * @param {Object} body
   * @returns {Promise<any>}
   */
  post: (path, body) =>
    request(path, {
      method: 'POST',
      body: JSON.stringify(body)
    })
};
