import intersect from '@alpinejs/intersect'
import focus from '@alpinejs/focus'

// ============================================================
// Livewire v4 bundles Alpine.js — DO NOT import/start Alpine separately.
// Use alpine:init to register plugins & components before Alpine starts.
// ============================================================
document.addEventListener('alpine:init', () => {
    window.Alpine.plugin(intersect)
    window.Alpine.plugin(focus)

    // LEGAL-3: DSGVO Cookie-Consent (Alpine.js Component)
    window.Alpine.data('cookieConsent', () => ({
        showBanner: false,
        showModal: false,
        statistics: false,
        marketing: false,
        STORAGE_KEY: 'cookie_consent_preferences',
        COOKIE_LIFETIME_DAYS: 365,

        init() {
            const stored = this._getStored();
            if (stored) {
                this.statistics = stored.statistics || false;
                this.marketing = stored.marketing || false;
                this.showBanner = false;
                this._applyConsent();
            } else {
                // Delay banner appearance (300ms) so user sees content first
                setTimeout(() => { this.showBanner = true; }, 300);
            }

            // Listen for footer link click to re-open settings
            window.addEventListener('open-cookie-settings', () => {
                this.showModal = true;
                this.showBanner = false;
            });
        },

        acceptAll() {
            this.statistics = true;
            this.marketing = true;
            this._save();
            this.showBanner = false;
            this.showModal = false;
            this._applyConsent();
        },

        acceptEssentialOnly() {
            this.statistics = false;
            this.marketing = false;
            this._save();
            this.showBanner = false;
            this.showModal = false;
            this._applyConsent();
        },

        savePreferences() {
            this._save();
            this.showModal = false;
            this.showBanner = false;
            this._applyConsent();
        },

        openSettings() {
            const stored = this._getStored();
            if (stored) {
                this.statistics = stored.statistics || false;
                this.marketing = stored.marketing || false;
            }
            this.showModal = true;
            this.showBanner = false;
        },

        _save() {
            const prefs = {
                essential: true,
                statistics: this.statistics,
                marketing: this.marketing,
                timestamp: Date.now(),
                version: 1
            };
            const maxAge = this.COOKIE_LIFETIME_DAYS * 86400;
            try {
                localStorage.setItem(this.STORAGE_KEY, JSON.stringify(prefs));
            } catch (e) {
                // localStorage not available — store prefs in cookie as fallback
                document.cookie = this.STORAGE_KEY + '=' + encodeURIComponent(JSON.stringify(prefs))
                    + ';path=/;max-age=' + maxAge + ';SameSite=Lax';
            }
            // Server-side cookies for Dimitri's backend checks (analytics.blade.php)
            document.cookie = 'cookie_consent_given=1;path=/;max-age=' + maxAge + ';SameSite=Lax';
            document.cookie = 'cookie_consent_statistics=' + (this.statistics ? '1' : '0') + ';path=/;max-age=' + maxAge + ';SameSite=Lax';
            document.cookie = 'cookie_consent_marketing=' + (this.marketing ? '1' : '0') + ';path=/;max-age=' + maxAge + ';SameSite=Lax';
        },

        _getStored() {
            // Primary: localStorage
            try {
                const data = localStorage.getItem(this.STORAGE_KEY);
                if (data) return JSON.parse(data);
            } catch (e) {}
            // Fallback: cookie (when localStorage is unavailable)
            try {
                const match = document.cookie.match(new RegExp('(?:^|;\\s*)' + this.STORAGE_KEY + '=([^;]*)'));
                if (match) return JSON.parse(decodeURIComponent(match[1]));
            } catch (e) {}
            return null;
        },

        _applyConsent() {
            // GA läuft immer mit vollem Tracking (granted default in analytics.blade.php)
            // Cookie-Consent-UI bleibt bestehen, ändert aber den GA-Status nicht
            if (this.statistics) {
                window.dispatchEvent(new CustomEvent('cookie-consent-statistics', { detail: { allowed: true } }));
            }
        }
    }))

    // Visual Redesign (VR-2) — Floating Header with Scroll-Shrink
    // IMG-3: Company Gallery Lightbox
    window.Alpine.data('companyGallery', (totalImages) => ({
        isOpen: false,
        current: 0,
        total: totalImages,
        open(index) {
            this.current = index;
            this.isOpen = true;
            document.body.style.overflow = 'hidden';
        },
        close() {
            this.isOpen = false;
            document.body.style.overflow = '';
        },
        next() {
            if (!this.isOpen) return;
            this.current = (this.current + 1) % this.total;
        },
        prev() {
            if (!this.isOpen) return;
            this.current = (this.current - 1 + this.total) % this.total;
        }
    }))

    // Visual Redesign (VR-2) — Floating Header with Scroll-Shrink
    window.Alpine.data('floatingHeader', () => ({
        scrolled: false,
        mobileOpen: false,
        scrollBind: {
            ['@scroll.window']() {
                this.scrolled = window.scrollY > 50;
            }
        },
        init() {
            this.scrolled = window.scrollY > 50;
        }
    }))
})

