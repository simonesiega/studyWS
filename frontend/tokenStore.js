let accessToken = null;
let refreshToken = null;

export const tokenStore = {
  /**
   * Get current access token.
   * @returns {string|null}
   */
  getAccessToken() {
    return accessToken;
  },

  /**
   * Set the access token.
   * @param {string|null} token
   */
  setAccessToken(token) {
    accessToken = token;
  },

  /**
   * Get current refresh token.
   * @returns {string|null}
   */
  getRefreshToken() {
    return refreshToken;
  },

  /**
   * Set the refresh token.
   * @param {string|null} token
   */
  setRefreshToken(token) {
    refreshToken = token;
  },

  /**
   * Clear both access and refresh tokens.
   */
  clear() {
    accessToken = null;
    refreshToken = null;
  }
};
