(() => {
    const run = action => {
        if (action === 'reload') {
            window.location.reload();
            return;
        }

        if (action === 'back') {
            window.history.back();
        }
    };

    document.addEventListener('click', event => {
        const button = event.target.closest('[data-error-action]');
        if (!button) return;

        run(button.getAttribute('data-error-action'));
    });
})();
