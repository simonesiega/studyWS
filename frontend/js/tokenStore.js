const TokenStore = (function() {
  'use strict';

  let accessToken = null;
  let refreshToken = null;

  /**
   * Get current access token
   * @returns {string|null}
   */
  function getAccessToken() {
    return accessToken;
  }

  /**
   * Set the access token
   * @param {string|null} token
   */
  function setAccessToken(token) {
    accessToken = token;
  }

  /**
   * Get current refresh token
   * @returns {string|null}
   */
  function getRefreshToken() {
    return refreshToken;
  }

  /**
   * Set the refresh token
   * @param {string|null} token
   */
  function setRefreshToken(token) {
    refreshToken = token;
  }

  /**
   * Clear both tokens
   */
  function clearTokens() {
    accessToken = null;
    refreshToken = null;
  }

  /**
   * Set both tokens at once
   * @param {Object} tokens
   * @param {string} tokens.accessToken
   * @param {string} tokens.refreshToken
   */
  function setTokens(tokens) {
    if (tokens.accessToken) {
      accessToken = tokens.accessToken;
    }
    if (tokens.refreshToken) {
      refreshToken = tokens.refreshToken;
    }
  }

  /**
   * Get both tokens
   * @returns {Object}
   */
  function getTokens() {
    return {
      accessToken,
      refreshToken
    };
  }

  /**
   * Check if there's a valid access token
   * @returns {boolean}
   */
  function hasValidToken() {
    return accessToken !== null && accessToken !== undefined && accessToken !== '';
  }

  // Public API
  return {
    getAccessToken,
    setAccessToken,
    getRefreshToken,
    setRefreshToken,
    clearTokens,
    setTokens,
    getTokens,
    hasValidToken
  };
})();
