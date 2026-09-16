<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

import { usePaginatedResource } from '@/composables/usePaginatedResource'
import { examService, type QuestionAnswerPayload } from '@/services/examService'
import { organizationService } from '@/services/organizationService'
import { departmentService } from '@/services/departmentService'
import { useAuthStore } from '@/stores/auth'
import type { Exam, ExamQuestion, Organization, Department, QuestionType } from '@/types/models'

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()
const isAdmin = computed(() => auth.can('exams.manage'))

// ---- Admin: exam list + CRUD ----
const { items, total, loading, page, itemsPerPage, search, reload } = usePaginatedResource(examService.list)

const organizations = ref<Organization[]>([])
const departments = ref<Department[]>([])
onMounted(async () => {
  if (isAdmin.value) {
    organizations.value = (await organizationService.list({ per_page: 100 })).data
    departments.value = (await departmentService.list({ per_page: 200 })).data
  } else {
    await loadMyExams()
  }
})

const headers = computed(() => [
  { title: t('exams.examTitle'), key: 'title' },
  { title: t('employees.organization'), key: 'organization.name', sortable: false },
  { title: t('common.status'), key: 'status', sortable: false },
  { title: t('exams.passingScore'), key: 'passing_score', sortable: false },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end' as const },
])

const dialogOpen = ref(false)
const editing = ref<Exam | null>(null)
const form = reactive({
  title: '',
  description: '',
  organization_id: null as number | null,
  department_id: null as number | null,
  duration_minutes: 30,
  passing_score: 70,
  attempts_allowed: 1,
  start_date: '',
  end_date: '',
  status: 'draft' as Exam['status'],
})
const formErrors = ref<Record<string, string[]>>({})
const saving = ref(false)

function openCreate() {
  editing.value = null
  Object.assign(form, {
    title: '', description: '', organization_id: null, department_id: null,
    duration_minutes: 30, passing_score: 70, attempts_allowed: 1,
    start_date: '', end_date: '', status: 'draft',
  })
  formErrors.value = {}
  dialogOpen.value = true
}

function openEdit(exam: Exam) {
  editing.value = exam
  Object.assign(form, {
    title: exam.title,
    description: exam.description ?? '',
    organization_id: exam.organization_id,
    department_id: exam.department_id,
    duration_minutes: exam.duration_minutes,
    passing_score: exam.passing_score,
    attempts_allowed: exam.attempts_allowed,
    start_date: exam.start_date ?? '',
    end_date: exam.end_date ?? '',
    status: exam.status,
  })
  formErrors.value = {}
  dialogOpen.value = true
}

async function save() {
  saving.value = true
  formErrors.value = {}
  try {
    const payload = { ...form }
    if (editing.value) {
      await examService.update(editing.value.id, payload)
    } else {
      await examService.create(payload)
    }
    dialogOpen.value = false
    await reload()
  } catch (error: unknown) {
    const axiosError = error as { response?: { data?: { errors?: Record<string, string[]> } } }
    formErrors.value = axiosError.response?.data?.errors ?? {}
  } finally {
    saving.value = false
  }
}

// ---- Admin: question authoring ----
const questionsDialogOpen = ref(false)
const questionsExam = ref<Exam | null>(null)
const questions = ref<ExamQuestion[]>([])
const loadingQuestions = ref(false)

async function openQuestions(exam: Exam) {
  questionsExam.value = exam
  questionsDialogOpen.value = true
  loadingQuestions.value = true
  try {
    questions.value = await examService.questions(exam.id)
  } finally {
    loadingQuestions.value = false
  }
}

const questionForm = reactive({
  question: '',
  type: 'single_choice' as QuestionType,
  points: 1,
  answers: [
    { answer: '', is_correct: false },
    { answer: '', is_correct: false },
  ] as QuestionAnswerPayload[],
})
const questionErrors = ref<Record<string, string[]>>({})
const editingQuestion = ref<ExamQuestion | null>(null)
const savingQuestion = ref(false)

