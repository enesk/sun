/*
 * Alpine-Komponenten, die vorher als Inline-Fabrik in Blade standen (#21).
 * ------------------------------------------------------------------
 * Alpine wertet x-data mit new Function() aus ('unsafe-eval' in
 * config/csp.php), aber die Fabriken selbst waren echte Inline-Skripte und
 * haetten eine Policy ohne 'unsafe-inline' nicht ueberlebt.
 *
 * Alpine.data() nimmt Parameter wie eine Fabrik, die Views konnten also ihre
 * Aufrufe behalten: x-data="statsCompany(42)", x-data="resendTimer(60)".
 *
 * Registriert wird in 'alpine:init' — Livewire 3 bringt Alpine mit und startet
 * es selbst (siehe resources/js/app.js).
 */

/** Registrierung: Passwortstaerke im SaaSykit-Registrierungsformular. */
function passwordStrength() {
    return {
        password: '',
        strength: 0,
        strengthLabel: '',
        strengthColor: 'bg-base-300',
        strengthTextColor: 'text-base-content/50',
        checkStrength() {
            const p = this.password;
            let score = 0;

            if (p.length >= 8) score++;
            if (p.length >= 12) score++;
            if (/[A-Z]/.test(p) && /[a-z]/.test(p)) score++;
            if (/[0-9]/.test(p)) score++;
            if (/[^A-Za-z0-9]/.test(p)) score++;

            this.strength = Math.min(4, score);

            const labels = ['', 'Schwach', 'Ausreichend', 'Gut', 'Stark'];
            const colors = ['bg-base-300', 'bg-red-400', 'bg-yellow-400', 'bg-blue-400', 'bg-green-500'];
            const textColors = ['text-base-content/50', 'text-red-500', 'text-yellow-600', 'text-blue-500', 'text-green-600'];

            this.strengthLabel = labels[this.strength] || '';
            this.strengthColor = colors[this.strength] || 'bg-base-300';
            this.strengthTextColor = textColors[this.strength] || 'text-base-content/50';
        },
    };
}

/** E-Mail-Bestaetigung: Sperre fuer "noch einmal senden". */
function resendTimer(start = 0) {
    return {
        cooldown: start,
        interval: null,
        startCooldown() {
            this.cooldown = 60;
            this.interval = setInterval(() => {
                this.cooldown--;

                if (this.cooldown <= 0) {
                    clearInterval(this.interval);
                }
            }, 1000);
        },
        init() {
            if (this.cooldown > 0) {
                this.startCooldown();
            }
        },
    };
}

/** Uebernahme-Dialog: Seite im Hintergrund nicht mitscrollen lassen. */
function claimModal() {
    return {
        init() {
            document.body.style.overflow = 'hidden';
            this.$cleanup(() => {
                document.body.style.overflow = '';
            });
        },
    };
}

