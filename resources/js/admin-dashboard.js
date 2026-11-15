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
        tagline: 'A considered car rental experience in Panabo City.',
    };
    const previewFields = settingsForm.querySelectorAll('[data-settings-preview-input]');
    const previewUrl = settingsForm.dataset.previewUrl;
    const previewCsrf = settingsForm.dataset.previewCsrf;
    let previewTimer;
    let previewSequence = 0;

    const updateSettingsPreview = (input) => {
        const key = input.dataset.settingsPreviewInput;
        const preview = document.querySelector(`[data-settings-preview="${key}"]`);

        if (preview && !['maps_url', 'address'].includes(key) && !key.startsWith('social.')) {
            const value = input.value.trim();
            const isPlaceholder = value === '';
            preview.textContent = value || previewDefaults[key] || 'To be added';
            preview.classList.toggle('is-placeholder', isPlaceholder && !previewDefaults[key]);
        }
    };

    const setDetection = (key, message, isError = false) => {
        const detection = settingsForm.querySelector(`[data-settings-detection="${key}"]`);
        if (detection) {
            detection.textContent = message;
            detection.classList.toggle('is-error', isError);
        }
    };

    const renderMapPreview = (map) => {
        const preview = document.querySelector('[data-settings-preview="maps_url"]');
        const holder = document.querySelector('[data-settings-map-preview]');

        if (preview) {
            preview.replaceChildren();
            preview.classList.toggle('is-placeholder', !map.open_url);
            const link = document.createElement(map.open_url ? 'a' : 'span');
            link.textContent = map.open_url ? 'Open in Maps' : 'To be added';
            if (map.open_url) {
                link.href = map.open_url;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
            }
            preview.append(link);
        }

        if (holder) {
            holder.replaceChildren();
            if (map.embed_url) {
                const frame = document.createElement('iframe');
                frame.src = map.embed_url;
                frame.title = 'FleetGuard location map preview';
                frame.loading = 'lazy';
                frame.referrerPolicy = 'no-referrer-when-downgrade';
                holder.append(frame);
            }
        }

        if (!map.valid) {
            setDetection('maps_url', map.error, true);
        } else if (map.embed_url) {
            setDetection('maps_url', 'Map embed ready.');
        } else if (map.open_url) {
            setDetection('maps_url', 'Add a full Google Maps link to show a live map.');
        } else {
            setDetection('maps_url', 'Enter an address to create a map preview.');
        }
    };

    const renderSocialPreview = (profiles) => {
        const preview = document.querySelector('[data-settings-preview="social"]');
        if (!preview) {
            return;
        }

        preview.querySelector('.public-social-links')?.remove();
        const emptyLabel = preview.querySelector('[data-settings-social-empty]');
        const activeProfiles = Object.values(profiles).filter((profile) => profile.valid && profile.url);
        preview.classList.toggle('is-placeholder', activeProfiles.length === 0);
        if (activeProfiles.length === 0) {
            if (!emptyLabel) {
                const placeholder = document.createElement('span');
                placeholder.dataset.settingsSocialEmpty = '';
                placeholder.textContent = 'To be added';
                preview.append(placeholder);
            }
            return;
        }

        emptyLabel?.remove();
        const linksTemplate = preview.querySelector('template[data-social-links-template]');
        const linkTemplate = preview.querySelector('template[data-social-link-template]');
        if (!linksTemplate || !linkTemplate) {
            preview.textContent = 'Social links preview is unavailable.';
            preview.classList.add('is-placeholder');
            return;
        }

        const links = linksTemplate.content.firstElementChild.cloneNode(true);
        activeProfiles.forEach((profile) => {
            const link = linkTemplate.content.firstElementChild.cloneNode(true);
            link.href = profile.url;
            link.setAttribute('aria-label', `FleetGuard on ${profile.name}${profile.username ? `, @${profile.username}` : ''}`);
            link.title = profile.name;
            link.querySelector('.public-social-link-icon').className = `public-social-link-icon bi bi-${profile.icon}`;
            link.querySelector('.public-social-link-label').textContent = profile.username ? `@${profile.username}` : profile.label;
            links.append(link);
        });
        preview.append(links);
    };

    const requestSettingsPreview = async () => {
        if (!previewUrl || !settingsForm.querySelector('[data-settings-preview-input]:not(:disabled)')) {
            return;
        }

        const sequence = ++previewSequence;
        const social = {};
        settingsForm.querySelectorAll('[data-settings-preview-input^="social."]').forEach((input) => {
            social[input.dataset.settingsPreviewInput.slice('social.'.length)] = input.value;
        });
        const payload = {
            address: settingsForm.querySelector('[data-settings-preview-input="address"]')?.value ?? '',
            maps_url: settingsForm.querySelector('[data-settings-preview-input="maps_url"]')?.value ?? '',
            social,
        };

        try {
            const response = await fetch(previewUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': previewCsrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            });
            const result = await response.json();
            if (sequence !== previewSequence) {
                return;
            }

            if (result.map) {
                renderMapPreview(result.map);
            }
            if (result.social) {
                Object.entries(result.social).forEach(([platform, profile]) => {
                    if (!profile.valid) {
                        setDetection(`social.${platform}`, profile.error, true);
                    } else if (profile.username) {
                        setDetection(`social.${platform}`, `Detected: @${profile.username}`);
                    } else if (profile.url) {
                        setDetection(`social.${platform}`, `${profile.label} link ready.`);
                    } else {
                        setDetection(`social.${platform}`, 'Leave empty to hide this social link on the public site.');
                    }
                });
                renderSocialPreview(result.social);
            }
            if (result.errors) {
                Object.entries(result.errors).forEach(([key, messages]) => {
                    setDetection(key, Array.isArray(messages) ? messages[0] : String(messages), true);
                });
            }

            if (response.status === 419) {
                setDetection('maps_url', 'Preview session expired. Reload the page before saving.', true);
            } else if (response.status === 429) {
                setDetection('maps_url', 'Preview is rate limited. You can still save; the form validates on submit.', true);
            }
        } catch {
            if (sequence === previewSequence) {
                setDetection('maps_url', 'Live preview is unavailable. The form will still validate when saved.', true);
            }
        }
    };

    previewFields.forEach((input) => {
        input.addEventListener('input', () => updateSettingsPreview(input));
        updateSettingsPreview(input);
        input.addEventListener('input', () => {
            window.clearTimeout(previewTimer);
            previewTimer = window.setTimeout(requestSettingsPreview, 300);
        });
    });
}