function resetQuestionForm() {
  editingQuestion.value = null
  questionForm.question = ''
  questionForm.type = 'single_choice'
  questionForm.points = 1
  questionForm.answers = [
    { answer: '', is_correct: false },
    { answer: '', is_correct: false },
  ]
  questionErrors.value = {}
}

function editQuestion(question: ExamQuestion) {
  editingQuestion.value = question
  questionForm.question = question.question
  questionForm.type = question.type
  questionForm.points = question.points
  questionForm.answers = question.answers.map((a) => ({ answer: a.answer, is_correct: Boolean(a.is_correct) }))
}

function addAnswerRow() {
  questionForm.answers.push({ answer: '', is_correct: false })
}

function removeAnswerRow(index: number) {
  questionForm.answers.splice(index, 1)
}

function onTypeChange() {
  if (questionForm.type === 'true_false') {
    questionForm.answers = [
      { answer: t('exams.true'), is_correct: true },
      { answer: t('exams.false'), is_correct: false },
    ]
  }
}

function setSingleCorrect(index: number) {
  questionForm.answers = questionForm.answers.map((a, i) => ({ ...a, is_correct: i === index }))
}

async function saveQuestion() {
  if (!questionsExam.value) return
  savingQuestion.value = true
  questionErrors.value = {}
  try {
    if (editingQuestion.value) {
      await examService.updateQuestion(questionsExam.value.id, editingQuestion.value.id, { ...questionForm })
    } else {
      await examService.createQuestion(questionsExam.value.id, { ...questionForm })
    }
    questions.value = await examService.questions(questionsExam.value.id)
    resetQuestionForm()
  } catch (error: unknown) {
    const axiosError = error as { response?: { data?: { errors?: Record<string, string[]> } } }
    questionErrors.value = axiosError.response?.data?.errors ?? {}
  } finally {
    savingQuestion.value = false
  }
}

async function deleteQuestion(question: ExamQuestion) {
  if (!questionsExam.value) return
  await examService.deleteQuestion(questionsExam.value.id, question.id)
  questions.value = await examService.questions(questionsExam.value.id)
}

function goToResults(exam: Exam) {
  router.push({ name: 'exam-results', params: { id: exam.id } })
}

// ---- Employee: available / my results ----
const myExams = ref<Exam[]>([])
const loadingMyExams = ref(false)

async function loadMyExams() {
  loadingMyExams.value = true
  try {
    const result = await examService.list({ per_page: 100 })
    myExams.value = result.data
  } finally {
    loadingMyExams.value = false
  }
}

const tab = ref('available')
const availableExams = computed(() =>
  myExams.value.filter((exam) => {
    const summary = exam.my_attempt_summary
    if (exam.status !== 'active') return false
    if (summary?.passed) return false
    if (summary && summary.attempts_used >= exam.attempts_allowed) return false
    return true
  }),
)
const myResultExams = computed(() => myExams.value.filter((exam) => exam.my_attempt_summary))

function startExam(exam: Exam) {
  router.push({ name: 'exam-attempt', params: { id: exam.id } })
}
</script>

