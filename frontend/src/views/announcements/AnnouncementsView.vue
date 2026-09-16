<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

import { announcementService, roleService } from '@/services/announcementService'
import { departmentService } from '@/services/departmentService'
import { employeeService } from '@/services/employeeService'
import { organizationService } from '@/services/organizationService'
import { usePaginatedResource } from '@/composables/usePaginatedResource'
import { useAuthStore } from '@/stores/auth'
import type { AnnouncementTargetType, Department, Employee, Organization, Role, TaskPriority } from '@/types/models'

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()

const canCreate = computed(() => auth.can('announcements.create'))
const isCentral = computed(() => auth.hasRole('central-admin') || auth.hasRole('hr'))

const { items, total, loading, page, itemsPerPage, reload } = usePaginatedResource(announcementService.list)

const headers = computed(() => [
  { title: t('announcements.title'), key: 'title' },
  { title: t('common.status'), key: 'status', sortable: false },
  { title: t('tasks.priority'), key: 'priority', sortable: false },
  { title: t('announcements.reads'), key: 'reads_count', sortable: false },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end' as const },
])

// ---- Lookups for the target picker ----
const organizations = ref<Organization[]>([])
const departments = ref<Department[]>([])
const employees = ref<Employee[]>([])
const roles = ref<Role[]>([])

onMounted(async () => {
  if (canCreate.value) {
    organizations.value = (await organizationService.list({ per_page: 100 })).data
    if (isCentral.value) {
      roles.value = await roleService.list()
    } else {
      const ownOrgId = auth.user?.employee?.organization_id
      departments.value = (await departmentService.list({ per_page: 200, 'filter[organization_id]': ownOrgId })).data
      employees.value = (await employeeService.list({ per_page: 200, 'filter[organization_id]': ownOrgId })).data
    }
  }
})

// ---- Create dialog ----
const dialogOpen = ref(false)
const form = reactive({
  title: '',
  content: '',
  priority: 'normal' as TaskPriority,
  target_type: 'organization' as AnnouncementTargetType,
  target_id: null as number | null,
  publish_at: '',
  expire_at: '',
  publish_immediately: false,
})
const imageFile = ref<File | null>(null)
const attachmentFile = ref<File | null>(null)
const formErrors = ref<Record<string, string[]>>({})
const saving = ref(false)

const targetTypeOptions = computed(() => {
  const all = [
    { title: t('announcements.everyone'), value: 'everyone' },
    { title: t('announcements.central'), value: 'central' },
    { title: t('employees.organization'), value: 'organization' },
    { title: t('employees.department'), value: 'department' },
    { title: t('announcements.specificEmployee'), value: 'employee' },
    { title: t('announcements.specificRole'), value: 'role' },
  ]
  return isCentral.value ? all : all.filter((o) => ['organization', 'department', 'employee'].includes(o.value))
})

const targetIdOptions = computed(() => {
  switch (form.target_type) {
    case 'organization':
      return organizations.value.map((o) => ({ title: o.name, value: o.id }))
    case 'department':
      return departments.value.map((d) => ({ title: d.name, value: d.id }))
    case 'employee':
      return employees.value.map((e) => ({ title: e.full_name, value: e.id }))
    case 'role':
      return roles.value.map((r) => ({ title: r.name, value: r.id }))
    default:
      return []
  }
})

watch(
  () => form.target_type,
  async (type) => {
    form.target_id = null
    if (!isCentral.value) return
    if (type === 'department' && departments.value.length === 0) {
      departments.value = (await departmentService.list({ per_page: 200 })).data
    }
    if (type === 'employee' && employees.value.length === 0) {
      employees.value = (await employeeService.list({ per_page: 200 })).data
    }
  },
)

function openCreate() {
  Object.assign(form, {
    title: '',
    content: '',
    priority: 'normal',
    target_type: isCentral.value ? 'everyone' : 'organization',
    target_id: isCentral.value ? null : (auth.user?.employee?.organization_id ?? null),
    publish_at: '',
    expire_at: '',
    publish_immediately: false,
  })
  imageFile.value = null
  attachmentFile.value = null
  formErrors.value = {}
  dialogOpen.value = true
}

async function save() {
  saving.value = true
  formErrors.value = {}
  try {
    const created = await announcementService.create({
      title: form.title,
      content: form.content,
      priority: form.priority,
      publish_at: form.publish_at || null,
      expire_at: form.expire_at || null,
      publish_immediately: form.publish_immediately,
      targets: [{ target_type: form.target_type, target_id: form.target_id }],
    })
    if (imageFile.value) await announcementService.uploadImage(created.id, imageFile.value)
    if (attachmentFile.value) await announcementService.uploadAttachment(created.id, attachmentFile.value)
    dialogOpen.value = false
    await reload()
  } catch (error: unknown) {
    const axiosError = error as { response?: { data?: { errors?: Record<string, string[]> } } }
    formErrors.value = axiosError.response?.data?.errors ?? {}
  } finally {
    saving.value = false
  }
}

function openAnnouncement(id: number) {
  router.push({ name: 'announcement-detail', params: { id } })
}

