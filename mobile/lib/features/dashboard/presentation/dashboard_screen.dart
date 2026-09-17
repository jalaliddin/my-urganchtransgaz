import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../l10n/app_localizations.dart';
import '../../announcements/presentation/announcements_controller.dart';
import '../../attendance/presentation/attendance_controller.dart';
import '../../attendance/presentation/today_attendance_card.dart';
import '../../auth/presentation/auth_controller.dart';
import '../../notifications/presentation/notifications_controller.dart';
import '../../tasks/presentation/tasks_controller.dart';
import 'dashboard_controller.dart';

class DashboardScreen extends ConsumerWidget {
  const DashboardScreen({super.key});

  Future<void> _refresh(WidgetRef ref) async {
    ref.invalidate(myTodayAttendanceProvider);
    ref.invalidate(announcementsControllerProvider);
    ref.invalidate(tasksControllerProvider);
    ref.invalidate(unreadNotificationsCountProvider);
    ref.invalidate(upcomingBusinessTripProvider);
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final user = ref.watch(authControllerProvider).value;
    final announcements = ref.watch(latestAnnouncementsProvider);
    final dueSoonTasks = ref.watch(dueSoonTasksProvider);
    final upcomingTrip = ref.watch(upcomingBusinessTripProvider);
    final unreadCount = ref.watch(unreadNotificationsCountProvider).value ?? 0;

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.dashboardTitle),
        actions: [
          IconButton(
            icon: Badge(isLabelVisible: unreadCount > 0, label: Text('$unreadCount'), child: const Icon(Icons.notifications_outlined)),
            onPressed: () => context.push('/notifications'),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () => _refresh(ref),
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text('${l10n.dashboardWelcome}, ${user?.employee?.firstName ?? user?.name ?? ''}', style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 16),
            const TodayAttendanceCard(),
            const SizedBox(height: 16),
            upcomingTrip.when(
              data: (trip) => trip == null
                  ? const SizedBox.shrink()
                  : Card(
                      child: ListTile(
                        leading: const Icon(Icons.flight_takeoff),
                        title: Text(l10n.dashboardUpcomingTrip),
                        subtitle: Text('${trip.destination} · ${trip.startDate} — ${trip.endDate}'),
                      ),
                    ),
              loading: () => const SizedBox.shrink(),
              error: (error, stackTrace) => const SizedBox.shrink(),
            ),
            const SizedBox(height: 16),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(l10n.dashboardLatestAnnouncements, style: Theme.of(context).textTheme.titleMedium),
                        TextButton(onPressed: () => context.push('/announcements'), child: Text(l10n.dashboardSeeAll)),
                      ],
                    ),
                    announcements.when(
                      data: (items) => items.isEmpty
                          ? Text(l10n.dashboardNoAnnouncements)
                          : Column(
                              children: items
                                  .map((a) => ListTile(
                                        contentPadding: EdgeInsets.zero,
                                        title: Text(a.title, maxLines: 1, overflow: TextOverflow.ellipsis),
                                        subtitle: Text(a.content, maxLines: 1, overflow: TextOverflow.ellipsis),
                                        onTap: () => context.push('/announcements/${a.id}'),
                                      ))
                                  .toList(),
                            ),
                      loading: () => const Padding(padding: EdgeInsets.all(16), child: Center(child: CircularProgressIndicator())),
                      error: (error, stackTrace) => Text(l10n.commonErrorGeneric),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(l10n.dashboardMyTasks, style: Theme.of(context).textTheme.titleMedium),
                        TextButton(onPressed: () => context.go('/tasks'), child: Text(l10n.dashboardSeeAll)),
                      ],
                    ),
                    dueSoonTasks.when(
                      data: (items) => items.isEmpty
                          ? Text(l10n.dashboardNoTasks)
                          : Column(
                              children: items
                                  .map((task) => ListTile(
                                        contentPadding: EdgeInsets.zero,
                                        title: Text(task.title, maxLines: 1, overflow: TextOverflow.ellipsis),
                                        subtitle: task.dueDate != null ? Text(task.dueDate!) : null,
                                        trailing: Text('${task.progress}%'),
                                        onTap: () => context.push('/tasks/${task.id}'),
                                      ))
                                  .toList(),
                            ),
                      loading: () => const Padding(padding: EdgeInsets.all(16), child: Center(child: CircularProgressIndicator())),
                      error: (error, stackTrace) => Text(l10n.commonErrorGeneric),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