document.querySelectorAll('[data-contact-form]').forEach((form) => {
    const message = form.querySelector('[data-contact-message]');
    const counter = form.querySelector('[data-contact-counter]');
    const submitButton = form.querySelector('button[type="submit"]');
    const submitLabel = form.querySelector('[data-contact-submit-label]');
    const submitIcon = form.querySelector('[data-contact-submit-icon]');
    const feedback = form.parentElement.querySelector('[data-contact-feedback]');
    const submittedAt = form.querySelector('[data-contact-submitted-at]');
    const originalSubmitLabel = submitLabel?.textContent ?? 'Send message';
    const originalIcon = submitIcon?.className ?? 'bi bi-send';
    let isSubmitting = false;

    if (message && counter) {
        const updateCounter = () => {
            counter.textContent = `${Array.from(message.value).length}/2000 characters`;
        };

        message.addEventListener('input', updateCounter);
        updateCounter();
    }

    const clearFieldErrors = () => {
        const errorIds = [...form.querySelectorAll('.contact-field-error')].map((error) => error.id).filter(Boolean);
        form.querySelectorAll('[data-contact-dynamic-error]').forEach((error) => error.remove());
        form.querySelectorAll('.contact-field-error:not([data-contact-dynamic-error])').forEach((error) => error.remove());
        form.querySelectorAll('[aria-invalid="true"]').forEach((field) => {
            field.setAttribute('aria-invalid', 'false');
            const describedBy = (field.getAttribute('aria-describedby') ?? '')
                .split(/\s+/)
                .filter((id) => id && !id.endsWith('-dynamic-error') && !errorIds.includes(id));
            if (describedBy.length > 0) {
                field.setAttribute('aria-describedby', describedBy.join(' '));
            } else {
                field.removeAttribute('aria-describedby');
            }
        });
    };

    const showFeedback = (text, isError = false) => {
        if (!feedback) {
            return;
        }
        feedback.hidden = !text;
        feedback.textContent = text;
        feedback.classList.toggle('is-error', isError);
    };

    const showValidationErrors = (errors) => {
        clearFieldErrors();
        let firstInvalidField;

        Object.entries(errors ?? {}).forEach(([name, messages]) => {
            const field = form.elements.namedItem(name);
            const messageText = Array.isArray(messages) ? messages[0] : messages;
            if (!(field instanceof HTMLElement) || !messageText) {
                return;
            }
            if (field instanceof HTMLInputElement && field.type === 'hidden') {
                return;
            }

            field.setAttribute('aria-invalid', 'true');
            const errorId = `contact-${name}-dynamic-error`;
            const describedBy = new Set((field.getAttribute('aria-describedby') ?? '').split(/\s+/).filter(Boolean));
            describedBy.add(errorId);
            field.setAttribute('aria-describedby', Array.from(describedBy).join(' '));
            const error = document.createElement('small');
            error.id = errorId;
            error.className = 'contact-field-error';
            error.dataset.contactDynamicError = '';
            error.textContent = messageText;
            field.closest('label')?.append(error);
            firstInvalidField ??= field;
        });

        firstInvalidField?.focus({ preventScroll: true });
    };

    const setSubmitting = (submitting) => {
        isSubmitting = submitting;
        form.setAttribute('aria-busy', String(submitting));
        if (submitButton) {
            submitButton.disabled = submitting;
        }
        if (submitLabel) {
            submitLabel.textContent = submitting ? 'Sending...' : originalSubmitLabel;
        }
        if (submitIcon) {
            submitIcon.className = submitting ? 'bi bi-arrow-repeat' : originalIcon;
        }

    };

    const focusWithoutScroll = (element) => {
        if (!element) {
            return;
        }
        try {
            element.focus({ preventScroll: true });
        } catch {
            element.focus();
        }
    };

    const renderSuccess = (reference, successMessage) => {
        const success = document.createElement('div');
        success.className = 'contact-form-success';
        success.setAttribute('role', 'status');
        success.setAttribute('aria-live', 'polite');
        success.setAttribute('tabindex', '-1');
        success.dataset.contactSuccess = '';
        const heading = document.createElement('h4');
        heading.textContent = 'Message received';
        const description = document.createElement('p');
        description.textContent = successMessage;
        const referenceLine = document.createElement('p');
        const referenceLabel = document.createElement('strong');
        referenceLabel.textContent = `Reference: ${reference}`;
        referenceLine.append(referenceLabel);
        const anotherMessage = document.createElement('button');
        anotherMessage.className = 'contact-send-another';
        anotherMessage.type = 'button';
        anotherMessage.innerHTML = '<i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Send another message';
        anotherMessage.addEventListener('click', () => {
            success.before(form);
            success.remove();
            form.reset();
            const honeypot = form.elements.namedItem('honeypot');
            if (honeypot instanceof HTMLInputElement) {
                honeypot.value = '';
            }
            if (submittedAt) {
                submittedAt.value = String(Date.now() / 1000);
            }
            clearFieldErrors();
            showFeedback('');
            setSubmitting(false);
            if (message && counter) {
                counter.textContent = `${Array.from(message.value).length}/2000 characters`;
            }
            focusWithoutScroll(form.querySelector('[name="name"]'));
        });
        success.append(heading, description, referenceLine, anotherMessage);
        form.replaceWith(success);
        focusWithoutScroll(success);

        const bounds = success.getBoundingClientRect();
        if (bounds.top < 0 || bounds.bottom > window.innerHeight) {
            success.scrollIntoView({
                block: 'nearest',
                behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
            });
        }
    };

    form.addEventListener('submit', async (event) => {
        if (isSubmitting) {
            event.preventDefault();
            return;
        }
        if (!window.fetch || !window.FormData) {
            return;
        }

        event.preventDefault();
        clearFieldErrors();
        showFeedback('');
        setSubmitting(true);

        try {
            const token = form.elements.namedItem('_token')?.value ?? '';
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new FormData(form),
            });
            const result = await response.json().catch(() => ({}));

            if (response.status === 201 && result.reference) {
                renderSuccess(result.reference, result.message ?? 'Your message was sent.');
                return;
            }
            if (response.status === 422) {
                showValidationErrors(result.errors);
                showFeedback(result.message ?? 'Please check the highlighted fields and try again.', true);
            } else if (response.status === 419) {
                showFeedback('Your form session expired. Reload this page and try again; your entries are still here.', true);
            } else if (response.status === 429) {
                showFeedback(result.message ?? 'Too many messages were sent recently. Please try again later.', true);
            } else {
                showFeedback(result.message ?? 'We could not send your message. Please try again.', true);
            }
        } catch {
            showFeedback('We could not reach FleetGuard. Please check your connection and try again.', true);
        }

        setSubmitting(false);
    });
});

