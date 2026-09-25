<script setup lang="ts">
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

import IssueTrendChart from '@/components/issues/IssueTrendChart.vue'
import LeafletMap, { type MapMarker } from '@/components/issues/LeafletMap.vue'
import { issueService } from '@/services/issueService'
import type { IssueOptions, IssueReport, IssueReportFilters } from '@/types/models'
import { formatDate, todayIso } from '@/utils/date'
import { ISSUE_PIN_STYLES, issueLegend, issuePinStyle } from '@/utils/issueMap'

const { t } = useI18n()
const router = useRouter()

type Preset = 'month' | '3months' | 'year' | 'all'

function presetRange(preset: Preset): Pick<IssueReportFilters, 'date_from' | 'date_to'> {
  const today = todayIso()
  switch (preset) {
    case 'month':
      return { date_from: `${today.slice(0, 8)}01`, date_to: today }
    case '3months':
      return { date_from: todayIso(-90), date_to: today }
    case 'year':
      return { date_from: todayIso(-365), date_to: today }
    default:
      return { date_from: null, date_to: null }
  }
}

const preset = ref<Preset | null>('year')
const filters = reactive<IssueReportFilters>({
  ...presetRange('year'),
  organization_id: null,
  issue_category_id: null,
})

const options = ref<IssueOptions | null>(null)
const report = ref<IssueReport | null>(null)
const loading = ref(true)
const failed = ref(false)
const forbidden = ref(false)

async function load() {
  loading.value = true
  failed.value = false
  try {
    report.value = await issueService.report(filters)
  } catch (error: unknown) {
    forbidden.value = (error as { response?: { status?: number } }).response?.status === 403
    failed.value = true
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await load()
  if (!forbidden.value) options.value = await issueService.options()
})

watch(filters, load)

function applyPreset(value: Preset | null) {
  if (!value) return
  Object.assign(filters, presetRange(value))
}

// Typing a date by hand means no preset describes the range any more.
function onDateInput(key: 'date_from' | 'date_to', value: string) {
  preset.value = null
  filters[key] = value || null
}

// Map ------------------------------------------------------------------------
const mapRef = ref<InstanceType<typeof LeafletMap>>()
const mapMode = ref<'pins' | 'bubbles'>('pins')
const openOnly = ref(false)

const pointMarkers = computed<MapMarker[]>(() =>
  (report.value?.points ?? [])
    .filter((point) => !openOnly.value || point.status === 'open')
    .map((point) => ({
      id: point.id,
      lat: point.latitude,
      lng: point.longitude,
      title: point.title,
      status: point.status,
      ...issuePinStyle(point.status, point.open_days),
      details: [
        point.category,
        point.organization,
        point.object_name,
        `${t('issues.reportedAt')}: ${formatDate(point.created_at)}`,
        point.open_days !== null
          ? t('issues.openDays', { n: point.open_days })
          : `${t('status.resolved')}: ${formatDate(point.resolved_at)}`,
      ].filter((line): line is string => !!line),
    })),
)

const bubbleMarkers = computed<MapMarker[]>(() =>
  (report.value?.by_organization ?? []).map((row) => ({
    id: row.organization_id,
    lat: row.latitude,
    lng: row.longitude,
    title: row.organization_name,
    count: row.total,
    details: [
      `${t('issueReport.total')}: ${row.total}`,
      `${t('issueReport.open')}: ${row.open_count}`,
      `${t('issueReport.stale', { days: report.value?.stale_after_days })}: ${row.stale_open_count}`,
      `${t('issueReport.resolved')}: ${row.resolved_count}`,
    ],
  })),
)

const markers = computed(() => (mapMode.value === 'bubbles' ? bubbleMarkers.value : pointMarkers.value))
const legend = computed(() =>
  mapMode.value === 'bubbles' ? [{ color: '#2a78d6', label: t('issueReport.bubbleLegend') }] : issueLegend(t),
)

// A pin's "Details" opens that issue; a bubble's drills the whole report
// down to that organization and switches back to its individual pins.
function onMarkerClick(id: number) {
  if (mapMode.value === 'pins') {
    router.push({ name: 'issue-detail', params: { id } })
    return
  }
  filters.organization_id = id
  mapMode.value = 'pins'
}

