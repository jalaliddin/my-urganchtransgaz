import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/models/user.dart';
import '../../../l10n/app_localizations.dart';
import '../../auth/presentation/auth_controller.dart';
import '../../notifications/presentation/notifications_controller.dart';

class MoreScreen extends ConsumerWidget {
  const MoreScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final user = ref.watch(authControllerProvider).value;
    final unreadCount = ref.watch(unreadNotificationsCountProvider).value ?? 0;

    return Scaffold(
      appBar: AppBar(title: Text(l10n.navMore)),
      body: ListView(
        children: [
          if (user != null) _ProfileHeader(user: user),
          const Divider(height: 1),
          ListTile(
            leading: const Icon(Icons.person_outline),
            title: Text(l10n.profileTitle),
            onTap: () => context.push('/profile'),
          ),
          ListTile(
            leading: const Icon(Icons.folder_outlined),
            title: Text(l10n.documentsTitle),
            onTap: () => context.push('/documents'),
          ),
          ListTile(
            leading: const Icon(Icons.insights_outlined),
            title: Text(l10n.kpiTitle),
            onTap: () => context.push('/kpi'),
          ),
          ListTile(
            leading: const Icon(Icons.school_outlined),
            title: Text(l10n.examsTitle),
            onTap: () => context.push('/exams'),
          ),
          ListTile(
            leading: const Icon(Icons.event_busy_outlined),
            title: Text(l10n.leaveRequestsTitle),
            onTap: () => context.push('/leave-requests'),
          ),
          ListTile(
            leading: const Icon(Icons.campaign_outlined),
            title: Text(l10n.announcementsTitle),
            onTap: () => context.push('/announcements'),
          ),
          ListTile(
            leading: Badge(
              isLabelVisible: unreadCount > 0,
              label: Text('$unreadCount'),
              child: const Icon(Icons.notifications_outlined),
            ),
            title: Text(l10n.notificationsTitle),
            onTap: () => context.push('/notifications'),
          ),
          const Divider(height: 1),
          ListTile(
            leading: Icon(Icons.logout, color: Theme.of(context).colorScheme.error),
            title: Text(l10n.commonLogout, style: TextStyle(color: Theme.of(context).colorScheme.error)),
            onTap: () => ref.read(authControllerProvider.notifier).logout(),
          ),
        ],
      ),
    );
  }
}

class _ProfileHeader extends StatelessWidget {
  const _ProfileHeader({required this.user});

  final User user;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(16),
      child: Row(
        children: [
          CircleAvatar(
            radius: 28,
            child: Text(user.name.isNotEmpty ? user.name[0].toUpperCase() : '?'),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(user.name, style: Theme.of(context).textTheme.titleMedium),
                if (user.employee != null)
                  Text(
                    user.employee!.position?.title ?? user.employee!.employeeNumber,
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
