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

// A single-hue bar (a lone series never needs a categorical assignment —
// its own axis labels are the identity channel) in the brand's primary
// navy. The pie's slots are the dataviz skill's validated 8-hue
// categorical order (CVD-safe adjacent pairs in both themes; see
// `references/palette.md`) — never a generated/cycled hue.
const barColor = '#1E3A5F'
const categoricalPalette = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#4a3aa7', '#e34948']

const chartData = computed(() => ({
  labels: props.labels,
  datasets: [
    {
      label: props.label,
      data: props.data,
      backgroundColor: props.type === 'pie' ? categoricalPalette : barColor,
      borderRadius: props.type === 'bar' ? 4 : 0,
      borderSkipped: false as const,
      // A 2px surface gap between touching bars/slices, not a stroke —
      // the stroke would add ink that isn't data.
      borderColor: '#FFFFFF',
      borderWidth: props.type === 'pie' ? 2 : 0,
      maxBarThickness: 24,
    },
  ],
}))

const chartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  // Horizontal, not columns: every bar chart in this app plots named
  // categories (organizations, employees, exams, report groups) whose
  // labels are full human-readable names, not short codes — vertical
  // columns force those onto a cramped, rotated x-axis (illegible past a
  // handful of entries; verified directly with this app's own org
  // names). A horizontal label column scales with how much text there
  // actually is instead of fighting it.
  indexAxis: props.type === 'bar' ? ('y' as const) : undefined,
  plugins: {
    // A lone series' identity is already named by the chart's own label/
    // title, so its legend box (one swatch restating that name) is noise;
    // the pie's multiple categories always keep theirs.
    legend: { display: props.type === 'pie', position: 'bottom' as const, labels: { boxWidth: 12, padding: 16 } },
    tooltip: { padding: 10, cornerRadius: 8 },
  },
  scales:
    props.type === 'bar'
      ? {
          x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#E5E9F0' } },
          y: {
            grid: { display: false },
            // Measured, not clipped: a long organization/employee name has
            // to fit inside a label column that's often only a few hundred
            // pixels wide on a phone — truncate with an ellipsis here
            // rather than let the browser hard-cut it off one edge (which
            // is what happens with no callback at all). The tooltip still
            // shows the untruncated name, so nothing is actually lost.
            ticks: {
              callback(value: string | number) {
                const text = String(props.labels[Number(value)] ?? '')
                return text.length > 22 ? `${text.slice(0, 21)}…` : text
              },
            },
          },
        }
      : undefined,
}))

// Each horizontal bar gets a comfortable, fixed-height row instead of the
// whole set being squeezed into one fixed box — a chart with 3 categories
// and one with 20 shouldn't render at the same height.
const resolvedHeight = computed(() => {
  if (props.type !== 'bar') return props.height
  return Math.max(props.height, props.labels.length * 34 + 24)
})
</script>

<template>
  <div :style="{ height: `${resolvedHeight}px` }">
    <Bar v-if="type === 'bar'" :data="chartData" :options="chartOptions" />
    <Pie v-else :data="chartData" :options="chartOptions" />
  </div>
</template>
