/**
 * AuthApi - API for authentication management
 *
 * Provides methods for all authentication operations:
 * - New user registration
 * - Credential-based login
 * - Logout
 * - Authentication state check
 *
 * @module AuthApi
 * @requires Http
 * @requires TokenStore
 */
const AuthApi = (function() {
  'use strict';

  /**
   * Registers a new user in the system
   *
   * @async
   * @function register
   * @param {Object} params Registration parameters
   * @param {string} params.email User email (used as identifier)
   * @param {string} params.password Plain-text password (will be hashed by the server)
   * @param {string} [params.first_name] User first name (optional)
   * @param {string} [params.last_name] User last name (optional)
   * @returns {Promise<Object>} Server response containing tokens and user data
   * @throws {Error} If registration fails (e.g. email already exists)
   *
   * @description
   * Creates a new user account. If registration succeeds,
   * the server automatically returns authentication tokens,
   * which are stored in TokenStore so the user is immediately
   * logged in after registration.
   *
   * @example
   * try {
   *   const result = await AuthApi.register({
   *     email: 'mario.rossi@example.com',
   *     password: 'SecurePass123!',
   *     first_name: 'Mario',
   *     last_name: 'Rossi'
   *   });
   *   console.log('Registration completed:', result.user);
   * } catch (error) {
   *   console.error('Registration error:', error.message);
   * }
   */
  async function register({ email, password, first_name, last_name }) {
    // Registration API call
    const res = await Http.post('/auth/register', {
      email,
      password,
      first_name,
      last_name
    });

    // If registration succeeds and returns tokens,
    // store them automatically to authenticate the user
    if (res.tokens) {
      TokenStore.setTokens(res.tokens);
    }

    return res;
  }


  /**
   * Logs in using credentials
   *
   * @async
   * @function login
   * @param {Object} params Login credentials
   * @param {string} params.email User email
   * @param {string} params.password User password
   * @returns {Promise<Object>} Server response containing tokens and user data
   * @throws {Error} If credentials are invalid or the user does not exist
   *
   * @description
   * Authenticates an existing user. On success, it stores the tokens
   * in TokenStore for subsequent requests. Throws an error if
   * the provided credentials are not valid.
   *
   * @example
   * try {
   *   const result = await AuthApi.login({
   *     email: 'mario.rossi@example.com',
   *     password: 'SecurePass123!'
   *   });
   *   console.log('Logged in:', result.user);
   *   // Redirect to dashboard
   *   window.location.href = '/dashboard';
   * } catch (error) {
   *   console.error('Login failed:', error.message);
   *   // Show error to the user
   * }
   */
  async function login({ email, password }) {
    // Login API call
    const res = await Http.post('/auth/login', {
      email,
      password
    });

    // Store tokens for automatic authentication
    if (res.tokens) {
      TokenStore.setTokens(res.tokens);
    }

    return res;
  }


  /**
   * Logs out the current user
   *
   * @async
   * @function logout
   * @returns {Promise<Object>} Server response
   * @throws {Error} If the server request fails
   *
   * @description
   * Invalidates the current session on the server and clears local tokens.
   * Even if the server call fails, local tokens are still removed to ensure
   * the client considers the user unauthenticated.
   *
   * Note: This method always clears tokens, even when the HTTP request fails,
   * to prevent the user from getting stuck in an inconsistent state.
   *
   * @example
   * try {
   *   await AuthApi.logout();
   *   console.log('Logged out');
   *   window.location.href = '/login';
   * } catch (error) {
   *   // Even if there is an error, tokens have been cleared
   *   console.warn('Logout error:', error.message);
   *   window.location.href = '/login';
   * }
   */
  async function logout() {
    try {
      // Notify the server that the session is being closed
      const res = await Http.post('/auth/logout');

      // Clear local tokens
      TokenStore.clearTokens();

      return res;
    }
    catch (error) {
      // Even in case of error, clear local tokens
      // to ensure a consistent state
      TokenStore.clearTokens();

      // Re-throw the error so the caller can handle it
      throw error;
    }
  }

  /**
   * Checks whether the user is currently authenticated
   *
   * @function isAuthenticated
   * @returns {boolean} True if a valid access token exists
   * @description
   * Checks whether an access token is present in TokenStore.
   * Note: this method does not verify whether the token is expired,
   * only that it exists and is not empty.
   *
   * To verify time validity you must check the JWT 'exp' claim (decoded)
   * or make a request to the server.
   *
   * @example
   * if (AuthApi.isAuthenticated()) {
   *   // Show UI for logged-in users
   *   showUserMenu();
   * } else {
   *   // Show login button
   *   showLoginButton();
   * }
   */
  function isAuthenticated() {
    return TokenStore.hasValidToken();
  }
  
  return {
    // Authentication operations
    register,
    login,
    logout,

    // State check
    isAuthenticated
  };
})();
