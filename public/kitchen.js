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
        const opened = detailsState();
        const focusId = document.activeElement?.id;
        const row = form?.closest('.stock-row');
        const rowId = row?.id;
        const rowTop = row?.getBoundingClientRect().top;
        let data;
        if (form) {
            data = new FormData(form);
            if (submitter?.name) data.append(submitter.name, submitter.value);
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
        if (mutation) toast(upload ? 'Uploading and reading…' : 'Saving…', false, true);
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
            } else {
                scrollTo(0, pop ? (history.state?.scroll ?? 0) : 0);
                focusMain();
            }
            if (mutation) toast(notice?.includes('removed') ? 'Removed · Undo below' : 'Saved ✓');
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
})();
