import { authApi } from 'auth.js';

const $ = (sel) => document.querySelector(sel);

// LOGIN
const loginSection = $('.login-form');
const loginBtn = loginSection.querySelector('button.primary');
loginBtn.addEventListener('click', async () => {
    const email = loginSection.querySelector('input[type="text"]').value;
    const password = loginSection.querySelector('input[type="password"]').value;
    
    const user = await authApi.login({ email, password });
});

