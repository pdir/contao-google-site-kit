/* Google Site Kit for Contao – back end behavior. Uses event delegation, so it
 * also works after Turbo navigations in Contao 5 and 6. */
(function () {
    'use strict';

    if (window.pdirSiteKitInitialized) {
        return;
    }

    window.pdirSiteKitInitialized = true;

    // Submit the toolbar when a select changes
    document.addEventListener('change', function (event) {
        var select = event.target.closest && event.target.closest('[data-sitekit-autosubmit]');

        if (select && select.form) {
            select.form.submit();
        }
    });

    // Select the redirect URI on focus so it can be copied easily
    document.addEventListener('focusin', function (event) {
        if (event.target.matches && event.target.matches('[data-sitekit-select-on-focus]')) {
            event.target.select();
        }
    });

    // Confirm dialogs
    document.addEventListener('submit', function (event) {
        var form = event.target;

        if (form.hasAttribute('data-sitekit-confirm') && !window.confirm(form.getAttribute('data-sitekit-confirm'))) {
            event.preventDefault();
        }
    }, true);

    // PageSpeed Insights
    document.addEventListener('submit', function (event) {
        var form = event.target;

        if (!form.hasAttribute('data-sitekit-pagespeed-form')) {
            return;
        }

        event.preventDefault();

        var card = form.closest('[data-sitekit-pagespeed]');
        var result = card.querySelector('[data-sitekit-pagespeed-result]');
        var submitter = event.submitter || form.querySelector('button[name="strategy"]');
        var buttons = form.querySelectorAll('button');
        var params = new URLSearchParams({
            url: form.elements.url.value,
            strategy: submitter ? submitter.value : 'mobile',
            site: card.getAttribute('data-site') || ''
        });

        buttons.forEach(function (button) { button.disabled = true; });
        result.classList.add('is-loading');
        result.setAttribute('aria-busy', 'true');
        result.innerHTML = '<p class="sitekit-hint"></p>';
        result.firstChild.textContent = result.getAttribute('data-loading-text');

        fetch(card.getAttribute('data-endpoint') + '?' + params.toString(), {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }
        })
            .then(function (response) { return response.text(); })
            .then(function (html) { result.innerHTML = html; })
            .catch(function (error) {
                result.innerHTML = '<p class="sitekit-flash sitekit-flash--error"></p>';
                result.firstChild.textContent = String(error);
            })
            .finally(function () {
                buttons.forEach(function (button) { button.disabled = false; });
                result.classList.remove('is-loading');
                result.removeAttribute('aria-busy');
            });
    });
})();
