<script setup lang="ts">
import { onMounted, reactive, ref, toRef } from 'vue'

import { useAuthenticatedImage } from '@/composables/useAuthenticatedImage'
import { settingsService } from '@/services/settingsService'
import type { SettingsValues } from '@/types/models'

const loading = ref(true)
const saving = ref(false)
const timezone = ref('')
const hasLogo = ref(false)

const form = reactive<SettingsValues>({
  'organization.name': '',
  'organization.contact_email': '',
  'organization.contact_phone': '',
  'organization.address': '',
  'attendance.work_start': '',
  'attendance.work_end': '',
  'attendance.late_grace_minutes': 15,
  'attendance.early_leave_grace_minutes': 15,
  'attendance.working_days': [],
  'documents.max_upload_kb': 10240,
  'exams.reminder_thresholds': [],
})

async function load() {
  loading.value = true
  try {
    const response = await settingsService.get()
    Object.assign(form, response.values)
    timezone.value = response.timezone
    hasLogo.value = response.has_logo
    reminderThresholdsText.value = form['exams.reminder_thresholds'].join(', ')
    if (hasLogo.value) logoSource.value = settingsService.logoUrl()
  } finally {
    loading.value = false
  }
}

onMounted(load)

const logoSource = ref<string | null>(null)
const { objectUrl: logoUrl } = useAuthenticatedImage(toRef(logoSource, 'value'))

async function save() {
  saving.value = true
  try {
    await settingsService.update({
      ...form,
      'attendance.working_days': form['attendance.working_days'],
      'exams.reminder_thresholds': form['exams.reminder_thresholds'],
    })
  } finally {
    saving.value = false
  }
}

const logoFile = ref<File | null>(null)
const uploadingLogo = ref(false)
async function uploadLogo() {
  if (!logoFile.value) return
  uploadingLogo.value = true
  try {
    await settingsService.uploadLogo(logoFile.value)
    hasLogo.value = true
    logoFile.value = null
    logoSource.value = settingsService.logoUrl() + `?t=${Date.now()}`
  } finally {
    uploadingLogo.value = false
  }
}

const weekdays = [
  { title: 'Dushanba', value: 1 },
  { title: 'Seshanba', value: 2 },
  { title: 'Chorshanba', value: 3 },
  { title: 'Payshanba', value: 4 },
  { title: 'Juma', value: 5 },
  { title: 'Shanba', value: 6 },
  { title: 'Yakshanba', value: 7 },
]

const reminderThresholdsText = ref('')
function syncThresholdsFromText() {
  form['exams.reminder_thresholds'] = reminderThresholdsText.value
    .split(',')
    .map((v) => parseInt(v.trim(), 10))
    .filter((v) => !isNaN(v))
}
</script>

<template>
  <AppPageHeader :title="$t('nav.settings')" />

  <AppLoading v-if="loading" />

  <v-row v-else>
    <v-col cols="12" md="6">
      <v-card class="mb-4">
        <v-card-title>{{ $t('settings.organization') }}</v-card-title>
        <v-card-text>
          <v-text-field v-model="form['organization.name']" :label="$t('settings.orgName')" />
          <v-text-field v-model="form['organization.contact_email']" :label="$t('settings.contactEmail')" />
          <v-text-field v-model="form['organization.contact_phone']" :label="$t('settings.contactPhone')" />
          <v-textarea v-model="form['organization.address']" :label="$t('settings.address')" rows="2" />

          <div class="d-flex align-center ga-4 mt-2">
            <v-avatar v-if="logoUrl || hasLogo" size="56" rounded="lg">
              <v-img v-if="logoUrl" :src="logoUrl" />
              <span v-else class="text-caption">{{ $t('settings.logo') }}</span>
            </v-avatar>
            <AppFileUpload v-model="logoFile" :label="$t('settings.logo')" accept="image/png,image/jpeg,image/svg+xml" />
            <v-btn size="small" variant="tonal" :disabled="!logoFile" :loading="uploadingLogo" @click="uploadLogo">
              {{ $t('common.save') }}
            </v-btn>
          </div>
        </v-card-text>
      </v-card>

      <v-card>
        <v-card-title>{{ $t('settings.documentsAndExams') }}</v-card-title>
        <v-card-text>
          <v-text-field v-model.number="form['documents.max_upload_kb']" type="number" :label="$t('settings.maxUploadKb')" />
          <v-text-field
            v-model="reminderThresholdsText"
            :label="$t('settings.examReminderThresholds')"
            :hint="$t('settings.examReminderThresholdsHint')"
            persistent-hint
            @update:model-value="syncThresholdsFromText"
          />
        </v-card-text>
      </v-card>
    </v-col>

    <v-col cols="12" md="6">
      <v-card>
        <v-card-title>{{ $t('settings.attendance') }}</v-card-title>
        <v-card-text>
          <v-row>
            <v-col cols="6">
              <v-text-field v-model="form['attendance.work_start']" type="time" :label="$t('settings.workStart')" />
            </v-col>
            <v-col cols="6">
              <v-text-field v-model="form['attendance.work_end']" type="time" :label="$t('settings.workEnd')" />
            </v-col>
            <v-col cols="6">
              <v-text-field
                v-model.number="form['attendance.late_grace_minutes']"
                type="number"
                :label="$t('settings.lateGraceMinutes')"
              />
            </v-col>
            <v-col cols="6">
              <v-text-field
                v-model.number="form['attendance.early_leave_grace_minutes']"
                type="number"
                :label="$t('settings.earlyLeaveGraceMinutes')"
              />
            </v-col>
          </v-row>

          <div class="text-body-2 text-medium-emphasis mb-2">{{ $t('settings.workingDays') }}</div>
          <v-chip-group v-model="form['attendance.working_days']" multiple column>
            <v-chip v-for="day in weekdays" :key="day.value" :value="day.value" filter variant="outlined">
              {{ day.title }}
            </v-chip>
          </v-chip-group>

          <v-text-field :model-value="timezone" :label="$t('settings.timezone')" readonly hint="APP_TIMEZONE" persistent-hint class="mt-2" />
        </v-card-text>
      </v-card>
    </v-col>

    <v-col cols="12">
      <v-btn color="primary" variant="flat" :loading="saving" @click="save">{{ $t('common.save') }}</v-btn>
    </v-col>
  </v-row>
</template>
