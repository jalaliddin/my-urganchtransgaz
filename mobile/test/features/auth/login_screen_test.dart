import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:urtg_mobile/core/locale/language_switcher.dart';
import 'package:urtg_mobile/core/locale/locale_controller.dart';
import 'package:urtg_mobile/core/models/user.dart';
import 'package:urtg_mobile/core/network/api_client.dart';
import 'package:urtg_mobile/core/network/api_providers.dart';
import 'package:urtg_mobile/features/auth/presentation/auth_controller.dart';
import 'package:urtg_mobile/features/auth/presentation/login_screen.dart';
import 'package:urtg_mobile/l10n/app_localizations.dart';

/// The real [AuthController.build] reads secure storage to restore a
/// session — a platform channel with no handler registered in a plain
/// widget test, which never resolves and would otherwise leave the
/// screen stuck showing its loading spinner forever. This fake skips
/// straight to "logged out" so the form itself can be exercised.
class _LoggedOutAuthController extends AuthController {
  @override
  Future<User?> build() async => null;
}

Future<void> _pumpLogin(WidgetTester tester) async {
  SharedPreferences.setMockInitialValues({});
  final prefs = await SharedPreferences.getInstance();

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        apiClientProvider.overrideWithValue(ApiClient()),
        authControllerProvider.overrideWith(_LoggedOutAuthController.new),
        sharedPreferencesProvider.overrideWithValue(prefs),
      ],
      child: Consumer(
        builder: (context, ref, _) => MaterialApp(
          // Follows the app's own language setting (Uzbek unless changed),
          // not the test harness's en-US platform locale.
          locale: ref.watch(localeControllerProvider),
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          supportedLocales: AppLocalizations.supportedLocales,
          home: const LoginScreen(),
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('language can be switched before signing in', (tester) async {
    await _pumpLogin(tester);

    expect(find.byType(LanguageSwitcher), findsOneWidget);
    expect(find.widgetWithText(FilledButton, 'Kirish'), findsOneWidget);

    await tester.tap(find.text('Русский'));
    await tester.pumpAndSettle();

    expect(find.widgetWithText(FilledButton, 'Kirish'), findsNothing);
    expect(find.widgetWithText(FilledButton, 'Войти'), findsOneWidget);
  });

  testWidgets('shows a validation error when submitting an empty form', (tester) async {
    await _pumpLogin(tester);

    await tester.tap(find.widgetWithText(FilledButton, 'Kirish'));
    await tester.pumpAndSettle();

    expect(find.text('Bu maydon to\'ldirilishi shart.'), findsWidgets);
  });
}
