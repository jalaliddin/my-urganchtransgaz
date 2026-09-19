import 'package:flutter/widgets.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:workmanager/workmanager.dart';

import '../../../core/locale/locale_controller.dart';
import '../../../core/network/api_client.dart';
import '../../../core/storage/secure_storage.dart';
import '../../../l10n/app_localizations.dart';
import '../data/notifications_repository.dart';
import 'notification_service.dart';
import 'notification_sync.dart';

const _uniqueName = 'urtg-notification-sync';
const _taskName = 'notification-sync';

/// Entry point Android's WorkManager calls, in its own isolate, while the
/// app is closed or in the background. It must be a top-level function
/// and survive tree-shaking, hence the annotation.
@pragma('vm:entry-point')
void notificationCallbackDispatcher() {
  Workmanager().executeTask((task, inputData) async {
    try {
      await runBackgroundNotificationSync();
    } catch (_) {
      // Offline, or the server hiccuped: the next scheduled run tries again.
    }

    return true;
  });
}

/// One sync with no UI and no provider container: read the stored session,
/// ask the server what's unread, announce what's new.
Future<void> runBackgroundNotificationSync() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Logged out (or never logged in): nothing to fetch, nobody to notify.
  if (await SecureStorage.readToken() == null) {
    return;
  }

  final client = ApiClient();
  await client.restoreBaseUrl();

  final preferences = await SharedPreferences.getInstance();
  final l10n = lookupAppLocalizations(localeFromPreferences(preferences));
  final service = NotificationService();
  await service.initialize();

  await NotificationSync(
    fetchUnread: () async => (await NotificationsRepository(client).list(unreadOnly: true)).items,
    present: (fresh) => service.present(fresh, l10n),
    preferences: preferences,
  ).run();
}

/// Registers and cancels the periodic background check.
///
/// Android runs a periodic task no more often than every 15 minutes and may
/// delay it further in battery-saving modes, so this is "within about a
/// quarter of an hour", not instant delivery — that needs push (FCM).
class BackgroundNotifications {
  BackgroundNotifications._();

  static Future<void> initialize() => Workmanager().initialize(notificationCallbackDispatcher);

  static Future<void> schedule() => Workmanager().registerPeriodicTask(
        _uniqueName,
        _taskName,
        frequency: const Duration(minutes: 15),
        existingWorkPolicy: ExistingPeriodicWorkPolicy.update,
        constraints: Constraints(networkType: NetworkType.connected),
      );

  static Future<void> cancel() => Workmanager().cancelByUniqueName(_uniqueName);
}
