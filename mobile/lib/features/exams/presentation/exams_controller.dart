import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_providers.dart';
import '../data/exams_repository.dart';
import '../domain/exam.dart';

final examsRepositoryProvider = Provider<ExamsRepository>((ref) {
  return ExamsRepository(ref.watch(apiClientProvider));
});

class ExamsController extends AsyncNotifier<List<Exam>> {
  @override
  Future<List<Exam>> build() => ref.watch(examsRepositoryProvider).list();

  Future<void> refresh() async {
    ref.invalidateSelf();
    await future;
  }
}

final examsControllerProvider = AsyncNotifierProvider<ExamsController, List<Exam>>(ExamsController.new);

/// One in-flight quiz session — created fresh each time the user opens the
/// exam-taking screen for a given exam, holding the started attempt plus
/// the answers picked so far (client-side only until submit).
class ExamAttemptController extends AsyncNotifier<ExamAttempt> {
  ExamAttemptController(this.examId);

  final int examId;
  final Map<int, Set<int>> _selectedAnswers = {};

  @override
  Future<ExamAttempt> build() => ref.watch(examsRepositoryProvider).startAttempt(examId);

  Set<int> selectedFor(int questionId) => _selectedAnswers[questionId] ?? {};

  void toggleAnswer(int questionId, int answerId, {required bool singleChoice}) {
    final current = _selectedAnswers.putIfAbsent(questionId, () => {});

    if (singleChoice) {
      current
        ..clear()
        ..add(answerId);
    } else if (!current.remove(answerId)) {
      current.add(answerId);
    }

    // AsyncNotifier state doesn't need to change for this — selections are
    // read imperatively by the UI on rebuild via selectedFor(), so a plain
    // field mutation plus a manual notify is enough without re-fetching.
    state = AsyncData(state.value!);
  }

  Future<ExamAttempt> submit() async {
    final attempt = state.value!;
    final answers = {for (final q in attempt.questions) q.id: _selectedAnswers[q.id]?.toList() ?? <int>[]};

    return ref.read(examsRepositoryProvider).submit(examId, attempt.id, answers);
  }
}

final examAttemptProvider = AsyncNotifierProvider.family<ExamAttemptController, ExamAttempt, int>(
  (examId) => ExamAttemptController(examId),
);
