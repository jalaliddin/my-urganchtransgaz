import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/widgets/async_value_view.dart';
import '../../../core/widgets/responsive_body.dart';
import '../../../l10n/app_localizations.dart';
import '../../auth/presentation/auth_controller.dart';
import '../domain/task.dart';
import '../domain/task_filters.dart';
import 'task_category_chip.dart';
import 'tasks_controller.dart';

/// Tab order: everything, then what still needs the user (overdue first
/// among those after the work in hand), then what's settled.
const _statusTabs = <String?>[null, 'new', 'in_progress', 'overdue', 'waiting', 'completed', 'cancelled'];

String taskStatusLabel(AppLocalizations l10n, String status) {
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

class TasksScreen extends ConsumerWidget {
  const TasksScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final tasks = ref.watch(taskListControllerProvider);
    final filters = ref.watch(taskFiltersProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.tasksTitle)),
      body: ResponsiveBody(
        child: Column(
          children: [
            const _SearchField(),
            const _StatusTabs(),
            const _FilterRow(),
            Expanded(
              child: RefreshIndicator(
                onRefresh: () async {
                  ref.invalidate(taskSummaryProvider);
                  await ref.read(taskListControllerProvider.notifier).refresh();
                },
                child: AsyncValueView(
                  value: tasks,
                  onRetry: () => ref.invalidate(taskListControllerProvider),
                  data: (context, state) {
                    if (state.items.isEmpty) {
                      final filtered = filters != const TaskFilters();

                      return ListView(
                        children: [
                          SizedBox(
                            height: 320,
                            child: EmptyStateView(
                              message: filtered ? l10n.tasksNoMatch : l10n.tasksNone,
                              icon: filtered ? Icons.filter_alt_off_outlined : Icons.checklist_outlined,
                            ),
                          ),
                        ],
                      );
                    }

                    return ListView.separated(
                      padding: const EdgeInsets.only(bottom: 16),
                      itemCount: state.items.length + (state.hasMore ? 1 : 0),
                      separatorBuilder: (context, index) => const Divider(height: 1),
                      itemBuilder: (context, index) {
                        if (index == state.items.length) {
                          return _LoadMoreFooter(failed: state.loadMoreFailed);
                        }

                        return _TaskTile(task: state.items[index]);
                      },
                    );
                  },
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _SearchField extends ConsumerStatefulWidget {
  const _SearchField();

  @override
  ConsumerState<_SearchField> createState() => _SearchFieldState();
}

class _SearchFieldState extends ConsumerState<_SearchField> {
  late final _controller = TextEditingController(text: ref.read(taskFiltersProvider).search);
  Timer? _debounce;

  @override
  void dispose() {
    _debounce?.cancel();
    _controller.dispose();
    super.dispose();
  }

  void _onChanged(String value) {
    _debounce?.cancel();
    // One request after the user pauses, not one per keystroke.
    _debounce = Timer(const Duration(milliseconds: 400), () {
      ref.read(taskFiltersProvider.notifier).setSearch(value);
    });
    setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
      child: TextField(
        controller: _controller,
        onChanged: _onChanged,
        textInputAction: TextInputAction.search,
        decoration: InputDecoration(
          hintText: l10n.tasksSearch,
          prefixIcon: const Icon(Icons.search),
          isDense: true,
          suffixIcon: _controller.text.isEmpty
              ? null
              : IconButton(
                  tooltip: l10n.commonClose,
                  icon: const Icon(Icons.close),
                  onPressed: () {
                    _controller.clear();
                    _onChanged('');
                  },
                ),
        ),
      ),
    );
  }
}

class _StatusTabs extends ConsumerWidget {
  const _StatusTabs();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final selected = ref.watch(taskFiltersProvider.select((f) => f.status));
    final summary = ref.watch(taskSummaryProvider).value;

    return SizedBox(
      height: 48,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
        itemCount: _statusTabs.length,
        separatorBuilder: (context, index) => const SizedBox(width: 8),
        itemBuilder: (context, index) {
          final status = _statusTabs[index];
          final label = status == null ? l10n.tasksAll : taskStatusLabel(l10n, status);
          final count = summary?.countFor(status);

          return ChoiceChip(
            label: Text(count == null ? label : '$label  $count'),
            selected: selected == status,
            showCheckmark: false,
            onSelected: (_) => ref.read(taskFiltersProvider.notifier).setStatus(status),
          );
        },
      ),
    );
  }
}

class _FilterRow extends ConsumerWidget {
  const _FilterRow();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final filters = ref.watch(taskFiltersProvider);
    final categories = ref.watch(taskCategoriesProvider).value ?? const <TaskCategory>[];
    // Only someone who can hand out tasks has any to tell apart from the
    // ones handed to them.
    final canCreate = ref.watch(authControllerProvider).value?.can('tasks.create') ?? false;

    if (categories.isEmpty && !canCreate) {
      return const SizedBox(height: 4);
    }

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
      child: Row(
        children: [
          if (categories.isNotEmpty)
            Expanded(
              child: _FilterDropdown<int?>(
                value: filters.categoryId,
                items: [
                  DropdownMenuItem(value: null, child: Text(l10n.tasksAllCategories)),
                  for (final category in categories)
                    DropdownMenuItem(value: category.id, child: TaskCategoryChip(category: category)),
                ],
                onChanged: (value) => ref.read(taskFiltersProvider.notifier).setCategory(value),
              ),
            ),
          if (categories.isNotEmpty && canCreate) const SizedBox(width: 8),
          if (canCreate)
            Expanded(
              child: _FilterDropdown<String>(
                value: filters.mine,
                items: [
                  DropdownMenuItem(value: 'all', child: Text(l10n.tasksMineAll)),
                  DropdownMenuItem(value: 'assigned', child: Text(l10n.tasksMineAssigned)),
                  DropdownMenuItem(value: 'created', child: Text(l10n.tasksMineCreated)),
                ],
                onChanged: (value) => ref.read(taskFiltersProvider.notifier).setMine(value ?? 'all'),
              ),
            ),
        ],
      ),
    );
  }
}

class _FilterDropdown<T> extends StatelessWidget {
  const _FilterDropdown({required this.value, required this.items, required this.onChanged});

  final T value;
  final List<DropdownMenuItem<T>> items;
  final ValueChanged<T?> onChanged;

  @override
  Widget build(BuildContext context) {
    return DropdownButtonFormField<T>(
      initialValue: value,
      items: items,
      onChanged: onChanged,
      isExpanded: true,
      isDense: true,
      decoration: const InputDecoration(isDense: true, contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 10)),
    );
  }
}

class _LoadMoreFooter extends ConsumerStatefulWidget {
  const _LoadMoreFooter({required this.failed});

  final bool failed;

  @override
  ConsumerState<_LoadMoreFooter> createState() => _LoadMoreFooterState();
}

class _LoadMoreFooterState extends ConsumerState<_LoadMoreFooter> {
  @override
  void initState() {
    super.initState();
    // Built lazily, so this only runs once the user scrolls near the end.
    if (!widget.failed) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) ref.read(taskListControllerProvider.notifier).loadMore();
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    if (widget.failed) {
      return Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          children: [
            Text(l10n.tasksLoadMoreFailed, style: Theme.of(context).textTheme.bodySmall),
            TextButton(
              onPressed: () => ref.read(taskListControllerProvider.notifier).loadMore(),
              child: Text(l10n.commonRetry),
            ),
          ],
        ),
      );
    }

    return const Padding(
      padding: EdgeInsets.all(16),
      child: Center(child: SizedBox(width: 24, height: 24, child: CircularProgressIndicator(strokeWidth: 2))),
    );
  }
}

class _TaskTile extends StatelessWidget {
  const _TaskTile({required this.task});

  final Task task;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final textTheme = Theme.of(context).textTheme;
    final isOverdue = task.status == 'overdue';

    return ListTile(
      leading: CircleAvatar(
        backgroundColor: _priorityColor(task.priority),
        child: Text('${task.progress}%', style: const TextStyle(color: Colors.white, fontSize: 11)),
      ),
      title: Text(task.title),
      subtitle: Padding(
        padding: const EdgeInsets.only(top: 4),
        child: Wrap(
          spacing: 8,
          runSpacing: 4,
          crossAxisAlignment: WrapCrossAlignment.center,
          children: [
            Text(taskStatusLabel(l10n, task.status)),
            if (task.category != null) TaskCategoryChip(category: task.category!),
          ],
        ),
      ),
      trailing: task.dueDate != null
          ? Text(
              task.dueDate!,
              style: textTheme.bodySmall?.copyWith(color: isOverdue ? Theme.of(context).colorScheme.error : null),
            )
          : null,
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
}
