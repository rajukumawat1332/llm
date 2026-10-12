// auth.js — password show/hide + live client-side validation for login.php / signup.php

document.querySelectorAll('.toggle-visibility').forEach(function (btn) {
  btn.addEventListener('click', function () {
    const target = document.getElementById(btn.dataset.target);
    const isPassword = target.type === 'password';
    target.type = isPassword ? 'text' : 'password';
    btn.textContent = isPassword ? 'Hide' : 'Show';
  });
});

/**
 * Shows/hides the field's error message and toggles the red border.
 * Reuses the .hint span already in the field if present, otherwise creates one.
 */
function setFieldError(inputEl, message) {
  const field = inputEl.closest('.field');
  if (!field) return;

  let errorHint = field.querySelector('.hint.error');
  if (!errorHint) {
    errorHint = document.createElement('span');
    errorHint.className = 'hint error';
    field.appendChild(errorHint);
  }

  if (message) {
    errorHint.textContent = message;
    errorHint.classList.add('show');
    field.classList.add('has-error');
  } else {
    errorHint.classList.remove('show');
    field.classList.remove('has-error');
  }
}

function validateEmail(inputEl) {
  const value = inputEl.value.trim();
  if (value === '') return setFieldError(inputEl, 'Email is required.'), false;
  if (!inputEl.checkValidity()) return setFieldError(inputEl, 'Enter a valid email address.'), false;
  setFieldError(inputEl, '');
  return true;
}

function validateMobile(inputEl) {
  const value = inputEl.value.trim();
  if (value === '') return setFieldError(inputEl, 'Mobile number is required.'), false;
  if (!/^\+?[0-9\s]{10,15}$/.test(value)) return setFieldError(inputEl, 'Enter a valid mobile number (10–15 digits).'), false;
  setFieldError(inputEl, '');
  return true;
}

function validatePassword(inputEl, { checkStrength = false } = {}) {
  const value = inputEl.value;
  if (value === '') return setFieldError(inputEl, 'Password is required.'), false;
  if (value.length < 8) return setFieldError(inputEl, 'Password must be at least 8 characters.'), false;
  if (checkStrength && !(/[A-Za-z]/.test(value) && /[0-9]/.test(value))) {
    return setFieldError(inputEl, 'Use at least one letter and one number.'), false;
  }
  setFieldError(inputEl, '');
  return true;
}

function validateConfirm(confirmEl, passwordEl) {
  const value = confirmEl.value;
  if (value === '') return setFieldError(confirmEl, 'Re-enter your password.'), false;
  if (value !== passwordEl.value) return setFieldError(confirmEl, "Passwords don't match."), false;
  setFieldError(confirmEl, '');
  return true;
}

// ---- Sign in page ----
const loginForm = document.querySelector('form[action="login.php"]');
if (loginForm) {
  const email = document.getElementById('email');
  const password = document.getElementById('password');

  email.addEventListener('blur', () => validateEmail(email));
  password.addEventListener('blur', () => validatePassword(password));

  loginForm.addEventListener('submit', function (e) {
    const emailOk = validateEmail(email);
    const passwordOk = validatePassword(password);
    if (!emailOk || !passwordOk) e.preventDefault();
  });
}

// ---- Sign up page ----
const signupForm = document.getElementById('signupForm');
if (signupForm) {
  const email = document.getElementById('email');
  const mobile = document.getElementById('mobile');
  const password = document.getElementById('password');
  const confirm = document.getElementById('confirm_password');

  email.addEventListener('blur', () => validateEmail(email));
  mobile.addEventListener('blur', () => validateMobile(mobile));
  password.addEventListener('blur', () => validatePassword(password, { checkStrength: true }));
  confirm.addEventListener('input', () => {
    if (confirm.value.length > 0) validateConfirm(confirm, password);
  });
  confirm.addEventListener('blur', () => validateConfirm(confirm, password));

  signupForm.addEventListener('submit', function (e) {
    const emailOk = validateEmail(email);
    const mobileOk = validateMobile(mobile);
    const passwordOk = validatePassword(password, { checkStrength: true });
    const confirmOk = validateConfirm(confirm, password);
    if (!emailOk || !mobileOk || !passwordOk || !confirmOk) e.preventDefault();
  });
}
