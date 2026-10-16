import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/formatting/dates.dart';
import '../../../core/theme/status_colors.dart';
import '../../../core/widgets/async_value_view.dart';
import '../../../core/widgets/responsive_body.dart';
import '../../../core/widgets/section.dart';
import '../../../l10n/app_localizations.dart';
import '../domain/notification_item.dart';
import '../domain/notification_route.dart';
import 'notifications_controller.dart';

class NotificationsScreen extends ConsumerWidget {
  const NotificationsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final notifications = ref.watch(notificationsControllerProvider);

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.notificationsTitle),
        actions: [
          IconButton(
            icon: const Icon(Icons.done_all),
            tooltip: l10n.notificationsMarkAllRead,
            onPressed: () => ref.read(notificationsControllerProvider.notifier).markAllRead(),
          ),
        ],
      ),
      body: ResponsiveBody(
        child: RefreshIndicator(
        onRefresh: () => ref.read(notificationsControllerProvider.notifier).refresh(),
        child: AsyncValueView(
          value: notifications,
          onRetry: () => ref.invalidate(notificationsControllerProvider),
          data: (context, items) {
            if (items.isEmpty) {
              return ListView(
                children: [
                  SizedBox(
                    height: 400,
                    child: EmptyStateView(message: l10n.notificationsNone, icon: Icons.notifications_none),
                  ),
                ],
              );
            }

            // Newest first, as the server sends them; one group per day.
            final groups = <String, List<NotificationItem>>{};
            for (final item in items) {
              final at = AppDates.parse(item.createdAt);
              final heading = at == null ? l10n.dateEarlier : AppDates.dayHeading(context, at);
              groups.putIfAbsent(heading, () => []).add(item);
            }

            return ListView(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 32),
              children: [
                for (final entry in groups.entries) ...[
                  SectionHeader(title: entry.key),
                  GroupCard(
                    dividerIndent: 72,
                    children: [
                      for (final item in entry.value)
                        _NotificationTile(
                          item: item,
                          onTap: () {
                            if (!item.isRead) {
                              ref.read(notificationsControllerProvider.notifier).markRead(item.id);
                            }

                            // Same destination a tap on the system notification opens.
                            final route = notificationRoute(item.data);
                            if (route != '/notifications') context.push(route);
                          },
                        ),
                    ],
                  ),
                ],
              ],
            );
          },
        ),
      ),
      ),
    );
  }
}

class _NotificationTile extends StatelessWidget {
  const _NotificationTile({required this.item, required this.onTap});

  final NotificationItem item;
  final VoidCallback onTap;

  /// An icon and tone from what the notification is about, so the list can
  /// be scanned without reading every line.
  (IconData, StatusTone) _kind() {
    final type = item.type;
    if (type.contains('Task')) return (Icons.checklist, StatusTone.info);
    if (type.contains('IssueResolved')) return (Icons.task_alt, StatusTone.success);
    if (type.contains('Issue')) return (Icons.location_on_outlined, StatusTone.danger);
    if (type.contains('Absence')) return (Icons.event_busy_outlined, StatusTone.warning);
    if (type.contains('DocumentApproved')) return (Icons.verified_outlined, StatusTone.success);
    if (type.contains('Document')) return (Icons.description_outlined, StatusTone.warning);
    if (type.contains('Exam')) return (Icons.school_outlined, StatusTone.success);
    if (type.contains('Announcement')) return (Icons.campaign_outlined, StatusTone.warning);
    if (type.contains('Kpi')) return (Icons.insights_outlined, StatusTone.info);

    return (Icons.notifications_outlined, StatusTone.neutral);
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final text = Theme.of(context).textTheme;
    final status = StatusColors.of(context);
    final (icon, tone) = _kind();
    final at = AppDates.parse(item.createdAt);

    return InkWell(
      onTap: onTap,
      child: Container(
        color: item.isRead ? null : scheme.primaryContainer.withValues(alpha: 0.35),
        padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            IconBadge(icon: icon, color: status.fg(tone), background: status.bg(tone)),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          item.title ?? '',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: text.titleSmall!.copyWith(fontWeight: item.isRead ? FontWeight.w500 : FontWeight.w700),
                        ),
                      ),
                      if (at != null)
                        Text(
                          AppDates.time(context, at),
                          style: text.labelSmall!.copyWith(color: scheme.onSurfaceVariant, fontWeight: FontWeight.w500),
                        ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Text(
                    item.message ?? '',
                    maxLines: 3,
                    overflow: TextOverflow.ellipsis,
                    style: text.bodyMedium!.copyWith(color: scheme.onSurfaceVariant),
                  ),
                ],
              ),
            ),
            if (!item.isRead) ...[
              const SizedBox(width: 10),
              Padding(
                padding: const EdgeInsets.only(top: 6),
                child: Container(
                  width: 8,
                  height: 8,
                  decoration: BoxDecoration(color: scheme.primary, shape: BoxShape.circle),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
