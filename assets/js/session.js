(function () {
    // Global session manager
    const SessionManager = {
        _pinging: false,
        _lastPing: 0,
        pingIntervalMs: 4 * 60 * 1000, // 4 minutes
        minActivityPingMs: 60 * 1000, // at most 1 ping per minute on activity

        ping() {
            if (this._pinging) return;
            this._pinging = true;
            fetch('ajax/check_session.php', { credentials: 'same-origin' })
                .then(async resp => {
                    const txt = await resp.text();
                    let j = null;
                    try { j = JSON.parse(txt); } catch (_) { }
                    if (!resp.ok || (j && j.success === false)) {
                        console.warn('[SESSION] Session check failed', resp.status, txt.substring(0, 300));
                        this.onExpired();
                    } else {
                        console.log('[SESSION] active');
                    }
                })
                .catch(err => {
                    console.warn('[SESSION] check error', err);
                })
                .finally(() => { this._pinging = false; });
        },

        start() {
            // Activity listeners
            const maybePing = () => {
                const now = Date.now();
                if (now - this._lastPing > this.minActivityPingMs) {
                    this._lastPing = now;
                    this.ping();
                }
            };
            ['mousemove', 'keydown', 'click', 'touchstart'].forEach(evt => window.addEventListener(evt, maybePing, { passive: true }));

            // Periodic ping
            this._interval = setInterval(() => this.ping(), this.pingIntervalMs);

            // Expose manual trigger
            window.SessionManager = {
                ping: () => this.ping(),
                stop: () => clearInterval(this._interval)
            };
        },

        onExpired() {
            // Show modal if exists
            try {
                const modalEl = document.getElementById('sessionExpiredModal');
                if (modalEl) {
                    const btn = document.getElementById('sessionExpiredLoginBtn');
                    if (btn) btn.addEventListener('click', () => { window.location.href = 'login.php'; });
                    const instance = (typeof bootstrap !== 'undefined') ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
                    if (instance) instance.show(); else alert('Sessão expirada. Serás redirecionado para o login.');
                } else {
                    alert('Sessão expirada. Serás redirecionado para o login.');
                }
            } catch (e) {
                try { alert('Sessão expirada. Serás redirecionado para o login.'); } catch (_) { }
            }
            setTimeout(() => { window.location.href = 'login.php'; }, 1200);
        }
    };

    // Auto-start when DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => SessionManager.start());
    } else {
        SessionManager.start();
    }
})();