// ============================================================
// Visual Redesign (VR-1) — ScrollReveal via IntersectionObserver
// ============================================================
function initScrollReveal() {
    // Respect reduced motion preference
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) return;

            const el = entry.target;

            // Stagger support: parent with [data-stagger] children get delayed
            const staggerDelay = el.getAttribute('data-stagger-delay');
            if (staggerDelay) {
                el.style.transitionDelay = staggerDelay;
            }

            el.classList.add('revealed');
            observer.unobserve(el);
        });
    }, {
        threshold: 0.1,
        rootMargin: '0px 0px -40px 0px'
    });

    document.querySelectorAll('.reveal').forEach(el => observer.observe(el));
}

// ============================================================
// Visual Redesign (VR-1) — Ripple Effect for Buttons
// ============================================================
function initRipple() {
    document.addEventListener('click', (e) => {
        const target = e.target.closest('.ripple');
        if (!target) return;

        // Respect reduced motion
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        const rect = target.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = e.clientX - rect.left - size / 2;
        const y = e.clientY - rect.top - size / 2;

        const circle = document.createElement('span');
        circle.classList.add('ripple-circle');
        circle.style.width = circle.style.height = `${size}px`;
        circle.style.left = `${x}px`;
        circle.style.top = `${y}px`;

        target.appendChild(circle);

        circle.addEventListener('animationend', () => circle.remove());
    });
}

document.addEventListener('DOMContentLoaded', function () {
    assignTabSliderEvents();
    initScrollReveal();
    initRipple();
});

function assignTabSliderEvents() {
    // do that for each .tab-slider
    let tabSliders = document.querySelectorAll(".tab-slider")

    tabSliders.forEach(tabSlider => {
        let tabs = tabSlider.querySelectorAll(".tab")
        let panels = tabSlider.querySelectorAll(".tab-panel")

        tabs.forEach(tab => {
            tab.addEventListener("click", ()=>{
                let tabTarget = tab.getAttribute("aria-controls")
                // set all tabs as not active
                tabs.forEach(tab =>{
                    tab.setAttribute("data-active-tab", "false")
                    tab.setAttribute("aria-selected", "false")
                })

                // set the clicked tab as active
                tab.setAttribute("data-active-tab", "true")
                tab.setAttribute("aria-selected", "true")

                panels.forEach(panel =>{
                    let panelId = panel.getAttribute("id")
                    if(tabTarget === panelId){
                        panel.classList.remove("hidden", "opacity-0")
                        panel.classList.add("block", "opacity-100")
                        // animate panel fade in

                        panel.animate([
                            { opacity: 0, maxHeight: 0 },
                            { opacity: 1, maxHeight: "100%" }
                        ], {
                            duration: 500,
                            easing: "ease-in-out",
                            fill: "forwards"
                        })

                    } else {
                        panel.classList.remove("block", "opacity-100")
                        panel.classList.add("hidden", "opacity-0")

                        // animate panel fade out
                        panel.animate([
                            { opacity: 1, maxHeight: "100%" },
                            { opacity: 0, maxHeight: 0 }
                        ], {
                            duration: 500,
                            easing: "ease-in-out",
                            fill: "forwards"
                        })
                    }
                })
            })
        })

        let activeTab = tabSlider.querySelector(".tab[data-active-tab='true']")
        activeTab.click()
    })

}
