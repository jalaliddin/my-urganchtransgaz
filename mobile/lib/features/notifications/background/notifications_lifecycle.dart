import 'dart:async';

import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../../core/locale/locale_controller.dart';
import '../../../core/router/app_router.dart';
import '../../../l10n/app_localizations.dart';
import '../../auth/presentation/auth_controller.dart';
import '../presentation/notifications_controller.dart';
import 'background_worker.dart';
import 'notification_service.dart';
import 'notification_sync.dart';

final notificationServiceProvider = Provider<NotificationService>((ref) => NotificationService());

/// How often the running app looks for new notifications. Only the app
/// being open pays for this; the closed-app path is [BackgroundNotifications].
const _foregroundInterval = Duration(seconds: 60);

/// Keeps notifications alive for the signed-in user, wherever the app is:
///
/// * in the foreground: a timer refreshes the badge and announces anything
///   new, and the same happens whenever the app is brought back;
/// * closed or in the background (Android): the WorkManager periodic task;
/// * tapping a notification opens the screen it is about.
///
/// It all starts when a session exists and stops (including forgetting what
/// was announced) when it ends.
class NotificationsLifecycle extends ConsumerStatefulWidget {
  const NotificationsLifecycle({super.key, required this.child});

  final Widget child;

  @override
  ConsumerState<NotificationsLifecycle> createState() => _NotificationsLifecycleState();
}

class _NotificationsLifecycleState extends ConsumerState<NotificationsLifecycle> with WidgetsBindingObserver {
  Timer? _timer;
  bool _running = false;
  bool _launchRouteHandled = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);

    ref.listenManual(authControllerProvider, (previous, next) {
      final signedIn = next.value != null;

      if (signedIn && !_running) {
        _start();
      } else if (!signedIn && !next.isLoading && _running) {
        _stop();
      }
    }, fireImmediately: true);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _timer?.cancel();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && _running) {
      _syncNow();
    }
  }

  AppLocalizations get _l10n => lookupAppLocalizations(ref.read(localeControllerProvider));

  Future<void> _start() async {
    _running = true;

    final service = ref.read(notificationServiceProvider);

    if (NotificationService.isSupported) {
      try {
        await service.initialize(onOpenRoute: (route) => ref.read(routerProvider).push(route));
        await service.ensureChannel(_l10n);
        await service.requestPermission();
        await BackgroundNotifications.schedule();

        if (!_launchRouteHandled) {
          _launchRouteHandled = true;
          final route = await service.launchRoute();

          if (route != null && mounted) {
            ref.read(routerProvider).push(route);
          }
        }
      } catch (e) {
        // Notifications are an extra: never let them break the app.
        debugPrint('Notification setup failed: $e');
      }
    }

    _timer?.cancel();
    _timer = Timer.periodic(_foregroundInterval, (_) => _syncNow());
    _syncNow();
  }

  Future<void> _stop() async {
    _running = false;
    _timer?.cancel();
    _timer = null;

    try {
      await NotificationSync.reset(ref.read(sharedPreferencesProvider));

      if (NotificationService.isSupported) {
        await BackgroundNotifications.cancel();
        await ref.read(notificationServiceProvider).cancelAll();
      }
    } catch (e) {
      debugPrint('Notification teardown failed: $e');
    }
  }

  Future<void> _syncNow() async {
    try {
      // The badge is refreshed on every platform; system notifications only
      // where they're supported.
      if (!NotificationService.isSupported) {
        ref.invalidate(unreadNotificationsCountProvider);

        return;
      }

      final repository = ref.read(notificationsRepositoryProvider);
      final service = ref.read(notificationServiceProvider);
      final SharedPreferences preferences = ref.read(sharedPreferencesProvider);

      await NotificationSync(
        fetchUnread: () async => (await repository.list(unreadOnly: true)).items,
        present: (fresh) => service.present(fresh, _l10n),
        preferences: preferences,
      ).run();

      ref.invalidate(unreadNotificationsCountProvider);
    } catch (e) {
      debugPrint('Notification sync failed: $e');
    }
  }

  @override
  Widget build(BuildContext context) => widget.child;
}