/** Uebernahme-Nachweis: Datei per Ziehen und Ablegen. */
function claimUploadDropzone() {
    return {
        dragover: false,
        handleDrop(event) {
            this.dragover = false;

            const dateien = event.dataTransfer.files;

            if (dateien.length > 0) {
                this.$refs.fileInput.files = dateien;
                this.$refs.fileInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
        },
    };
}

/** Betriebsbereich: Seitenleiste auf kleinen Schirmen. */
function dashboardApp() {
    return {
        sidebarOpen: false,
        _previousFocus: null,

        toggleSidebar() {
            this.sidebarOpen = !this.sidebarOpen;

            if (!this.sidebarOpen) {
                this._previousFocus?.focus();

                return;
            }

            this._previousFocus = document.activeElement;
            this.$nextTick(() => {
                document.querySelector('.dash-sidebar')?.querySelector('.dash-nav-item')?.focus();
            });
        },

        closeSidebarOnEscape(e) {
            if (e.key === 'Escape' && this.sidebarOpen && window.innerWidth < 768) {
                this.sidebarOpen = false;
                this._previousFocus?.focus();
            }
        },

        init() {
            document.addEventListener('keydown', (e) => this.closeSidebarOnEscape(e));

            this.announce = (msg) => {
                const el = document.getElementById('dash-announcements');

                if (!el) {
                    return;
                }

                el.textContent = '';
                requestAnimationFrame(() => {
                    el.textContent = msg;
                });
            };
        },
    };
}

/** Betriebsbereich: Hinweisfenster oben rechts. */
function toastManager() {
    return {
        toasts: [],
        nextId: 0,
        addToast({ type = 'info', message = '', duration = 5000 }) {
            const id = this.nextId++;
            this.toasts.push({ id, type, message, visible: true });

            if (duration > 0) {
                setTimeout(() => this.removeToast(id), duration);
            }
        },
        removeToast(id) {
            const toast = this.toasts.find((t) => t.id === id);

            if (toast) {
                toast.visible = false;
            }

            setTimeout(() => {
                this.toasts = this.toasts.filter((t) => t.id !== id);
            }, 300);
        },
    };
}

/** Gemeinsame Darstellung der Statistik-Seiten. */
const statsFormat = {
    fmt(n) {
        return new Intl.NumberFormat('de-DE').format(n || 0);
    },
    trendClass(change) {
        if (change > 0) return 'dash-stat-trend dash-stat-trend-up';
        if (change < 0) return 'dash-stat-trend dash-stat-trend-down';

        return 'dash-stat-trend';
    },
    trendText(change) {
        if (change > 0) return `+${Math.round(change)}%`;
        if (change < 0) return `${Math.round(change)}%`;

        return 'stabil';
    },
};

/**
 * Portal-Statistik, Uebersicht. Die beiden Adressen kommen aus der View
 * (@js(...) im x-data), damit hier keine Route nachgebaut wird.
 */
function statsOverview(urls = {}) {
    return {
        ...statsFormat,
        period: '30d',
        loading: true,
        summary: null,
        trend: [],
        topCompanies: [],
        maxViews: 0,
        tooltip: null,

        async load() {
            this.loading = true;

            try {
                const [overviewRes, topRes] = await Promise.all([
                    fetch(`${urls.overview}?period=${this.period}`),
                    fetch(`${urls.topCompanies}?period=${this.period}&limit=20`),
                ]);

                const overview = await overviewRes.json();
                const top = await topRes.json();

                this.summary = overview.summary;
                this.trend = overview.trend || [];
                this.maxViews = Math.max(...this.trend.map((d) => d.page_views || 0), 1);
                this.topCompanies = top.companies || [];
            } catch (e) {
                this.summary = null;
            } finally {
                this.loading = false;
            }
        },

        setPeriod(p) {
            this.period = p;
            this.load();
        },
    };
}

/** Portal-Statistik, ein Betrieb. */
function statsCompany(companyId, baseUrl = '') {
    return {
        ...statsFormat,
        companyId,
        period: '30d',
        loading: true,
        summary: null,
        trend: [],
        referrers: [],
        searchQueries: [],
        weekly: [],
        maxViews: 0,
        tooltip: null,

        async load() {
            this.loading = true;

            try {
                const res = await fetch(`${baseUrl}/${this.companyId}?period=${this.period}`);
                const data = await res.json();

                this.summary = data.summary;
                this.trend = data.trend || [];
                this.referrers = data.referrers || [];
                this.searchQueries = data.search_queries || [];
                this.weekly = data.weekly || [];
                this.maxViews = Math.max(...this.trend.map((d) => d.page_views || 0), 1);
            } catch (e) {
                this.summary = null;
            } finally {
                this.loading = false;
            }
        },

        setPeriod(p) {
            this.period = p;
            this.load();
        },
    };
}

export function registerAlpineData(Alpine) {
    Alpine.data('passwordStrength', passwordStrength);
    Alpine.data('resendTimer', resendTimer);
    Alpine.data('claimModal', claimModal);
    Alpine.data('claimUploadDropzone', claimUploadDropzone);
    Alpine.data('dashboardApp', dashboardApp);
    Alpine.data('toastManager', toastManager);
    Alpine.data('statsOverview', statsOverview);
    Alpine.data('statsCompany', statsCompany);
}
