import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_providers.dart';
import '../data/tasks_repository.dart';
import '../domain/task.dart';
import '../domain/task_filters.dart';

final tasksRepositoryProvider = Provider<TasksRepository>((ref) {
  return TasksRepository(ref.watch(apiClientProvider));
});

class TaskFiltersController extends Notifier<TaskFilters> {
  @override
  TaskFilters build() => const TaskFilters();

  void setStatus(String? status) => state = state.copyWith(status: () => status);

  void setCategory(int? categoryId) => state = state.copyWith(categoryId: () => categoryId);

  void setMine(String mine) => state = state.copyWith(mine: mine);

  void setSearch(String search) => state = state.copyWith(search: search);
}

final taskFiltersProvider = NotifierProvider<TaskFiltersController, TaskFilters>(TaskFiltersController.new);

class TaskListState {
  const TaskListState({
    required this.items,
    required this.page,
    required this.hasMore,
    this.isLoadingMore = false,
    this.loadMoreFailed = false,
  });

  final List<Task> items;
  final int page;
  final bool hasMore;
  final bool isLoadingMore;
  final bool loadMoreFailed;
}

/// The Tasks screen's list: filtered, a page at a time. Separate from
/// [tasksControllerProvider], which the dashboard derives its cards from.
class TaskListController extends AsyncNotifier<TaskListState> {
  @override
  Future<TaskListState> build() async {
    final first = await ref.watch(tasksRepositoryProvider).page(ref.watch(taskFiltersProvider));

    return TaskListState(items: first.items, page: first.currentPage, hasMore: first.hasMore);
  }

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || !current.hasMore || current.isLoadingMore) return;

    final filters = ref.read(taskFiltersProvider);
    state = AsyncData(TaskListState(items: current.items, page: current.page, hasMore: true, isLoadingMore: true));

    try {
      final next = await ref.read(tasksRepositoryProvider).page(filters, page: current.page + 1);

      // The filters changed while this page loaded — build() is already
      // fetching the new list, so this page belongs to the old one.
      if (!ref.mounted || ref.read(taskFiltersProvider) != filters) return;

      state = AsyncData(TaskListState(items: [...current.items, ...next.items], page: next.currentPage, hasMore: next.hasMore));
    } catch (_) {
      if (!ref.mounted || ref.read(taskFiltersProvider) != filters) return;

      state = AsyncData(TaskListState(items: current.items, page: current.page, hasMore: true, loadMoreFailed: true));
    }
  }

  Future<void> refresh() async {
    ref.invalidateSelf();
    await future;
  }
}

final taskListControllerProvider = AsyncNotifierProvider<TaskListController, TaskListState>(TaskListController.new);

/// Per-status counts for the tabs. Watches the filters minus the selected
/// tab, so switching tabs doesn't refetch the counts.
final taskSummaryProvider = FutureProvider.autoDispose<TaskSummary>((ref) {
  final filters = ref.watch(taskFiltersProvider.select((f) => f.copyWith(status: () => null)));

  return ref.watch(tasksRepositoryProvider).summary(filters);
});

/// Counts across every visible task, unfiltered — for the dashboard.
final allTasksSummaryProvider = FutureProvider.autoDispose<TaskSummary>((ref) {
  return ref.watch(tasksRepositoryProvider).summary(const TaskFilters());
});

final taskCategoriesProvider = FutureProvider.autoDispose<List<TaskCategory>>((ref) {
  return ref.watch(tasksRepositoryProvider).categories();
});

class TasksController extends AsyncNotifier<List<Task>> {
  @override
  Future<List<Task>> build() => ref.watch(tasksRepositoryProvider).list();

  Future<void> refresh() async {
    ref.invalidateSelf();
    await future;
  }
}

final tasksControllerProvider = AsyncNotifierProvider<TasksController, List<Task>>(TasksController.new);

/// Due within the next 3 days (or already overdue), for the Dashboard's
/// "My tasks" card — a derived view over the same list, no extra call.
final dueSoonTasksProvider = Provider<AsyncValue<List<Task>>>((ref) {
  final all = ref.watch(tasksControllerProvider);

  return all.whenData((items) {
    final now = DateTime.now();

    final dueSoon = items.where((task) {
      if (!task.isActionable || task.dueDate == null) {
        return task.status == 'overdue';
      }

      final due = DateTime.tryParse(task.dueDate!);
      if (due == null) return false;

      return due.difference(now).inDays <= 3;
    }).toList();

    return dueSoon.take(5).toList();
  });
});

class TaskDetailController extends AsyncNotifier<Task> {
  TaskDetailController(this.taskId);

  final int taskId;

  @override
  Future<Task> build() => ref.watch(tasksRepositoryProvider).show(taskId);

  Future<void> refresh() async {
    ref.invalidateSelf();
    await future;
  }

  Future<void> updateProgress(int progress) async {
    await ref.read(tasksRepositoryProvider).updateProgress(taskId, progress);
    _invalidateLists();
    await refresh();
  }

  Future<void> complete({String? result}) async {
    await ref.read(tasksRepositoryProvider).complete(taskId, result: result);
    _invalidateLists();
    await refresh();
  }

  /// A status/progress change shows up on the list, its tab counts and the
  /// dashboard when the user goes back.
  void _invalidateLists() {
    ref.invalidate(taskListControllerProvider);
    ref.invalidate(taskSummaryProvider);
    ref.invalidate(allTasksSummaryProvider);
    ref.invalidate(tasksControllerProvider);
  }

  Future<void> addComment(String body) async {
    await ref.read(tasksRepositoryProvider).addComment(taskId, body);
    await refresh();
  }
}

final taskDetailProvider = AsyncNotifierProvider.family<TaskDetailController, Task, int>(
  (id) => TaskDetailController(id),
);
