import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/widgets/async_value_view.dart';
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
      body: RefreshIndicator(
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
              padding: const EdgeInsets.symmetric(vertical: 8),
              itemCount: items.length,
              separatorBuilder: (context, index) => const Divider(height: 1),
              itemBuilder: (context, index) => _AnnouncementTile(announcement: items[index]),
            );
          },
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

    return ListTile(
      leading: CircleAvatar(
        backgroundColor: _priorityColor(context, announcement.priority),
        child: const Icon(Icons.campaign, color: Colors.white, size: 20),
      ),
      title: Text(announcement.title, style: TextStyle(fontWeight: isUnread ? FontWeight.bold : FontWeight.normal)),
      subtitle: Text(
        announcement.content,
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
      ),
      trailing: isUnread ? Container(width: 8, height: 8, decoration: BoxDecoration(color: Theme.of(context).colorScheme.primary, shape: BoxShape.circle)) : null,
      onTap: () => context.push('/announcements/${announcement.id}'),
    );
  }

  Color _priorityColor(BuildContext context, String priority) {
    return switch (priority) {
      'urgent' => Colors.red,
      'high' => Colors.orange,
      _ => Theme.of(context).colorScheme.primary,
    };
  }
}
