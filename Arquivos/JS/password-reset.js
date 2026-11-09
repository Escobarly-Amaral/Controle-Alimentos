document.addEventListener('DOMContentLoaded', () => {
    const password = document.querySelector('#new-password');
    const toggle = document.querySelector('[data-toggle-password]');
    const eye = toggle?.querySelector('.material-symbols-outlined');

    toggle?.addEventListener('click', () => {
        if (!password || !eye) return;
        const showPassword = password.type === 'password';
        password.type = showPassword ? 'text' : 'password';
        eye.textContent = showPassword ? 'visibility_off' : 'visibility';
        toggle.setAttribute('aria-label', showPassword ? 'Ocultar senha' : 'Mostrar senha');
        toggle.setAttribute('aria-pressed', String(showPassword));
        password.focus();
    });

    const ruleRows = [...document.querySelectorAll('[data-rule]')];
    const updateRules = () => {
        if (!password) return;
        const value = password.value;
        const length = [...value].length >= 12;
        const hasCase = /[a-z]/.test(value) && /[A-Z]/.test(value);
        const hasNumber = /[0-9]/.test(value);
        const results = { length, case: hasCase, number: hasNumber };
        ruleRows.forEach((row) => row.classList.toggle('is-met', Boolean(results[row.dataset.rule])));
    };
    password?.addEventListener('input', updateRules);

    const countdown = document.querySelector('[data-countdown]');
    const resendButton = document.querySelector('[data-resend-button]');
    let seconds = Number(countdown?.dataset.seconds ?? 0);
    const renderCountdown = () => {
        if (!countdown || !resendButton) return;
        const remaining = Math.max(0, seconds);
        resendButton.disabled = remaining > 0;
        if (remaining > 0) {
            const minutes = String(Math.floor(remaining / 60)).padStart(2, '0');
            const secs = String(remaining % 60).padStart(2, '0');
            countdown.textContent = `Disponível em ${minutes}:${secs}`;
        } else {
            countdown.textContent = 'Você já pode solicitar outro link.';
        }
    };
    if (countdown && resendButton) {
        renderCountdown();
        if (seconds > 0) {
            const timer = window.setInterval(() => {
                seconds -= 1;
                renderCountdown();
                if (seconds <= 0) window.clearInterval(timer);
            }, 1000);
        }
    }
});
