<script setup lang="ts">
import { computed } from 'vue'
import { Line } from 'vue-chartjs'
import {
  CategoryScale,
  Chart as ChartJS,
  Legend,
  LinearScale,
  LineController,
  LineElement,
  PointElement,
  Tooltip,
} from 'chart.js'

ChartJS.register(CategoryScale, Legend, LinearScale, LineController, LineElement, PointElement, Tooltip)

export interface TrendSeries {
  label: string
  data: number[]
}

const props = withDefaults(
  defineProps<{
    labels: string[]
    series: TrendSeries[]
    height?: number
  }>(),
  { height: 280 },
)

// The first slots of AppChart's validated categorical order (blue, orange),
// assigned in fixed order — checked with the dataviz skill's validator
// (CVD ΔE 24.7, normal 33.6, both ≥ 3:1 on the light surface).
const SERIES_COLORS = ['#2a78d6', '#eb6834']

const chartData = computed(() => ({
  labels: props.labels,
  datasets: props.series.map((series, index) => ({
    label: series.label,
    data: series.data,
    borderColor: SERIES_COLORS[index],
    backgroundColor: SERIES_COLORS[index],
    borderWidth: 2,
    pointRadius: 4,
    pointHoverRadius: 6,
    pointBorderColor: '#FFFFFF',
    pointBorderWidth: 2,
    tension: 0.25,
  })),
}))

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  // Hovering anywhere over a month shows both series' values for it.
  interaction: { mode: 'index' as const, intersect: false },
  plugins: {
    legend: { position: 'bottom' as const, labels: { boxWidth: 12, padding: 16, usePointStyle: true } },
    tooltip: { padding: 10, cornerRadius: 8 },
  },
  scales: {
    x: { grid: { display: false } },
    y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#E5E9F0' } },
  },
}
</script>

<template>
  <div :style="{ height: `${height}px` }">
    <Line :data="chartData" :options="chartOptions" />
  </div>
</template>
