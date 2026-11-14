const adminShell = document.querySelector('[data-admin-shell]');

if (adminShell) {
    const menuToggle = adminShell.querySelector('[data-admin-menu-toggle]');
    const backdrop = adminShell.querySelector('[data-admin-backdrop]');
    const sidebar = adminShell.querySelector('#admin-sidebar');
    const mobileNavigation = window.matchMedia('(max-width: 760px)');

    const updateToggleState = () => {
        const isMobile = mobileNavigation.matches;
        const isExpanded = isMobile
            ? adminShell.classList.contains('is-sidebar-open')
            : !adminShell.classList.contains('is-sidebar-collapsed');

        if (sidebar) {
            sidebar.inert = isMobile && !isExpanded;
            sidebar.setAttribute('aria-hidden', String(isMobile && !isExpanded));
        }

        if (menuToggle) {
            menuToggle.setAttribute('aria-expanded', String(isExpanded));
            menuToggle.setAttribute(
                'aria-label',
                isMobile
                    ? (isExpanded ? 'Close navigation menu' : 'Open navigation menu')
                    : (isExpanded ? 'Collapse sidebar' : 'Expand sidebar'),
            );
        }
    };

    const closeMobileNavigation = () => {
        adminShell.classList.remove('is-sidebar-open');
        updateToggleState();
    };

    menuToggle?.addEventListener('click', () => {
        if (mobileNavigation.matches) {
            adminShell.classList.toggle('is-sidebar-open');
        } else {
            adminShell.classList.toggle('is-sidebar-collapsed');
        }

        updateToggleState();
    });

    backdrop?.addEventListener('click', closeMobileNavigation);

    adminShell.querySelectorAll('.admin-navigation a').forEach((link) => {
        link.addEventListener('click', closeMobileNavigation);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeMobileNavigation();
        }
    });

    mobileNavigation.addEventListener('change', () => {
        adminShell.classList.remove('is-sidebar-open', 'is-sidebar-collapsed');
        updateToggleState();
    });

    updateToggleState();
}

const searchForm = document.querySelector('form[data-preserve-search-position]');
const searchInput = searchForm?.querySelector('input[type="search"]');

document.querySelectorAll('[data-admin-submit-on-change]').forEach((select) => {
    select.addEventListener('change', () => select.form?.requestSubmit());
});

if (searchForm && searchInput) {
    const storageKey = 'admin-search-scroll-position';
    const pendingPosition = sessionStorage.getItem(storageKey);

    if (pendingPosition !== null) {
        sessionStorage.removeItem(storageKey);
        const position = JSON.parse(pendingPosition);
        const submittedSearch = new URLSearchParams(window.location.search).get(searchInput.name) ?? '';

        if (position.path === window.location.pathname && position.search === submittedSearch) {
            window.addEventListener('pageshow', () => {
                window.requestAnimationFrame(() => {
                    window.scrollTo(position.x, position.y);

                    if (position.focused) {
                        searchInput.focus({ preventScroll: true });
                        searchInput.setSelectionRange(position.selectionStart, position.selectionEnd);
                    }
                });
            }, { once: true });
        }
    }

    searchForm.addEventListener('submit', () => {
        sessionStorage.setItem(storageKey, JSON.stringify({
            path: new URL(searchForm.action, window.location.href).pathname,
            search: searchInput.value,
            x: window.scrollX,
            y: window.scrollY,
            focused: document.activeElement === searchInput,
            selectionStart: searchInput.selectionStart ?? searchInput.value.length,
            selectionEnd: searchInput.selectionEnd ?? searchInput.value.length,
        }));

        searchForm.querySelectorAll('input[name], select[name]').forEach((control) => {
            const isDefaultSort = control.name === 'sort' && control.value === 'created_at';
            const isDefaultDirection = control.name === 'direction' && control.value === 'desc';

            if (control.value === '' || isDefaultSort || isDefaultDirection) {
                control.disabled = true;
            }
        });
    });
}

const fleetViewButtons = document.querySelectorAll('[data-fleet-view-toggle]');

