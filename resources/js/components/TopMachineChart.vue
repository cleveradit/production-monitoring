<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { BarController, BarElement, CategoryScale, Chart, LinearScale, Tooltip } from 'chart.js';
import { formatNumber } from '../utils/format.js';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip);

const props = defineProps({
    machines: { type: Array, required: true },
});

const canvas = ref(null);
let chart = null;

function chartData() {
    return {
        labels: props.machines.map((row) => row.machine_code),
        datasets: [
            {
                label: 'Good Qty',
                data: props.machines.map((row) => row.good_qty),
                backgroundColor: '#6366f1',
                borderRadius: 4,
            },
        ],
    };
}

onMounted(() => {
    chart = new Chart(canvas.value, {
        type: 'bar',
        data: chartData(),
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        title: (items) => props.machines[items[0].dataIndex]?.machine_name ?? items[0].label,
                        label: (ctx) => `Good Qty: ${formatNumber(ctx.parsed.x)}`,
                    },
                },
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: { callback: (value) => formatNumber(value) },
                },
                y: { grid: { display: false } },
            },
        },
    });
});

watch(
    () => props.machines,
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
    <div class="relative h-80">
        <canvas ref="canvas" role="img" aria-label="Horizontal bar chart of the top 10 machines by good quantity"></canvas>
    </div>
</template>