async function showOrganizationOnMap(organizationId: number) {
  mapMode.value = 'bubbles'
  await nextTick()
  mapRef.value?.$el?.scrollIntoView({ behavior: 'smooth', block: 'center' })
  mapRef.value?.focusMarker(organizationId)
}

// Numbers ------------------------------------------------------------------
function formatDuration(hours: number | null): string {
  if (hours === null) return '—'
  if (hours < 48) return t('issueReport.hours', { n: hours })
  return t('issueReport.days', { n: Math.round((hours / 24) * 10) / 10 })
}

const organizationHeaders = computed(() => [
  { title: t('issueReport.organization'), key: 'organization_name' },
  { title: t('issueReport.total'), key: 'total', align: 'end' as const },
  { title: t('issueReport.open'), key: 'open_count', align: 'end' as const },
  { title: t('issueReport.stale', { days: report.value?.stale_after_days ?? 7 }), key: 'stale_open_count', align: 'end' as const },
  { title: t('issueReport.resolutionRate'), key: 'resolution_rate', width: 200 },
  { title: t('issueReport.avgResolution'), key: 'avg_resolution_hours', align: 'end' as const },
])

const categoryLabels = computed(() =>
  (report.value?.by_category ?? []).map((row) => row.category_name ?? t('issueReport.noCategory')),
)

const monthLabels = computed(() => (report.value?.monthly ?? []).map((row) => `${row.month.slice(5)}.${row.month.slice(0, 4)}`))
const trendSeries = computed(() => [
  { label: t('issueReport.created'), data: (report.value?.monthly ?? []).map((row) => row.created) },
  { label: t('issueReport.resolved'), data: (report.value?.monthly ?? []).map((row) => row.resolved) },
])
const showTrendTable = ref(false)

async function exportAs(format: 'csv' | 'xlsx' | 'pdf') {
  await issueService.exportReport(filters, format)
}
</script>

