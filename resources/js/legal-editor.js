const form = document.querySelector('[data-legal-editor]');
const toolbar = form?.querySelector('[data-legal-format-toolbar]');
const contentEditor = form?.querySelector('[data-legal-content-editor]');
const contentInput = form?.querySelector('#policy-content');
const errorMessage = form?.querySelector('[data-legal-editor-error]');
const stylePicker = toolbar?.querySelector('[data-legal-style-picker]');
const styleTrigger = stylePicker?.querySelector('[data-legal-style-trigger]');
const styleOptions = stylePicker?.querySelector('[data-legal-style-options]');

const blockStyles = {
    normal: { label: 'Normal text', tag: 'p' },
    title: { label: 'Title', tag: 'h1', fontSize: '30pt', fontWeight: '600', color: '#1b2530' },
    subtitle: { label: 'Subtitle', tag: 'p', fontSize: '18pt', fontWeight: '400', color: '#697582' },
    heading1: { label: 'Heading 1', tag: 'h1', fontSize: '24pt', fontWeight: '600', color: '#1b2530' },
    heading2: { label: 'Heading 2', tag: 'h2', fontSize: '18pt', fontWeight: '600', color: '#1b2530' },
    heading3: { label: 'Heading 3', tag: 'h3', fontSize: '14pt', fontWeight: '600', color: '#1b2530' },
};

