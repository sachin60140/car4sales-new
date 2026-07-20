<script setup lang="ts">
import { useChartTheme } from '@/composables/useChartTheme';
import {
    CategoryScale,
    Chart as ChartJS,
    Filler,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
    type ChartData,
    type ChartOptions,
    type ScriptableContext,
} from 'chart.js';
import { computed } from 'vue';
import { Line } from 'vue-chartjs';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Filler, Tooltip);

const props = defineProps<{
    labels: string[];
    values: number[];
    label?: string;
}>();

const { isDark, cssHsl } = useChartTheme();

const chartData = computed<ChartData<'line'>>(() => {
    void isDark.value;
    return {
        labels: props.labels,
        datasets: [
            {
                label: props.label ?? 'Value',
                data: props.values,
                borderColor: cssHsl('--brand-orange'),
                borderWidth: 2.5,
                tension: 0.4,
                fill: true,
                pointRadius: 0,
                pointHoverRadius: 5,
                pointHoverBackgroundColor: cssHsl('--brand-orange'),
                pointHoverBorderColor: cssHsl('--card'),
                backgroundColor: (ctx: ScriptableContext<'line'>) => {
                    const { chart } = ctx;
                    const { ctx: c, chartArea } = chart;
                    if (!chartArea) return cssHsl('--brand-yellow', 0.15);
                    const gradient = c.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                    gradient.addColorStop(0, cssHsl('--brand-yellow', 0.35));
                    gradient.addColorStop(1, cssHsl('--brand-yellow', 0.02));
                    return gradient;
                },
            },
        ],
    };
});

const options = computed<ChartOptions<'line'>>(() => {
    void isDark.value;
    const text = cssHsl('--muted-foreground');
    const grid = cssHsl('--border', 0.6);
    return {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { intersect: false, mode: 'index' },
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: cssHsl('--popover'),
                titleColor: cssHsl('--popover-foreground'),
                bodyColor: cssHsl('--popover-foreground'),
                borderColor: cssHsl('--border'),
                borderWidth: 1,
                padding: 10,
            },
        },
        scales: {
            x: { grid: { display: false }, ticks: { color: text, font: { size: 10 }, maxRotation: 0, autoSkipPadding: 12 }, border: { color: grid } },
            y: { beginAtZero: true, grid: { color: grid }, ticks: { color: text, font: { size: 10 }, precision: 0 }, border: { display: false } },
        },
    };
});
</script>

<template>
    <div class="relative h-64">
        <Line :data="chartData" :options="options" />
    </div>
</template>
