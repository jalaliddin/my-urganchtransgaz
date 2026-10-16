import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/formatting/dates.dart';
import '../../../core/theme/status_colors.dart';
import '../../../core/widgets/async_value_view.dart';
import '../../../core/widgets/section.dart';
import '../../../core/widgets/responsive_body.dart';
import '../../../l10n/app_localizations.dart';
import '../domain/announcement.dart';
import 'announcements_controller.dart';

class AnnouncementsScreen extends ConsumerWidget {
  const AnnouncementsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final announcements = ref.watch(announcementsControllerProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.announcementsTitle)),
      body: ResponsiveBody(
        child: RefreshIndicator(
        onRefresh: () => ref.read(announcementsControllerProvider.notifier).refresh(),
        child: AsyncValueView(
          value: announcements,
          onRetry: () => ref.invalidate(announcementsControllerProvider),
          data: (context, items) {
            if (items.isEmpty) {
              return ListView(
                children: [
                  SizedBox(height: 400, child: EmptyStateView(message: l10n.announcementsNone, icon: Icons.campaign_outlined)),
                ],
              );
            }

            return ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: items.length,
              separatorBuilder: (context, index) => const SizedBox(height: 10),
              itemBuilder: (context, index) => _AnnouncementTile(announcement: items[index]),
            );
          },
        ),
      ),
      ),
    );
  }
}

class _AnnouncementTile extends StatelessWidget {
  const _AnnouncementTile({required this.announcement});

  final Announcement announcement;

  @override
  Widget build(BuildContext context) {
    final isUnread = announcement.isRead == false;
    final scheme = Theme.of(context).colorScheme;
    final text = Theme.of(context).textTheme;
    final status = StatusColors.of(context);
    final tone = switch (announcement.priority) {
      'urgent' => StatusTone.danger,
      'high' => StatusTone.warning,
      _ => StatusTone.info,
    };
    final published = AppDates.parse(announcement.publishAt ?? announcement.createdAt);

    return Card(
      child: InkWell(
        onTap: () => context.push('/announcements/${announcement.id}'),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              IconBadge(icon: Icons.campaign_outlined, color: status.fg(tone), background: status.bg(tone)),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            announcement.title,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: text.titleSmall!.copyWith(fontWeight: isUnread ? FontWeight.w700 : FontWeight.w600),
                          ),
                        ),
                        if (isUnread) ...[
                          const SizedBox(width: 8),
                          Container(
                            width: 8,
                            height: 8,
                            decoration: BoxDecoration(color: scheme.primary, shape: BoxShape.circle),
                          ),
                        ],
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      announcement.content,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: text.bodyMedium!.copyWith(color: scheme.onSurfaceVariant),
                    ),
                    if (published != null || announcement.authorName != null) ...[
                      const SizedBox(height: 8),
                      Text(
                        [announcement.authorName, if (published != null) AppDates.moment(context, published)]
                            .whereType<String>()
                            .join(', '),
                        style: text.labelMedium!.copyWith(color: scheme.onSurfaceVariant, fontWeight: FontWeight.w500),
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
