import intersect from '@alpinejs/intersect'

// ============================================================
// Livewire v4 bundles Alpine.js — DO NOT import/start Alpine separately.
// Use alpine:init to register plugins & components before Alpine starts.
// ============================================================
document.addEventListener('alpine:init', () => {
    window.Alpine.plugin(intersect)

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
