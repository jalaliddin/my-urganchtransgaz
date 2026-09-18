import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/widgets/async_value_view.dart';
import '../../../core/widgets/responsive_body.dart';
import '../../../l10n/app_localizations.dart';
import '../domain/task.dart';
import 'tasks_controller.dart';

class TasksScreen extends ConsumerWidget {
  const TasksScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final tasks = ref.watch(tasksControllerProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.tasksTitle)),
      body: ResponsiveBody(
        child: RefreshIndicator(
        onRefresh: () => ref.read(tasksControllerProvider.notifier).refresh(),
        child: AsyncValueView(
          value: tasks,
          onRetry: () => ref.invalidate(tasksControllerProvider),
          data: (context, items) {
            if (items.isEmpty) {
              return ListView(
                children: [
                  SizedBox(height: 400, child: EmptyStateView(message: l10n.tasksNone, icon: Icons.checklist_outlined)),
                ],
              );
            }

            return ListView.separated(
              padding: const EdgeInsets.symmetric(vertical: 8),
              itemCount: items.length,
              separatorBuilder: (context, index) => const Divider(height: 1),
              itemBuilder: (context, index) => _TaskTile(task: items[index]),
            );
          },
        ),
      ),
      ),
    );
  }
}

class _TaskTile extends StatelessWidget {
  const _TaskTile({required this.task});

  final Task task;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    return ListTile(
      leading: CircleAvatar(
        backgroundColor: _priorityColor(task.priority),
        child: Text('${task.progress}%', style: const TextStyle(color: Colors.white, fontSize: 11)),
      ),
      title: Text(task.title),
      subtitle: Text(_statusLabel(l10n, task.status)),
      trailing: task.dueDate != null ? Text(task.dueDate!) : null,
      onTap: () => context.push('/tasks/${task.id}'),
    );
  }

  Color _priorityColor(String priority) {
    return switch (priority) {
      'urgent' => Colors.red,
      'high' => Colors.orange,
      'low' => Colors.blueGrey,
      _ => Colors.blue,
    };
  }

  String _statusLabel(AppLocalizations l10n, String status) {
    return switch (status) {
      'new' => l10n.statusNew,
      'in_progress' => l10n.statusInProgress,
      'waiting' => l10n.statusWaiting,
      'completed' => l10n.statusCompleted,
      'cancelled' => l10n.statusCancelled,
      'overdue' => l10n.statusOverdue,
      _ => status,
    };
  }
}
