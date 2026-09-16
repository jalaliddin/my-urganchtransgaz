<script setup lang="ts">
import { computed } from 'vue'
import { Bar, Pie } from 'vue-chartjs'
import {
  ArcElement,
  BarController,
  BarElement,
  CategoryScale,
  Chart as ChartJS,
  Legend,
  LinearScale,
  PieController,
  Tooltip,
} from 'chart.js'

ChartJS.register(ArcElement, BarController, BarElement, CategoryScale, LinearScale, PieController, Tooltip, Legend)

const props = withDefaults(
  defineProps<{
    type: 'bar' | 'pie'
    labels: string[]
    data: number[]
    label?: string
    height?: number
  }>(),
  {
    label: '',
    height: 260,
  },
)

const palette = ['#1e3a5f', '#2e7d32', '#f9a825', '#c62828', '#6a1b9a', '#00838f', '#ef6c00', '#546e7a']

const chartData = computed(() => ({
  labels: props.labels,
  datasets: [
    {
      label: props.label,
      data: props.data,
      backgroundColor: props.type === 'pie' ? palette : '#1e3a5f',
    },
  ],
}))

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: true },
  },
}
</script>

<template>
  <div :style="{ height: `${height}px` }">
    <Bar v-if="type === 'bar'" :data="chartData" :options="chartOptions" />
    <Pie v-else :data="chartData" :options="chartOptions" />
  </div>
</template>
