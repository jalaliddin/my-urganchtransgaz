<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

import { departmentService } from '@/services/departmentService'
import { kpiPeriodService, kpiService, kpiTemplateService, type KpiIndicatorPayload } from '@/services/kpiService'
import { organizationService } from '@/services/organizationService'
import { useAuthStore } from '@/stores/auth'
import type {
  Department,
  EmployeeKpi,
  KpiIndicator,
  KpiPeriod,
  KpiTemplate,
  Organization,
} from '@/types/models'

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()
const isAdmin = computed(() => auth.can('kpi.manage'))
const isSupervisor = computed(() => auth.hasRole('manager') || auth.hasRole('department-manager'))

// ---- Everyone: my own results ----
const myResults = ref<EmployeeKpi[]>([])
const loadingMy = ref(true)
async function loadMy() {
  loadingMy.value = true
  try {
    myResults.value = (await kpiService.my({ per_page: 50 })).data
  } finally {
    loadingMy.value = false
  }
}

// ---- Manager/department-manager: team results ----
const teamResults = ref<EmployeeKpi[]>([])
const loadingTeam = ref(false)
async function loadTeam() {
  loadingTeam.value = true
  try {
    teamResults.value = (await kpiService.list({ per_page: 100 })).data
  } finally {
    loadingTeam.value = false
  }
}

const tab = ref('my')

onMounted(async () => {
  if (isAdmin.value) {
    await Promise.all([loadTemplates(), loadPeriods(), loadOrgLookups()])
    await loadResults()
  } else {
    await loadMy()
    if (isSupervisor.value) await loadTeam()
  }
})

// ---- Admin: templates ----
const templates = ref<KpiTemplate[]>([])
const loadingTemplates = ref(false)
async function loadTemplates() {
  loadingTemplates.value = true
  try {
    templates.value = (await kpiTemplateService.list({ per_page: 50 })).data
  } finally {
    loadingTemplates.value = false
  }
}

const organizations = ref<Organization[]>([])
const departments = ref<Department[]>([])
async function loadOrgLookups() {
  organizations.value = (await organizationService.list({ per_page: 100 })).data
  departments.value = (await departmentService.list({ per_page: 200 })).data
}

const templateDialogOpen = ref(false)
const templateForm = reactive({ name: '', description: '', organization_id: null as number | null, department_id: null as number | null })
const templateErrors = ref<Record<string, string[]>>({})
const savingTemplate = ref(false)

function openCreateTemplate() {
  Object.assign(templateForm, { name: '', description: '', organization_id: null, department_id: null })
  templateErrors.value = {}
  templateDialogOpen.value = true
}

async function saveTemplate() {
  savingTemplate.value = true
  templateErrors.value = {}
  try {
    await kpiTemplateService.create({ ...templateForm })
    templateDialogOpen.value = false
    await loadTemplates()
  } catch (error: unknown) {
    const axiosError = error as { response?: { data?: { errors?: Record<string, string[]> } } }
    templateErrors.value = axiosError.response?.data?.errors ?? {}
  } finally {
    savingTemplate.value = false
  }
}

// ---- Admin: indicators ----
const indicatorsDialogOpen = ref(false)
const indicatorsTemplate = ref<KpiTemplate | null>(null)
const indicators = ref<KpiIndicator[]>([])
const loadingIndicators = ref(false)

async function openIndicators(template: KpiTemplate) {
  indicatorsTemplate.value = template
  indicatorsDialogOpen.value = true
  loadingIndicators.value = true
  try {
    indicators.value = await kpiTemplateService.indicators(template.id)
  } finally {
    loadingIndicators.value = false
  }
}

const indicatorForm = reactive<KpiIndicatorPayload>({
  name: '', description: '', weight: 50, target: 100, measurement_unit: '%',
  calculation_type: 'percentage', period: 'monthly',
})
const editingIndicator = ref<KpiIndicator | null>(null)
const indicatorErrors = ref<Record<string, string[]>>({})
const savingIndicator = ref(false)

