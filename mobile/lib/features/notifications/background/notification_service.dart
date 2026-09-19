import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

import '../../../l10n/app_localizations.dart';
import '../domain/notification_item.dart';
import '../domain/notification_route.dart';

/// System notifications shown on this device.
///
/// Android only for now: iOS needs its own background setup (and a device
/// to verify it on), so nothing here claims to work there. Everywhere else
/// the in-app bell and badge are the notification surface, as before.
class NotificationService {
  NotificationService([FlutterLocalNotificationsPlugin? plugin]) : _plugin = plugin ?? FlutterLocalNotificationsPlugin();

  final FlutterLocalNotificationsPlugin _plugin;

  static bool get isSupported => !kIsWeb && defaultTargetPlatform == TargetPlatform.android;

  static const channelId = 'urtg_updates';
  static const _statusIcon = 'ic_stat_notification';
  static const _maxIndividual = 3;

  /// [onOpenRoute] is called with the route stored in a notification when
  /// the user taps it while the app is already running (or in the
  /// background). Omit it in the background worker, which has no UI.
  Future<void> initialize({void Function(String route)? onOpenRoute}) async {
    await _plugin.initialize(
      settings: const InitializationSettings(android: AndroidInitializationSettings(_statusIcon)),
      onDidReceiveNotificationResponse: (response) {
        final route = response.payload;

        if (route != null && route.isNotEmpty) {
          onOpenRoute?.call(route);
        }
      },
    );
  }

  /// Creates (or renames) the channel — the name shown in the system's
  /// notification settings follows the app language.
  Future<void> ensureChannel(AppLocalizations l10n) async {
    await _androidPlugin?.createNotificationChannel(
      AndroidNotificationChannel(
        channelId,
        l10n.notificationsChannelName,
        description: l10n.notificationsChannelDescription,
        importance: Importance.high,
      ),
    );
  }

  /// Android 13+ makes notifications opt-in; earlier versions grant them
  /// implicitly and this returns true.
  Future<bool> requestPermission() async => await _androidPlugin?.requestNotificationsPermission() ?? false;

  /// The route of the notification that launched the app from a terminated
  /// state, if that is how it was opened.
  Future<String?> launchRoute() async {
    final details = await _plugin.getNotificationAppLaunchDetails();

    if (details == null || !details.didNotificationLaunchApp) {
      return null;
    }

    final payload = details.notificationResponse?.payload;

    return payload == null || payload.isEmpty ? null : payload;
  }

  /// A handful of new notifications are shown one by one; a burst
  /// collapses into a single "N new" so the shade isn't flooded.
  Future<void> present(List<NotificationItem> fresh, AppLocalizations l10n) async {
    await ensureChannel(l10n);

    if (fresh.length > _maxIndividual) {
      await _show(id: 1, title: l10n.appName, body: l10n.notificationsBackgroundSummary(fresh.length), route: '/notifications', l10n: l10n);

      return;
    }

    for (final item in fresh) {
      await _show(
        // Stable per notification, so a re-announced one replaces itself.
        id: item.id.hashCode & 0x7fffffff,
        title: item.title ?? l10n.notificationsBackgroundTitle,
        body: item.message ?? '',
        route: notificationRoute(item.data),
        l10n: l10n,
      );
    }
  }

  Future<void> cancelAll() => _plugin.cancelAll();

  Future<void> _show({
    required int id,
    required String title,
    required String body,
    required String route,
    required AppLocalizations l10n,
  }) {
    return _plugin.show(
      id: id,
      title: title,
      body: body,
      payload: route,
      notificationDetails: NotificationDetails(
        android: AndroidNotificationDetails(
          channelId,
          l10n.notificationsChannelName,
          channelDescription: l10n.notificationsChannelDescription,
          importance: Importance.high,
          priority: Priority.high,
          icon: _statusIcon,
          styleInformation: BigTextStyleInformation(body),
        ),
      ),
    );
  }

  AndroidFlutterLocalNotificationsPlugin? get _androidPlugin =>
      _plugin.resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>();
}
