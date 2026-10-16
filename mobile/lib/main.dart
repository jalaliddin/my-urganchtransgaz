import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'core/locale/locale_controller.dart';
import 'core/network/api_client.dart';
import 'core/network/api_providers.dart';
import 'core/router/app_router.dart';
import 'core/theme/app_theme.dart';
import 'features/app_update/app_update_controller.dart';
import 'features/auth/presentation/auth_controller.dart';
import 'features/notifications/background/background_worker.dart';
import 'features/notifications/background/notification_service.dart';
import 'features/notifications/background/notifications_lifecycle.dart';
import 'l10n/app_localizations.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  // Content draws under transparent status and navigation bars; the theme's
  // app bars set dark or light system icons to match the page.
  SystemChrome.setEnabledSystemUIMode(SystemUiMode.edgeToEdge);
  LicenseRegistry.addLicense(() async* {
    yield LicenseEntryWithLineBreaks(['Inter'], await rootBundle.loadString('assets/fonts/OFL.txt'));
  });

  final apiClient = ApiClient();
  final preferences = await SharedPreferences.getInstance();

  if (NotificationService.isSupported) {
    try {
      await BackgroundNotifications.initialize();
    } catch (e) {
      // Background checks are an extra; the app itself must still start.
      debugPrint('Background notifications unavailable: $e');
    }
  }

  runApp(
    ProviderScope(
      overrides: [
        apiClientProvider.overrideWithValue(apiClient),
        sharedPreferencesProvider.overrideWithValue(preferences),
      ],
      child: _AppBootstrap(apiClient: apiClient),
    ),
  );
}

/// Wires [ApiClient.onUnauthorized] to [AuthController.forceLogout] once
/// the provider container exists — the client itself is constructed
/// before Riverpod, so this one-time hookup happens here rather than in
/// [ApiClient]'s own constructor.
class _AppBootstrap extends ConsumerStatefulWidget {
  const _AppBootstrap({required this.apiClient});

  final ApiClient apiClient;

  @override
  ConsumerState<_AppBootstrap> createState() => _AppBootstrapState();
}

class _AppBootstrapState extends ConsumerState<_AppBootstrap> {
  @override
  void initState() {
    super.initState();
    widget.apiClient.onUnauthorized = () => ref.read(authControllerProvider.notifier).forceLogout();
    widget.apiClient.onUpdateRequired = (body) => ref.read(appUpdateControllerProvider.notifier).requireUpdate(body);
    ref.read(appUpdateControllerProvider.notifier).check();
  }

  @override
  Widget build(BuildContext context) {
    return const NotificationsLifecycle(child: UrtgApp());
  }
}

class UrtgApp extends ConsumerWidget {
  const UrtgApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final router = ref.watch(routerProvider);
    final locale = ref.watch(localeControllerProvider);

    return MaterialApp.router(
      locale: locale,
      onGenerateTitle: (context) => AppLocalizations.of(context).appName,
      theme: AppTheme.light(),
      darkTheme: AppTheme.dark(),
      // Screens without an app bar (the dashboard, login) still need
      // readable system icons over the light or dark page.
      builder: (context, child) => AnnotatedRegion<SystemUiOverlayStyle>(
        value: Theme.of(context).appBarTheme.systemOverlayStyle!,
        child: child!,
      ),
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: AppLocalizations.supportedLocales,
      routerConfig: router,
    );
  }
}
