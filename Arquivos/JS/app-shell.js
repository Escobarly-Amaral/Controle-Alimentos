document.addEventListener('DOMContentLoaded', () => {
    const body = document.body;
    const toggle = document.querySelector('[data-sidebar-toggle]');
    const closeButton = document.querySelector('[data-sidebar-close]');
    const sidebar = document.querySelector('#app-sidebar');
    const mobileLayout = window.matchMedia('(max-width: 700px)');

    const syncResponsiveSidebar = () => {
        if (!sidebar) return;
        if (!mobileLayout.matches) {
            body.classList.remove('sidebar-open');
            sidebar.inert = false;
            toggle?.setAttribute('aria-expanded', 'false');
        } else if (!body.classList.contains('sidebar-open')) {
            sidebar.inert = true;
        }
    };

    const setOpen = (open) => {
        body.classList.toggle('sidebar-open', open);
        toggle?.setAttribute('aria-expanded', String(open));
        if (sidebar && mobileLayout.matches) sidebar.inert = !open;
        if (open) sidebar?.querySelector('a[href]')?.focus();
        else toggle?.focus();
    };

    syncResponsiveSidebar();
    mobileLayout.addEventListener('change', syncResponsiveSidebar);
    toggle?.addEventListener('click', () => setOpen(!body.classList.contains('sidebar-open')));
    closeButton?.addEventListener('click', () => setOpen(false));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && body.classList.contains('sidebar-open')) setOpen(false);
    });

    // --- Acessibilidade: controle de tamanho de fonte ---
    const SCALE_STEPS = [0.85, 0.925, 1.0, 1.075, 1.15, 1.225, 1.3];
    const SCALE_KEY   = 'sca_font_scale';

    const applyFontScale = (scale) => {
        document.documentElement.style.setProperty('--font-scale', String(scale));
        const pct = Math.round(scale * 100);
        document.querySelectorAll('[data-font-scale-current]').forEach(el => {
            el.textContent = scale === 1.0 ? 'A' : (scale < 1 ? 'A−' : 'A+');
            el.setAttribute('aria-label', `Tamanho ${pct}%`);
        });
        // Atualiza estado desabilitado dos botões
        const idx = SCALE_STEPS.findIndex(s => Math.abs(s - scale) < 0.001);
        document.querySelectorAll('[data-font-scale-step="-1"]').forEach(b => { b.disabled = idx <= 0; });
        document.querySelectorAll('[data-font-scale-step="1"]').forEach(b => { b.disabled = idx >= SCALE_STEPS.length - 1; });
    };

    // Restaura preferência salva
    const savedScale = parseFloat(localStorage.getItem(SCALE_KEY) || '1');
    const initScale  = SCALE_STEPS.find(s => Math.abs(s - savedScale) < 0.001) ?? 1.0;
    applyFontScale(initScale);

    document.querySelectorAll('[data-font-scale-step]').forEach(btn => {
        btn.addEventListener('click', () => {
            const current = parseFloat(
                getComputedStyle(document.documentElement).getPropertyValue('--font-scale')
            ) || 1.0;
            const idx     = SCALE_STEPS.findIndex(s => Math.abs(s - current) < 0.001);
            const curIdx  = idx === -1 ? 2 : idx; // índice 2 = 1.0 (padrão)
            const step    = Number(btn.dataset.fontScaleStep);
            const nextIdx = Math.max(0, Math.min(SCALE_STEPS.length - 1, curIdx + step));
            const newScale = SCALE_STEPS[nextIdx];
            applyFontScale(newScale);
            localStorage.setItem(SCALE_KEY, String(newScale));
        });
    });
});
