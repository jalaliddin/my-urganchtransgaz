<script setup lang="ts">
import { onMounted, ref } from 'vue'

import { changeRequestService } from '@/services/changeRequestService'
import { profileService, type ProfileCompletion } from '@/services/profileService'
import { useAuthStore } from '@/stores/auth'
import type { Employee, EmployeeChangeRequest } from '@/types/models'

const auth = useAuthStore()

const employee = ref<Employee | null>(null)
const completion = ref<ProfileCompletion | null>(null)
const changeRequests = ref<EmployeeChangeRequest[]>([])
const loading = ref(true)
const tab = ref('info')

const form = ref({
  phone: '', email: '', address: '',
  first_name: '', last_name: '', middle_name: '',
  birth_date: '', birth_place: '', gender: '',
  passport_number: '', pinfl: '',
})
const saving = ref(false)
const formErrors = ref<Record<string, string[]>>({})
const successMessage = ref('')
const photoUploading = ref(false)

async function load() {
  loading.value = true
  const [employeeData, completionData, requestsResult] = await Promise.all([
    profileService.show(),
    profileService.completion(),
    changeRequestService.list({ per_page: 20 }),
  ])
  employee.value = employeeData
  completion.value = completionData
  changeRequests.value = requestsResult.data
  applyFormFromEmployee(employeeData)
  loading.value = false
}

/**
 * The API returns ISO datetimes ("1995-02-25T19:00:00.000000Z"); an
 * <input type="date"> needs exactly "YYYY-MM-DD". Submitting the
 * untrimmed value back would never match the stored value, triggering a
 * bogus "changed" change request on every save.
 */
function toDateInputValue(value: string | null): string {
  return value ? value.slice(0, 10) : ''
}

function applyFormFromEmployee(data: Employee) {
  form.value = {
    phone: data.phone ?? '',
    email: data.email ?? '',
    address: data.address ?? '',
    first_name: data.first_name,
    last_name: data.last_name,
    middle_name: data.middle_name ?? '',
    birth_date: toDateInputValue(data.birth_date),
    birth_place: data.birth_place ?? '',
    gender: data.gender ?? '',
    passport_number: '',
    pinfl: '',
  }
}

onMounted(load)

async function save() {
  saving.value = true
  formErrors.value = {}
  successMessage.value = ''
  try {
    const payload = { ...form.value }
    if (!payload.passport_number) delete (payload as Record<string, unknown>).passport_number
    if (!payload.pinfl) delete (payload as Record<string, unknown>).pinfl

    const result = await profileService.update(payload)
    employee.value = result.employee
    applyFormFromEmployee(result.employee)
    successMessage.value = result.change_request
      ? 'profile.pendingNotice'
      : 'common.updatedSuccess'
    if (result.change_request) {
      changeRequests.value.unshift(result.change_request)
    }
  } catch (error: unknown) {
    const axiosError = error as { response?: { data?: { errors?: Record<string, string[]> } } }
    formErrors.value = axiosError.response?.data?.errors ?? {}
  } finally {
    saving.value = false
  }
}

async function onPhotoSelected(file: File | null) {
  if (!file) return
  photoUploading.value = true
  try {
    employee.value = await profileService.uploadPhoto(file)
    if (auth.user) auth.user.employee = employee.value
  } finally {
    photoUploading.value = false
  }
}

const newContact = ref({ type: 'emergency', full_name: '', relationship: '', phone: '', bank_name: '', bank_account_number: '' })
const addingContact = ref(false)

async function addContact() {
  if (!employee.value) return
  addingContact.value = true
  try {
    const existing = (employee.value.contacts ?? []).map((c) => ({
      id: c.id,
      type: c.type,
      full_name: c.full_name,
      relationship: c.relationship,
      phone: c.phone,
      address: c.address,
      bank_name: c.bank_name,
      bank_account_number: c.bank_account_number,
    }))
    const result = await profileService.update({ contacts: [...existing, newContact.value] })
    employee.value = result.employee
    newContact.value = { type: 'emergency', full_name: '', relationship: '', phone: '', bank_name: '', bank_account_number: '' }
  } finally {
    addingContact.value = false
  }
}

