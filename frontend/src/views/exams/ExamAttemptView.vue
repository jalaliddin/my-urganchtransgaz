<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { examService } from '@/services/examService'
import type { Exam, ExamAttempt, ExamAttemptQuestion } from '@/types/models'

const route = useRoute()
const router = useRouter()

const examId = Number(route.params.id)
const exam = ref<Exam | null>(null)
const attempt = ref<ExamAttempt | null>(null)
const questions = ref<ExamAttemptQuestion[]>([])
const answers = ref<Record<number, number[]>>({})
const loading = ref(true)
const submitting = ref(false)
const result = ref<ExamAttempt | null>(null)
const errorMessage = ref('')

const now = ref(Date.now())
let timer: ReturnType<typeof setInterval> | undefined

onMounted(async () => {
  try {
    exam.value = await examService.get(examId)
    const started = await examService.startAttempt(examId)
    attempt.value = started.attempt
    questions.value = started.questions
    answers.value = Object.fromEntries(started.questions.map((q) => [q.id, [] as number[]]))
    timer = setInterval(() => (now.value = Date.now()), 1000)
  } catch (error: unknown) {
    const axiosError = error as { response?: { data?: { message?: string } } }
    errorMessage.value = axiosError.response?.data?.message ?? 'Xatolik yuz berdi.'
  } finally {
    loading.value = false
  }
})

onUnmounted(() => {
  if (timer) clearInterval(timer)
})

const deadline = computed(() => {
  if (!attempt.value || !exam.value) return null
  return new Date(attempt.value.started_at).getTime() + exam.value.duration_minutes * 60_000
})

const remainingLabel = computed(() => {
  if (!deadline.value) return ''
  const remainingMs = deadline.value - now.value
  const isOver = remainingMs < 0
  const totalSeconds = Math.floor(Math.abs(remainingMs) / 1000)
  const minutes = Math.floor(totalSeconds / 60)
  const seconds = totalSeconds % 60
  const label = `${minutes}:${String(seconds).padStart(2, '0')}`
  return isOver ? `-${label}` : label
})

function toggleSingle(questionId: number, answerId: number) {
  answers.value[questionId] = [answerId]
}

function toggleMultiple(questionId: number, answerId: number, checked: boolean) {
  const current = answers.value[questionId] ?? []
  answers.value[questionId] = checked ? [...current, answerId] : current.filter((id) => id !== answerId)
}

async function submit() {
  if (!attempt.value) return
  submitting.value = true
  try {
    result.value = await examService.submitAttempt(examId, attempt.value.id, {
      answers: questions.value.map((q) => ({ question_id: q.id, answer_ids: answers.value[q.id] ?? [] })),
    })
  } finally {
    submitting.value = false
  }
}

function backToExams() {
  router.push({ name: 'exams' })
}
</script>

<template>
  <AppLoading v-if="loading" />

  <v-alert v-else-if="errorMessage" type="error" class="mb-4">
    {{ errorMessage }}
    <template #append>
      <v-btn variant="text" @click="backToExams">{{ $t('common.close') }}</v-btn>
    </template>
  </v-alert>

  <template v-else-if="result">
    <AppPageHeader :title="exam?.title ?? ''" />
    <v-card>
      <v-card-text class="text-center py-8">
        <AppStatusChip :status="result.passed ? 'passed' : 'failed'" class="mb-4" />
        <div class="text-h3 font-weight-bold mb-2">{{ result.percentage }}%</div>
        <div class="text-body-1 text-medium-emphasis mb-6">
          {{ $t('exams.score') }}: {{ result.score }} · {{ $t('exams.passingScore') }}: {{ exam?.passing_score }}%
        </div>

        <v-list class="text-start mx-auto" style="max-width: 640px">
          <v-list-item v-for="question in result.questions" :key="question.id">
            <div class="text-body-1 font-weight-medium mb-1">{{ question.question }}</div>
            <div
              v-for="ans in question.answers"
              :key="ans.id"
              class="text-body-2"
              :class="{ 'text-success': ans.is_correct, 'text-error': !ans.is_correct && ans.was_selected }"
            >
              <v-icon
                :icon="ans.is_correct ? 'mdi-check-circle-outline' : (ans.was_selected ? 'mdi-close-circle-outline' : 'mdi-circle-outline')"
                size="small"
                class="mr-1"
              />
              {{ ans.answer }}
            </div>
          </v-list-item>
        </v-list>

        <v-btn color="primary" variant="flat" class="mt-6" @click="backToExams">{{ $t('common.close') }}</v-btn>
      </v-card-text>
    </v-card>
  </template>

  <template v-else>
    <AppPageHeader :title="exam?.title ?? ''">
      <template #actions>
        <v-chip :color="deadline && now > deadline ? 'error' : 'primary'" variant="tonal">
          <v-icon icon="mdi-clock-outline" start />
          {{ remainingLabel }}
        </v-chip>
      </template>
    </AppPageHeader>

    <v-card v-for="(question, index) in questions" :key="question.id" class="mb-4">
      <v-card-text>
        <div class="text-body-1 font-weight-medium mb-3">{{ index + 1 }}. {{ question.question }}</div>

        <!--
          Direct @click handlers computing the new state, not
          @update:model-value — a v-radio-group's aggregated update event
          did not reliably fire on this Vuetify version when verified in
          a real browser, so every control here follows the same
          @click-based pattern already proven to work in the question
          authoring form below (see setSingleCorrect).
        -->
        <div v-if="question.type === 'multiple_choice'">
          <v-checkbox
            v-for="ans in question.answers"
            :key="ans.id"
            :model-value="(answers[question.id] ?? []).includes(ans.id)"
            :label="ans.answer"
            hide-details
            density="compact"
            @click="toggleMultiple(question.id, ans.id, !(answers[question.id] ?? []).includes(ans.id))"
          />
        </div>
        <div v-else>
          <v-radio
            v-for="ans in question.answers"
            :key="ans.id"
            :model-value="(answers[question.id] ?? [])[0] === ans.id"
            :value="true"
            :label="ans.answer"
            hide-details
            density="compact"
            @click="toggleSingle(question.id, ans.id)"
          />
        </div>
      </v-card-text>
    </v-card>

    <v-btn color="primary" variant="flat" size="large" :loading="submitting" @click="submit">
      {{ $t('exams.submit') }}
    </v-btn>
  </template>
</template>
