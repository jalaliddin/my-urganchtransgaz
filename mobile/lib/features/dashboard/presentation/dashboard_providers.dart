import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../exams/presentation/exams_controller.dart';
import '../../issues/presentation/issues_controller.dart';
import '../../kpi/presentation/kpi_controller.dart';
import '../../tasks/presentation/tasks_controller.dart';

/// Tasks still waiting on the user: the same statuses as `Task.isActionable`,
/// counted across every visible task rather than the first page the card
/// list loads.
final activeTasksCountProvider = Provider<AsyncValue<int>>((ref) {
  return ref
      .watch(allTasksSummaryProvider)
      .whenData((summary) => summary.countFor('new') + summary.countFor('in_progress') + summary.countFor('overdue'));
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

/// A single "overall KPI" figure for the dashboard (spec Module 1 asks
/// for one) from a screen that otherwise only shows per-indicator scores
/// (`/kpi/my` has no server-side aggregate) — the weighted average of the
/// current period's own indicators, using each result's own `weight`
/// exactly like the backend's own department-report aggregate does.
/// "Current period" is simply the most recent approved result's period,
/// since `/kpi/my` is already ordered newest-first server-side.
final kpiOverallScoreProvider = Provider<AsyncValue<double?>>((ref) {
  return ref.watch(kpiControllerProvider).whenData((results) {
    if (results.isEmpty) return null;

    final currentPeriodId = results.first.period?.id;
    final currentPeriodResults = results.where((r) => r.period?.id == currentPeriodId);

    final totalWeight = currentPeriodResults.fold<int>(0, (sum, r) => sum + r.weight);
    if (totalWeight == 0) return null;

    final weightedSum = currentPeriodResults.fold<double>(0, (sum, r) => sum + (r.score ?? 0) * r.weight);

    return weightedSum / totalWeight;
  });
});
