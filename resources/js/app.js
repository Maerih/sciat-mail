import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';

window.Alpine = Alpine;
window.Chart = Chart;

/** Copy text to clipboard with a short "Copied" state. */
Alpine.data('copy', (text) => ({
    copied: false,
    copy(value) {
        navigator.clipboard.writeText(value ?? text);
        this.copied = true;
        setTimeout(() => (this.copied = false), 1500);
    },
}));

Alpine.start();
