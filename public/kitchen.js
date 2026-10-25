(() => {
    'use strict';
    document.body.dataset.clientSession = crypto.randomUUID ? crypto.randomUUID() : String(Date.now());
    let busy = false;
    const live = document.createElement('div');
    live.id = 'save-status';
    live.setAttribute('role', 'status');
    live.setAttribute('aria-live', 'polite');
    document.body.append(live);
    let toastTimer;

    function toast(message, error = false, keep = false) {
        clearTimeout(toastTimer);
        live.textContent = message;
        live.className = `visible${error ? ' failed' : ''}`;
        if (!keep) toastTimer = setTimeout(() => live.className = '', 3500);
    }

    function detailsState() {
        return [...document.querySelectorAll('main details[id][open]')].map(el => el.id);
    }

    function focusMain() {
        const heading = document.querySelector('main h1');
        if (heading) {
            heading.tabIndex = -1;
            heading.focus({ preventScroll: true });
        }
    }

    function restoreControls(form) {
        form?.querySelectorAll('[data-kitchen-disabled]').forEach(control => {
            control.disabled = false;
            delete control.dataset.kitchenDisabled;
        });
    }

    async function visit(url, options = {}) {
        if (busy) {
            toast('Please wait for this save to finish.', false, true);
            return;
        }
        const { form, submitter, pop = false } = options;
        const mutation = form && form.method.toLowerCase() !== 'get';
        const previousUrl = location.href;
        const oldScroll = scrollY;
        // A "Done" button closes the popover it sits in; everything else stays as it was.
        const closing = submitter?.dataset.close !== undefined ? form?.closest('details[id]')?.id : null;
        const opened = detailsState().filter(id => id !== closing);
        const focusId = form?.dataset.keepFocus !== undefined ? form.querySelector('[data-food-suggest]')?.id : document.activeElement?.id;
        const row = form?.closest('.item, .stock-row');
        const rowId = row?.id;
        const rowTop = row?.getBoundingClientRect().top;
        let data;
        if (form) {
            data = new FormData(form);
            if (submitter?.name) data.append(submitter.name, submitter.value);
            if (form.photoBlob) data.set('photo', form.photoBlob, 'photo.jpg');
            if (!mutation) {
                url = new URL(form.action, location.href);
                url.search = new URLSearchParams(data).toString();
                data = undefined;
            }
        }
        busy = true;
        document.body.classList.add('is-saving');
        if (mutation) document.querySelector('main').inert = true;
        if (form) {
            form.setAttribute('aria-busy', 'true');
            form.querySelectorAll('button,input[type=submit]').forEach(control => {
                if (!control.disabled) {
                    control.dataset.kitchenDisabled = 'true';
                    control.disabled = true;
                }
            });
        }
        const upload = form?.enctype === 'multipart/form-data';
        if (mutation) toast(upload ? 'Looking at your photo…' : 'Saving…', false, true);
        const abort = new AbortController();
        const timeout = setTimeout(() => abort.abort(), 95000);
        try {
            const response = await fetch(url, {
                method: mutation ? 'POST' : 'GET',
                body: mutation ? data : undefined,
                credentials: 'same-origin', cache: 'no-store',
                headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest', 'X-Kitchen-Visit': '1' },
                signal: abort.signal,
            });
            if (response.status === 419) throw new Error('Your session expired. Open Sign in again before saving.');
            if (!response.headers.get('content-type')?.includes('text/html')) throw new Error('Could not read the result. Please try again.');
            const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
            const errors = parsed.querySelector('main .notice.error');
            if (errors && form) {
                document.querySelector('main > .notice.error')?.remove();
                const alert = document.importNode(errors, true);
                alert.tabIndex = -1;
                document.querySelector('main').prepend(alert);
                restoreControls(form);
                const retry = document.createElement('button');
                retry.type = 'submit'; retry.className = 'small secondary retry-save'; retry.textContent = 'Try again';
                if (form.querySelector('[data-auto-save]') && !form.querySelector('.retry-save')) form.append(retry);
                alert.focus();
                toast('Not saved — check the message above.', true);
                return;
            }
            if (!response.ok) throw new Error(`Could not complete this action (${response.status}). Your entries are still here.`);
            const incoming = parsed.querySelector('main');
            if (!incoming) throw new Error('Could not load the page. Please try again.');
            const destination = new URL(response.url);
            if (destination.origin !== location.origin) throw new Error('Could not open this destination.');
            const samePage = destination.pathname === new URL(previousUrl).pathname && destination.search === new URL(previousUrl).search;
            const notice = incoming.querySelector('.notice.success')?.textContent.trim();
            incoming.querySelectorAll('.notice.success').forEach(el => el.remove());
            document.querySelector('main').replaceChildren(...[...incoming.childNodes].map(el => document.importNode(el, true)));
            for (const selector of ['header.top', 'nav[aria-label="Main navigation"]']) {
                const current = document.querySelector(selector);
                const next = parsed.querySelector(selector);
                if (current && next) current.replaceWith(document.importNode(next, true));
                else if (current) current.remove();
                else if (next) document.querySelector('main').before(document.importNode(next, true));
            }
            document.title = parsed.title;
            if (!pop && destination.href !== location.href) {
                history.replaceState({ scroll: oldScroll }, '', previousUrl);
                history.pushState({ scroll: 0 }, '', destination.href);
            }
            if (samePage && mutation) {
                opened.forEach(id => { const el = document.getElementById(id); if (el?.tagName === 'DETAILS') el.open = true; });
                const newRow = rowId && document.getElementById(rowId);
                if (newRow?.getClientRects().length && rowTop !== undefined) scrollTo(0, scrollY + newRow.getBoundingClientRect().top - rowTop);
                else scrollTo(0, oldScroll);
                const focus = focusId && document.getElementById(focusId);
                if (focus) focus.focus({ preventScroll: true });
            } else if (form?.dataset.keepFocus !== undefined && focusId && document.getElementById(focusId)) {
                document.getElementById(focusId).focus({ preventScroll: true });
            } else {
                scrollTo(0, pop ? (history.state?.scroll ?? 0) : 0);
                focusMain();
            }
            if (mutation && notice) toast(notice);
            else live.className = '';
        } catch (error) {
            restoreControls(form);
            toast(error.name === 'AbortError' ? 'Connection timed out. Check your list before trying again.' : (error.message || 'Connection lost. Please try again.'), true, true);
            if (form?.querySelector('[data-auto-save]') && !form.querySelector('.retry-save')) {
                const retry = document.createElement('button');
                retry.type = 'submit'; retry.className = 'small secondary retry-save'; retry.textContent = 'Try again';
                form.append(retry);
            }
        } finally {
            clearTimeout(timeout);
            form?.removeAttribute('aria-busy');
            document.body.classList.remove('is-saving');
            document.querySelector('main').inert = false;
            busy = false;
        }
    }

    document.addEventListener('submit', event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.dataset.fullPage !== undefined || form.target) return;
        if (new URL(form.action).origin !== location.origin) return;
        event.preventDefault();
        if (form.reportValidity()) visit(form.action, { form, submitter: event.submitter });
    });
    // Kitchen photos: shrink on the phone first so uploads are quick and stay under server limits.
    function shrinkPhoto(file, size = 1600) {
        return new Promise(resolve => {
            const url = URL.createObjectURL(file);
            const img = new Image();
            img.onload = () => {
                const scale = Math.min(1, size / Math.max(img.naturalWidth, img.naturalHeight));
                const canvas = document.createElement('canvas');
                canvas.width = Math.round(img.naturalWidth * scale);
                canvas.height = Math.round(img.naturalHeight * scale);
                canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
                URL.revokeObjectURL(url);
                canvas.toBlob(blob => resolve(blob), 'image/jpeg', 0.85);
            };
            img.onerror = () => { URL.revokeObjectURL(url); resolve(null); };
            img.src = url;
        });
    }
    document.addEventListener('change', async event => {
        const input = event.target.closest('[data-photo-upload]');
        if (!input?.files?.length) return;
        toast('Looking at your photo…', false, true);
        input.form.photoBlob = await shrinkPhoto(input.files[0]);
        input.form.requestSubmit();
        input.form.photoBlob = null;
        input.value = '';
    });
    document.addEventListener('change', event => {
        const control = event.target.closest('[data-auto-save]');
        if (control?.form) control.form.requestSubmit();
    });
    document.addEventListener('click', event => {
        const link = event.target.closest('a[href]');
        if (!link || link.target || link.hasAttribute('download') || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
        const url = new URL(link.href, location.href);
        if (url.origin !== location.origin || !['http:', 'https:'].includes(url.protocol)) return;
        if (url.pathname === location.pathname && url.search === location.search && url.hash) return;
        event.preventDefault();
        visit(url.href);
    });
    window.addEventListener('popstate', () => {
        if (busy) {
            toast('Please wait for this save to finish.', false, true);
            return;
        }
        visit(location.href, { pop: true });
    });

    // Type-ahead food suggestions. Picking one also selects where it usually lives.
    const placeNames = { fridge: 'Fridge', freezer: 'Freezer', pantry: 'Cupboard' };
    let suggest = null;
    function foods() {
        try { return JSON.parse(document.getElementById('food-suggestions')?.textContent || '[]'); } catch { return []; }
    }
    function closeSuggest() {
        suggest?.list.remove();
        suggest?.input.setAttribute('aria-expanded', 'false');
        suggest = null;
    }
    function escapeHtml(text) {
        return text.replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    }
    const quantityPrefix = /^(\d+(?:\.\d+)?\s*(?:g|kg|ml|l|litres?|pints?|packs?|tins?|cans?|bottles?|bags?|boxes?|jars?|loaf|loaves|bunch(?:es)?|dozen)?\s*(?:x|×)?\s+)(.*)$/i;
    function splitQuantity(value) {
        const match = value.match(quantityPrefix);
        return match ? [match[1], match[2]] : ['', value];
    }
    function choose(input, food) {
        input.value = (input.dataset.quantity !== undefined ? splitQuantity(input.value)[0] : '') + food[0];
        const radio = input.form?.querySelector(`input[name="${input.dataset.placeTarget}"][value="${food[1]}"]`);
        if (radio) radio.checked = true;
        closeSuggest();
        input.focus();
        // Picking a suggestion adds it straight away.
        input.form?.requestSubmit();
    }
    function showSuggest(input) {
        const term = (input.dataset.quantity !== undefined ? splitQuantity(input.value)[1] : input.value).trim().toLowerCase();
        if (!term) return closeSuggest();
        const all = foods();
        const starts = all.filter(f => f[0].toLowerCase().startsWith(term));
        const words = all.filter(f => !starts.includes(f) && f[0].toLowerCase().split(/[\s-]+/).some(w => w.startsWith(term)));
        const contains = term.length > 2 ? all.filter(f => !starts.includes(f) && !words.includes(f) && f[0].toLowerCase().includes(term)) : [];
        const matches = [...starts, ...words, ...contains].slice(0, 7);
        if (!matches.length || (matches.length === 1 && matches[0][0].toLowerCase() === term)) return closeSuggest();
        if (!suggest || suggest.input !== input) {
            closeSuggest();
            const list = document.createElement('ul');
            list.className = 'suggest';
            list.id = `${input.id}-suggest`;
            list.setAttribute('role', 'listbox');
            input.setAttribute('aria-controls', list.id);
            input.setAttribute('aria-autocomplete', 'list');
            input.setAttribute('role', 'combobox');
            input.closest('.quick-add-row').after(list);
            suggest = { input, list, matches: [], active: -1 };
        }
        suggest.matches = matches;
        suggest.active = -1;
        input.setAttribute('aria-expanded', 'true');
        input.removeAttribute('aria-activedescendant');
        const at = new RegExp(`(${term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'i');
        suggest.list.innerHTML = matches.map((f, i) => `<li role="option" id="${input.id}-opt-${i}" data-index="${i}" aria-selected="false"><span>${escapeHtml(f[0]).replace(at, '<mark>$1</mark>')}</span><small>${escapeHtml(f[2] || placeNames[f[1]] || '')}</small></li>`).join('');
    }
    function highlight(index) {
        if (!suggest) return;
        suggest.active = (index + suggest.matches.length) % suggest.matches.length;
        suggest.list.querySelectorAll('li').forEach((li, i) => li.setAttribute('aria-selected', String(i === suggest.active)));
        const li = suggest.list.children[suggest.active];
        li?.scrollIntoView({ block: 'nearest' });
        suggest.input.setAttribute('aria-activedescendant', li?.id || '');
    }
    document.addEventListener('input', event => {
        if (event.target.matches('[data-food-suggest]')) showSuggest(event.target);
    });
    document.addEventListener('keydown', event => {
        if (!suggest || event.target !== suggest.input) return;
        if (event.key === 'ArrowDown') { event.preventDefault(); highlight(suggest.active + 1); }
        else if (event.key === 'ArrowUp') { event.preventDefault(); highlight(suggest.active - 1); }
        else if (event.key === 'Escape') closeSuggest();
        else if (event.key === 'Enter' && suggest.active >= 0) { event.preventDefault(); choose(suggest.input, suggest.matches[suggest.active]); }
    });
    document.addEventListener('pointerdown', event => {
        const li = event.target.closest('.suggest li');
        if (li && suggest) { event.preventDefault(); choose(suggest.input, suggest.matches[Number(li.dataset.index)]); }
        else if (suggest && !event.target.closest('.quick-add')) closeSuggest();
    });
    document.addEventListener('submit', closeSuggest, true);
    document.addEventListener('focusout', event => {
        if (suggest && event.target === suggest.input) setTimeout(() => { if (suggest && document.activeElement !== suggest.input) closeSuggest(); }, 150);
    });

    // Kitchen rows: swipe right to send to the shopping list, swipe left to remove.
    let swipe = null;
    let swipedAt = 0;
    // Swallow the click that follows a swipe so it doesn't press a button.
    document.addEventListener('click', event => {
        if (Date.now() - swipedAt < 400) { event.stopPropagation(); event.preventDefault(); }
    }, true);
    document.addEventListener('pointerdown', event => {
        if (event.pointerType === 'mouse' || busy) return;
        const row = event.target.closest('[data-swipe] > .item');
        if (!row || event.target.closest('details[open], select, input')) return;
        swipe = { row, wrap: row.parentElement, x: event.clientX, y: event.clientY, dx: 0, active: false, id: event.pointerId };
    });
    document.addEventListener('pointermove', event => {
        if (!swipe || event.pointerId !== swipe.id) return;
        const dx = event.clientX - swipe.x;
        const dy = event.clientY - swipe.y;
        if (!swipe.active) {
            if (Math.abs(dy) > 12 && Math.abs(dy) > Math.abs(dx)) { swipe = null; return; }
            if (Math.abs(dx) < 12) return;
            swipe.active = true;
            swipe.wrap.classList.add('is-swiping');
            try { swipe.row.setPointerCapture(event.pointerId); } catch { /* Capture is best effort. */ }
        }
        swipe.dx = dx;
        const width = swipe.row.offsetWidth;
        swipe.row.style.transform = `translateX(${dx}px)`;
        swipe.wrap.classList.toggle('show-right', dx > 0);
        swipe.wrap.classList.toggle('show-left', dx < 0);
        swipe.wrap.classList.toggle('armed', Math.abs(dx) > width * 0.35);
    });
    function endSwipe(event) {
        if (!swipe || event.pointerId !== swipe.id) return;
        const { row, wrap, dx, active } = swipe;
        swipe = null;
        if (!active) return;
        wrap.classList.remove('is-swiping');
        const width = row.offsetWidth;
        const form = Math.abs(dx) > width * 0.35 && event.type === 'pointerup' && document.getElementById(dx > 0 ? row.dataset.swipeRight : row.dataset.swipeLeft);
        swipedAt = Date.now();
        setTimeout(() => wrap.classList.remove('show-right', 'show-left', 'armed'), 200);
        if (form) {
            row.style.transform = `translateX(${dx > 0 ? width : -width}px)`;
            setTimeout(() => form.requestSubmit(), 180);
        } else {
            row.style.transform = '';
        }
    }
    document.addEventListener('pointerup', endSwipe);
    document.addEventListener('pointercancel', endSwipe);

    document.addEventListener('click', event => {
        const cancel = event.target.closest('[data-cancel]');
        if (cancel) cancel.closest('details').open = false;
    });
})();
