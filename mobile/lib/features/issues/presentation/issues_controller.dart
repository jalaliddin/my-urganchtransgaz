import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_providers.dart';
import '../data/issues_repository.dart';
import '../domain/issue.dart';

final issuesRepositoryProvider = Provider<IssuesRepository>((ref) {
  return IssuesRepository(ref.watch(apiClientProvider));
});

/// Defaults to open-only — "shown on the map until resolved" is the
/// primary view; "All" is there for history, not the default.
class IssuesController extends AsyncNotifier<List<Issue>> {
  bool _showAll = false;

  bool get showAll => _showAll;

  @override
  Future<List<Issue>> build() => ref.watch(issuesRepositoryProvider).list(openOnly: !_showAll);

  Future<void> setShowAll(bool value) async {
    _showAll = value;
    ref.invalidateSelf();
    await future;
  }

  Future<void> refresh() async {
    ref.invalidateSelf();
    await future;
  }
}

final issuesControllerProvider = AsyncNotifierProvider<IssuesController, List<Issue>>(IssuesController.new);

class IssueDetailController extends AsyncNotifier<Issue> {
  IssueDetailController(this.issueId);

  final int issueId;

  @override
  Future<Issue> build() => ref.watch(issuesRepositoryProvider).show(issueId);

  Future<void> refresh() async {
    ref.invalidateSelf();
    await future;
  }

  Future<void> addComment(String body) async {
    await ref.read(issuesRepositoryProvider).addComment(issueId, body);
    await refresh();
  }

  Future<void> resolve(String resolutionNote) async {
    await ref.read(issuesRepositoryProvider).resolve(issueId, resolutionNote);
    await refresh();
  }
}

final issueDetailProvider = AsyncNotifierProvider.family<IssueDetailController, Issue, int>(
  (id) => IssueDetailController(id),
);
