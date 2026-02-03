import { authApi } from '../auth.js';
import { tokenStore } from '../tokenStore.js';

const $ = (sel) => document.querySelector(sel);
const out = $('#output');

function show(obj, label = ''){
  const header = label ? `${label} — ${new Date().toISOString()}` : new Date().toISOString();
  const payload = typeof obj === 'string' ? obj : JSON.stringify(obj, null, 2);
  out.textContent = `${header}\n${payload}\n\n` + out.textContent;
}

function showError(err, label='Error'){
  show((err && err.message) ? err.message : String(err), label);
  console.error(err);
}

// Register
$('#regBtn').addEventListener('click', async () => {
  const payload = {
    email: $('#reg_email').value,
    password: $('#reg_pass').value,
    first_name: $('#reg_first').value,
    last_name: $('#reg_last').value
  };

  try{
    const user = await authApi.register(payload);
    show(user, 'Register success');
  }catch(err){
    showError(err, 'Register failed');
  }
});

$('#regSample').addEventListener('click', () => {
  $('#reg_first').value = 'Mario';
  $('#reg_last').value = 'Rossi';
  $('#reg_email').value = `mario.rossi+${Date.now()}@example.com`;
  $('#reg_pass').value = 'password123';
});

// Login
$('#loginBtn').addEventListener('click', async () => {
  const payload = {
    email: $('#login_email').value,
    password: $('#login_pass').value
  };

  try{
    const user = await authApi.login(payload);
    show(user, 'Login success');
  }catch(err){
    showError(err, 'Login failed');
  }
});

$('#loginSample').addEventListener('click', () => {
  $('#login_email').value = 'mario.rossi+test@example.com';
  $('#login_pass').value = 'password123';
});

// Logout
$('#logoutBtn').addEventListener('click', async () => {
  try{
    await authApi.logout();
    show('Logged out', 'Logout');
  }catch(err){
    showError(err, 'Logout failed');
  }
});

$('#checkAuthBtn').addEventListener('click', () => {
  const ok = authApi.isAuthenticated();
  show({ isAuthenticated: ok }, 'Auth status');
});

$('#showTokensBtn').addEventListener('click', () => {
  show({ access: tokenStore.getAccessToken(), refresh: tokenStore.getRefreshToken() }, 'Tokens');
});

$('#clearOutput').addEventListener('click', () => { out.textContent = ''; });

show('Test UI loaded. Note: requests go to the configured API base URL; CORS or network errors may appear here.');
