<script setup lang="ts">
import { useChartTheme } from '@/composables/useChartTheme';
import { ArcElement, Chart as ChartJS, Legend, Tooltip, type ChartData, type ChartOptions } from 'chart.js';
import { computed } from 'vue';
import { Doughnut } from 'vue-chartjs';

ChartJS.register(ArcElement, Tooltip, Legend);

const props = defineProps<{
    labels: string[];
    values: number[];
}>();

const { isDark, cssHsl, palette } = useChartTheme();

const chartData = computed<ChartData<'doughnut'>>(() => {
    void isDark.value; // recompute on theme change
    return {
        labels: props.labels,
        datasets: [
            {
                data: props.values,
                backgroundColor: palette(props.values.length),
                borderColor: cssHsl('--card'),
                borderWidth: 2,
                hoverOffset: 6,
            },
        ],
    };
});

const options = computed<ChartOptions<'doughnut'>>(() => {
    void isDark.value;
    const text = cssHsl('--muted-foreground');
    return {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '62%',
        plugins: {
            legend: {
                position: 'right',
                labels: { color: text, boxWidth: 12, boxHeight: 12, padding: 12, font: { size: 11 } },
            },
            tooltip: {
                backgroundColor: cssHsl('--popover'),
                titleColor: cssHsl('--popover-foreground'),
                bodyColor: cssHsl('--popover-foreground'),
                borderColor: cssHsl('--border'),
                borderWidth: 1,
                padding: 10,
            },
        },
    };
});
</script>

<template>
    <div class="relative h-64">
        <Doughnut :data="chartData" :options="options" />
    </div>
</template>
