import { onMounted, onUnmounted, ref } from 'vue';

/**
 * Reactive chart theming. Tracks the `.dark` class on <html> so Chart.js
 * datasets recompute from the live CSS custom properties when the user
 * toggles light/dark mode.
 */
export function useChartTheme() {
    const isDark = ref(typeof document !== 'undefined' && document.documentElement.classList.contains('dark'));
    let observer: MutationObserver | null = null;

    onMounted(() => {
        observer = new MutationObserver(() => {
            isDark.value = document.documentElement.classList.contains('dark');
        });
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    });

    onUnmounted(() => observer?.disconnect());

    /** Resolve a CSS HSL-triplet variable to a usable colour string. */
    function cssHsl(name: string, alpha = 1): string {
        if (typeof document === 'undefined') return '#000';
        const raw = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        return alpha === 1 ? `hsl(${raw})` : `hsl(${raw} / ${alpha})`;
    }

    /** Ordered brand palette for categorical series. */
    function palette(count: number, alpha = 1): string[] {
        const base = ['--brand-yellow', '--brand-orange', '--brand-red', '--brand-maroon', '--brand-gold'];
        return Array.from({ length: count }, (_, i) => cssHsl(base[i % base.length], alpha));
    }

    return { isDark, cssHsl, palette };
}
