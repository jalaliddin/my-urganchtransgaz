import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../l10n/app_localizations.dart';
import '../../announcements/presentation/announcements_controller.dart';
import '../../auth/presentation/auth_controller.dart';
import '../../tasks/domain/task.dart';
import '../../tasks/presentation/tasks_controller.dart';
import 'dashboard_providers.dart';

class SectionHeader extends StatelessWidget {
  const SectionHeader({super.key, required this.title, this.actionLabel, this.onAction});

  final String title;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        children: [
          Expanded(child: Text(title, style: Theme.of(context).textTheme.titleMedium)),
          if (actionLabel != null) TextButton(onPressed: onAction, child: Text(actionLabel!)),
        ],
      ),
    );
  }
}

/// Three numbers that answer "what needs me right now?" — each one opens
/// the place where it can be dealt with.
class DashboardStats extends ConsumerWidget {
  const DashboardStats({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final colorScheme = Theme.of(context).colorScheme;
    final user = ref.watch(authControllerProvider).value;
    final showIssues = user != null && (user.can('issues.view') || user.can('issues.create'));

    // Equal-height tiles: `stretch` needs a bounded height, which a
    // scrolling parent doesn't give, so the row sizes to its tallest tile.
    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Expanded(
            child: _StatTile(
              icon: Icons.checklist,
              color: colorScheme.primary,
              label: l10n.dashboardStatTasks,
              value: ref.watch(activeTasksCountProvider),
              onTap: () => context.go('/tasks'),
            ),
          ),
          if (showIssues) ...[
            const SizedBox(width: 12),
            Expanded(
              child: _StatTile(
                icon: Icons.report_problem_outlined,
                color: const Color(0xFFD97706),
                label: l10n.dashboardStatIssues,
                value: ref.watch(openIssuesCountProvider),
                onTap: () => context.go('/issues'),
              ),
            ),
          ],
          const SizedBox(width: 12),
          Expanded(
            child: _StatTile(
              icon: Icons.school_outlined,
              color: const Color(0xFF3F8F2F),
              label: l10n.dashboardStatExams,
              value: ref.watch(availableExamsCountProvider),
              onTap: () => context.push('/exams'),
            ),
          ),
        ],
      ),
    );
  }
}

class _StatTile extends StatelessWidget {
  const _StatTile({
    required this.icon,
    required this.color,
    required this.label,
    required this.value,
    required this.onTap,
  });

  final IconData icon;
  final Color color;
  final String label;
  final AsyncValue<int> value;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;

    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.14),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(icon, size: 20, color: color),
              ),
              const SizedBox(height: 12),
              // A failed count shows a dash rather than a wrong number.
              Text(
                value.when(data: (count) => '$count', loading: () => '…', error: (_, _) => '–'),
                style: textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 2),
              Text(
                label,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: textTheme.bodySmall?.copyWith(color: Theme.of(context).colorScheme.onSurfaceVariant),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// "Report a problem" is the one thing worth a big button; the rest are
/// small shortcuts to the screens that otherwise live under "More".
class QuickActions extends ConsumerWidget {
  const QuickActions({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final colorScheme = Theme.of(context).colorScheme;
    final user = ref.watch(authControllerProvider).value;
    final canReport = user != null && user.can('issues.create');

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SectionHeader(title: l10n.dashboardQuickActions),
        if (canReport) ...[
          Material(
            color: colorScheme.tertiaryContainer,
            borderRadius: BorderRadius.circular(16),
            clipBehavior: Clip.antiAlias,
            child: InkWell(
              onTap: () => context.push('/issues/new'),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(10),
                      decoration: BoxDecoration(color: colorScheme.tertiary, borderRadius: BorderRadius.circular(12)),
                      child: const Icon(Icons.add_location_alt_outlined, color: Colors.white),
                    ),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Text(
                        l10n.dashboardReportIssue,
                        style: Theme.of(context).textTheme.titleMedium?.copyWith(color: const Color(0xFF26350F)),
                      ),
                    ),
                    const Icon(Icons.arrow_forward, color: Color(0xFF26350F)),
                  ],
                ),
              ),
            ),
          ),
          const SizedBox(height: 12),
        ],
        Row(
          children: [
            _ShortcutButton(icon: Icons.school_outlined, label: l10n.examsTitle, onTap: () => context.push('/exams')),
            _ShortcutButton(icon: Icons.folder_outlined, label: l10n.documentsTitle, onTap: () => context.push('/documents')),
            _ShortcutButton(icon: Icons.insights_outlined, label: l10n.kpiTitle, onTap: () => context.push('/kpi')),
            _ShortcutButton(
              icon: Icons.campaign_outlined,
              label: l10n.announcementsTitle,
              onTap: () => context.push('/announcements'),
            ),
          ],
        ),
      ],
    );
  }
}

class _ShortcutButton extends StatelessWidget {
  const _ShortcutButton({required this.icon, required this.label, required this.onTap});

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final colorScheme = Theme.of(context).colorScheme;