if (form && toolbar && contentEditor instanceof HTMLElement && contentInput instanceof HTMLTextAreaElement) {
    let savedRange = null;

    const saveSelection = () => {
        const selection = window.getSelection();

        if (selection?.rangeCount && contentEditor.contains(selection.anchorNode)) {
            savedRange = selection.getRangeAt(0).cloneRange();
        }
    };

    const restoreSelection = () => {
        contentEditor.focus();

        if (!savedRange) {
            return;
        }

        const selection = window.getSelection();
        selection?.removeAllRanges();
        selection?.addRange(savedRange);
    };

    const syncContent = () => {
        contentInput.value = contentEditor.innerHTML;
    };

    const selectedBlocks = () => {
        const selection = window.getSelection();
        const range = selection?.rangeCount ? selection.getRangeAt(0) : null;
        const blocks = [...contentEditor.querySelectorAll('blockquote, div, h1, h2, h3, h4, li, p')];

        if (!range || range.collapsed) {
            const node = range?.startContainer;
            const parent = node instanceof Element ? node : node?.parentElement;
            const block = parent?.closest('blockquote, div, h1, h2, h3, h4, li, p');

            return block && contentEditor.contains(block) ? [block] : [contentEditor];
        }

        const matches = blocks.filter((block) => range.intersectsNode(block));
        const leafMatches = matches.filter((block) => !matches.some((other) => other !== block && block.contains(other)));

        return leafMatches.length ? leafMatches : [contentEditor];
    };

    const selectedElement = () => {
        const selection = window.getSelection();
        const range = savedRange || (selection?.rangeCount ? selection.getRangeAt(0) : null);
        const node = range?.startContainer;
        const element = node instanceof Element ? node : node?.parentElement;

        return element && contentEditor.contains(element) ? element : contentEditor;
    };

    const selectedBlock = () => selectedElement().closest('blockquote, div, h1, h2, h3, h4, li, p') || contentEditor;

    const nearestInlineStyle = (property) => {
        let element = selectedElement();

        while (element && contentEditor.contains(element)) {
            const value = element.style?.getPropertyValue(property);
            if (value) {
                return value;
            }
            element = element.parentElement;
        }

        return '';
    };

    const colorToHex = (value) => {
        const match = value.match(/^rgba?\((\d+),\s*(\d+),\s*(\d+)/i);
        if (!match) {
            return value.startsWith('#') ? value : null;
        }

        return `#${match.slice(1, 4).map((part) => Number(part).toString(16).padStart(2, '0')).join('')}`;
    };

    const selectedBlockStyle = () => {
        const block = selectedBlock();
        const tag = block.tagName.toLowerCase();
        const size = block.style.fontSize;

        if (tag === 'h1') {
            return size === '30pt' ? 'title' : 'heading1';
        }
        if (tag === 'h2') {
            return 'heading2';
        }
        if (tag === 'h3') {
            return 'heading3';
        }
        if (tag === 'p' && size === '18pt') {
            return 'subtitle';
        }

        return 'normal';
    };

    const updateStylePicker = () => {
        if (!stylePicker || !styleTrigger || !styleOptions) {
            return;
        }

        const currentStyle = selectedBlockStyle();
        const style = blockStyles[currentStyle];
        styleTrigger.querySelector('[data-legal-style-current]').textContent = style.label;
        styleTrigger.setAttribute('aria-label', `Paragraph style: ${style.label}`);

        styleOptions.querySelectorAll('[data-legal-block-style]').forEach((option) => {
            const selected = option.dataset.legalBlockStyle === currentStyle;
            option.setAttribute('aria-selected', String(selected));
            option.classList.toggle('is-active', selected);
            option.querySelector('.admin-legal-style-check').hidden = !selected;
        });
    };

    const updateToolbarState = () => {
        toolbar.querySelectorAll('[data-legal-state]').forEach((control) => {
            const state = control.dataset.legalState;
            let active = false;

            if (state === 'foreColor') {
                active = Boolean(nearestInlineStyle('color'));
            } else if (state === 'hiliteColor') {
                active = Boolean(nearestInlineStyle('background-color'));
            } else if (state === 'indent') {
                active = Boolean(selectedBlock().closest('blockquote') || nearestInlineStyle('margin-left'));
            } else {
                active = document.queryCommandState(state);
            }

            control.classList.toggle('is-active', active);
            if (control instanceof HTMLButtonElement) {
                control.setAttribute('aria-pressed', String(active));
            } else {
                control.dataset.active = String(active);
            }
        });

        const fontFamily = nearestInlineStyle('font-family');
        const fontFamilySelect = toolbar.querySelector('[data-legal-action="fontName"]');
        if (fontFamilySelect) {
            if (!fontFamily) {
                fontFamilySelect.value = '';
            } else {
                const family = fontFamily.replaceAll('"', '').split(',')[0].trim();
                fontFamilySelect.value = [...fontFamilySelect.options].find((option) => option.value === family)?.value || '';
            }
        }

        const fontSizeSelect = toolbar.querySelector('[data-legal-action="fontSize"]');
        if (fontSizeSelect) {
            const size = nearestInlineStyle('font-size') || window.getComputedStyle(selectedElement()).fontSize;
            const points = size.endsWith('px') ? Math.round(Number.parseFloat(size) * 0.75) : Number.parseFloat(size);
            fontSizeSelect.value = [...fontSizeSelect.options].reduce((closest, option) => (
                Math.abs(Number(option.value) - points) < Math.abs(Number(closest.value) - points) ? option : closest
            ), fontSizeSelect.options[0]).value;
        }

        const lineHeightSelect = toolbar.querySelector('[data-legal-action="lineHeight"]');
        if (lineHeightSelect) {
            const block = selectedBlock();
            const computed = window.getComputedStyle(block);
            const lineHeight = nearestInlineStyle('line-height') || computed.lineHeight;
            const blockSize = Number.parseFloat(computed.fontSize);
            const numericLineHeight = Number.parseFloat(lineHeight);
            const ratio = lineHeight === 'normal'
                ? 1.5
                : lineHeight.endsWith('px') ? numericLineHeight / blockSize : numericLineHeight;
            const candidates = [...lineHeightSelect.options];
            lineHeightSelect.value = candidates.reduce((closest, option) => (
                Math.abs(Number(option.value) - ratio) < Math.abs(Number(closest.value) - ratio) ? option : closest
            ), candidates[0]).value;
        }

        const foreground = colorToHex(nearestInlineStyle('color'));
        const foregroundInput = toolbar.querySelector('[data-legal-action="foreColor"]');
        if (foreground && foregroundInput) {
            foregroundInput.value = foreground;
        }

        const highlight = colorToHex(nearestInlineStyle('background-color'));
        const highlightInput = toolbar.querySelector('[data-legal-action="hiliteColor"]');
        if (highlight && highlightInput) {
            highlightInput.value = highlight;
        }

        updateStylePicker();
    };

    const normalizeFontTags = (fontSize = null) => {
        contentEditor.querySelectorAll('font').forEach((font) => {
            const span = document.createElement('span');
            const face = font.getAttribute('face');
            const color = font.getAttribute('color');
            const size = font.getAttribute('size');
            const sizeMap = { 1: '8pt', 2: '10pt', 3: '12pt', 4: '14pt', 5: '18pt', 6: '24pt', 7: '36pt' };

            if (face) {
                span.style.fontFamily = face;
            }

            if (color) {
                span.style.color = color;
            }

            if (fontSize) {
                span.style.fontSize = `${fontSize}pt`;
            } else if (sizeMap[size]) {
                span.style.fontSize = sizeMap[size];
            }

            while (font.firstChild) {
                span.append(font.firstChild);
            }

            font.replaceWith(span);
        });
    };

    const normalizeListContainers = () => {
        contentEditor.querySelectorAll('ol, ul').forEach((list) => {
            let parent = list.parentElement;

            while (
                parent
                && parent !== contentEditor
                && ['DIV', 'P'].includes(parent.tagName)
                && [...parent.childNodes].every((node) => node === list || (node.nodeType === Node.TEXT_NODE && !node.textContent.trim()))
            ) {
                const container = parent;
                parent = container.parentElement;
                container.replaceWith(list);
            }
        });
    };

    const applyLineHeight = (value) => {
        restoreSelection();
        selectedBlocks().forEach((block) => {
            block.style.lineHeight = value;
        });
    };

    const clearFormatting = () => {
        restoreSelection();
        document.execCommand('removeFormat', false);
        normalizeFontTags();
        const blocks = new Set(selectedBlocks());

        blocks.forEach((block) => {
            const quote = block.closest('blockquote');
            if (quote && contentEditor.contains(quote)) {
                blocks.add(quote);
            }
        });

        blocks.forEach((block) => {
            block.removeAttribute('style');

            if (block.tagName === 'BLOCKQUOTE') {
                while (block.firstChild) {
                    block.parentNode.insertBefore(block.firstChild, block);
                }
                block.remove();
            }
        });
    };

    const applyBlockStyle = (styleKey) => {
        const style = blockStyles[styleKey];
        if (!style) {
            return;
        }

        restoreSelection();
        document.execCommand('formatBlock', false, `<${style.tag}>`);
        selectedBlocks().forEach((block) => {
            ['font-size', 'font-weight', 'color'].forEach((property) => block.style.removeProperty(property));

            if (style.fontSize) {
                block.style.fontSize = style.fontSize;
                block.style.fontWeight = style.fontWeight;
                block.style.color = style.color;
            }

            if (!block.getAttribute('style')) {
                block.removeAttribute('style');
            }
        });

        saveSelection();
        syncContent();
        updateToolbarState();
    };

    const runAction = (action, value = null) => {
        restoreSelection();

        if (action === 'lineHeight') {
            applyLineHeight(value);
        } else if (action === 'removeFormat') {
            clearFormatting();
        } else if (action === 'fontSize') {
            document.execCommand('fontSize', false, '7');
            normalizeFontTags(value);
        } else {
            document.execCommand(action, false, value);
            if (action === 'fontName' || action === 'foreColor' || action === 'hiliteColor') {
                normalizeFontTags();
            }
            if (action === 'insertOrderedList' || action === 'insertUnorderedList') {
                normalizeListContainers();
            }
        }

        saveSelection();
        syncContent();
        contentEditor.removeAttribute('aria-invalid');
        if (errorMessage) {
            errorMessage.hidden = true;
        }
        updateToolbarState();
    };

    toolbar.addEventListener('pointerdown', (event) => {
        if (event.target instanceof Element) {
            saveSelection();
        }
    });

    toolbar.addEventListener('mousedown', (event) => {
        if (event.target instanceof Element && event.target.closest('button')) {
            event.preventDefault();
        }
    });

    toolbar.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) {
            return;
        }

        const stylePickerButton = event.target.closest('[data-legal-style-trigger]');
        if (stylePickerButton && styleOptions) {
            const opening = styleOptions.hidden;
            styleOptions.hidden = !opening;
            styleTrigger.setAttribute('aria-expanded', String(opening));
            updateStylePicker();
            if (opening) {
                styleOptions.querySelector('[aria-selected="true"]')?.focus();
            }
            return;
        }

        const styleOption = event.target.closest('button[data-legal-block-style]');
        if (styleOption) {
            applyBlockStyle(styleOption.dataset.legalBlockStyle);
            styleOptions.hidden = true;
            styleTrigger.setAttribute('aria-expanded', 'false');
            styleTrigger.focus();
            return;
        }

        const button = event.target.closest('button[data-legal-action]');
        if (button) {
            runAction(button.dataset.legalAction);
        }
    });

    styleOptions?.addEventListener('keydown', (event) => {
        const options = [...styleOptions.querySelectorAll('[data-legal-block-style]')];
        const currentIndex = options.indexOf(document.activeElement);
        let nextIndex = null;

        if (event.key === 'ArrowDown') {
            nextIndex = (currentIndex + 1) % options.length;
        } else if (event.key === 'ArrowUp') {
            nextIndex = (currentIndex - 1 + options.length) % options.length;
        } else if (event.key === 'Home') {
            nextIndex = 0;
        } else if (event.key === 'End') {
            nextIndex = options.length - 1;
        } else if (event.key === 'Escape') {
            styleOptions.hidden = true;
            styleTrigger.setAttribute('aria-expanded', 'false');
            styleTrigger.focus();
        }

        if (nextIndex !== null) {
            event.preventDefault();
            options[nextIndex].focus();
        }
    });

    document.addEventListener('pointerdown', (event) => {
        if (stylePicker && !stylePicker.contains(event.target) && styleOptions && !styleOptions.hidden) {
            styleOptions.hidden = true;
            styleTrigger.setAttribute('aria-expanded', 'false');
        }
    });

    toolbar.addEventListener('change', (event) => {
        if (!(event.target instanceof HTMLSelectElement || event.target instanceof HTMLInputElement)) {
            return;
        }

        const { legalAction } = event.target.dataset;
        if (legalAction) {
            runAction(legalAction, event.target.value);
        }
    });

    contentEditor.addEventListener('keyup', () => {
        saveSelection();
        updateToolbarState();
    });
    contentEditor.addEventListener('mouseup', () => {
        saveSelection();
        updateToolbarState();
    });
    contentEditor.addEventListener('input', () => {
        saveSelection();
        syncContent();
        updateToolbarState();
        contentEditor.removeAttribute('aria-invalid');
        if (errorMessage) {
            errorMessage.hidden = true;
        }
    });

    contentEditor.addEventListener('keydown', (event) => {
        const commandKey = event.ctrlKey || event.metaKey;
        const key = event.key.toLowerCase();
        const shortcuts = {
            b: 'bold',
            i: 'italic',
            u: 'underline',
        };
        let action = commandKey && !event.altKey ? shortcuts[key] : null;

        if (commandKey && event.shiftKey && key === 'x') {
            action = 'strikeThrough';
        } else if (commandKey && event.shiftKey && event.code === 'Digit7') {
            action = 'insertOrderedList';
        } else if (commandKey && event.shiftKey && event.code === 'Digit8') {
            action = 'insertUnorderedList';
        } else if (event.altKey && event.shiftKey && event.key === 'ArrowRight') {
            action = 'indent';
        } else if (event.altKey && event.shiftKey && event.key === 'ArrowLeft') {
            action = 'outdent';
        }

        if (action) {
            event.preventDefault();
            saveSelection();
            runAction(action);
        }
    });

    document.addEventListener('selectionchange', () => {
        const selection = window.getSelection();
        if (selection?.rangeCount && contentEditor.contains(selection.anchorNode)) {
            saveSelection();
            updateToolbarState();
        }
    });

    form.addEventListener('submit', (event) => {
        syncContent();
        const hasText = contentEditor.textContent.trim().length > 0;
        const withinLimit = contentInput.value.length <= 50000;

        if (!hasText || !withinLimit) {
            event.preventDefault();
            contentEditor.setAttribute('aria-invalid', 'true');
            contentEditor.focus();

            if (errorMessage) {
                errorMessage.textContent = hasText
                    ? 'Policy content must be 50,000 characters or fewer.'
                    : 'Enter policy content before saving.';
                errorMessage.hidden = false;
            }
        }
    });

    const publishForm = document.querySelector('[data-legal-publish-form]');
    const effectiveDate = form.querySelector('[name="effective_at"]');
    const publishEffectiveDate = publishForm?.querySelector('[name="effective_at"]');

    if (publishForm && effectiveDate && publishEffectiveDate) {
        publishForm.addEventListener('submit', () => {
            publishEffectiveDate.value = effectiveDate.value;
        });
    }

    syncContent();
    updateToolbarState();
}
