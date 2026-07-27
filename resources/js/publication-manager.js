/**
 * Standard Site — Publication Manager
 *
 * Adds interactive publication check/create actions to the CP settings page.
 * Every action is explicitly user-triggered — no hidden API calls.
 */

(function () {
    'use strict';

    function init() {
        var publicationTab = document.querySelector('[data-tab="publication"]');
        if (!publicationTab) return;

        injectButtons(publicationTab);
    }

    function injectButtons(container) {
        var section = container.querySelector('.section');
        if (!section) return;

        // Avoid double-injecting
        if (section.querySelector('.standard-site-actions')) return;

        var wrapper = document.createElement('div');
        wrapper.className = 'standard-site-actions';
        wrapper.style.cssText = 'padding: 1rem 0; border-top: 1px solid var(--border); margin-top: 1rem;';

        var buttonGroup = document.createElement('div');
        buttonGroup.style.cssText = 'display: flex; gap: 0.5rem; flex-wrap: wrap;';

        // Check button
        var checkBtn = document.createElement('button');
        checkBtn.type = 'button';
        checkBtn.className = 'btn';
        checkBtn.textContent = 'Check for existing publications';
        checkBtn.addEventListener('click', function () { handleCheck(wrapper); });

        // Create button
        var createBtn = document.createElement('button');
        createBtn.type = 'button';
        createBtn.className = 'btn btn-primary';
        createBtn.textContent = 'Create new publication';
        createBtn.addEventListener('click', function () { handleCreate(wrapper); });

        buttonGroup.appendChild(checkBtn);
        buttonGroup.appendChild(createBtn);
        wrapper.appendChild(buttonGroup);

        // Results area
        var results = document.createElement('div');
        results.className = 'standard-site-results';
        results.style.cssText = 'margin-top: 1rem;';
        wrapper.appendChild(results);

        section.appendChild(wrapper);
    }

    function getFormField(name) {
        var el = document.querySelector('[name="standard-site_' + name + '"], [name="' + name + '"]');
        return el ? el.value : '';
    }

    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function showStatus(container, message, isError) {
        var div = document.createElement('div');
        div.style.cssText = 'padding: 0.5rem; margin-bottom: 0.5rem; border-radius: 4px;' +
            (isError
                ? 'background: #fef2f2; color: #991b1b; border: 1px solid #fecaca;'
                : 'background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0;');
        div.textContent = message;
        return div;
    }

    function handleCheck(container) {
        var results = container.querySelector('.standard-site-results');
        results.innerHTML = '';
        results.appendChild(showStatus(container, 'Checking for publications...', false));

        fetch('/cp/standard-site/publication/check', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                identifier: getFormField('identifier'),
                app_password: getFormField('app_password'),
                pds_host: getFormField('pds_host'),
            }),
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            results.innerHTML = '';

            if (!data.success) {
                results.appendChild(showStatus(container, data.error || 'Check failed.', true));
                return;
            }

            if (data.publications.length === 0) {
                results.appendChild(showStatus(container,
                    'No publication records found for DID ' + data.did + '. Create one using the button above.',
                    false));
                return;
            }

            results.appendChild(showStatus(container,
                'Found ' + data.publications.length + ' publication(s) for DID ' + data.did + ':',
                false));

            var list = document.createElement('div');
            list.style.cssText = 'margin-top: 0.5rem;';

            data.publications.forEach(function (pub) {
                var item = document.createElement('label');
                item.style.cssText = 'display: flex; align-items: flex-start; gap: 0.5rem; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px; margin-bottom: 0.5rem; cursor: pointer;';

                var radio = document.createElement('input');
                radio.type = 'radio';
                radio.name = 'existing_publication';
                radio.value = pub.uri;

                var info = document.createElement('div');
                var name = document.createElement('strong');
                name.textContent = pub.name;
                var url = document.createElement('div');
                url.style.cssText = 'font-size: 0.875rem; color: #6b7280;';
                url.textContent = pub.url;
                var uri = document.createElement('code');
                uri.style.cssText = 'font-size: 0.75rem; color: #6b7280; word-break: break-all;';
                uri.textContent = pub.uri;

                info.appendChild(name);
                info.appendChild(url);
                info.appendChild(uri);

                item.appendChild(radio);
                item.appendChild(info);

                radio.addEventListener('change', function () {
                    setPublicationUri(pub.uri);
                });

                list.appendChild(item);
            });

            results.appendChild(list);
        })
        .catch(function (err) {
            results.innerHTML = '';
            results.appendChild(showStatus(container, 'Request failed: ' + err.message, true));
        });
    }

    function handleCreate(container) {
        var name = getFormField('publication_name');
        var url = window.location.origin; // default to the site's own URL

        if (!name) {
            container.querySelector('.standard-site-results')
                .appendChild(showStatus(container,
                    'Please enter a Publication Name before creating.',
                    true));
            return;
        }

        var results = container.querySelector('.standard-site-results');
        results.innerHTML = '';
        results.appendChild(showStatus(container, 'Creating publication record...', false));

        fetch('/cp/standard-site/publication/create', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                identifier: getFormField('identifier'),
                app_password: getFormField('app_password'),
                pds_host: getFormField('pds_host'),
                name: name,
                url: url,
                description: getFormField('publication_description'),
            }),
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            results.innerHTML = '';

            if (!data.success) {
                results.appendChild(showStatus(container, data.error || 'Create failed.', true));
                return;
            }

            setPublicationUri(data.uri);
            results.appendChild(showStatus(container,
                'Publication created successfully: ' + data.uri,
                false));
        })
        .catch(function (err) {
            results.innerHTML = '';
            results.appendChild(showStatus(container, 'Request failed: ' + err.message, true));
        });
    }

    function setPublicationUri(uri) {
        var field = document.querySelector('[name="standard-site_publication_uri"], [name="publication_uri"]');
        if (field) {
            field.value = uri;
            field.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