document.querySelectorAll('[data-reply-chooser]').forEach((chooser) => {
    const main = chooser.querySelector('[data-reply-main]');
    const toggle = chooser.querySelector('[data-reply-menu-toggle]');
    const menu = chooser.querySelector('[data-reply-menu]');
    const currentLabel = chooser.querySelector('[data-reply-current-label]');
    const remember = chooser.querySelector('[data-reply-remember]');
    const toast = chooser.querySelector('[data-reply-toast]');
    const storageKey = 'fleetguard.admin-message-reply-client';
    const clientLabels = {
        mailto: 'Default mail app',
        gmail: 'Gmail',
        outlook: 'Outlook',
        microsoft365: 'Microsoft 365',
        yahoo: 'Yahoo Mail',
    };
    let storageAvailable = true;
    let toastTimer;
    let choice = 'mailto';

    try {
        const storedChoice = window.localStorage.getItem(storageKey);
        if (Object.prototype.hasOwnProperty.call(clientLabels, storedChoice)) {
            choice = storedChoice;
        }
    } catch {
        storageAvailable = false;
    }

    const encode = (value) => encodeURIComponent(value).replace(/[!'()*]/g, (character) => `%${character.charCodeAt(0).toString(16).toUpperCase()}`);
    const composeUrl = (client) => {
        const to = encode(chooser.dataset.email ?? '');
        const subject = encode(chooser.dataset.subject ?? '');
        const body = encode(chooser.dataset.body ?? '');

        if (client === 'gmail') {
            return `https://mail.google.com/mail/?view=cm&fs=1&to=${to}&su=${subject}&body=${body}`;
        }
        if (client === 'outlook') {
            return `https://outlook.live.com/mail/0/deeplink/compose?to=${to}&subject=${subject}&body=${body}`;
        }
        if (client === 'microsoft365') {
            return `https://outlook.office.com/mail/deeplink/compose?to=${to}&subject=${subject}&body=${body}`;
        }
        if (client === 'yahoo') {
            return `https://compose.mail.yahoo.com/?to=${to}&subject=${subject}&body=${body}`;
        }

        return `mailto:${to}?subject=${subject}&body=${body}`;
    };

    const showToast = (text, isError = false) => {
        if (!toast) {
            return;
        }
        toast.textContent = text;
        toast.hidden = false;
        toast.classList.toggle('is-error', isError);
        window.clearTimeout(toastTimer);
        toastTimer = window.setTimeout(() => {
            toast.hidden = true;
        }, 2600);
    };

    const updateChoice = (nextChoice, persist = false) => {
        choice = Object.prototype.hasOwnProperty.call(clientLabels, nextChoice) ? nextChoice : 'mailto';
        if (main) {
            main.href = composeUrl(choice);
            main.target = choice === 'mailto' ? '_self' : '_blank';
            main.rel = 'noopener noreferrer';
        }
        if (currentLabel) {
            currentLabel.textContent = clientLabels[choice];
        }
        chooser.querySelectorAll('[data-reply-choice]').forEach((item) => {
            item.setAttribute('aria-checked', String(item.dataset.replyChoice === choice));
        });

        if (persist) {
            try {
                if (remember?.checked) {
                    window.localStorage.setItem(storageKey, choice);
                } else {
                    window.localStorage.removeItem(storageKey);
                }
            } catch {
                storageAvailable = false;
                choice = 'mailto';
                if (main) {
                    main.href = composeUrl(choice);
                }
                if (currentLabel) {
                    currentLabel.textContent = clientLabels.mailto;
                }
                showToast('Saved choices are unavailable; using the default mail app.', true);
            }
        }
    };

    const closeMenu = (returnFocus = false) => {
        if (!menu || !toggle) {
            return;
        }
        menu.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
        if (returnFocus) {
            toggle.focus();
        }
    };

    const openMenu = (focusLast = false) => {
        if (!menu || !toggle) {
            return;
        }
        menu.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        const items = [...menu.querySelectorAll('[role^="menuitem"]')];
        (focusLast ? items.at(-1) : items[0])?.focus();
    };

    updateChoice(choice);

    toggle?.addEventListener('click', () => {
        if (menu?.hidden) {
            openMenu();
        } else {
            closeMenu();
        }
    });
    toggle?.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            openMenu(event.key === 'ArrowUp');
        }
    });

    menu?.addEventListener('click', async (event) => {
        const choiceButton = event.target.closest('[data-reply-choice]');
        if (choiceButton) {
            if (!storageAvailable) {
                closeMenu();
                showToast('Saved choices are unavailable; using the default mail app.', true);
                return;
            }
            updateChoice(choiceButton.dataset.replyChoice, true);
            closeMenu();
            main?.focus();
            document.dispatchEvent(new CustomEvent('fleetguard:reply-client-changed', {
                detail: { choice },
            }));
            return;
        }

        if (event.target.closest('[data-reply-copy]')) {
            try {
                if (navigator.clipboard?.writeText) {
                    await navigator.clipboard.writeText(chooser.dataset.email ?? '');
                } else {
                    const temporary = document.createElement('textarea');
                    temporary.value = chooser.dataset.email ?? '';
                    temporary.setAttribute('readonly', '');
                    temporary.style.position = 'fixed';
                    temporary.style.opacity = '0';
                    document.body.append(temporary);
                    temporary.select();
                    let copied;
                    try {
                        copied = document.execCommand('copy');
                    } finally {
                        temporary.remove();
                    }
                    if (!copied) {
                        throw new Error('Clipboard copy was not available.');
                    }
                }
                closeMenu();
                showToast('Email address copied.');
                toggle?.focus();
            } catch {
                showToast('Could not copy the email address. Please select it manually.', true);
            }
        }
    });

    menu?.addEventListener('keydown', (event) => {
        const items = [...menu.querySelectorAll('[role^="menuitem"]')];
        const index = items.indexOf(document.activeElement);
        let nextIndex = index;
        if (event.key === 'ArrowDown') {
            nextIndex = (index + 1) % items.length;
        } else if (event.key === 'ArrowUp') {
            nextIndex = (index - 1 + items.length) % items.length;
        } else if (event.key === 'Home') {
            nextIndex = 0;
        } else if (event.key === 'End') {
            nextIndex = items.length - 1;
        } else if (event.key === 'Escape') {
            event.preventDefault();
            closeMenu(true);
            return;
        } else {
            return;
        }
        event.preventDefault();
        items[nextIndex]?.focus();
    });

    document.addEventListener('click', (event) => {
        if (!chooser.contains(event.target)) {
            closeMenu();
        }
    });
    document.addEventListener('fleetguard:reply-client-changed', (event) => {
        if (chooser !== event.target?.closest?.('[data-reply-chooser]')) {
            updateChoice(event.detail?.choice ?? 'mailto');
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