    return Expanded(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 6, horizontal: 2),
          child: Column(
            children: [
              Container(
                width: 52,
                height: 52,
                decoration: BoxDecoration(color: colorScheme.primaryContainer, borderRadius: BorderRadius.circular(16)),
                child: Icon(icon, color: colorScheme.onPrimaryContainer),
              ),
              const SizedBox(height: 6),
              Text(
                label,
                maxLines: 2,
                textAlign: TextAlign.center,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.labelSmall,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class DueSoonSection extends ConsumerWidget {
  const DueSoonSection({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final tasks = ref.watch(dueSoonTasksProvider);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SectionHeader(title: l10n.dashboardDueSoon, actionLabel: l10n.dashboardSeeAll, onAction: () => context.go('/tasks')),
        tasks.when(
          data: (items) => items.isEmpty
              ? _EmptyCard(icon: Icons.task_alt, message: l10n.dashboardAllCaughtUp)
              : Column(
                  children: [
                    for (final task in items) ...[_TaskCard(task: task), const SizedBox(height: 8)],
                  ],
                ),
          loading: () => const _LoadingCard(),
          error: (_, _) => _ErrorCard(message: l10n.dashboardLoadError, onRetry: () => ref.invalidate(tasksControllerProvider)),
        ),
      ],
    );
  }
}

class _TaskCard extends StatelessWidget {
  const _TaskCard({required this.task});

  final Task task;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final colorScheme = Theme.of(context).colorScheme;
    final due = task.dueDate == null ? null : DateTime.tryParse(task.dueDate!);
    final isOverdue = task.status == 'overdue' || (due != null && due.isBefore(DateTime.now()));
    final accent = isOverdue ? colorScheme.error : colorScheme.primary;

    String? dueLabel;
    if (due != null) {
      try {
        dueLabel = DateFormat('d MMM', Localizations.localeOf(context).toString()).format(due);
      } catch (_) {
        dueLabel = DateFormat('yyyy-MM-dd').format(due);
      }
    }

    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => context.push('/tasks/${task.id}'),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                task.title,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.titleSmall,
              ),
              if (dueLabel != null) ...[
                const SizedBox(height: 8),
                // Its own line and shrinkable: a long "overdue · date" label
                // must never push the title or the row off a narrow screen.
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: accent.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(
                    isOverdue ? '${l10n.dashboardOverdue} · $dueLabel' : dueLabel,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(color: accent, fontSize: 12, fontWeight: FontWeight.w600),
                  ),
                ),
              ],
              const SizedBox(height: 10),
              Row(
                children: [
                  Expanded(
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(4),
                      child: LinearProgressIndicator(value: task.progress / 100, minHeight: 6, color: accent),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Text('${task.progress}%', style: Theme.of(context).textTheme.labelMedium),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class AnnouncementsSection extends ConsumerWidget {
  const AnnouncementsSection({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final announcements = ref.watch(latestAnnouncementsProvider);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SectionHeader(
          title: l10n.dashboardLatestAnnouncements,
          actionLabel: l10n.dashboardSeeAll,
          onAction: () => context.push('/announcements'),
        ),
        announcements.when(
          data: (items) => items.isEmpty
              ? _EmptyCard(icon: Icons.campaign_outlined, message: l10n.dashboardNoAnnouncements)
              : Card(
                  clipBehavior: Clip.antiAlias,
                  child: Column(
                    children: [
                      for (final announcement in items)
                        ListTile(
                          leading: Icon(Icons.campaign_outlined, color: Theme.of(context).colorScheme.primary),
                          title: Text(announcement.title, maxLines: 1, overflow: TextOverflow.ellipsis),
                          subtitle: Text(announcement.content, maxLines: 1, overflow: TextOverflow.ellipsis),
                          onTap: () => context.push('/announcements/${announcement.id}'),
                        ),
                    ],
                  ),
                ),
          loading: () => const _LoadingCard(),
          error: (_, _) => _ErrorCard(
            message: l10n.dashboardLoadError,
            onRetry: () => ref.invalidate(announcementsControllerProvider),
          ),
        ),
      ],
    );
  }
}

class _EmptyCard extends StatelessWidget {
  const _EmptyCard({required this.icon, required this.message});

  final IconData icon;
  final String message;

  @override
  Widget build(BuildContext context) {
    final colorScheme = Theme.of(context).colorScheme;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Row(
          children: [
            Icon(icon, color: colorScheme.tertiary, size: 28),
            const SizedBox(width: 14),
            Expanded(child: Text(message, style: TextStyle(color: colorScheme.onSurfaceVariant))),
          ],
        ),
      ),
    );
  }
}

class _LoadingCard extends StatelessWidget {
  const _LoadingCard();

  @override
  Widget build(BuildContext context) {
    return const Card(
      child: Padding(padding: EdgeInsets.all(24), child: Center(child: CircularProgressIndicator())),
    );
  }
}

class _ErrorCard extends StatelessWidget {
  const _ErrorCard({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            Icon(Icons.error_outline, color: Theme.of(context).colorScheme.error),
            const SizedBox(width: 12),
            Expanded(child: Text(message)),
            TextButton(onPressed: onRetry, child: Text(l10n.commonRetry)),
          ],
        ),
      ),
    );
  }
}
