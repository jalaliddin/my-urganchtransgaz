import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../exams/presentation/exams_controller.dart';
import '../../issues/presentation/issues_controller.dart';
import '../../tasks/presentation/tasks_controller.dart';

/// Tasks still waiting on the user — a view over the list the Tasks tab
/// already loads, so the dashboard adds no request of its own.
final activeTasksCountProvider = Provider<AsyncValue<int>>((ref) {
  return ref
      .watch(tasksControllerProvider)
      .whenData((tasks) => tasks.where((task) => task.isActionable).length);
});

/// Exams the user can start right now (active, attempts left, not passed).
final availableExamsCountProvider = Provider<AsyncValue<int>>((ref) {
  return ref
      .watch(examsControllerProvider)
      .whenData((exams) => exams.where((exam) => exam.canAttempt).length);
});

/// Unresolved issues visible to this user. Kept separate from the Issues
/// tab's own controller, which follows that screen's Open/All toggle.
final openIssuesCountProvider = FutureProvider.autoDispose<int>((ref) async {
  final issues = await ref.watch(issuesRepositoryProvider).list(openOnly: true);

  return issues.length;
});
