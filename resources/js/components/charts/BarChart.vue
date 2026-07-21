<script setup lang="ts">
import { useChartTheme } from '@/composables/useChartTheme';
import { BarElement, CategoryScale, Chart as ChartJS, LinearScale, Tooltip, type ChartData, type ChartOptions } from 'chart.js';
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';

ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip);

const props = defineProps<{
    labels: string[];
    values: number[];
}>();

const { isDark, cssHsl, palette } = useChartTheme();

const chartData = computed<ChartData<'bar'>>(() => {
    void isDark.value;
    return {
        labels: props.labels,
        datasets: [
            {
                data: props.values,
                backgroundColor: palette(props.values.length, 0.85),
                borderRadius: 6,
                maxBarThickness: 40,
            },
        ],
    };
});

const options = computed<ChartOptions<'bar'>>(() => {
    void isDark.value;
    const text = cssHsl('--muted-foreground');
    const grid = cssHsl('--border', 0.6);
    return {
        responsive: true,
        maintainAspectRatio: false,
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
            x: { grid: { display: false }, ticks: { color: text, font: { size: 10 }, maxRotation: 45, minRotation: 0 }, border: { color: grid } },
            y: { beginAtZero: true, grid: { color: grid }, ticks: { color: text, font: { size: 10 }, precision: 0 }, border: { display: false } },
        },
    };
});
</script>

<template>
    <div class="relative h-64">
        <Bar :data="chartData" :options="options" />
    </div>
</template>
