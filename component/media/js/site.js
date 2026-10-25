if (!window.__comarquageSiteLoaded) {
window.__comarquageSiteLoaded = true;

document.addEventListener('click', function (event) {
    const button = event.target.closest('[data-comarquage-expand]');
    if (!button) {
        return;
    }

    const root = button.closest('.comarquage');
    if (!root) {
        return;
    }

    const open = button.getAttribute('data-comarquage-expand') === 'all';
    root.querySelectorAll('details.comarquage-accordion').forEach((details) => {
        details.open = open;
    });
});

(function () {
    const memory = new Map();
    const pending = new Map();
    const storagePrefix = 'comarquage:view:';
    const ajaxLinkSelector = [
        '.comarquage-pagination a[href]',
        '.comarquage-switcher a[href]',
        '.comarquage-guide-result a[href]',
        'a.comarquage-back[href]',
        'a.comarquage-guided-link[href]',
        'a.comarquage-topic[href]'
    ].join(',');

    function sameOriginUrl(href) {
        try {
            const url = new URL(href, window.location.href);
            return url.origin === window.location.origin ? url : null;
        } catch (error) {
            return null;
        }
    }

    function cacheKey(url) {
        return storagePrefix + url.pathname + url.search;
    }

    function readCached(url) {
        const key = cacheKey(url);
        if (memory.has(key)) {
            return memory.get(key);
        }

        try {
            const cached = window.sessionStorage.getItem(key);
            if (cached) {
                memory.set(key, cached);
                return cached;
            }
        } catch (error) {
            return null;
        }

        return null;
    }

    function writeCached(url, html) {
        const key = cacheKey(url);
        memory.set(key, html);

        try {
            window.sessionStorage.setItem(key, html);
        } catch (error) {
            // Session storage can be full or disabled; memory cache is enough for this page.
        }
    }

    function extractComponent(html) {
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const root = doc.querySelector('.comarquage');
        return root ? root.outerHTML : '';
    }

    async function loadComponent(url) {
        const cached = readCached(url);
        if (cached) {
            return cached;
        }

        const key = cacheKey(url);
        if (pending.has(key)) {
            return pending.get(key);
        }

        const fetchUrl = new URL(url.href);
        fetchUrl.searchParams.set('tmpl', 'component');

        const request = fetch(fetchUrl.href, {
            credentials: 'same-origin',
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        })
            .then(async (response) => {
                if (!response.ok) {
                    return '';
                }

                const html = extractComponent(await response.text());
                if (html) {
                    writeCached(url, html);
                }

                return html;
            })
            .finally(() => pending.delete(key));

        pending.set(key, request);

        return request;
    }

    function replaceComponent(html, url, push) {
        const current = document.querySelector('.comarquage');
        if (!current || !html) {
            return false;
        }

        const wrapper = document.createElement('div');
        wrapper.innerHTML = html;
        const next = wrapper.querySelector('.comarquage');

        if (!next) {
            return false;
        }

        current.replaceWith(next);

        if (push) {
            window.history.pushState({comarquage: true}, '', url.href);
        }

        const results = next.querySelector('.comarquage-results') || next;
        results.scrollIntoView({block: 'start'});
        prefetchPagination(next);
        prefetchNavigation(next);
        initGuides(next);
        applyConditionalContent(next, new URL(url.href));

        return true;
    }

    function prefetchPagination(scope) {
        const links = Array.from((scope || document).querySelectorAll('.comarquage-pagination a[href]'));
        prefetchLinks(links);
    }

    function prefetchNavigation(scope) {
        const links = Array.from((scope || document).querySelectorAll('.comarquage-switcher a[href], a.comarquage-back[href]'));
        prefetchLinks(links);
    }

    function prefetchLinks(links) {
        const seen = new Set();

        links.forEach((link) => {
            const url = sameOriginUrl(link.getAttribute('href'));
            if (!url || readCached(url)) {
                return;
            }

            const key = cacheKey(url);
            if (seen.has(key) || pending.has(key)) {
                return;
            }

            seen.add(key);
            const run = () => loadComponent(url).catch(() => {});
            window.setTimeout(run, 80);
        });
    }

    function selectedVarsFromUrl(url) {
        const raw = url.searchParams.get('cq_vars') || '';
        return new Set(raw.split(',').map((value) => value.trim()).filter(Boolean));
    }

    function applyConditionalContent(scope, url) {
        const vars = selectedVarsFromUrl(url || new URL(window.location.href));
        const hasFilter = vars.size > 0;

        (scope || document).querySelectorAll('[data-comarquage-condition]').forEach((node) => {
            if (!hasFilter) {
                node.hidden = false;
                return;
            }

            const required = (node.getAttribute('data-comarquage-condition') || '')
                .split(',')
                .map((value) => value.trim())
                .filter(Boolean);

            node.hidden = required.length > 0 && !required.every((value) => vars.has(value));
        });
    }

    function resetGuideBranch(scope) {
        scope.querySelectorAll('[data-comarquage-guide-choice]').forEach((choice) => {
            choice.classList.remove('is-selected');
            choice.setAttribute('aria-expanded', 'false');
        });

        scope.querySelectorAll('.comarquage-guide-children, .comarquage-guide-result').forEach((branch) => {
            branch.hidden = true;
        });
    }

    function selectGuideChoice(button) {
        const node = button.closest('[data-comarquage-guide-node]');
        const level = node ? node.parentElement : null;

        if (!node || !level) {
            return;
        }

        Array.from(level.children).forEach((sibling) => {
            if (sibling !== node && sibling.matches('[data-comarquage-guide-node]')) {
                resetGuideBranch(sibling);
            }
        });

        button.classList.add('is-selected');
        button.setAttribute('aria-expanded', 'true');

        const children = node.querySelector(':scope > .comarquage-guide-children');
        const result = node.querySelector(':scope > .comarquage-guide-result');

        if (children) {
            children.hidden = false;
        }

        if (result && !children) {
            result.hidden = false;
        }
    }

    function initGuides(scope) {
        (scope || document).querySelectorAll('[data-comarquage-guide-choice]').forEach((choice) => {
            choice.setAttribute('aria-expanded', 'false');
        });
    }

    function initComarquage() {
        const root = document.querySelector('.comarquage');
        if (root) {
            writeCached(new URL(window.location.href), root.outerHTML);
            prefetchPagination(root);
            prefetchNavigation(root);
            initGuides(root);
            applyConditionalContent(root, new URL(window.location.href));
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initComarquage);
    } else {
        initComarquage();
    }

    document.addEventListener('click', async function (event) {
        const guideChoice = event.target.closest('[data-comarquage-guide-choice]');
        if (guideChoice) {
            event.preventDefault();
            selectGuideChoice(guideChoice);
            return;
        }

        const link = event.target.closest(ajaxLinkSelector);
        if (!link) {
            return;
        }

        const url = sameOriginUrl(link.getAttribute('href'));
        if (!url || link.getAttribute('href') === '#') {
            return;
        }

        event.preventDefault();

        const root = document.querySelector('.comarquage');
        if (root) {
            root.classList.add('comarquage-is-loading');
        }

        const html = await loadComponent(url);
        replaceComponent(html, url, true);
    });

    document.addEventListener('mouseover', function (event) {
        const link = event.target.closest(ajaxLinkSelector);
        if (!link) {
            return;
        }

        const url = sameOriginUrl(link.getAttribute('href'));
        if (url) {
            loadComponent(url).catch(() => {});
        }
    });

    window.addEventListener('popstate', async function () {
        const url = new URL(window.location.href);
        const html = await loadComponent(url);
        replaceComponent(html, url, false);
    });
})();
}
