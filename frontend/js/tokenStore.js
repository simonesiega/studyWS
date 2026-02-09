/**
 * TokenStore - Centralized management of JWT tokens (access and refresh)
 *
 * Provides an interface for managing authentication tokens
 * in memory. It keeps token state separate from business logic,
 * enabling controlled and consistent access across the entire application.
 */
const TokenStore = (function() {
  'use strict';

  /** @type {string|null} JWT token used to access protected resources */
  let accessToken = null;

  /** @type {string|null} JWT token used to refresh the access token */
  let refreshToken = null;

  /**
   * Retrieves the current access token
   *
   * @function getAccessToken
   * @returns {string|null} The JWT access token, or null if not set
   * 
   * @example
   * const token = TokenStore.getAccessToken();
   * if (token) {
   *   headers.Authorization = `Bearer ${token}`;
   * }
   */
  function getAccessToken() {
    return accessToken;
  }

  /**
   * Sets the access token
   *
   * @function setAccessToken
   * @param {string|null} token The JWT token to store, or null to remove it
   * @description
   * Overwrites any previous token. Use null to log out
   * or to invalidate the current session.
   */
  function setAccessToken(token) {
    accessToken = token;
  }

  /**
   * Retrieves the current refresh token
   *
   * @function getRefreshToken
   * @returns {string|null} The JWT refresh token, or null if not set
   * @description
   * The refresh token is used to obtain a new access token
   * when the current one expires, without asking the user
   * to re-enter their credentials.
   */
  function getRefreshToken() {
    return refreshToken;
  }

  /**
   * Sets the refresh token
   *
   * @function setRefreshToken
   * @param {string|null} token The JWT refresh token to store
   */
  function setRefreshToken(token) {
    refreshToken = token;
  }

  /**
   * Clears both tokens (logout)
   *
   * @function clearTokens
   * @description
   * Completely removes the authentication state.
   * Call this on logout, authentication errors,
   * or whenever tokens are no longer valid.
   */
  function clearTokens() {
    accessToken = null;
    refreshToken = null;
  }

  /**
   * Sets both tokens in a single operation
   *
   * @function setTokens
   * @param {Object} tokens Object containing tokens
   * @param {string} [tokens.accessToken] JWT access token
   * @param {string} [tokens.refreshToken] JWT refresh token
   * @description
   * Typically used after login or session refresh
   * to update both tokens atomically.
   *
   * @example
   * TokenStore.setTokens({
   *   accessToken: response.tokens.accessToken,
   *   refreshToken: response.tokens.refreshToken
   * });
   */
  function setTokens(tokens) {
    // Only update tokens if they are provided in the input object
    if (tokens.accessToken) {
      accessToken = tokens.accessToken;
    }

    if (tokens.refreshToken) {
      refreshToken = tokens.refreshToken;
    }
  }

  /**
   * Retrieves both tokens
   *
   * @function getTokens
   * @returns {Object} Object containing the current tokens
   * @returns {string|null} returns.accessToken
   * @returns {string|null} returns.refreshToken
   */
  function getTokens() {
    return {
      accessToken,
      refreshToken
    };
  }

  /**
   * Checks whether a valid-looking access token is present
   *
   * @function hasValidToken
   * @returns {boolean} True if a non-empty access token exists
   * @description
   * This does not check the token's time validity (expiration),
   * only whether a non-null token is present.
   */
  function hasValidToken() {
    return accessToken !== null && accessToken !== undefined && accessToken !== '';
  }

  return {
    // Access token
    getAccessToken,
    setAccessToken,

    // Refresh token
    getRefreshToken,
    setRefreshToken,

    // Batch operations
    clearTokens,
    setTokens,
    getTokens,

    // Validation
    hasValidToken
  };
})();