const actingId = ref<number | null>(null)
async function publish(id: number) {
  actingId.value = id
  try {
    await announcementService.publish(id)
    await reload()
  } finally {
    actingId.value = null
  }
}
async function archive(id: number) {
  actingId.value = id
  try {
    await announcementService.archive(id)
    await reload()
  } finally {
    actingId.value = null
  }
}
</script>

<template>
  <AppPageHeader :title="$t('nav.announcements')">
    <template #actions>
      <v-btn v-if="canCreate" color="primary" prepend-icon="mdi-plus" @click="openCreate">
        {{ $t('common.create') }}
      </v-btn>
    </template>
  </AppPageHeader>

  <template v-if="canCreate">
    <AppDataTable
      :headers="headers"
      :items="items"
      :items-length="total"
      :loading="loading"
      :page="page"
      :items-per-page="itemsPerPage"
      @update:page="(v: number) => (page = v)"
      @update:items-per-page="(v: number) => (itemsPerPage = v)"
    >
      <template #item.title="{ item }">
        <a href="#" class="text-decoration-none" @click.prevent="openAnnouncement(item.id)">{{ item.title }}</a>
      </template>
      <template #item.status="{ item }">
        <AppStatusChip :status="item.status" />
      </template>
      <template #item.priority="{ item }">
        <AppStatusChip :status="item.priority" />
      </template>
      <template #item.reads_count="{ item }">
        {{ item.reads_count ?? '—' }}
      </template>
      <template #item.actions="{ item }">
        <v-btn
          v-if="item.status === 'draft' && auth.can('announcements.publish')"
          size="small"
          variant="text"
          color="success"
          :loading="actingId === item.id"
          @click="publish(item.id)"
        >
          {{ $t('announcements.publish') }}
        </v-btn>
        <v-btn
          v-if="item.status !== 'archived' && auth.can('announcements.publish')"
          size="small"
          variant="text"
          :loading="actingId === item.id"
          @click="archive(item.id)"
        >
          {{ $t('announcements.archive') }}
        </v-btn>
      </template>
      <template #empty>
        <AppEmptyState icon="mdi-bullhorn-outline" />
      </template>
    </AppDataTable>
  </template>

  <template v-else>
    <v-row>
      <v-col v-for="announcement in items" :key="announcement.id" cols="12" md="6">
        <v-card :variant="announcement.is_read ? 'flat' : 'elevated'" @click="openAnnouncement(announcement.id)">
          <v-card-item>
            <template #prepend>
              <v-icon v-if="!announcement.is_read" color="primary" icon="mdi-circle-medium" />
            </template>
            <v-card-title>{{ announcement.title }}</v-card-title>
            <v-card-subtitle>{{ announcement.author_name }}</v-card-subtitle>
          </v-card-item>
          <v-card-text>
            <AppStatusChip :status="announcement.priority" class="mb-2" />
            <p class="text-body-2 text-truncate">{{ announcement.content }}</p>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
    <AppLoading v-if="loading" />
    <AppEmptyState v-else-if="items.length === 0" icon="mdi-bullhorn-outline" />
  </template>

  <v-dialog v-model="dialogOpen" max-width="600">
    <v-card>
      <v-card-title>{{ $t('announcements.createAnnouncement') }}</v-card-title>
      <v-card-text>
        <v-text-field v-model="form.title" :label="$t('announcements.announcementTitle')" :error-messages="formErrors.title" />
        <v-textarea v-model="form.content" :label="$t('announcements.content')" rows="4" :error-messages="formErrors.content" />
        <v-select
          v-model="form.priority"
          :items="[
            { title: $t('status.low'), value: 'low' },
            { title: $t('status.normal'), value: 'normal' },
            { title: $t('status.high'), value: 'high' },
            { title: $t('status.urgent'), value: 'urgent' },
          ]"
          :label="$t('tasks.priority')"
        />

        <v-row>
          <v-col cols="12" sm="6">
            <v-select v-model="form.target_type" :items="targetTypeOptions" :label="$t('announcements.target')" />
          </v-col>
          <v-col v-if="!['everyone', 'central'].includes(form.target_type)" cols="12" sm="6">
            <v-select v-model="form.target_id" :items="targetIdOptions" :label="$t('announcements.targetValue')" :error-messages="formErrors['targets.0.target_id']" />
          </v-col>
        </v-row>

        <v-row>
          <v-col cols="12" sm="6">
            <v-text-field v-model="form.publish_at" type="datetime-local" :label="$t('announcements.publishAt')" :error-messages="formErrors.publish_at" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="form.expire_at" type="datetime-local" :label="$t('announcements.expireAt')" :error-messages="formErrors.expire_at" />
          </v-col>
        </v-row>

        <AppFileUpload v-model="imageFile" :label="$t('announcements.image')" accept="image/jpeg,image/png,image/webp" class="mb-2" />
        <AppFileUpload v-model="attachmentFile" :label="$t('announcements.attachment')" />

        <v-checkbox
          v-if="auth.can('announcements.publish')"
          v-model="form.publish_immediately"
          :label="$t('announcements.publishImmediately')"
          hide-details
        />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="dialogOpen = false">{{ $t('common.cancel') }}</v-btn>
        <v-btn color="primary" variant="flat" :loading="saving" @click="save">{{ $t('common.save') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