async function removeContact(contactId: number) {
  if (!employee.value) return
  const remaining = (employee.value.contacts ?? [])
    .filter((c) => c.id !== contactId)
    .map((c) => ({ id: c.id, type: c.type, full_name: c.full_name, relationship: c.relationship, phone: c.phone, address: c.address, bank_name: c.bank_name, bank_account_number: c.bank_account_number }))

  const result = await profileService.update({ contacts: remaining })
  employee.value = result.employee
}
</script>

<template>
  <AppPageHeader :title="$t('profile.title')" />

  <AppLoading v-if="loading" />

  <template v-else-if="employee">
    <v-row class="mb-2">
      <v-col cols="12" md="4">
        <v-card>
          <v-card-text class="d-flex flex-column align-center text-center">
            <AppAvatar :photo-url="employee.photo_url" :name="employee.full_name" :size="96" class="mb-3" />
            <AppFileUpload
              :model-value="null"
              accept="image/png,image/jpeg"
              :label="$t('profile.uploadPhoto')"
              class="mb-2"
              @update:model-value="onPhotoSelected"
            />
            <v-progress-linear v-if="photoUploading" indeterminate color="primary" class="mb-2" />
            <div class="text-subtitle-1 font-weight-bold">{{ employee.full_name }}</div>
            <div class="text-body-2 text-medium-emphasis">{{ employee.employee_number }}</div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" md="8">
        <v-card v-if="completion">
          <v-card-text>
            <div class="d-flex justify-space-between mb-2">
              <span class="text-subtitle-2">{{ $t('dashboard.profileCompletion') }}</span>
              <span class="text-subtitle-2 font-weight-bold">{{ completion.percentage }}%</span>
            </div>
            <v-progress-linear :model-value="completion.percentage" color="primary" height="10" rounded />
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-card>
      <v-tabs v-model="tab">
        <v-tab value="info">{{ $t('profile.tabInfo') }}</v-tab>
        <v-tab value="contacts">{{ $t('profile.tabContacts') }}</v-tab>
        <v-tab value="requests">{{ $t('profile.tabRequests') }}</v-tab>
      </v-tabs>
      <v-divider />
      <v-window v-model="tab">
        <v-window-item value="info">
          <v-card-text>
            <v-alert v-if="successMessage" type="success" variant="tonal" class="mb-4">
              {{ $t(successMessage) }}
            </v-alert>

            <div class="text-subtitle-2 mb-2">{{ $t('profile.contactInfo') }}</div>
            <v-row>
              <v-col cols="12" sm="4">
                <v-text-field v-model="form.phone" :label="$t('employees.phone')" :error-messages="formErrors.phone" />
              </v-col>
              <v-col cols="12" sm="4">
                <v-text-field v-model="form.email" :label="$t('employees.email')" :error-messages="formErrors.email" />
              </v-col>
              <v-col cols="12" sm="4">
                <v-text-field v-model="form.address" :label="$t('organizations.address')" :error-messages="formErrors.address" />
              </v-col>
            </v-row>

            <v-divider class="my-4" />

            <div class="d-flex align-center ga-2 mb-2">
              <span class="text-subtitle-2">{{ $t('profile.personalInfo') }}</span>
              <v-chip size="x-small" color="warning" variant="tonal">{{ $t('profile.sensitiveNotice') }}</v-chip>
            </div>
            <v-row>
              <v-col cols="12" sm="4">
                <v-text-field v-model="form.last_name" :label="$t('employees.lastName')" :error-messages="formErrors.last_name" />
              </v-col>
              <v-col cols="12" sm="4">
                <v-text-field v-model="form.first_name" :label="$t('employees.firstName')" :error-messages="formErrors.first_name" />
              </v-col>
              <v-col cols="12" sm="4">
                <v-text-field v-model="form.middle_name" :label="$t('employees.middleName')" :error-messages="formErrors.middle_name" />
              </v-col>
              <v-col cols="12" sm="4">
                <v-text-field v-model="form.birth_date" type="date" :label="$t('employees.birthDate')" :error-messages="formErrors.birth_date" />
              </v-col>
              <v-col cols="12" sm="4">
                <v-text-field v-model="form.birth_place" :label="$t('employees.birthPlace')" :error-messages="formErrors.birth_place" />
              </v-col>
              <v-col cols="12" sm="4">
                <v-select
                  v-model="form.gender"
                  :items="[
                    { title: $t('employees.genderMale'), value: 'male' },
                    { title: $t('employees.genderFemale'), value: 'female' },
                  ]"
                  :label="$t('employees.gender')"
                  :error-messages="formErrors.gender"
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="form.passport_number"
                  :label="$t('employees.passportNumber')"
                  :placeholder="$t('employees.changeOnlyHint')"
                  :error-messages="formErrors.passport_number"
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="form.pinfl"
                  :label="$t('employees.pinfl')"
                  :placeholder="$t('employees.changeOnlyHint')"
                  :error-messages="formErrors.pinfl"
                />
              </v-col>
            </v-row>

            <v-btn color="primary" :loading="saving" @click="save">{{ $t('common.save') }}</v-btn>
          </v-card-text>
        </v-window-item>

        <v-window-item value="contacts">
          <v-card-text>
            <v-list>
              <v-list-item
                v-for="contact in employee.contacts"
                :key="contact.id"
                :title="contact.full_name"
                :subtitle="`${contact.type === 'bank' ? $t('profile.bankInfo') : $t('profile.emergencyContact')} · ${contact.phone ?? contact.bank_name ?? ''}`"
              >
                <template #append>
                  <v-btn icon="mdi-delete-outline" variant="text" size="small" color="error" @click="removeContact(contact.id)" />
                </template>
              </v-list-item>
            </v-list>

            <v-divider class="my-4" />

            <div class="text-subtitle-2 mb-2">{{ $t('profile.addContact') }}</div>
            <v-row>
              <v-col cols="12" sm="3">
                <v-select
                  v-model="newContact.type"
                  :items="[{ title: $t('profile.emergencyContact'), value: 'emergency' }, { title: $t('profile.bankInfo'), value: 'bank' }]"
                  :label="$t('profile.contactType')"
                />
              </v-col>
              <v-col cols="12" sm="3">
                <v-text-field v-model="newContact.full_name" :label="$t('profile.fullName')" />
              </v-col>
              <template v-if="newContact.type === 'emergency'">
                <v-col cols="12" sm="3">
                  <v-text-field v-model="newContact.relationship" :label="$t('profile.relationship')" />
                </v-col>
                <v-col cols="12" sm="3">
                  <v-text-field v-model="newContact.phone" :label="$t('employees.phone')" />
                </v-col>
              </template>
              <template v-else>
                <v-col cols="12" sm="3">
                  <v-text-field v-model="newContact.bank_name" :label="$t('profile.bankName')" />
                </v-col>
                <v-col cols="12" sm="3">
                  <v-text-field v-model="newContact.bank_account_number" :label="$t('profile.bankAccount')" />
                </v-col>
              </template>
            </v-row>
            <v-btn color="primary" variant="tonal" :loading="addingContact" @click="addContact">
              {{ $t('profile.addContact') }}
            </v-btn>
          </v-card-text>
        </v-window-item>

        <v-window-item value="requests">
          <v-list v-if="changeRequests.length">
            <v-list-item
              v-for="request in changeRequests"
              :key="request.id"
              :title="Object.keys(request.changes).join(', ')"
              :subtitle="request.review_comment ?? ''"
            >
              <template #append>
                <AppStatusChip :status="request.status" />
              </template>
            </v-list-item>
          </v-list>
          <AppEmptyState v-else icon="mdi-file-document-edit-outline" />
        </v-window-item>
      </v-window>
    </v-card>
  </template>
</template>
