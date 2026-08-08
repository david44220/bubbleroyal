(() => {
  const form = document.querySelector('#auth-form');
  const title = document.querySelector('#auth-title');
  const copy = document.querySelector('#auth-copy');
  const submit = document.querySelector('.auth-submit');
  const message = document.querySelector('#auth-message');
  const registerField = document.querySelector('.field-register');
  const tabs = [...document.querySelectorAll('.auth-tab')];
  let mode = new URLSearchParams(window.location.search).get('mode') === 'register' ? 'register' : 'login';

  const csrfToken = () => document.cookie.split(';').map((item) => item.trim()).find((item) => item.startsWith('br_csrf='))?.slice(8) || '';
  const updateMode = () => {
    const register = mode === 'register';
    title.textContent = register ? 'Create your account.' : 'Enter the arena.';
    copy.textContent = register ? 'Keep your verified virtual progression across devices.' : 'Access your Bubble Royale player account.';
    submit.childNodes[0].textContent = register ? 'Create account ' : 'Log in ';
    registerField.hidden = !register;
    registerField.querySelector('input').required = register;
    tabs.forEach((tab) => {
      const active = tab.dataset.mode === mode;
      tab.classList.toggle('is-active', active);
      tab.setAttribute('aria-selected', String(active));
    });
  };

  tabs.forEach((tab) => tab.addEventListener('click', () => { mode = tab.dataset.mode; updateMode(); }));
  updateMode();

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    message.textContent = '';
    message.className = 'auth-message';
    const data = Object.fromEntries(new FormData(form).entries());
    const headers = { 'Content-Type': 'application/json', Accept: 'application/json' };
    const csrf = csrfToken();
    if (csrf) headers['X-CSRF-Token'] = csrf;
    submit.disabled = true;
    try {
      const response = await fetch(`/api/v1/auth/${mode}`, { method: 'POST', credentials: 'same-origin', headers, body: JSON.stringify(data) });
      const result = await response.json();
      if (!response.ok) throw new Error(result.message || result.error || 'Unable to complete the request.');
      message.textContent = 'Access granted. Opening the arena…';
      message.classList.add('is-success');
      window.setTimeout(() => { window.location.href = '/practice/'; }, 500);
    } catch (error) {
      message.textContent = error instanceof Error ? error.message : 'Unable to complete the request.';
      message.classList.add('is-error');
    } finally {
      submit.disabled = false;
    }
  });
})();