if (fleetViewButtons.length > 0) {
    const viewPanels = document.querySelectorAll('[data-fleet-view-panel]');
    const viewStorageKey = 'admin-fleet-inventory-view';

    const setFleetView = (view, persist = true) => {
        viewPanels.forEach((panel) => {
            panel.hidden = panel.dataset.fleetViewPanel !== view;
        });
        fleetViewButtons.forEach((button) => {
            button.setAttribute('aria-pressed', String(button.dataset.fleetViewToggle === view));
        });

        if (persist) {
            window.localStorage.setItem(viewStorageKey, view);
        }
    };

    const storedView = window.localStorage.getItem(viewStorageKey);
    setFleetView(
        ['table', 'grid'].includes(storedView) ? storedView : 'table',
        false,
    );

    fleetViewButtons.forEach((button) => {
        button.addEventListener('click', () => setFleetView(button.dataset.fleetViewToggle));
    });
}

document.querySelectorAll('[data-fleet-tabs]').forEach((tabList) => {
    const tabs = [...tabList.querySelectorAll('[data-fleet-tab]')];
    const panels = [...document.querySelectorAll('[data-fleet-tab-panel]')];

    if (tabs.length === 0 || panels.length === 0) {
        return;
    }

    const activateTab = (tab, moveFocus = false) => {
        const selectedTab = tab.dataset.fleetTab;

        tabs.forEach((item) => {
            const isSelected = item === tab;
            item.setAttribute('aria-selected', String(isSelected));
            item.tabIndex = isSelected ? 0 : -1;
        });
        panels.forEach((panel) => {
            panel.hidden = panel.dataset.fleetTabPanel !== selectedTab;
        });

        if (moveFocus) {
            tab.focus();
        }
    };

    tabs.forEach((tab) => {
        tab.setAttribute('role', 'tab');
        tab.addEventListener('click', () => activateTab(tab));
    });
    panels.forEach((panel) => {
        panel.setAttribute('role', 'tabpanel');
        panel.tabIndex = 0;
    });

    tabList.setAttribute('role', 'tablist');
    tabList.addEventListener('keydown', (event) => {
        const currentIndex = tabs.indexOf(document.activeElement);
        let nextIndex = currentIndex;

        if (event.key === 'ArrowRight') {
            nextIndex = (currentIndex + 1) % tabs.length;
        } else if (event.key === 'ArrowLeft') {
            nextIndex = (currentIndex - 1 + tabs.length) % tabs.length;
        } else if (event.key === 'Home') {
            nextIndex = 0;
        } else if (event.key === 'End') {
            nextIndex = tabs.length - 1;
        } else {
            return;
        }

        event.preventDefault();
        activateTab(tabs[nextIndex], true);
    });

    const initiallySelected = tabs.find((tab) => tab.getAttribute('aria-selected') === 'true') ?? tabs[0];
    activateTab(initiallySelected);
});

const fleetImageSelect = document.querySelector('[data-fleet-image-select]');
const fleetImagePreview = document.querySelector('[data-fleet-image-preview-img]');
const fleetImagePlaceholder = document.querySelector('[data-fleet-image-placeholder]');

if (fleetImageSelect && fleetImagePreview && fleetImagePlaceholder) {
    const updateFleetImagePreview = () => {
        const previewUrl = fleetImageSelect.selectedOptions[0]?.dataset.previewUrl ?? '';
        fleetImagePreview.src = previewUrl;
        fleetImagePreview.hidden = previewUrl === '';
        fleetImagePlaceholder.hidden = previewUrl !== '';
    };

    fleetImageSelect.addEventListener('change', updateFleetImagePreview);
    updateFleetImagePreview();
}

document.querySelectorAll('form[data-admin-disable-submit]').forEach((form) => {
    form.addEventListener('submit', () => {
        form.setAttribute('aria-busy', 'true');
        form.querySelectorAll('button[type="submit"]').forEach((button) => {
            button.disabled = true;
        });
    });
});
