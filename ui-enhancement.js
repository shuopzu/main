(() => {
    const motionPreference = window.matchMedia('(prefers-reduced-motion: reduce)');
    const root = document.documentElement;
    const themeStorageKey = 'main-ui-theme';
    const themeToggle = document.createElement('button');
    themeToggle.type = 'button';
    themeToggle.className = 'ui-theme-toggle no-print';
    themeToggle.setAttribute('role', 'switch');
    themeToggle.setAttribute('aria-label', 'Dark mode');
    themeToggle.setAttribute('aria-checked', 'false');
    const themeTrack = document.createElement('span');
    themeTrack.className = 'ui-theme-switch-track';
    themeTrack.setAttribute('aria-hidden', 'true');
    const themeThumb = document.createElement('span');
    themeThumb.className = 'ui-theme-switch-thumb';
    themeTrack.appendChild(themeThumb);
    const themeLabel = document.createElement('span');
    themeLabel.className = 'ui-theme-switch-label';
    themeToggle.append(themeTrack, themeLabel);

    function applyTheme(theme, persist = false) {
        const isDark = theme === 'dark';
        root.dataset.theme = isDark ? 'dark' : 'light';
        themeLabel.textContent = 'Dark mode';
        themeToggle.setAttribute('aria-checked', String(isDark));
        if (persist) {
            try {
                window.localStorage.setItem(themeStorageKey, root.dataset.theme);
            } catch (error) {
                console.warn('Unable to save the color theme preference.', error);
            }
        }
    }

    let savedTheme = 'light';
    try {
        savedTheme = window.localStorage.getItem(themeStorageKey) === 'dark' ? 'dark' : 'light';
    } catch (error) {
        console.warn('Unable to read the saved color theme preference.', error);
    }
    applyTheme(savedTheme);
    themeToggle.addEventListener('click', () => {
        applyTheme(root.dataset.theme === 'dark' ? 'light' : 'dark', true);
    });
    function mountThemeToggle() {
        if (document.body && !themeToggle.isConnected) {
            document.body.appendChild(themeToggle);
        }
    }

    if (document.body) {
        mountThemeToggle();
    } else {
        document.addEventListener('DOMContentLoaded', mountThemeToggle, { once: true });
    }

    const viewTransitionsSupported = typeof document.startViewTransition === 'function';
    let activeTransitionId = 0;
    const alertQueue = [];
    let activeAlert = null;

    function showNextAlert() {
        if (activeAlert || !alertQueue.length || !document.body) return;

        const request = alertQueue.shift();
        const previousFocus = document.activeElement;
        const overlay = document.createElement('div');
        overlay.className = 'ui-alert-backdrop';

        const dialog = document.createElement('section');
        dialog.className = 'ui-alert-dialog';
        dialog.setAttribute('role', 'alertdialog');
        dialog.setAttribute('aria-modal', 'true');
        dialog.setAttribute('aria-labelledby', 'uiAlertTitle');
        dialog.setAttribute('aria-describedby', 'uiAlertMessage');
        dialog.tabIndex = -1;

        const title = document.createElement('h2');
        title.id = 'uiAlertTitle';
        title.textContent = 'Notice';

        const message = document.createElement('p');
        message.id = 'uiAlertMessage';
        message.textContent = request.message;

        const actions = document.createElement('div');
        actions.className = 'ui-alert-actions';
        const closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'ui-alert-button';
        closeButton.textContent = 'OK';
        actions.appendChild(closeButton);

        dialog.append(title, message, actions);
        overlay.appendChild(dialog);
        document.body.appendChild(overlay);
        activeAlert = { overlay, previousFocus, resolve: request.resolve };

        function closeAlert() {
            if (!activeAlert || activeAlert.overlay !== overlay) return;
            overlay.remove();
            activeAlert.resolve();
            activeAlert = null;
            if (previousFocus instanceof HTMLElement && previousFocus.isConnected) previousFocus.focus();
            showNextAlert();
        }

        closeButton.addEventListener('click', closeAlert);
        overlay.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                event.preventDefault();
                closeAlert();
            }
        });
        closeButton.focus();
    }

    window.showSiteAlert = message => new Promise(resolve => {
        alertQueue.push({ message: String(message ?? ''), resolve });
        showNextAlert();
    });
    window.alert = window.showSiteAlert;

    window.showSiteConfirm = (message, options = {}) => new Promise(resolve => {
        const previousFocus = document.activeElement;
        const overlay = document.createElement('div');
        overlay.className = 'ui-confirm-backdrop';

        const dialog = document.createElement('section');
        dialog.className = 'ui-confirm-dialog';
        dialog.setAttribute('role', 'alertdialog');
        dialog.setAttribute('aria-modal', 'true');

        const title = document.createElement('h2');
        title.textContent = options.title || 'Please confirm';
        const description = document.createElement('p');
        description.textContent = String(message ?? '');

        const actions = document.createElement('div');
        actions.className = 'ui-confirm-actions';
        const cancelButton = document.createElement('button');
        cancelButton.type = 'button';
        cancelButton.className = 'ui-confirm-cancel';
        cancelButton.textContent = options.cancelText || 'Cancel';
        const confirmButton = document.createElement('button');
        confirmButton.type = 'button';
        confirmButton.className = options.danger === false ? 'ui-confirm-accept' : 'ui-confirm-danger';
        confirmButton.textContent = options.confirmText || 'Continue';
        actions.append(cancelButton, confirmButton);
        dialog.append(title, description, actions);
        overlay.appendChild(dialog);
        document.body.appendChild(overlay);

        let finished = false;
        const close = confirmed => {
            if (finished) return;
            finished = true;
            overlay.remove();
            resolve(confirmed);
            if (previousFocus instanceof HTMLElement && previousFocus.isConnected) previousFocus.focus();
        };
        cancelButton.addEventListener('click', () => close(false));
        confirmButton.addEventListener('click', () => close(true));
        overlay.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                event.preventDefault();
                close(false);
            } else if (event.key === 'Tab') {
                event.preventDefault();
                (document.activeElement === cancelButton ? confirmButton : cancelButton).focus();
            }
        });
        cancelButton.focus();
    });

    window.runUITransition = (update, transition = 'content') => {
        if (typeof update !== 'function') return;
        if (motionPreference.matches || !viewTransitionsSupported) {
            update();
            return;
        }

        const transitionId = String(++activeTransitionId);
        root.dataset.uiTransition = transition;
        root.dataset.uiTransitionId = transitionId;
        const cleanup = () => {
            if (root.dataset.uiTransitionId !== transitionId) return;
            delete root.dataset.uiTransition;
            delete root.dataset.uiTransitionId;
        };
        const viewTransition = document.startViewTransition(update);
        viewTransition.finished.then(cleanup, cleanup);
        return viewTransition;
    };

    if (motionPreference.matches || !('IntersectionObserver' in window)) return;

    const revealSelector = 'main > .card, .pg > .cd, #positions > .position, .recent-form, .section-table, .sheet-row';
    const revealedElements = new Set();
    let staggerIndex = 0;

    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            const element = entry.target;
            element.classList.add('ui-visible');
            observer.unobserve(element);
        });
    }, { threshold: 0.01, rootMargin: '0px 0px -24px 0px' });

    function observeElement(element) {
        if (revealedElements.has(element) || element.closest('#tables-wrapper')) return;
        revealedElements.add(element);
        element.classList.add('ui-reveal');
        element.style.setProperty('--ui-stagger', `${Math.min(staggerIndex++, 6) * 55}ms`);
        observer.observe(element);
    }

    function scan(node) {
        if (!(node instanceof Element) || node.closest('#tables-wrapper')) return;
        if (node.matches(revealSelector)) observeElement(node);
        node.querySelectorAll(revealSelector).forEach(observeElement);
    }

    document.querySelectorAll(revealSelector).forEach(observeElement);

    const mutations = new MutationObserver(records => {
        records.forEach(record => record.addedNodes.forEach(scan));
    });
    mutations.observe(document.body, { childList: true, subtree: true });
})();
