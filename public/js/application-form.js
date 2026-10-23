document.querySelectorAll('[data-application-form]').forEach((form) => {
    const select = form.querySelector('[data-service-select]');
    const sections = form.querySelectorAll('[data-service-fields]');
    const changeService = () => sections.forEach((section) => {
        const active = section.dataset.serviceFields === select.value;
        section.hidden = !active;
        section.disabled = !active;
    });
    select.addEventListener('change', changeService);
    changeService();
    form.addEventListener('submit', (event) => {
        const error = form.querySelector('[data-upload-error]');
        const inputs = [...form.querySelectorAll('input[type=file]')].filter((input) => !input.closest('fieldset').disabled);
        const files = inputs.flatMap((input) => [...input.files]);
        if (files.length > 12 || inputs.some((input) => input.files.length > 5) || files.some((file) => file.size > 10 * 1024 * 1024)) {
            event.preventDefault();
            error.textContent = 'Choose no more than 5 files per category and 12 in total, up to 10 MB each.';
            error.hidden = false;
            error.scrollIntoView({ block: 'center' });
            return;
        }
        error.hidden = true;
        form.querySelector('[data-submit-button]').disabled = true;
        form.querySelector('[data-submit-status]').hidden = false;
    });
    window.addEventListener('pageshow', () => {
        form.querySelector('[data-submit-button]').disabled = false;
        form.querySelector('[data-submit-status]').hidden = true;
    });
});
