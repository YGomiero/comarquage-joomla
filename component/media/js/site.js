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

// Native navigation preserves Joomla menus, breadcrumbs and fresh server content.
(function () {
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

    function initialise() {
        const root = document.querySelector(".comarquage");
        if (root) { initGuides(root); applyConditionalContent(root, new URL(window.location.href)); }
    }
    if (document.readyState === "loading") { document.addEventListener("DOMContentLoaded", initialise); }
    else { initialise(); }
    document.addEventListener("click", function (event) {
        const choice = event.target.closest("[data-comarquage-guide-choice]");
        if (choice) { event.preventDefault(); selectGuideChoice(choice); }
    });
})();
}
