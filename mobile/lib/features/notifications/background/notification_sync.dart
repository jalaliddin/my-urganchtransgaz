import 'package:shared_preferences/shared_preferences.dart';

import '../domain/notification_item.dart';

typedef FetchUnread = Future<List<NotificationItem>> Function();
typedef PresentNotifications = Future<void> Function(List<NotificationItem> fresh);

/// Turns "the server's unread list" into "notifications the user has not
/// been alerted about yet". Shared by the foreground timer and the
/// background worker, which run in different isolates — the record of what
/// was already announced therefore lives in [SharedPreferences], not in
/// memory.
class NotificationSync {
  NotificationSync({required this.fetchUnread, required this.present, required this.preferences});

  final FetchUnread fetchUnread;
  final PresentNotifications present;
  final SharedPreferences preferences;

  static const announcedIdsKey = 'announced_notification_ids';

  /// Forget everything announced — on logout, so the next account starts
  /// from its own baseline instead of inheriting this one's.
  static Future<void> reset(SharedPreferences preferences) => preferences.remove(announcedIdsKey);

  /// Returns how many notifications were newly announced.
  Future<int> run() async {
    // The other isolate may have written since this one last read.
    await preferences.reload();

    final unread = await fetchUnread();
    final ids = [for (final item in unread) item.id];
    final announced = preferences.getStringList(announcedIdsKey);

    // First run for this account: whatever is unread already existed before
    // notifications were switched on. It's on the badge; alerting for all of
    // it at once would just be noise.
    if (announced == null) {
      await preferences.setStringList(announcedIdsKey, ids);

      return 0;
    }

    final known = announced.toSet();
    final fresh = [
      for (final item in unread)
        if (!known.contains(item.id)) item,
    ];

    if (fresh.isEmpty) {
      return 0;
    }

    // Announce first, remember second: if showing fails, the next run tries
    // again rather than silently losing the notification. Only ids that are
    // still unread are kept — a read notification never becomes unread again.
    await present(fresh);
    await preferences.setStringList(announcedIdsKey, ids);

    return fresh.length;
  }
}
