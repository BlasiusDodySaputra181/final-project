(function () {
    try {
        if (sessionStorage.getItem('authPageLoader')) {
            var el = document.getElementById('pageloader');
            if (el) {
                el.classList.remove('hide');
                el.setAttribute('aria-hidden', 'false');
            }
        }
    } catch (e) {}
})();
