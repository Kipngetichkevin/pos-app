import Alpine from 'alpinejs';
import QRCode from 'qrcode';

window.QRCode = QRCode;

import { Chart, BarController, BarElement, LineController, LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Filler } from 'chart.js';

Chart.register(BarController, BarElement, LineController, LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Filler);
Chart.defaults.font.family = "'Inter Variable', ui-sans-serif, system-ui, sans-serif";
Chart.defaults.font.size = 11;
Chart.defaults.color = '#64748b';

const ink = { primary: '#0f172a', secondary: '#475569', muted: '#94a3b8', grid: '#eef1f6' };

/**
 * Bar chart for one measure over categories (days, hours).
 * x-data="barChart({ labels, values, color, money: true, highlight: index })"
 */
window.barChart = (config) => ({
    chart: null,
    init() {
        const color = config.color || '#4f46e5';
        const muted = config.mutedColor || '#c7d2fe';
        const highlight = config.highlight ?? null;

        this.chart = new Chart(this.$refs.canvas, {
            type: 'bar',
            data: {
                labels: config.labels,
                datasets: [{
                    data: config.values,
                    backgroundColor: config.values.map((_, i) => (highlight === null || i === highlight ? color : muted)),
                    hoverBackgroundColor: color,
                    borderRadius: { topLeft: 4, topRight: 4 },
                    borderSkipped: 'bottom',
                    maxBarThickness: 22,
                    categoryPercentage: 0.8,
                    barPercentage: 0.9,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 300 },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        padding: 10,
                        cornerRadius: 8,
                        displayColors: false,
                        titleColor: '#cbd5e1',
                        titleFont: { weight: '500' },
                        bodyFont: { size: 13, weight: '600' },
                        callbacks: {
                            label: (c) => (config.money ? 'KSh ' + money(c.parsed.y) : qty(c.parsed.y)) + (config.unit ? ' ' + config.unit : ''),
                            afterLabel: (c) => (config.extra ? config.extra[c.dataIndex] : ''),
                        },
                    },
                },
                scales: {
                    x: {
                        grid: { display: false },
                        border: { color: ink.grid },
                        ticks: { color: ink.muted, maxRotation: 0, autoSkip: true, autoSkipPadding: 12 },
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: ink.grid },
                        border: { display: false },
                        ticks: {
                            color: ink.muted,
                            maxTicksLimit: 5,
                            callback: (v) => (config.money ? (v >= 1000 ? (v / 1000).toLocaleString('en-KE') + 'k' : v) : v),
                        },
                    },
                },
            },
        });
    },
});

window.Alpine = Alpine;

/**
 * Small JSON helper for pages that talk to the server without reloading (POS, receiving).
 * Throws an Error whose message is the first validation message, so screens can show it directly.
 */
window.api = async function (method, url, data = null) {
    const options = {
        method,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
    };

    if (data !== null) {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(data);
    }

    let response;

    try {
        response = await fetch(url, options);
    } catch (e) {
        throw new Error('Cannot reach the POS server. Check that it is running.');
    }

    if (response.status === 419) {
        throw new Error('Your session expired. Reload the page and log in again.');
    }

    const body = await response.json().catch(() => ({}));

    if (!response.ok) {
        const first = body.errors ? Object.values(body.errors).flat()[0] : null;
        const error = new Error(first || body.message || 'Something went wrong. Try again.');
        error.errors = body.errors || {};
        error.status = response.status;
        throw error;
    }

    return body;
};

window.money = (value) =>
    Number(value || 0).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

window.qty = (value) => String(Math.round(Number(value || 0) * 1000) / 1000);

Alpine.start();