function resetIndicatorForm() {
  editingIndicator.value = null
  Object.assign(indicatorForm, { name: '', description: '', weight: 50, target: 100, measurement_unit: '%', calculation_type: 'percentage', period: 'monthly' })
  indicatorErrors.value = {}
}

function editIndicator(indicator: KpiIndicator) {
  editingIndicator.value = indicator
  Object.assign(indicatorForm, {
    name: indicator.name, description: indicator.description ?? '', weight: indicator.weight,
    target: indicator.target, measurement_unit: indicator.measurement_unit ?? '',
    calculation_type: indicator.calculation_type, period: indicator.period,
  })
}

async function saveIndicator() {
  if (!indicatorsTemplate.value) return
  savingIndicator.value = true
  indicatorErrors.value = {}
  try {
    if (editingIndicator.value) {
      await kpiTemplateService.updateIndicator(indicatorsTemplate.value.id, editingIndicator.value.id, { ...indicatorForm })
    } else {
      await kpiTemplateService.createIndicator(indicatorsTemplate.value.id, { ...indicatorForm })
    }
    indicators.value = await kpiTemplateService.indicators(indicatorsTemplate.value.id)
    resetIndicatorForm()
  } catch (error: unknown) {
    const axiosError = error as { response?: { data?: { errors?: Record<string, string[]> } } }
    indicatorErrors.value = axiosError.response?.data?.errors ?? {}
  } finally {
    savingIndicator.value = false
  }
}

// ---- Admin: periods ----
const periods = ref<KpiPeriod[]>([])
const loadingPeriods = ref(false)
async function loadPeriods() {
  loadingPeriods.value = true
  try {
    periods.value = (await kpiPeriodService.list({ per_page: 50 })).data
  } finally {
    loadingPeriods.value = false
  }
}

const periodDialogOpen = ref(false)
const periodForm = reactive({ name: '', period_type: 'monthly' as KpiPeriod['period_type'], start_date: '', end_date: '' })
const periodErrors = ref<Record<string, string[]>>({})
const savingPeriod = ref(false)

function openCreatePeriod() {
  Object.assign(periodForm, { name: '', period_type: 'monthly', start_date: '', end_date: '' })
  periodErrors.value = {}
  periodDialogOpen.value = true
}

async function savePeriod() {
  savingPeriod.value = true
  periodErrors.value = {}
  try {
    await kpiPeriodService.create({ ...periodForm })
    periodDialogOpen.value = false
    await loadPeriods()
  } catch (error: unknown) {
    const axiosError = error as { response?: { data?: { errors?: Record<string, string[]> } } }
    periodErrors.value = axiosError.response?.data?.errors ?? {}
  } finally {
    savingPeriod.value = false
  }
}

// ---- Admin: results (generate/enter/approve) ----
const results = ref<EmployeeKpi[]>([])
const loadingResults = ref(false)
async function loadResults() {
  loadingResults.value = true
  try {
    results.value = (await kpiService.list({ per_page: 100 })).data
  } finally {
    loadingResults.value = false
  }
}

const generateForm = reactive({ kpi_period_id: null as number | null, kpi_template_id: null as number | null })
const generating = ref(false)
const generateMessage = ref('')

async function runGenerate() {
  if (!generateForm.kpi_period_id || !generateForm.kpi_template_id) return
  generating.value = true
  try {
    const result = await kpiService.generate({
      kpi_period_id: generateForm.kpi_period_id,
      kpi_template_id: generateForm.kpi_template_id,
    })
    generateMessage.value = t('kpi.generatedCount', { count: result.created })
    await loadResults()
  } finally {
    generating.value = false
  }
}

const savingResultId = ref<number | null>(null)
async function saveActualValue(result: EmployeeKpi) {
  savingResultId.value = result.id
  try {
    await kpiService.update(result.id, { actual_value: result.actual_value ?? undefined, comment: result.comment })
    await loadResults()
  } finally {
    savingResultId.value = null
  }
}

