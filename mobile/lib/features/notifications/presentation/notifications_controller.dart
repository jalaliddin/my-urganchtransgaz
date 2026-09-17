import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_providers.dart';
import '../data/notifications_repository.dart';
import '../domain/notification_item.dart';

final notificationsRepositoryProvider = Provider<NotificationsRepository>((ref) {
  return NotificationsRepository(ref.watch(apiClientProvider));
});

/// Powers the unread badge on the "More" tab and the Dashboard — a plain
/// [FutureProvider] rather than polling, refreshed on pull-to-refresh and
/// after marking something read. There's no push-notification transport
/// in this backend (see the mobile README's scope notes), so this is as
/// live as the in-app-only notification model gets.
final unreadNotificationsCountProvider = FutureProvider<int>((ref) {
  return ref.watch(notificationsRepositoryProvider).unreadCount();
});

class NotificationsController extends AsyncNotifier<List<NotificationItem>> {
  @override
  Future<List<NotificationItem>> build() async {
    final page = await ref.watch(notificationsRepositoryProvider).list();
    return page.items;
  }

  Future<void> refresh() async {
    ref.invalidateSelf();
    await future;
  }

  Future<void> markRead(String id) async {
    await ref.read(notificationsRepositoryProvider).markRead(id);
    ref.invalidateSelf();
    ref.invalidate(unreadNotificationsCountProvider);
  }

  Future<void> markAllRead() async {
    await ref.read(notificationsRepositoryProvider).markAllRead();
    ref.invalidateSelf();
    ref.invalidate(unreadNotificationsCountProvider);
  }
}

final notificationsControllerProvider =
    AsyncNotifierProvider<NotificationsController, List<NotificationItem>>(NotificationsController.new);
