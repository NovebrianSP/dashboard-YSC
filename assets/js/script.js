document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-sidebar-toggle]');
    if (toggle) {
        document.querySelector('.sidebar')?.classList.toggle('is-open');
    }

    const dismiss = event.target.closest('[data-alert-dismiss]');
    if (dismiss) {
        dismiss.closest('[role="alert"]')?.remove();
    }
});

document.addEventListener('submit', (event) => {
    const form = event.target.closest('form[data-confirm]');
    if (form && !window.confirm(form.dataset.confirm)) {
        event.preventDefault();
    }
});
