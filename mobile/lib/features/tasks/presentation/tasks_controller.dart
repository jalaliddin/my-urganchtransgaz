import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_providers.dart';
import '../data/tasks_repository.dart';
import '../domain/task.dart';

final tasksRepositoryProvider = Provider<TasksRepository>((ref) {
  return TasksRepository(ref.watch(apiClientProvider));
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
    await refresh();
  }

  Future<void> complete({String? result}) async {
    await ref.read(tasksRepositoryProvider).complete(taskId, result: result);
    await refresh();
  }

  Future<void> addComment(String body) async {
    await ref.read(tasksRepositoryProvider).addComment(taskId, body);
    await refresh();
  }
}

final taskDetailProvider = AsyncNotifierProvider.family<TaskDetailController, Task, int>(
  (id) => TaskDetailController(id),
);
