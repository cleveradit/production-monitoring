<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import {
    CategoryScale,
    Chart,
    Legend,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    Tooltip,
} from 'chart.js';
import { formatNumber, formatShortDate } from '../utils/format.js';

Chart.register(LineController, LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Legend);

const props = defineProps({
    trend: { type: Array, required: true },
});

const canvas = ref(null);
let chart = null;

function chartData() {
    return {
        labels: props.trend.map((row) => formatShortDate(row.date)),
        datasets: [
            {
                label: 'Good Qty',
                data: props.trend.map((row) => row.good_qty),
                borderColor: '#10b981',
                backgroundColor: '#10b981',
                yAxisID: 'yGood',
                cubicInterpolationMode: 'monotone',
            },
            {
                label: 'Reject Qty',
                data: props.trend.map((row) => row.reject_qty),
                borderColor: '#f43f5e',
                backgroundColor: '#f43f5e',
                yAxisID: 'yReject',
                cubicInterpolationMode: 'monotone',
            },
        ],
    };
}

onMounted(() => {
    chart = new Chart(canvas.value, {
        type: 'line',
        data: chartData(),
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, boxHeight: 8 } },
                tooltip: {
                    callbacks: {
                        label: (ctx) => `${ctx.dataset.label}: ${formatNumber(ctx.parsed.y)}`,
                    },
                },
            },
            scales: {
                x: { grid: { display: false } },
                yGood: {
                    position: 'left',
                    beginAtZero: true,
                    title: { display: true, text: 'Good' },
                    ticks: { callback: (value) => formatNumber(value) },
                },
                yReject: {
                    position: 'right',
                    beginAtZero: true,
                    title: { display: true, text: 'Reject' },
                    grid: { drawOnChartArea: false },
                    ticks: { callback: (value) => formatNumber(value) },
                },
            },
        },
    });
});

watch(
    () => props.trend,
    () => {
        if (!chart) return;
        chart.data = chartData();
        chart.update();
    },
);

onBeforeUnmount(() => {
    chart?.destroy();
    chart = null;
});
</script>

<template>
    <div class="relative h-72">
        <canvas ref="canvas" role="img" aria-label="Line chart of good and reject quantity over the last 7 days"></canvas>
    </div>
</template>
