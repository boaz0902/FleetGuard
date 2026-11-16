const adminShell = document.querySelector('[data-admin-shell]');

if (adminShell) {
    const sidebarStorageKey = 'fleetguard.admin-sidebar-collapsed';
    const menuToggles = adminShell.querySelectorAll('[data-admin-menu-toggle]');
    const backdrop = adminShell.querySelector('[data-admin-backdrop]');
    const sidebar = adminShell.querySelector('#admin-sidebar');
    const mobileNavigation = window.matchMedia('(max-width: 760px)');

    if (!mobileNavigation.matches && window.localStorage.getItem(sidebarStorageKey) === 'true') {
        adminShell.classList.add('is-sidebar-collapsed');
    }

    const updateToggleState = () => {
        const isMobile = mobileNavigation.matches;
        const isExpanded = isMobile
            ? adminShell.classList.contains('is-sidebar-open')
            : !adminShell.classList.contains('is-sidebar-collapsed');

        document.body.classList.toggle('admin-sidebar-open', isMobile && isExpanded);

        if (sidebar) {
            sidebar.inert = isMobile && !isExpanded;
            sidebar.setAttribute('aria-hidden', String(isMobile && !isExpanded));
        }

        menuToggles.forEach((menuToggle) => {
            menuToggle.setAttribute('aria-expanded', String(isExpanded));
            menuToggle.setAttribute(
                'aria-label',
                isMobile
                    ? (isExpanded ? 'Close navigation menu' : 'Open navigation menu')
                    : (isExpanded ? 'Collapse sidebar' : 'Expand sidebar'),
            );
            menuToggle.setAttribute(
                'title',
                isMobile
                    ? (isExpanded ? 'Close navigation menu' : 'Open navigation menu')
                    : (isExpanded ? 'Collapse sidebar' : 'Expand sidebar'),
            );
        });
    };

    const closeMobileNavigation = () => {
        adminShell.classList.remove('is-sidebar-open');
        updateToggleState();
    };

    menuToggles.forEach((menuToggle) => {
        menuToggle.addEventListener('click', () => {
            const isMobile = mobileNavigation.matches;

            if (isMobile) {
                adminShell.classList.toggle('is-sidebar-open');
            } else {
                adminShell.classList.toggle('is-sidebar-collapsed');
                window.localStorage.setItem(
                    sidebarStorageKey,
                    String(adminShell.classList.contains('is-sidebar-collapsed')),
                );
            }

            updateToggleState();

            if (!isMobile && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                window.dispatchEvent(new Event('resize'));
            }
        });
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
        adminShell.classList.remove('is-sidebar-open');
        adminShell.classList.toggle(
            'is-sidebar-collapsed',
            !mobileNavigation.matches && window.localStorage.getItem(sidebarStorageKey) === 'true',
        );
        updateToggleState();
    });

    adminShell.addEventListener('transitionend', (event) => {
        if (
            !mobileNavigation.matches
            && event.target === adminShell
            && event.propertyName === 'grid-template-columns'
        ) {
            window.dispatchEvent(new Event('resize'));
        }
    });

    updateToggleState();
    window.requestAnimationFrame(() => {
        window.requestAnimationFrame(() => adminShell.classList.add('is-sidebar-motion-ready'));
    });
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

const customerViewButtons = document.querySelectorAll('[data-customer-view-toggle]');

if (customerViewButtons.length > 0) {
    const viewPanels = document.querySelectorAll('[data-customer-view-panel]');
    const viewStorageKey = 'admin-customer-directory-view';

    const setCustomerView = (view, persist = true) => {
        viewPanels.forEach((panel) => {
            panel.hidden = panel.dataset.customerViewPanel !== view;
        });
        customerViewButtons.forEach((button) => {
            button.setAttribute('aria-pressed', String(button.dataset.customerViewToggle === view));
        });

        if (persist) {
            window.localStorage.setItem(viewStorageKey, view);
        }
    };

    const storedView = window.localStorage.getItem(viewStorageKey);
    setCustomerView(
        ['table', 'grid'].includes(storedView) ? storedView : 'table',
        false,
    );

    customerViewButtons.forEach((button) => {
        button.addEventListener('click', () => setCustomerView(button.dataset.customerViewToggle));
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

const messageViewButtons = document.querySelectorAll('[data-message-view-toggle]');

if (messageViewButtons.length > 0) {
    const messageViewPanels = document.querySelectorAll('[data-message-view-panel]');
    const viewStorageKey = 'admin-message-inbox-view';
    const compactLayout = window.matchMedia('(max-width: 850px)');
    const setMessageView = (view, persist = true) => {
        messageViewPanels.forEach((panel) => {
            panel.hidden = panel.dataset.messageViewPanel !== view;
        });
        messageViewButtons.forEach((button) => {
            button.setAttribute('aria-pressed', String(button.dataset.messageViewToggle === view));
        });

        if (persist) {
            window.localStorage.setItem(viewStorageKey, view);
        }
    };
    const storedView = window.localStorage.getItem(viewStorageKey);
    const preferredView = ['table', 'cards'].includes(storedView) ? storedView : 'table';

    setMessageView(compactLayout.matches ? 'cards' : preferredView, false);
    messageViewButtons.forEach((button) => {
        button.addEventListener('click', () => {
            if (!compactLayout.matches) {
                setMessageView(button.dataset.messageViewToggle);
            }
        });
    });
    compactLayout.addEventListener('change', (event) => {
        setMessageView(event.matches ? 'cards' : window.localStorage.getItem(viewStorageKey) || preferredView, false);
    });
}

const settingsForm = document.querySelector('[data-settings-form]');

if (settingsForm) {
    const previewDefaults = {
        name: 'FleetGuard',
        tagline: 'Your next journey starts here',
    };

    const updateSettingsPreview = (input) => {
        const key = input.dataset.settingsPreviewInput;
        const preview = document.querySelector(`[data-settings-preview="${key}"]`);

        if (key === 'maps_url' || key === 'address') {
            const mapsInput = settingsForm.querySelector('[data-settings-preview-input="maps_url"]');
            const addressInput = settingsForm.querySelector('[data-settings-preview-input="address"]');
            const mapsPreview = document.querySelector('[data-settings-preview="maps_url"]');
            const hasMap = mapsInput?.value.trim() && addressInput?.value.trim();

            if (mapsPreview) {
                mapsPreview.textContent = hasMap ? 'Open in Maps' : 'To be added';
                mapsPreview.title = mapsInput?.value.trim() ?? '';
                mapsPreview.classList.toggle('is-placeholder', !hasMap);
            }
        }

        if (key.startsWith('social.')) {
            const socialPreview = document.querySelector('[data-settings-preview="social"]');
            const profiles = ['Facebook', 'Instagram', 'TikTok'].filter((label) => {
                const profileKey = `social.${label.toLowerCase()}`;
                const profileInput = settingsForm.querySelector(`[data-settings-preview-input="${profileKey}"]`);

                return profileInput?.value.trim();
            });

            if (socialPreview) {
                socialPreview.textContent = profiles.join(' · ') || 'To be added';
                socialPreview.classList.toggle('is-placeholder', profiles.length === 0);
            }
        }

        if (preview && key !== 'maps_url') {
            const value = input.value.trim();
            const isPlaceholder = value === '';
            preview.textContent = value || previewDefaults[key] || 'To be added';
            preview.classList.toggle('is-placeholder', isPlaceholder && !previewDefaults[key]);
        }
    };

    settingsForm.querySelectorAll('[data-settings-preview-input]').forEach((input) => {
        input.addEventListener('input', () => updateSettingsPreview(input));
        updateSettingsPreview(input);
    });
}

document.querySelectorAll('[data-contact-form]').forEach((form) => {
    const message = form.querySelector('[data-contact-message]');
    const counter = form.querySelector('[data-contact-counter]');
    const submitButton = form.querySelector('button[type="submit"]');
    const submitLabel = form.querySelector('[data-contact-submit-label]');
    const submitIcon = form.querySelector('[data-contact-submit-icon]');

    if (message && counter) {
        const updateCounter = () => {
            counter.textContent = `${Array.from(message.value).length}/2000 characters`;
        };

        message.addEventListener('input', updateCounter);
        updateCounter();
    }

    form.addEventListener('submit', () => {
        form.setAttribute('aria-busy', 'true');

        if (submitButton) {
            submitButton.disabled = true;
        }
        if (submitLabel) {
            submitLabel.textContent = 'Sending...';
        }
        if (submitIcon) {
            submitIcon.classList.remove('bi-send');
            submitIcon.classList.add('bi-arrow-repeat');
        }
    });
});

const rentalViewButtons = document.querySelectorAll('[data-rental-view-toggle]');

if (rentalViewButtons.length > 0) {
    const rentalViewPanels = document.querySelectorAll('[data-rental-view-panel]');
    const viewStorageKey = 'admin-rental-directory-view';
    const setRentalView = (view, persist = true) => {
        rentalViewPanels.forEach((panel) => {
            panel.hidden = panel.dataset.rentalViewPanel !== view;
        });
        rentalViewButtons.forEach((button) => {
            button.setAttribute('aria-pressed', String(button.dataset.rentalViewToggle === view));
        });

        if (persist) {
            window.localStorage.setItem(viewStorageKey, view);
        }
    };
    const storedView = window.localStorage.getItem(viewStorageKey);

    setRentalView(['table', 'grid'].includes(storedView) ? storedView : 'table', false);
    rentalViewButtons.forEach((button) => {
        button.addEventListener('click', () => setRentalView(button.dataset.rentalViewToggle));
    });
}

document.querySelectorAll('[data-rental-dialog-open]').forEach((button) => {
    const dialog = document.getElementById(button.dataset.rentalDialogOpen);

    if (!dialog) {
        return;
    }

    button.addEventListener('click', () => dialog.showModal());
    dialog.querySelectorAll('[data-rental-dialog-close]').forEach((closeButton) => {
        closeButton.addEventListener('click', () => dialog.close());
    });
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });
});

document.querySelectorAll('[data-customer-dialog-open]').forEach((button) => {
    const dialog = document.getElementById(button.dataset.customerDialogOpen);

    if (!dialog) {
        return;
    }

    button.addEventListener('click', () => dialog.showModal());
    dialog.querySelectorAll('[data-customer-dialog-close]').forEach((closeButton) => {
        closeButton.addEventListener('click', () => dialog.close());
    });
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });

    if (dialog.hasAttribute('data-dialog-open-on-load')) {
        dialog.showModal();
    }
});

document.querySelectorAll('[data-customer-note-input]').forEach((input) => {
    const count = document.querySelector('[data-customer-note-count]');

    if (!count) {
        return;
    }

    const updateCount = () => {
        count.textContent = String(input.value.length);
    };

    input.addEventListener('input', updateCount);
    updateCount();
});
