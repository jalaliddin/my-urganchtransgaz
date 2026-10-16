import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:go_router/go_router.dart';

import '../../../core/formatting/dates.dart';
import '../../../core/theme/status_colors.dart';
import '../../../core/widgets/section.dart';
import '../../../core/widgets/status_pill.dart';
import '../../../l10n/app_localizations.dart';
import '../../announcements/presentation/announcements_controller.dart';
import '../../auth/presentation/auth_controller.dart';
import '../../profile/presentation/profile_controller.dart';
import '../../tasks/domain/task.dart';
import '../../tasks/presentation/tasks_controller.dart';
import 'dashboard_providers.dart';

/// "What needs me right now?" on one brand card: active tasks as the big
/// number, open issues / exams / KPI beside it. Each figure opens the place
/// where it can be dealt with.
class DashboardStats extends ConsumerWidget {
  const DashboardStats({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final scheme = Theme.of(context).colorScheme;
    final text = Theme.of(context).textTheme;
    final user = ref.watch(authControllerProvider).value;
    final showIssues = user != null && (user.can('issues.view') || user.can('issues.create'));
    const onCard = Colors.white;

    String show<T>(AsyncValue<T> value, String Function(T) format) =>
        // A failed value shows a dash rather than a wrong number.
        value.when(data: format, loading: () => '…', error: (_, _) => '–');

    final navy = scheme.brightness == Brightness.dark ? const Color(0xFF22416B) : scheme.primary;

    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(24),
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [navy, Color.lerp(navy, const Color(0xFF2F7FB5), 0.55)!],
        ),
      ),
      clipBehavior: Clip.antiAlias,
      child: Stack(
        children: [
          Positioned(
            right: -40,
            top: -24,
            child: Opacity(
              opacity: 0.06,
              // Crops the "UZTRANSGAZ" caption off the bottom of the logo so
              // the watermark is just the emblem, not text that reads through.
              child: ClipRect(
                child: Align(
                  alignment: Alignment.topCenter,
                  heightFactor: 0.86,
                  child: SvgPicture.asset(
                    'assets/images/logo.svg',
                    height: 170,
                    colorFilter: const ColorFilter.mode(Colors.white, BlendMode.srcIn),
                  ),
                ),
              ),
            ),
          ),
          Material(
            type: MaterialType.transparency,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                InkWell(
                  onTap: () => context.go('/tasks'),
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(20, 18, 16, 14),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                l10n.dashboardStatTasks,
                                style: text.labelLarge!.copyWith(color: onCard.withValues(alpha: 0.8)),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                show(ref.watch(activeTasksCountProvider), (count) => '$count'),
                                style: text.displaySmall!.copyWith(
                                  color: onCard,
                                  fontFeatures: const [FontFeature.tabularFigures()],
                                ),
                              ),
                            ],
                          ),
                        ),
                        Icon(Icons.arrow_forward, color: onCard.withValues(alpha: 0.8)),
                      ],
                    ),
                  ),
                ),
                Divider(color: onCard.withValues(alpha: 0.14), height: 1),
                IntrinsicHeight(
                  child: Row(
                    children: [
                      if (showIssues)
                        _MiniStat(
                          icon: Icons.report_problem_outlined,
                          value: show(ref.watch(openIssuesCountProvider), (count) => '$count'),
                          label: l10n.dashboardStatIssues,
                          onTap: () => context.go('/issues'),
                        ),
                      _MiniStat(
                        icon: Icons.school_outlined,
                        value: show(ref.watch(availableExamsCountProvider), (count) => '$count'),
                        label: l10n.dashboardStatExams,
                        onTap: () => context.push('/exams'),
                      ),
                      _MiniStat(
                        icon: Icons.insights_outlined,
                        value: show(ref.watch(kpiOverallScoreProvider), (score) => score == null ? '—' : '${score.round()}%'),
                        label: l10n.dashboardStatKpi,
                        onTap: () => context.push('/kpi'),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _MiniStat extends StatelessWidget {
  const _MiniStat({required this.icon, required this.value, required this.label, required this.onTap});

  final IconData icon;
  final String value;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    const onCard = Colors.white;

    return Expanded(
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 8, 16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(icon, size: 18, color: onCard.withValues(alpha: 0.75)),
              const SizedBox(height: 6),
              Text(
                value,
                style: text.titleLarge!.copyWith(
                  color: onCard,
                  fontFeatures: const [FontFeature.tabularFigures()],
                ),
              ),
              Text(
                label,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: text.bodySmall!.copyWith(color: onCard.withValues(alpha: 0.75), height: 1.25),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// "Report a problem" is the one thing worth a big button; the rest are
/// shortcuts to the screens that otherwise live under "More".
class QuickActions extends ConsumerWidget {
  const QuickActions({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final scheme = Theme.of(context).colorScheme;
    final status = StatusColors.of(context);
    final user = ref.watch(authControllerProvider).value;
    final canReport = user != null && user.can('issues.create');

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        SectionHeader(title: l10n.dashboardQuickActions),
        if (canReport) ...[
          Card(
            child: InkWell(
              onTap: () => context.push('/issues/new'),
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Row(
                  children: [
                    IconBadge(
                      icon: Icons.add_location_alt_outlined,
                      size: 48,
                      color: Colors.white,
                      background: scheme.tertiary,
                    ),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Text(l10n.dashboardReportIssue, style: Theme.of(context).textTheme.titleMedium),
                    ),
                    Icon(Icons.chevron_right, color: scheme.onSurfaceVariant),
                  ],
                ),
              ),
            ),
          ),
          const SizedBox(height: 12),
        ],
        Card(
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 4),
            child: Row(
              children: [
                _ShortcutButton(
                  icon: Icons.school_outlined,
                  tone: status.fg(StatusTone.success),
                  label: l10n.examsTitle,
                  onTap: () => context.push('/exams'),
                ),
                _ShortcutButton(
                  icon: Icons.folder_outlined,
                  tone: status.fg(StatusTone.info),
                  label: l10n.documentsTitle,
                  onTap: () => context.push('/documents'),
                ),
                _ShortcutButton(
                  icon: Icons.insights_outlined,
                  tone: const Color(0xFF6D5DD3),
                  label: l10n.kpiTitle,
                  onTap: () => context.push('/kpi'),
                ),
                _ShortcutButton(
                  icon: Icons.campaign_outlined,
                  tone: status.fg(StatusTone.warning),
                  label: l10n.announcementsTitle,
                  onTap: () => context.push('/announcements'),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class _ShortcutButton extends StatelessWidget {
  const _ShortcutButton({required this.icon, required this.tone, required this.label, required this.onTap});

  final IconData icon;
  final Color tone;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 6, horizontal: 2),
          child: Column(
            children: [
              IconBadge(icon: icon, size: 48, color: tone, background: tone.withValues(alpha: 0.12)),
              const SizedBox(height: 8),
              Text(
                label,
                maxLines: 2,
                textAlign: TextAlign.center,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.labelMedium,
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
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        SectionHeader(title: l10n.dashboardDueSoon, actionLabel: l10n.dashboardSeeAll, onAction: () => context.go('/tasks')),
        tasks.when(
          data: (items) => items.isEmpty
              ? _EmptyCard(icon: Icons.task_alt, message: l10n.dashboardAllCaughtUp)
              : Column(
                  children: [
                    for (final task in items) ...[_TaskCard(task: task), const SizedBox(height: 10)],
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
    final scheme = Theme.of(context).colorScheme;
    final status = StatusColors.of(context);
    final due = AppDates.parse(task.dueDate);
    final isOverdue = task.status == 'overdue' || (due != null && AppDates.daysFromToday(due) < 0);
    final tone = isOverdue
        ? StatusTone.danger
        : (due != null && AppDates.daysFromToday(due) <= 1 ? StatusTone.warning : StatusTone.info);

    return Card(
      child: InkWell(
        onTap: () => context.push('/tasks/${task.id}'),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                task.title,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.titleSmall,
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  if (due != null) ...[
                    // Shrinkable: a long label must never push the row off a narrow screen.
                    Flexible(
                      child: StatusPill(label: AppDates.due(context, due), tone: tone, icon: Icons.schedule),
                    ),
                    const SizedBox(width: 12),
                  ],
                  Expanded(
                    child: LinearProgressIndicator(
                      value: task.progress / 100,
                      color: isOverdue ? status.fg(StatusTone.danger) : scheme.primary,
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
    final status = StatusColors.of(context);
    final announcements = ref.watch(latestAnnouncementsProvider);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        SectionHeader(
          title: l10n.dashboardLatestAnnouncements,
          actionLabel: l10n.dashboardSeeAll,
          onAction: () => context.push('/announcements'),
        ),
        announcements.when(
          data: (items) => items.isEmpty
              ? _EmptyCard(icon: Icons.campaign_outlined, message: l10n.dashboardNoAnnouncements)
              : GroupCard(
                  dividerIndent: 72,
                  children: [
                    for (final announcement in items)
                      ListTile(
                        leading: IconBadge(
                          icon: Icons.campaign_outlined,
                          color: status.fg(StatusTone.warning),
                          background: status.bg(StatusTone.warning),
                        ),
                        title: Text(announcement.title, maxLines: 1, overflow: TextOverflow.ellipsis),
                        subtitle: Text(announcement.content, maxLines: 1, overflow: TextOverflow.ellipsis),
                        onTap: () => context.push('/announcements/${announcement.id}'),
                      ),
                  ],
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
    final status = StatusColors.of(context);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            IconBadge(icon: icon, color: status.fg(StatusTone.success), background: status.bg(StatusTone.success)),
            const SizedBox(width: 14),
            Expanded(
              child: Text(
                message,
                style: Theme.of(context).textTheme.bodyMedium!.copyWith(color: Theme.of(context).colorScheme.onSurfaceVariant),
              ),
            ),
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
      child: Padding(
        padding: EdgeInsets.all(24),
        child: Center(child: SizedBox(width: 24, height: 24, child: CircularProgressIndicator(strokeWidth: 2.5))),
      ),
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
        padding: const EdgeInsets.fromLTRB(16, 8, 8, 8),
        child: Row(
          children: [
            Icon(Icons.cloud_off_outlined, color: Theme.of(context).colorScheme.error),
            const SizedBox(width: 12),
            Expanded(child: Text(message)),
            TextButton(onPressed: onRetry, child: Text(l10n.commonRetry)),
          ],
        ),
      ),
    );
  }
}

/// A single progress bar toward a complete profile — silent once it's
/// already 100% (no reminder needed) or on load failure (this is a
/// nice-to-have prompt, not something worth interrupting the dashboard
/// over if it can't be fetched).
class ProfileCompletionCard extends ConsumerWidget {
  const ProfileCompletionCard({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final scheme = Theme.of(context).colorScheme;
    final completion = ref.watch(profileCompletionProvider);

    return completion.when(
      data: (value) {
        if (value.percentage >= 100) return const SizedBox.shrink();

        return Card(
          child: InkWell(
            onTap: () => context.push('/profile'),
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                children: [
                  SizedBox(
                    width: 44,
                    height: 44,
                    child: Stack(
                      alignment: Alignment.center,
                      children: [
                        CircularProgressIndicator(
                          value: value.percentage / 100,
                          strokeWidth: 4,
                          backgroundColor: scheme.surfaceContainerHigh,
                        ),
                        Text('${value.percentage}%', style: Theme.of(context).textTheme.labelSmall),
                      ],
                    ),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(l10n.dashboardProfileCompletion, style: Theme.of(context).textTheme.titleSmall),
                        Text(
                          l10n.dashboardMyProfile,
                          style: Theme.of(context).textTheme.bodyMedium!.copyWith(color: scheme.onSurfaceVariant),
                        ),
                      ],
                    ),
                  ),
                  Icon(Icons.chevron_right, color: scheme.onSurfaceVariant),
                ],
              ),
            ),
          ),
        );
      },
      loading: () => const SizedBox.shrink(),
      error: (_, _) => const SizedBox.shrink(),
    );
  }
}
