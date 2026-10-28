/*
 * Kitchen HQ ⇄ phone app.
 *
 * Only does anything inside the iOS/Android app (window.NativeApp). While you're signed in here,
 * it gives the app a device token so its offline shopping list can sync on its own; on the login
 * page it forgets that token. Nothing happens in a normal browser.
 */
(function () {
    'use strict';
    if (!window.NativeApp || !window.NativeApp.isNative) return;

    var KEY = 'khq.session';
    var meta = function (name) { var m = document.querySelector('meta[name="' + name + '"]'); return m ? m.getAttribute('content') : null; };

    NativeApp.ready(function () {
        var userId = meta('khq-user');
        if (!userId) {
            NativeApp.storage.remove(KEY).catch(function () {});
            return;
        }
        NativeApp.storage.getJSON(KEY).then(function (saved) {
            if (saved && saved.token && String(saved.user && saved.user.id) === userId && saved.api === location.origin) return;
            return fetch('/app/token', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': meta('csrf-token') },
                body: JSON.stringify({ device: (NativeApp.platform || 'phone') + ' app' })
            }).then(function (r) { return r.ok ? r.json() : null; }).then(function (d) {
                if (d && d.token) {
                    return NativeApp.storage.setJSON(KEY, { token: d.token, user: d.user, household: d.household, api: location.origin });
                }
            });
        }).catch(function (e) { console.warn('[Kitchen HQ] app sign-in for offline lists failed', e); });
    });
})();
