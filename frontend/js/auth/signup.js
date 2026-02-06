import { authApi } from 'auth.js';

const $ = (sel) => document.querySelector(sel);

// SIGNUP
const signupSection = $('.signup-form');
const signupBtn = signupSection.querySelector('button.primary');
signupBtn.addEventListener('click', async () => {
    const username = signupSection.querySelector('input[type="text"]').value;
    const email = signupSection.querySelector('input[type="email"]').value;
    const password = signupSection.querySelector('input[type="password"]').value;

    const user = await authApi.register({ username, email, password });
});