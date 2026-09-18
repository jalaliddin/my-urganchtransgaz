import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:go_router/go_router.dart';

import '../../../core/models/user.dart';
import '../../../core/widgets/responsive_body.dart';
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
      body: ResponsiveBody(
        child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (user != null) _ProfileHeader(user: user),
          const SizedBox(height: 16),
          Card(
            clipBehavior: Clip.antiAlias,
            child: Column(
              children: [
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
                if (user != null && (user.can('issues.view') || user.can('issues.create')))
                  ListTile(
                    leading: const Icon(Icons.map_outlined),
                    title: Text(l10n.issuesTitle),
                    onTap: () => context.push('/issues'),
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
              ],
            ),
          ),
          const SizedBox(height: 16),
          Card(
            clipBehavior: Clip.antiAlias,
            child: ListTile(
              leading: Icon(Icons.logout, color: Theme.of(context).colorScheme.error),
              title: Text(l10n.commonLogout, style: TextStyle(color: Theme.of(context).colorScheme.error)),
              onTap: () => ref.read(authControllerProvider.notifier).logout(),
            ),
          ),
          const SizedBox(height: 24),
          Center(
            child: Opacity(
              opacity: 0.5,
              child: SvgPicture.asset('assets/images/logo.svg', height: 32),
            ),
          ),
          const SizedBox(height: 8),
        ],
      ),
      ),
    );
  }
}

class _ProfileHeader extends StatelessWidget {
  const _ProfileHeader({required this.user});

  final User user;

  @override
  Widget build(BuildContext context) {
    final colorScheme = Theme.of(context).colorScheme;

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(20),
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [colorScheme.primary, colorScheme.primary.withValues(alpha: 0.85)],
        ),
      ),
      child: Row(
        children: [
          CircleAvatar(
            radius: 28,
            backgroundColor: colorScheme.onPrimary.withValues(alpha: 0.15),
            child: Text(
              user.name.isNotEmpty ? user.name[0].toUpperCase() : '?',
              style: TextStyle(color: colorScheme.onPrimary, fontWeight: FontWeight.w700, fontSize: 20),
            ),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  user.name,
                  style: Theme.of(context)
                      .textTheme
                      .titleMedium
                      ?.copyWith(color: colorScheme.onPrimary, fontWeight: FontWeight.w700),
                ),
                if (user.employee != null)
                  Text(
                    user.employee!.position?.title ?? user.employee!.employeeNumber,
                    style: TextStyle(color: colorScheme.onPrimary.withValues(alpha: 0.8), fontSize: 13),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
