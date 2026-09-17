/**
 * Verlaufs-Chart im Statistik-Dashboard (#16) fuer canvas[data-stats-chart].
 * Chart.js wird lokal gebundelt und nur auf Seiten mit so einem Canvas
 * nachgeladen (kein CDN). Die Werte stehen als JSON im data-Attribut
 * ({labels, values, label}); bei Livewire-Updates schickt die Komponente
 * dieselbe Struktur ueber das Event stats-chart. Eine Reihe, Markenfarbe
 * aus der CSS-Variable --brand, eine y-Achse ab 0.
 */
export function initStatsChart() {
    const canvas = document.querySelector('canvas[data-stats-chart]');

    if (!canvas) {
        return;
    }

    import('chart.js/auto').then(({ default: Chart }) => {
        const styles = getComputedStyle(document.documentElement);
        const brand = styles.getPropertyValue('--brand').trim() || '#1d4ed8';
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const data = JSON.parse(canvas.dataset.statsChart || '{}');

        const chart = new Chart(canvas, {
            type: 'line',
            data: {
                labels: data.labels || [],
                datasets: [dataset(data, brand)],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: reducedMotion ? false : { duration: 200 },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: { displayColors: false },
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: '#71717a', maxRotation: 0, autoSkip: true, maxTicksLimit: 6 },
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#e4e4e7' },
                        border: { display: false },
                        ticks: { color: '#71717a', precision: 0, maxTicksLimit: 5 },
                    },
                },
            },
        });

        window.addEventListener('stats-chart', (event) => {
            const next = event.detail?.chart;

            if (!next) {
                return;
            }

            chart.data.labels = next.labels;
            chart.data.datasets = [dataset(next, brand)];
            chart.update();
        });
    });
}

function dataset(data, brand) {
    return {
        label: data.label || '',
        data: data.values || [],
        borderColor: brand,
        backgroundColor: brand,
        borderWidth: 2,
        pointRadius: 0,
        pointHoverRadius: 4,
        pointHitRadius: 12,
        tension: 0.25,
        fill: false,
    };
}