<template>
  <AppPageHeader :title="$t('issueReport.title')">
    <template #actions>
      <v-btn variant="text" prepend-icon="mdi-map-marker-alert-outline" class="mr-2" :to="{ name: 'issues' }">
        {{ $t('nav.issues') }}
      </v-btn>
      <v-menu>
        <template #activator="{ props }">
          <v-btn v-bind="props" variant="tonal" prepend-icon="mdi-download" :disabled="!report?.by_organization.length">
            {{ $t('export.export') }}
          </v-btn>
        </template>
        <v-list>
          <v-list-item title="CSV" @click="exportAs('csv')" />
          <v-list-item title="Excel" @click="exportAs('xlsx')" />
          <v-list-item title="PDF" @click="exportAs('pdf')" />
        </v-list>
      </v-menu>
    </template>
  </AppPageHeader>

  <v-card v-if="!forbidden" class="mb-4">
    <v-card-text>
      <v-row dense align="center">
        <v-col cols="12" md="auto">
          <v-btn-toggle v-model="preset" density="comfortable" color="primary" variant="outlined" divided @update:model-value="applyPreset">
            <v-btn value="month">{{ $t('issueReport.presetMonth') }}</v-btn>
            <v-btn value="3months">{{ $t('issueReport.preset3Months') }}</v-btn>
            <v-btn value="year">{{ $t('issueReport.presetYear') }}</v-btn>
            <v-btn value="all">{{ $t('issueReport.presetAll') }}</v-btn>
          </v-btn-toggle>
        </v-col>
        <v-col cols="6" md="2">
          <v-text-field
            :model-value="filters.date_from ?? ''"
            type="date"
            :label="$t('issueReport.dateFrom')"
            hide-details
            density="comfortable"
            @update:model-value="(value: string) => onDateInput('date_from', value)"
          />
        </v-col>
        <v-col cols="6" md="2">
          <v-text-field
            :model-value="filters.date_to ?? ''"
            type="date"
            :label="$t('issueReport.dateTo')"
            hide-details
            density="comfortable"
            @update:model-value="(value: string) => onDateInput('date_to', value)"
          />
        </v-col>
        <v-col v-if="(options?.organizations.length ?? 0) > 1" cols="12" sm="6" md>
          <v-select
            v-model="filters.organization_id"
            :items="options?.organizations ?? []"
            item-title="name"
            item-value="id"
            :label="$t('issueReport.organization')"
            :placeholder="$t('issueReport.allOrganizations')"
            clearable
            hide-details
            density="comfortable"
          />
        </v-col>
        <v-col cols="12" sm="6" md>
          <v-select
            v-model="filters.issue_category_id"
            :items="options?.categories ?? []"
            item-title="name"
            item-value="id"
            :label="$t('issueReport.category')"
            :placeholder="$t('issues.allCategories')"
            clearable
            hide-details
            density="comfortable"
          />
        </v-col>
      </v-row>
    </v-card-text>
  </v-card>

  <v-alert v-if="failed" type="error" variant="tonal" class="mb-4">
    {{ forbidden ? $t('common.forbiddenError') : $t('common.genericError') }}
  </v-alert>

  <v-row v-if="!forbidden">
    <v-col cols="12" lg="8">
      <v-card>
        <div class="d-flex flex-wrap align-center ga-3 px-4 py-2">
          <v-btn-toggle v-model="mapMode" mandatory density="compact" color="primary" variant="outlined" divided>
            <v-btn value="pins" prepend-icon="mdi-map-marker-multiple-outline">{{ $t('issueReport.mapPoints') }}</v-btn>
            <v-btn value="bubbles" prepend-icon="mdi-chart-bubble">{{ $t('issueReport.mapBubbles') }}</v-btn>
          </v-btn-toggle>
          <v-switch
            v-if="mapMode === 'pins'"
            v-model="openOnly"
            :label="$t('issueReport.openOnly')"
            color="primary"
            density="compact"
            hide-details
            inset
          />
          <v-progress-circular v-if="loading" indeterminate size="20" width="2" class="ml-auto" />
        </div>
        <LeafletMap ref="mapRef" :markers="markers" :mode="mapMode" :legend="legend" :height="520" @marker-click="onMarkerClick" />
        <v-alert v-if="report?.points_truncated && mapMode === 'pins'" type="info" variant="tonal" density="compact" rounded="0">
          {{ $t('issueReport.pointsTruncated', { n: report.points.length }) }}
        </v-alert>
      </v-card>
    </v-col>

    <v-col cols="12" lg="4">
      <v-card class="h-100">
        <v-list v-if="report" lines="two" class="py-0" :aria-busy="loading">
          <v-list-item :subtitle="$t('issueReport.total')">
            <template #title><span class="figure">{{ report.totals.total }}</span></template>
          </v-list-item>
          <v-divider />
          <v-list-item :subtitle="$t('issueReport.open')">
            <template #prepend>
              <span class="legend-pin" :style="{ background: ISSUE_PIN_STYLES.open.color }" />
            </template>
            <template #title><span class="figure">{{ report.totals.open_count }}</span></template>
          </v-list-item>
          <v-divider />
          <v-list-item :subtitle="$t('issueReport.stale', { days: report.stale_after_days })">
            <template #prepend>
              <span class="legend-pin" :style="{ background: ISSUE_PIN_STYLES.stale.color }">!</span>
            </template>
            <template #title>
              <span class="figure" :class="{ 'text-error': report.totals.stale_open_count > 0 }">{{ report.totals.stale_open_count }}</span>
            </template>
          </v-list-item>
          <v-divider />
          <v-list-item :subtitle="$t('issueReport.resolved')">
            <template #prepend>
              <span class="legend-pin" :style="{ background: ISSUE_PIN_STYLES.resolved.color }">✓</span>
            </template>
            <template #title>
              <span class="figure">{{ report.totals.resolved_count }}</span>
              <span class="text-body-2 text-medium-emphasis ml-2">{{ report.totals.resolution_rate }}%</span>
            </template>
          </v-list-item>
          <v-divider />
          <v-list-item :subtitle="$t('issueReport.avgResolution')">
            <template #title><span class="figure">{{ formatDuration(report.totals.avg_resolution_hours) }}</span></template>
          </v-list-item>
        </v-list>
        <AppLoading v-else-if="loading" />
      </v-card>
    </v-col>
  </v-row>

  <template v-if="report">
    <AppEmptyState v-if="report.totals.total === 0" icon="mdi-map-marker-check-outline" :message="$t('issueReport.empty')" class="my-6" />

    <template v-else>
      <v-card class="mt-4">
        <v-card-title class="text-subtitle-1">{{ $t('issueReport.byOrganization') }}</v-card-title>
        <v-data-table :headers="organizationHeaders" :items="report.by_organization" item-value="organization_id" density="comfortable" :items-per-page="-1" hide-default-footer>
          <template #item.organization_name="{ item }">
            <a href="#" class="text-decoration-none" @click.prevent="showOrganizationOnMap(item.organization_id)">
              <v-icon icon="mdi-map-marker-outline" size="16" class="mr-1" />{{ item.organization_name }}
            </a>
          </template>
          <template #item.stale_open_count="{ item }">
            <span :class="{ 'text-error font-weight-bold': item.stale_open_count > 0 }">{{ item.stale_open_count }}</span>
          </template>
          <template #item.resolution_rate="{ item }">
            <div class="d-flex align-center ga-2">
              <v-progress-linear
                :model-value="item.resolution_rate"
                color="primary"
                bg-color="grey-lighten-2"
                height="6"
                rounded
                :aria-label="$t('issueReport.resolutionRate')"
              />
              <span class="text-caption rate">{{ item.resolution_rate }}%</span>
            </div>
          </template>
          <template #item.avg_resolution_hours="{ item }">{{ formatDuration(item.avg_resolution_hours) }}</template>
        </v-data-table>
      </v-card>

      <v-row class="mt-1">
        <v-col cols="12" md="6">
          <v-card class="h-100">
            <v-card-title class="text-subtitle-1">{{ $t('issueReport.byCategory') }}</v-card-title>
            <v-card-text>
              <AppChart type="bar" :labels="categoryLabels" :data="report.by_category.map((row) => row.total)" :label="$t('issueReport.total')" />
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="6">
          <v-card class="h-100">
            <v-card-title class="d-flex align-center text-subtitle-1">
              {{ $t('issueReport.monthlyTrend') }}
              <v-spacer />
              <v-btn
                size="small"
                variant="text"
                :prepend-icon="showTrendTable ? 'mdi-chart-line' : 'mdi-table'"
                @click="showTrendTable = !showTrendTable"
              >
                {{ showTrendTable ? $t('issueReport.showChart') : $t('issueReport.showTable') }}
              </v-btn>
            </v-card-title>
            <v-card-text>
              <v-table v-if="showTrendTable" density="compact">
                <thead>
                  <tr>
                    <th>{{ $t('issueReport.month') }}</th>
                    <th class="text-end">{{ $t('issueReport.created') }}</th>
                    <th class="text-end">{{ $t('issueReport.resolved') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, index) in report.monthly" :key="row.month">
                    <td>{{ monthLabels[index] }}</td>
                    <td class="text-end">{{ row.created }}</td>
                    <td class="text-end">{{ row.resolved }}</td>
                  </tr>
                </tbody>
              </v-table>
              <IssueTrendChart v-else :labels="monthLabels" :series="trendSeries" />
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
    </template>
  </template>
</template>

<style scoped>
.figure {
  font-size: 1.75rem;
  font-weight: 600;
  line-height: 1.2;
  font-variant-numeric: tabular-nums;
}

.legend-pin {
  width: 18px;
  height: 18px;
  margin-right: 14px;
  border-radius: 50%;
  border: 2px solid white;
  box-shadow: 0 0 2px rgba(0, 0, 0, 0.5);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 11px;
  font-weight: 700;
}

.rate {
  min-width: 36px;
  text-align: end;
  font-variant-numeric: tabular-nums;
}
</style>
