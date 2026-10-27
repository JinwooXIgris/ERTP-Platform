(() => {
  const form = document.querySelector('#register-form');
  if (!form) return;

  const alertBox = document.querySelector('#form-alert');
  const submitButton = document.querySelector('#submit-button');
  const fields = {
    name: document.querySelector('#full-name'),
    email: document.querySelector('#email'),
    phone: document.querySelector('#phone'),
    type: document.querySelector('#attendee-type'),
    password: document.querySelector('#password'),
    confirm: document.querySelector('#confirm-password'),
    terms: document.querySelector('#terms')
  };
  const errorTargets = {
    name: document.querySelector('#name-error'),
    email: document.querySelector('#email-error'),
    phone: document.querySelector('#phone-error'),
    type: document.querySelector('#type-error'),
    password: document.querySelector('#password-error'),
    confirm: document.querySelector('#confirm-error'),
    terms: document.querySelector('#terms-error')
  };

  document.querySelector('#current-year').textContent = new Date().getFullYear();

  document.querySelectorAll('.password-toggle').forEach((button) => {
    button.addEventListener('click', () => {
      const input = document.getElementById(button.dataset.target);
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      button.textContent = show ? 'Hide' : 'Show';
      button.setAttribute('aria-label', `${show ? 'Hide' : 'Show'} ${button.dataset.target === 'password' ? 'password' : 'confirmation password'}`);
      input.focus();
    });
  });

  function setError(key, message) {
    errorTargets[key].textContent = message || '';
    if (key === 'terms') {
      fields.terms.setAttribute('aria-invalid', message ? 'true' : 'false');
      return;
    }
    fields[key].classList.toggle('invalid-field', Boolean(message));
    fields[key].setAttribute('aria-invalid', message ? 'true' : 'false');
  }

  function validate() {
    let valid = true;
    const name = fields.name.value.trim();
    const email = fields.email.value.trim();
    const phone = fields.phone.value.trim();
    const password = fields.password.value;

    const checks = {
      name: name.length >= 2 ? '' : 'Enter your full name (at least 2 characters).',
      email: /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) ? '' : 'Enter a valid email address.',
      phone: phone.replace(/\D/g, '').length >= 7 ? '' : 'Enter a valid phone number.',
      type: fields.type.value ? '' : 'Choose your attendee type.',
      password: password.length >= 8 ? '' : 'Your password must be at least 8 characters.',
      confirm: fields.confirm.value && fields.confirm.value === password ? '' : 'Your passwords do not match.',
      terms: fields.terms.checked ? '' : 'Please confirm the terms before continuing.'
    };

    Object.entries(checks).forEach(([key, message]) => {
      setError(key, message);
      if (message) valid = false;
    });
    return valid;
  }

  function showAlert(message, type = 'error') {
    alertBox.textContent = message;
    alertBox.className = `form-alert ${type}`;
    alertBox.hidden = false;
    alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function clearAlert() {
    alertBox.hidden = true;
    alertBox.textContent = '';
    alertBox.className = 'form-alert';
  }

  Object.values(fields).forEach((field) => {
    field.addEventListener('input', () => {
      clearAlert();
      // Keep feedback responsive after the first submit attempt.
      if (form.dataset.attempted === 'true') validate();
    });
    field.addEventListener('change', () => {
      if (form.dataset.attempted === 'true') validate();
    });
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearAlert();
    form.dataset.attempted = 'true';
    if (!validate()) {
      const firstInvalid = form.querySelector('[aria-invalid="true"]');
      if (firstInvalid) firstInvalid.focus();
      return;
    }

    if (typeof apiRequest !== 'function') {
      showAlert('The form is ready, but the shared API helper could not load. Check the script path and try again.');
      return;
    }

    submitButton.disabled = true;
    submitButton.querySelector('span').textContent = 'Creating your account…';

    try {
      const result = await apiRequest('register.php', {
        method: 'POST',
        body: {
          full_name: fields.name.value.trim(),
          name: fields.name.value.trim(),
          email: fields.email.value.trim(),
          phone: fields.phone.value.trim(),
          password: fields.password.value,
          attendee_type: fields.type.value
        }
      });
      showAlert(result.message || 'Your account has been created. You can now sign in.', 'success');
      form.reset();
      form.dataset.attempted = 'false';
      window.setTimeout(() => { window.location.href = '../login/login.html?registered=1'; }, 1300);
    } catch (error) {
      const details = Array.isArray(error.details) ? error.details : [];
      const fieldErrors = details.filter((item) => item && item.field && item.message);
      if (fieldErrors.length) {
        const fieldMap = { name: 'name', full_name: 'name', email: 'email', phone: 'phone', attendee_type: 'type', password: 'password' };
        fieldErrors.forEach((item) => {
          const key = fieldMap[item.field];
          if (key) setError(key, item.message);
        });
      }
      showAlert(error.message || 'We could not create your account. Please try again.');
    } finally {
      submitButton.disabled = false;
      submitButton.querySelector('span').textContent = 'Create my account';
    }
  });
})();
