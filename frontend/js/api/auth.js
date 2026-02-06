const AuthApi = (function() {
  'use strict';

  /**
   * Register a new user
   * @param {Object} params
   * @param {string} params.email
   * @param {string} params.password
   * @param {string} [params.first_name]
   * @param {string} [params.last_name]
   * @returns {Promise<Object>}
   */
  async function register({ email, password, first_name, last_name }) {
    const res = await Http.post('/auth/register', {
      email,
      password,
      first_name,
      last_name
    });

    if (res.tokens) {
      TokenStore.setTokens(res.tokens);
    }

    return res;
  }

  /**
   * Login user
   * @param {Object} params
   * @param {string} params.email
   * @param {string} params.password
   * @returns {Promise<Object>}
   */
  async function login({ email, password }) {
    const res = await Http.post('/auth/login', {
      email,
      password
    });

    if (res.tokens) {
      TokenStore.setTokens(res.tokens);
    }

    return res;
  }

  /**
   * Logout user
   * @returns {Promise<Object>}
   */
  async function logout() {
    try {
      const res = await Http.post('/auth/logout');
      TokenStore.clearTokens();
      return res;
    } catch (error) {
      TokenStore.clearTokens();
      throw error;
    }
  }

  /**
   * Check if user is authenticated
   * @returns {boolean}
   */
  function isAuthenticated() {
    return TokenStore.hasValidToken();
  }

  // Public API
  return {
    register,
    login,
    logout,
    isAuthenticated
  };
})();
