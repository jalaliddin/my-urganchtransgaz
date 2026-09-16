import { createResourceService } from '@/services/resourceService'
import { http } from '@/services/http'
import type { ApiSuccessResponse, ListParams, PaginationMeta } from '@/types/api'
import type { Exam, ExamAttempt, ExamAttemptQuestion, ExamQuestion, ExamResultRow, ExamStatus, QuestionType } from '@/types/models'

export interface ExamPayload {
  title: string
  description?: string | null
  organization_id?: number | null
  department_id?: number | null
  duration_minutes: number
  passing_score: number
  attempts_allowed?: number
  start_date?: string | null
  end_date?: string | null
  status?: ExamStatus
}

export interface QuestionAnswerPayload {
  answer: string
  is_correct: boolean
}

export interface QuestionPayload {
  question: string
  type: QuestionType
  points?: number
  order?: number
  answers: QuestionAnswerPayload[]
}

export interface StartAttemptResponse {
  attempt: ExamAttempt
  questions: ExamAttemptQuestion[]
}

export interface SubmitAttemptPayload {
  answers: { question_id: number; answer_ids: number[] }[]
}

export interface ExamResultsResponse {
  rows: ExamResultRow[]
  meta: PaginationMeta
  stats: { total_eligible: number; passed: number; failed: number; not_taken: number }
}

const base = createResourceService<Exam, ExamPayload, Partial<ExamPayload>>('/exams')

export const examService = {
  ...base,

  async questions(examId: number): Promise<ExamQuestion[]> {
    const { data } = await http.get<ApiSuccessResponse<ExamQuestion[]>>(`/exams/${examId}/questions`)
    return data.data
  },

  async createQuestion(examId: number, payload: QuestionPayload): Promise<ExamQuestion> {
    const { data } = await http.post<ApiSuccessResponse<ExamQuestion>>(`/exams/${examId}/questions`, payload)
    return data.data
  },

  async updateQuestion(examId: number, questionId: number, payload: QuestionPayload): Promise<ExamQuestion> {
    const { data } = await http.put<ApiSuccessResponse<ExamQuestion>>(`/exams/${examId}/questions/${questionId}`, payload)
    return data.data
  },

  async deleteQuestion(examId: number, questionId: number): Promise<void> {
    await http.delete(`/exams/${examId}/questions/${questionId}`)
  },

  async startAttempt(examId: number): Promise<StartAttemptResponse> {
    const { data } = await http.post<ApiSuccessResponse<StartAttemptResponse>>(`/exams/${examId}/attempts`)
    return data.data
  },

  async submitAttempt(examId: number, attemptId: number, payload: SubmitAttemptPayload): Promise<ExamAttempt> {
    const { data } = await http.post<ApiSuccessResponse<ExamAttempt>>(`/exams/${examId}/attempts/${attemptId}/submit`, payload)
    return data.data
  },

  async getAttempt(examId: number, attemptId: number): Promise<ExamAttempt> {
    const { data } = await http.get<ApiSuccessResponse<ExamAttempt>>(`/exams/${examId}/attempts/${attemptId}`)
    return data.data
  },

  async results(examId: number, params: ListParams = {}): Promise<ExamResultsResponse> {
    const { data } = await http.get<ApiSuccessResponse<ExamResultRow[]>>(`/exams/${examId}/results`, { params })
    const meta = data.meta as unknown as PaginationMeta & { stats: ExamResultsResponse['stats'] }
    const { stats, ...paginationMeta } = meta

    return { rows: data.data, meta: paginationMeta, stats }
  },
}
