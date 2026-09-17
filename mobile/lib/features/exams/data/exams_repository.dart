import 'package:dio/dio.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_exception.dart';
import '../domain/exam.dart';

class ExamsRepository {
  ExamsRepository(this._client);

  final ApiClient _client;

  Future<List<Exam>> list() async {
    try {
      final response = await _client.dio.get<Map<String, dynamic>>('/exams');
      final data = response.data!['data'] as List<dynamic>;

      return data.map((e) => Exam.fromJson(e as Map<String, dynamic>)).toList();
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  /// Starts a new attempt (or resumes one already in progress). The
  /// response nests the attempt and its questions as siblings — see
  /// `ExamAttemptController::store()` — so they're merged into one
  /// [ExamAttempt] here for a single consistent shape the quiz screen
  /// can render regardless of whether it just started or is resuming.
  Future<ExamAttempt> startAttempt(int examId) async {
    try {
      final response = await _client.dio.post<Map<String, dynamic>>('/exams/$examId/attempts');
      final data = response.data!['data'] as Map<String, dynamic>;
      final attempt = ExamAttempt.fromJson(data['attempt'] as Map<String, dynamic>);
      final questions = (data['questions'] as List<dynamic>)
          .map((e) => ExamAttemptQuestion.fromJson(e as Map<String, dynamic>))
          .toList();

      return ExamAttempt(
        id: attempt.id,
        examId: attempt.examId,
        status: attempt.status,
        startedAt: attempt.startedAt,
        questions: questions,
      );
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<ExamAttempt> submit(int examId, int attemptId, Map<int, List<int>> answers) async {
    try {
      final response = await _client.dio.post<Map<String, dynamic>>(
        '/exams/$examId/attempts/$attemptId/submit',
        data: {
          'answers': answers.entries.map((e) => {'question_id': e.key, 'answer_ids': e.value}).toList(),
        },
      );

      return ExamAttempt.fromJson(response.data!['data'] as Map<String, dynamic>);
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<ExamAttempt> reviewAttempt(int examId, int attemptId) async {
    try {
      final response = await _client.dio.get<Map<String, dynamic>>('/exams/$examId/attempts/$attemptId');

      return ExamAttempt.fromJson(response.data!['data'] as Map<String, dynamic>);
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }
}
