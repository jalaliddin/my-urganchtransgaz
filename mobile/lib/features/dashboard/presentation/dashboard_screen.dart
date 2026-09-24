import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/layout/breakpoints.dart';
import '../../../l10n/app_localizations.dart';
import '../../announcements/presentation/announcements_controller.dart';
import '../../auth/presentation/auth_controller.dart';
import '../../exams/presentation/exams_controller.dart';
import '../../kpi/presentation/kpi_controller.dart';
import '../../notifications/presentation/notifications_controller.dart';
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
      body: LayoutBuilder(
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
                      padding: const EdgeInsets.fromLTRB(16, 20, 16, 32),
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
                          // On a phone, quick actions come first: reporting a
                          // problem is what people open the app to do.
                          : const Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                QuickActions(),
                                gap,
                                DashboardStats(),
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
    );
  }
}

/// Greeting, date and the notification bell on the brand gradient, with
/// the company mark as a faint watermark. Runs edge to edge (under the
/// status bar) while its content lines up with the page column below.
class DashboardHero extends ConsumerWidget {
  const DashboardHero({super.key, required this.maxContentWidth});

  final double maxContentWidth;

  String _greeting(AppLocalizations l10n, int hour) {
    if (hour < 12) return l10n.dashboardGreetingMorning;
    if (hour < 18) return l10n.dashboardGreetingAfternoon;

    return l10n.dashboardGreetingEvening;
  }

  String _today(BuildContext context) {
    final localeName = Localizations.localeOf(context).toString();

    try {
      return DateFormat('EEEE, d MMMM', localeName).format(DateTime.now());
    } catch (_) {
      return DateFormat('yyyy-MM-dd').format(DateTime.now());
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final colorScheme = Theme.of(context).colorScheme;
    final user = ref.watch(authControllerProvider).value;
    final unreadCount = ref.watch(unreadNotificationsCountProvider).value ?? 0;
    final name = user?.employee?.firstName ?? user?.name ?? '';
    return Container(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            colorScheme.primary,
            Color.lerp(colorScheme.primary, Colors.black, 0.28)!,
          ],
        ),
        borderRadius: const BorderRadius.vertical(bottom: Radius.circular(28)),
      ),
      clipBehavior: Clip.antiAlias,
      child: Stack(
        children: [
          Positioned(
            right: -24,
            bottom: -18,
            child: Opacity(
              opacity: 0.09,
              child: SvgPicture.asset(
                'assets/images/logo.svg',
                height: 170,
                colorFilter: const ColorFilter.mode(
                  Colors.white,
                  BlendMode.srcIn,
                ),
              ),
            ),
          ),
          SafeArea(
            bottom: false,
            child: Center(
              child: ConstrainedBox(
                constraints: BoxConstraints(maxWidth: maxContentWidth),
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(20, 16, 12, 28),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              _today(context),
                              style: TextStyle(
                                color: colorScheme.onPrimary.withValues(
                                  alpha: 0.75,
                                ),
                                fontSize: 13,
                              ),
                            ),
                            const SizedBox(height: 10),
                            Text(
                              _greeting(l10n, DateTime.now().hour),
                              style: TextStyle(
                                color: colorScheme.onPrimary.withValues(
                                  alpha: 0.85,
                                ),
                                fontSize: 16,
                              ),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              name,
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: Theme.of(context).textTheme.headlineSmall
                                  ?.copyWith(
                                    color: colorScheme.onPrimary,
                                    fontWeight: FontWeight.w800,
                                  ),
                            ),
                          ],
                        ),
                      ),
                      IconButton(
                        tooltip: l10n.notificationsTitle,
                        style: IconButton.styleFrom(
                          backgroundColor: colorScheme.onPrimary.withValues(
                            alpha: 0.14,
                          ),
                          foregroundColor: colorScheme.onPrimary,
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
          ),
        ],
      ),
    );
  }
}