<template>
  <AppPageHeader :title="$t('nav.exams')">
    <template #actions>
      <v-btn v-if="isAdmin" color="primary" prepend-icon="mdi-plus" @click="openCreate">
        {{ $t('common.create') }}
      </v-btn>
    </template>
  </AppPageHeader>

  <template v-if="isAdmin">
    <AppDataTable
      :headers="headers"
      :items="items"
      :items-length="total"
      :loading="loading"
      :page="page"
      :items-per-page="itemsPerPage"
      @update:page="(v: number) => (page = v)"
      @update:items-per-page="(v: number) => (itemsPerPage = v)"
      @update:search="(v: string) => (search = v)"
    >
      <template #item.organization.name="{ item }">
        {{ item.organization?.name ?? $t('exams.companyWide') }}
      </template>
      <template #item.status="{ item }">
        <AppStatusChip :status="item.status" />
      </template>
      <template #item.actions="{ item }">
        <v-btn icon="mdi-pencil-outline" variant="text" size="small" @click="openEdit(item)" />
        <v-btn icon="mdi-format-list-checks" variant="text" size="small" @click="openQuestions(item)" />
        <v-btn icon="mdi-chart-bar" variant="text" size="small" @click="goToResults(item)" />
      </template>
      <template #empty>
        <AppEmptyState icon="mdi-school-outline" />
      </template>
    </AppDataTable>
  </template>

  <template v-else>
    <v-tabs v-model="tab" class="mb-4">
      <v-tab value="available">{{ $t('exams.tabAvailable') }}</v-tab>
      <v-tab value="results">{{ $t('exams.tabResults') }}</v-tab>
    </v-tabs>

    <v-window v-model="tab">
      <v-window-item value="available">
        <v-row>
          <v-col v-for="exam in availableExams" :key="exam.id" cols="12" md="6" lg="4">
            <v-card>
              <v-card-item>
                <div class="text-subtitle-1 font-weight-bold">{{ exam.title }}</div>
                <div class="text-body-2 text-medium-emphasis">{{ exam.description }}</div>
              </v-card-item>
              <v-card-text>
                <div class="text-body-2">{{ $t('exams.duration') }}: {{ exam.duration_minutes }} {{ $t('exams.minutes') }}</div>
                <div class="text-body-2">{{ $t('exams.passingScore') }}: {{ exam.passing_score }}%</div>
              </v-card-text>
              <v-card-actions>
                <v-btn color="primary" variant="flat" @click="startExam(exam)">{{ $t('exams.start') }}</v-btn>
              </v-card-actions>
            </v-card>
          </v-col>
        </v-row>
        <AppEmptyState v-if="!loadingMyExams && availableExams.length === 0" icon="mdi-school-outline" />
      </v-window-item>

      <v-window-item value="results">
        <v-table>
          <thead>
            <tr>
              <th>{{ $t('exams.examTitle') }}</th>
              <th>{{ $t('exams.bestScore') }}</th>
              <th>{{ $t('exams.attemptsUsed') }}</th>
              <th>{{ $t('common.status') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="exam in myResultExams" :key="exam.id">
              <td>{{ exam.title }}</td>
              <td>{{ exam.my_attempt_summary?.best_percentage ?? '—' }}%</td>
              <td>{{ exam.my_attempt_summary?.attempts_used }} / {{ exam.attempts_allowed }}</td>
              <td>
                <AppStatusChip :status="exam.my_attempt_summary?.passed ? 'passed' : 'failed'" />
              </td>
            </tr>
          </tbody>
        </v-table>
        <AppEmptyState v-if="!loadingMyExams && myResultExams.length === 0" icon="mdi-clipboard-text-outline" />
      </v-window-item>
    </v-window>
  </template>

  <v-dialog v-model="dialogOpen" max-width="560">
    <v-card>
      <v-card-title>{{ editing ? $t('common.edit') : $t('exams.createExam') }}</v-card-title>
      <v-card-text>
        <v-text-field v-model="form.title" :label="$t('exams.examTitle')" :error-messages="formErrors.title" />
        <v-textarea v-model="form.description" :label="$t('tasks.description')" rows="2" :error-messages="formErrors.description" />
        <v-select
          v-model="form.organization_id"
          :items="[{ title: $t('exams.companyWide'), value: null }, ...organizations.map((o) => ({ title: o.name, value: o.id }))]"
          :label="$t('employees.organization')"
        />
        <v-select
          v-model="form.department_id"
          :items="[{ title: $t('exams.wholeOrganization'), value: null }, ...departments.filter((d) => d.organization_id === form.organization_id).map((d) => ({ title: d.name, value: d.id }))]"
          :label="$t('employees.department')"
          :disabled="!form.organization_id"
        />
        <v-row>
          <v-col cols="6">
            <v-text-field v-model.number="form.duration_minutes" type="number" :label="$t('exams.duration')" :error-messages="formErrors.duration_minutes" />
          </v-col>
          <v-col cols="6">
            <v-text-field v-model.number="form.passing_score" type="number" :label="$t('exams.passingScore')" :error-messages="formErrors.passing_score" />
          </v-col>
        </v-row>
        <v-row>
          <v-col cols="6">
            <v-text-field v-model.number="form.attempts_allowed" type="number" :label="$t('exams.attemptsAllowed')" />
          </v-col>
          <v-col cols="6">
            <v-select
              v-model="form.status"
              :items="[
                { title: $t('status.draft'), value: 'draft' },
                { title: $t('status.active'), value: 'active' },
                { title: $t('status.closed'), value: 'closed' },
              ]"
              :label="$t('common.status')"
            />
          </v-col>
        </v-row>
        <v-row>
          <v-col cols="6">
            <v-text-field v-model="form.start_date" type="date" :label="$t('tasks.dueDate')" />
          </v-col>
          <v-col cols="6">
            <v-text-field v-model="form.end_date" type="date" :label="$t('exams.endDate')" />
          </v-col>
        </v-row>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="dialogOpen = false">{{ $t('common.cancel') }}</v-btn>
        <v-btn color="primary" variant="flat" :loading="saving" @click="save">{{ $t('common.save') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="questionsDialogOpen" max-width="640" @update:model-value="(v: boolean) => !v && resetQuestionForm()">
    <v-card>
      <v-card-title>{{ questionsExam?.title }} — {{ $t('exams.questions') }}</v-card-title>
      <v-card-text>
        <AppLoading v-if="loadingQuestions" />
        <v-list v-else density="compact">
          <v-list-item v-for="question in questions" :key="question.id" :title="question.question" :subtitle="question.type">
            <template #append>
              <v-btn icon="mdi-pencil-outline" variant="text" size="small" @click="editQuestion(question)" />
              <v-btn icon="mdi-delete-outline" variant="text" size="small" @click="deleteQuestion(question)" />
            </template>
          </v-list-item>
        </v-list>
        <AppEmptyState v-if="!loadingQuestions && questions.length === 0" icon="mdi-help-circle-outline" />

        <v-divider class="my-4" />

        <v-textarea v-model="questionForm.question" :label="$t('exams.questionText')" rows="2" :error-messages="questionErrors.question" />
        <v-select
          v-model="questionForm.type"
          :items="[
            { title: $t('exams.singleChoice'), value: 'single_choice' },
            { title: $t('exams.multipleChoice'), value: 'multiple_choice' },
            { title: $t('exams.trueFalse'), value: 'true_false' },
          ]"
          :label="$t('exams.questionType')"
          @update:model-value="onTypeChange"
        />
        <v-text-field v-model.number="questionForm.points" type="number" :label="$t('exams.points')" style="max-width: 140px" />

        <div v-for="(answer, index) in questionForm.answers" :key="index" class="d-flex align-center ga-2 mb-2">
          <v-checkbox
            v-if="questionForm.type === 'multiple_choice'"
            v-model="answer.is_correct"
            hide-details
            density="compact"
          />
          <v-radio
            v-else
            :model-value="answer.is_correct"
            :value="true"
            hide-details
            density="compact"
            @click="setSingleCorrect(index)"
          />
          <v-text-field
            v-model="answer.answer"
            :label="$t('exams.answerText')"
            density="compact"
            hide-details
            :disabled="questionForm.type === 'true_false'"
          />
          <v-btn
            v-if="questionForm.type !== 'true_false'"
            icon="mdi-close"
            size="small"
            variant="text"
            @click="removeAnswerRow(index)"
          />
        </div>
        <v-btn v-if="questionForm.type !== 'true_false'" variant="tonal" size="small" @click="addAnswerRow">
          {{ $t('exams.addAnswer') }}
        </v-btn>
        <div v-if="questionErrors.answers" class="text-error text-caption mt-2">{{ questionErrors.answers[0] }}</div>

        <div class="d-flex ga-2 mt-4">
          <v-btn v-if="editingQuestion" variant="text" @click="resetQuestionForm">{{ $t('common.cancel') }}</v-btn>
          <v-btn color="primary" variant="flat" :loading="savingQuestion" @click="saveQuestion">
            {{ editingQuestion ? $t('common.save') : $t('exams.addQuestion') }}
          </v-btn>
        </div>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="questionsDialogOpen = false">{{ $t('common.close') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