async function approveResult(result: EmployeeKpi) {
  savingResultId.value = result.id
  try {
    await kpiService.approve(result.id)
    await loadResults()
  } finally {
    savingResultId.value = null
  }
}

function goToReport() {
  router.push({ name: 'kpi-report' })
}
</script>

<template>
  <AppPageHeader :title="$t('nav.kpi')">
    <template #actions>
      <v-btn variant="text" prepend-icon="mdi-chart-line" @click="goToReport">{{ $t('kpi.report') }}</v-btn>
    </template>
  </AppPageHeader>

  <template v-if="isAdmin">
    <v-row class="mb-2">
      <v-col cols="12" md="6">
        <v-card>
          <v-card-title class="d-flex align-center justify-space-between">
            {{ $t('kpi.templates') }}
            <v-btn size="small" color="primary" variant="tonal" @click="openCreateTemplate">{{ $t('common.create') }}</v-btn>
          </v-card-title>
          <v-list density="compact">
            <v-list-item
              v-for="template in templates"
              :key="template.id"
              :title="template.name"
              :subtitle="template.organization?.name ?? $t('exams.companyWide')"
            >
              <template #append>
                <v-btn size="small" variant="text" @click="openIndicators(template)">{{ $t('kpi.indicators') }}</v-btn>
              </template>
            </v-list-item>
          </v-list>
          <AppEmptyState v-if="!loadingTemplates && templates.length === 0" icon="mdi-target" />
        </v-card>
      </v-col>

      <v-col cols="12" md="6">
        <v-card>
          <v-card-title class="d-flex align-center justify-space-between">
            {{ $t('kpi.periods') }}
            <v-btn size="small" color="primary" variant="tonal" @click="openCreatePeriod">{{ $t('common.create') }}</v-btn>
          </v-card-title>
          <v-list density="compact">
            <v-list-item v-for="period in periods" :key="period.id" :title="period.name" :subtitle="`${period.start_date} — ${period.end_date}`" />
          </v-list>
          <AppEmptyState v-if="!loadingPeriods && periods.length === 0" icon="mdi-calendar-range" />
        </v-card>
      </v-col>
    </v-row>

    <v-card class="mb-4">
      <v-card-text class="d-flex flex-wrap align-center ga-3">
        <v-select
          v-model="generateForm.kpi_period_id"
          :items="periods.map((p) => ({ title: p.name, value: p.id }))"
          :label="$t('kpi.period')"
          style="max-width: 220px"
          hide-details
        />
        <v-select
          v-model="generateForm.kpi_template_id"
          :items="templates.map((t) => ({ title: t.name, value: t.id }))"
          :label="$t('kpi.template')"
          style="max-width: 220px"
          hide-details
        />
        <v-btn color="primary" variant="flat" :loading="generating" @click="runGenerate">{{ $t('kpi.generate') }}</v-btn>
        <span v-if="generateMessage" class="text-body-2 text-medium-emphasis">{{ generateMessage }}</span>
      </v-card-text>
    </v-card>

    <v-card>
      <v-table>
        <thead>
          <tr>
            <th>{{ $t('employees.fullName') }}</th>
            <th>{{ $t('kpi.indicator') }}</th>
            <th>{{ $t('kpi.target') }}</th>
            <th>{{ $t('kpi.actual') }}</th>
            <th>{{ $t('kpi.score') }}</th>
            <th>{{ $t('common.status') }}</th>
            <th class="text-end">{{ $t('common.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="result in results" :key="result.id">
            <td>{{ result.employee?.full_name }}</td>
            <td>{{ result.indicator?.name }}</td>
            <td>{{ result.target_value }}</td>
            <td>
              <v-text-field
                v-model.number="result.actual_value"
                type="number"
                density="compact"
                hide-details
                style="max-width: 120px"
                :disabled="!!result.approved_at"
              />
            </td>
            <td>{{ result.score ?? '—' }}</td>
            <td>
              <AppStatusChip :status="result.approved_at ? 'approved' : 'pending'" />
            </td>
            <td class="text-end">
              <v-btn
                v-if="!result.approved_at"
                size="small"
                variant="text"
                :loading="savingResultId === result.id"
                @click="saveActualValue(result)"
              >
                {{ $t('common.save') }}
              </v-btn>
              <v-btn
                v-if="!result.approved_at"
                size="small"
                color="success"
                variant="text"
                :loading="savingResultId === result.id"
                :disabled="result.actual_value === null"
                @click="approveResult(result)"
              >
                {{ $t('kpi.approve') }}
              </v-btn>
            </td>
          </tr>
        </tbody>
      </v-table>
      <AppEmptyState v-if="!loadingResults && results.length === 0" icon="mdi-target" />
    </v-card>
  </template>

  <template v-else>
    <v-tabs v-model="tab" class="mb-4">
      <v-tab value="my">{{ $t('kpi.myResults') }}</v-tab>
      <v-tab v-if="isSupervisor" value="team">{{ $t('kpi.teamResults') }}</v-tab>
    </v-tabs>

    <v-window v-model="tab">
      <v-window-item value="my">
        <v-table>
          <thead>
            <tr>
              <th>{{ $t('kpi.indicator') }}</th>
              <th>{{ $t('kpi.period') }}</th>
              <th>{{ $t('kpi.target') }}</th>
              <th>{{ $t('kpi.actual') }}</th>
              <th>{{ $t('kpi.score') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="result in myResults" :key="result.id">
              <td>{{ result.indicator?.name }}</td>
              <td>{{ result.period?.name }}</td>
              <td>{{ result.target_value }}</td>
              <td>{{ result.actual_value }}</td>
              <td class="font-weight-bold">{{ result.score }}%</td>
            </tr>
          </tbody>
        </v-table>
        <AppEmptyState v-if="!loadingMy && myResults.length === 0" icon="mdi-target" />
      </v-window-item>

      <v-window-item v-if="isSupervisor" value="team">
        <v-table>
          <thead>
            <tr>
              <th>{{ $t('employees.fullName') }}</th>
              <th>{{ $t('kpi.indicator') }}</th>
              <th>{{ $t('kpi.score') }}</th>
              <th>{{ $t('common.status') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="result in teamResults" :key="result.id">
              <td>{{ result.employee?.full_name }}</td>
              <td>{{ result.indicator?.name }}</td>
              <td>{{ result.score ?? '—' }}</td>
              <td><AppStatusChip :status="result.approved_at ? 'approved' : 'pending'" /></td>
            </tr>
          </tbody>
        </v-table>
        <AppEmptyState v-if="!loadingTeam && teamResults.length === 0" icon="mdi-target" />
      </v-window-item>
    </v-window>
  </template>

  <v-dialog v-model="templateDialogOpen" max-width="480">
    <v-card>
      <v-card-title>{{ $t('kpi.createTemplate') }}</v-card-title>
      <v-card-text>
        <v-text-field v-model="templateForm.name" :label="$t('exams.examTitle')" :error-messages="templateErrors.name" />
        <v-textarea v-model="templateForm.description" :label="$t('tasks.description')" rows="2" />
        <v-select
          v-model="templateForm.organization_id"
          :items="[{ title: $t('exams.companyWide'), value: null }, ...organizations.map((o) => ({ title: o.name, value: o.id }))]"
          :label="$t('employees.organization')"
        />
        <v-select
          v-model="templateForm.department_id"
          :items="[{ title: $t('exams.wholeOrganization'), value: null }, ...departments.filter((d) => d.organization_id === templateForm.organization_id).map((d) => ({ title: d.name, value: d.id }))]"
          :label="$t('employees.department')"
          :disabled="!templateForm.organization_id"
        />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="templateDialogOpen = false">{{ $t('common.cancel') }}</v-btn>
        <v-btn color="primary" variant="flat" :loading="savingTemplate" @click="saveTemplate">{{ $t('common.save') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="periodDialogOpen" max-width="440">
    <v-card>
      <v-card-title>{{ $t('kpi.createPeriod') }}</v-card-title>
      <v-card-text>
        <v-text-field v-model="periodForm.name" :label="$t('exams.examTitle')" :error-messages="periodErrors.name" />
        <v-select
          v-model="periodForm.period_type"
          :items="[
            { title: $t('kpi.monthly'), value: 'monthly' },
            { title: $t('kpi.quarterly'), value: 'quarterly' },
            { title: $t('kpi.semiannual'), value: 'semiannual' },
            { title: $t('kpi.annual'), value: 'annual' },
          ]"
          :label="$t('kpi.periodType')"
        />
        <v-text-field v-model="periodForm.start_date" type="date" :label="$t('tasks.dueDate')" :error-messages="periodErrors.start_date" />
        <v-text-field v-model="periodForm.end_date" type="date" :label="$t('exams.endDate')" :error-messages="periodErrors.end_date" />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="periodDialogOpen = false">{{ $t('common.cancel') }}</v-btn>
        <v-btn color="primary" variant="flat" :loading="savingPeriod" @click="savePeriod">{{ $t('common.save') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="indicatorsDialogOpen" max-width="560" @update:model-value="(v: boolean) => !v && resetIndicatorForm()">
    <v-card>
      <v-card-title>{{ indicatorsTemplate?.name }} — {{ $t('kpi.indicators') }}</v-card-title>
      <v-card-text>
        <AppLoading v-if="loadingIndicators" />
        <v-list v-else density="compact">
          <v-list-item
            v-for="indicator in indicators"
            :key="indicator.id"
            :title="indicator.name"
            :subtitle="`${indicator.calculation_type} · ${indicator.weight}% · ${indicator.period}`"
          >
            <template #append>
              <v-btn icon="mdi-pencil-outline" variant="text" size="small" @click="editIndicator(indicator)" />
            </template>
          </v-list-item>
        </v-list>
        <AppEmptyState v-if="!loadingIndicators && indicators.length === 0" icon="mdi-target" />

        <v-divider class="my-4" />

        <v-text-field v-model="indicatorForm.name" :label="$t('exams.examTitle')" :error-messages="indicatorErrors.name" />
        <v-row>
          <v-col cols="6">
            <v-text-field v-model.number="indicatorForm.weight" type="number" :label="$t('kpi.weight')" :error-messages="indicatorErrors.weight" />
          </v-col>
          <v-col cols="6">
            <v-text-field v-model.number="indicatorForm.target" type="number" :label="$t('kpi.target')" :error-messages="indicatorErrors.target" />
          </v-col>
        </v-row>
        <v-text-field v-model="indicatorForm.measurement_unit" :label="$t('kpi.unit')" />
        <v-select
          v-model="indicatorForm.calculation_type"
          :items="[
            { title: $t('kpi.manual'), value: 'manual' },
            { title: $t('kpi.percentage'), value: 'percentage' },
            { title: $t('kpi.quantity'), value: 'quantity' },
            { title: $t('kpi.rating'), value: 'rating' },
            { title: $t('kpi.formula'), value: 'formula' },
          ]"
          :label="$t('kpi.calculationType')"
        />
        <v-select
          v-model="indicatorForm.period"
          :items="[
            { title: $t('kpi.monthly'), value: 'monthly' },
            { title: $t('kpi.quarterly'), value: 'quarterly' },
            { title: $t('kpi.semiannual'), value: 'semiannual' },
            { title: $t('kpi.annual'), value: 'annual' },
          ]"
          :label="$t('kpi.periodType')"
        />

        <div class="d-flex ga-2 mt-2">
          <v-btn v-if="editingIndicator" variant="text" @click="resetIndicatorForm">{{ $t('common.cancel') }}</v-btn>
          <v-btn color="primary" variant="flat" :loading="savingIndicator" @click="saveIndicator">
            {{ editingIndicator ? $t('common.save') : $t('kpi.addIndicator') }}
          </v-btn>
        </div>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="indicatorsDialogOpen = false">{{ $t('common.close') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
