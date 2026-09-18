import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/widgets/async_value_view.dart';
import '../../../core/widgets/responsive_body.dart';
import '../../../l10n/app_localizations.dart';
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

            return ListView.separated(
              itemCount: items.length,
              separatorBuilder: (context, index) => const Divider(height: 1),
              itemBuilder: (context, index) {
                final item = items[index];

                return ListTile(
                  leading: Icon(
                    item.isRead ? Icons.notifications_none : Icons.notifications,
                    color: item.isRead ? null : Theme.of(context).colorScheme.primary,
                  ),
                  title: Text(item.title ?? '', style: TextStyle(fontWeight: item.isRead ? FontWeight.normal : FontWeight.bold)),
                  subtitle: Text(item.message ?? ''),
                  onTap: () {
                    if (!item.isRead) {
                      ref.read(notificationsControllerProvider.notifier).markRead(item.id);
                    }
                  },
                );
              },
            );
          },
        ),
      ),
      ),
    );
  }
}
