const toggle = document.querySelector('.menu-toggle');
const navigation = document.querySelector('#main-navigation');
toggle?.addEventListener('click', () => {
    const expanded = toggle.getAttribute('aria-expanded') === 'true';
    toggle.setAttribute('aria-expanded', String(!expanded));
    navigation.classList.toggle('is-open', !expanded);
});
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && toggle?.getAttribute('aria-expanded') === 'true') {
        toggle.setAttribute('aria-expanded', 'false');
        navigation.classList.remove('is-open');
        toggle.focus();
    }
});
document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) event.preventDefault();
    });
});
document.querySelectorAll('form').forEach((form) => {
    if (form.matches('[data-application-form]')) return;
    form.addEventListener('submit', (event) => {
        if (event.defaultPrevented || form.method.toLowerCase() === 'get') return;
        const button = form.querySelector('button[type="submit"], button:not([type])');
        if (!button) return;
        button.disabled = true;
        button.dataset.originalHtml = button.innerHTML;
        button.textContent = 'Please wait…';
        window.setTimeout(() => {
            button.disabled = false;
            button.innerHTML = button.dataset.originalHtml;
        }, 15000);
    });
});
window.addEventListener('pageshow', () => document.querySelectorAll('button[data-original-html]').forEach((button) => {
    button.disabled = false; button.innerHTML = button.dataset.originalHtml;
}));
