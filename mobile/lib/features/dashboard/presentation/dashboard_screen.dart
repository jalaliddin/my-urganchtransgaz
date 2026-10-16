import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/layout/breakpoints.dart';
import '../../../l10n/app_localizations.dart';
import '../../announcements/presentation/announcements_controller.dart';
import '../../auth/presentation/auth_controller.dart';
import '../../exams/presentation/exams_controller.dart';
import '../../kpi/presentation/kpi_controller.dart';
import '../../notifications/presentation/notifications_controller.dart';
import '../../profile/presentation/profile_controller.dart';
import '../../tasks/presentation/tasks_controller.dart';
import 'dashboard_providers.dart';
import 'dashboard_sections.dart';

const _contentMaxWidth = 1100.0;
const _singleColumnMaxWidth = 640.0;

class DashboardScreen extends ConsumerWidget {
  const DashboardScreen({super.key});

  Future<void> _refresh(WidgetRef ref) async {
    ref.invalidate(announcementsControllerProvider);
    ref.invalidate(tasksControllerProvider);
    ref.invalidate(allTasksSummaryProvider);
    ref.invalidate(examsControllerProvider);
    ref.invalidate(openIssuesCountProvider);
    ref.invalidate(unreadNotificationsCountProvider);
    ref.invalidate(profileCompletionProvider);
    ref.invalidate(kpiControllerProvider);

    // Held until the list is back so the spinner means something.
    try {
      await ref.read(tasksControllerProvider.future);
    } catch (_) {
      // The sections show their own error state.
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    const gap = SizedBox(height: 24);

    // Decided from the room the page itself has — beside the navigation
    // rail that is less than the window — not from the window.
    return Scaffold(
      // The hero runs up under the status bar on purpose; the rest of the
      // page keeps clear of the navigation bar the app is drawn under from
      // Android 15 (only beside the rail — the bottom bar takes it on phones).
      body: SafeArea(
        top: false,
        child: LayoutBuilder(
          builder: (context, constraints) {
            final isExpanded =
                windowSizeForWidth(constraints.maxWidth) == WindowSize.expanded;
            final contentWidth = isExpanded
                ? _contentMaxWidth
                : _singleColumnMaxWidth;

            return RefreshIndicator(
              edgeOffset: 0,
              onRefresh: () => _refresh(ref),
              child: ListView(
                padding: EdgeInsets.zero,
                physics: const AlwaysScrollableScrollPhysics(),
                children: [
                  DashboardHero(maxContentWidth: contentWidth),
                  Center(
                    child: ConstrainedBox(
                      constraints: BoxConstraints(maxWidth: contentWidth),
                      child: Padding(
                        padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
                        child: isExpanded
                            ? const Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Expanded(
                                    flex: 3,
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        DashboardStats(),
                                        gap,
                                        ProfileCompletionCard(),
                                        gap,
                                        DueSoonSection(),
                                      ],
                                    ),
                                  ),
                                  SizedBox(width: 24),
                                  Expanded(
                                    flex: 2,
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        QuickActions(),
                                        gap,
                                        AnnouncementsSection(),
                                      ],
                                    ),
                                  ),
                                ],
                              )
                            : const Column(
                                crossAxisAlignment: CrossAxisAlignment.stretch,
                                children: [
                                  DashboardStats(),
                                  gap,
                                  QuickActions(),
                                  gap,
                                  ProfileCompletionCard(),
                                  gap,
                                  DueSoonSection(),
                                  gap,
                                  AnnouncementsSection(),
                                ],
                              ),
                      ),
                    ),
                  ),
                ],
              ),
            );
          },
        ),
      ),
    );
  }
}

/// Greeting, today's date and the notification bell, on the page itself —
/// the brand colour is saved for the summary card right below.
class DashboardHero extends ConsumerWidget {
  const DashboardHero({super.key, required this.maxContentWidth});

  final double maxContentWidth;

  String _greeting(AppLocalizations l10n, int hour) {
    if (hour < 12) return l10n.dashboardGreetingMorning;
    if (hour < 18) return l10n.dashboardGreetingAfternoon;

    return l10n.dashboardGreetingEvening;
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final scheme = Theme.of(context).colorScheme;
    final text = Theme.of(context).textTheme;
    final user = ref.watch(authControllerProvider).value;
    final unreadCount = ref.watch(unreadNotificationsCountProvider).value ?? 0;
    final name = user?.employee?.firstName ?? user?.name ?? '';
    final initials = [user?.employee?.firstName, user?.employee?.lastName]
        .whereType<String>()
        .where((part) => part.isNotEmpty)
        .map((part) => part[0])
        .join()
        .toUpperCase();

    return SafeArea(
      bottom: false,
      child: Center(
        child: ConstrainedBox(
          constraints: BoxConstraints(maxWidth: maxContentWidth),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(20, 12, 12, 4),
            child: Row(
              children: [
                InkWell(
                  onTap: () => context.push('/profile'),
                  customBorder: const CircleBorder(),
                  child: CircleAvatar(
                    radius: 24,
                    backgroundColor: scheme.primaryContainer,
                    foregroundColor: scheme.onPrimaryContainer,
                    child: Text(initials.isEmpty ? '?' : initials, style: text.titleMedium!.copyWith(color: scheme.onPrimaryContainer)),
                  ),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '${_greeting(l10n, DateTime.now().hour)},',
                        style: text.bodyMedium!.copyWith(color: scheme.onSurfaceVariant),
                      ),
                      Text(name, maxLines: 1, overflow: TextOverflow.ellipsis, style: text.headlineSmall),
                    ],
                  ),
                ),
                IconButton(
                  tooltip: l10n.notificationsTitle,
                  style: IconButton.styleFrom(
                    backgroundColor: scheme.surfaceContainerLowest,
                    side: BorderSide(color: scheme.outlineVariant),
                    minimumSize: const Size(48, 48),
                  ),
                  icon: Badge(
                    isLabelVisible: unreadCount > 0,
                    label: Text('$unreadCount'),
                    child: const Icon(Icons.notifications_outlined),
                  ),
                  onPressed: () => context.push('/notifications'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
