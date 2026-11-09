document.addEventListener('DOMContentLoaded', () => {
    const password = document.querySelector('#password');
    const toggle = document.querySelector('#toggle-password');
    const eye = document.querySelector('#eye-icon');
    const form = document.querySelector('#login-form');
    const submit = document.querySelector('#submit-button');

    toggle?.addEventListener('click', () => {
        if (!password || !eye) return;
        const showPassword = password.type === 'password';
        password.type = showPassword ? 'text' : 'password';
        eye.textContent = showPassword ? 'visibility_off' : 'visibility';
        toggle.setAttribute('aria-label', showPassword ? 'Ocultar senha' : 'Mostrar senha');
        toggle.setAttribute('aria-pressed', String(showPassword));
        password.focus();
    });

    form?.addEventListener('submit', () => {
        if (!submit) return;
        submit.disabled = true;
        submit.setAttribute('aria-busy', 'true');
        const label = submit.querySelector('span:last-child');
        if (label) label.textContent = 'Autenticando…';
        const icon = submit.querySelector('.material-symbols-outlined');
        if (icon) icon.textContent = 'progress_activity';
    });
});
