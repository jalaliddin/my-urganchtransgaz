import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/formatting/dates.dart';
import '../../../core/theme/status_colors.dart';
import '../../../core/widgets/async_value_view.dart';
import '../../../core/widgets/responsive_body.dart';
import '../../../core/widgets/status_pill.dart';
import '../../../l10n/app_localizations.dart';
import '../../auth/presentation/auth_controller.dart';
import '../domain/task.dart';
import '../domain/task_filters.dart';
import 'task_category_chip.dart';
import 'task_labels.dart';
import 'tasks_controller.dart';

/// Tab order: everything, then what still needs the user (overdue first
/// among those after the work in hand), then what's settled.
const _statusTabs = <String?>[null, 'new', 'in_progress', 'overdue', 'waiting', 'completed', 'cancelled'];

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
                      padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
                      itemCount: state.items.length + (state.hasMore ? 1 : 0),
                      separatorBuilder: (context, index) => const SizedBox(height: 10),
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
          contentPadding: const EdgeInsets.symmetric(vertical: 14),
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(28),
            borderSide: BorderSide(color: Theme.of(context).colorScheme.outlineVariant),
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(28),
            borderSide: BorderSide(color: Theme.of(context).colorScheme.outlineVariant),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(28),
            borderSide: BorderSide(color: Theme.of(context).colorScheme.primary, width: 1.6),
          ),
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
      height: 52,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        itemCount: _statusTabs.length,
        separatorBuilder: (context, index) => const SizedBox(width: 8),
        itemBuilder: (context, index) {
          final status = _statusTabs[index];
          final label = status == null ? l10n.tasksAll : taskStatusLabel(l10n, status);
          final count = summary?.countFor(status);

          return ChoiceChip(
            label: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(label),
                if (count != null) ...[
                  const SizedBox(width: 6),
                  _CountBadge(count: count, selected: selected == status),
                ],
              ],
            ),
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

class _CountBadge extends StatelessWidget {
  const _CountBadge({required this.count, required this.selected});

  final int count;
  final bool selected;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 1),
      decoration: BoxDecoration(
        color: selected ? scheme.primary : scheme.surfaceContainerHigh,
        borderRadius: BorderRadius.circular(99),
      ),
      child: Text(
        '$count',
        style: Theme.of(context).textTheme.labelSmall!.copyWith(color: selected ? scheme.onPrimary : scheme.onSurfaceVariant),
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
    final scheme = Theme.of(context).colorScheme;
    final text = Theme.of(context).textTheme;
    final status = StatusColors.of(context);
    final due = AppDates.parse(task.dueDate);
    final settled = task.status == 'completed' || task.status == 'cancelled';
    final dueTone = settled
        ? StatusTone.neutral
        : due == null
            ? null
            : AppDates.daysFromToday(due) < 0
                ? StatusTone.danger
                : AppDates.daysFromToday(due) <= 1
                    ? StatusTone.warning
                    : StatusTone.neutral;
    final priorityTone = settled ? null : taskPriorityTone(task.priority);

    return Card(
      child: InkWell(
        onTap: () => context.push('/tasks/${task.id}'),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Text(
                      task.title,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: text.titleSmall!.copyWith(
                        color: settled ? scheme.onSurfaceVariant : null,
                        decoration: task.status == 'cancelled' ? TextDecoration.lineThrough : null,
                      ),
                    ),
                  ),
                  if (priorityTone != null) ...[
                    const SizedBox(width: 8),
                    Tooltip(
                      message: taskPriorityLabel(l10n, task.priority),
                      child: Icon(Icons.flag, size: 18, color: status.fg(priorityTone)),
                    ),
                  ],
                ],
              ),
              const SizedBox(height: 10),
              Wrap(
                spacing: 8,
                runSpacing: 6,
                crossAxisAlignment: WrapCrossAlignment.center,
                children: [
                  StatusPill(label: taskStatusLabel(l10n, task.status), tone: taskStatusTone(task.status)),
                  if (task.category != null) TaskCategoryChip(category: task.category!),
                ],
              ),
              if (!settled || due != null) ...[
                const SizedBox(height: 12),
                Row(
                  children: [
                    if (due != null && dueTone != null) ...[
                      Icon(Icons.schedule, size: 16, color: status.fg(dueTone)),
                      const SizedBox(width: 4),
                      Text(
                        settled ? AppDates.short(context, due) : AppDates.due(context, due),
                        style: text.labelMedium!.copyWith(color: status.fg(dueTone)),
                      ),
                      const SizedBox(width: 12),
                    ],
                    if (!settled) ...[
                      Expanded(
                        child: LinearProgressIndicator(
                          value: task.progress / 100,
                          minHeight: 5,
                          color: task.status == 'overdue' ? status.fg(StatusTone.danger) : scheme.primary,
                        ),
                      ),
                      const SizedBox(width: 10),
                      Text('${task.progress}%', style: text.labelMedium!.copyWith(color: scheme.onSurfaceVariant)),
                    ],
                  ],
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
