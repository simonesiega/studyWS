/**
 * Authentication API wrapper.
 *
 * Exposes register, login, logout and isAuthenticated helpers which use `http`.
 */
// src/api/auth.js
import { http } from './http';
import { tokenStore } from './tokenStore';

export const authApi = {
  /**
   * Register a new user and store tokens.
   *
   * @param {{email:string,password:string,first_name:string,last_name:string}} params
   * @returns {Promise<object>} The created user object
   */
  async register({ email, password, first_name, last_name }) {
    const res = await http.post('/auth/register', {
      email,
      password,
      first_name,
      last_name
    });

    tokenStore.setAccessToken(res.data.access_token);
    tokenStore.setRefreshToken(res.data.refresh_token);

    return res.data.user;
  },
  /*
  async login({ email, password }) {
    const res = await http.post('/auth/login', {
      email,
      password
    });

    tokenStore.setAccessToken(res.data.access_token);
    tokenStore.setRefreshToken(res.data.refresh_token);

    return res.data.user;
  },*/

  /**
   * Login a user and store tokens.
   *
   * @param {{email:string,password:string}} params
   * @returns {Promise<object>} The logged-in user object
   */
  async login({ email, password }) {
    const res = await http.post('/auth/login', {
      email,
      password
    });

    tokenStore.setAccessToken(res.data.access_token);
    tokenStore.setRefreshToken(res.data.refresh_token);

    return res.data.user;
  },

  /**
   * Logout current user on server and clear stored tokens.
   * @returns {Promise<void>}
   */
  async logout() {
    await http.post('/auth/logout');
    tokenStore.clear();
  },

  /**
   * Checks whether an access token is present.
   * @returns {boolean}
   */
  isAuthenticated() {
    return !!tokenStore.getAccessToken();
  }
};
