import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
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

void main() {
  testWidgets('shows a validation error when submitting an empty form', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          apiClientProvider.overrideWithValue(ApiClient()),
          authControllerProvider.overrideWith(_LoggedOutAuthController.new),
        ],
        child: MaterialApp(
          // The test harness's platform locale is en-US by default, not
          // the app's uz default — force it so the assertions below (in
          // Uzbek) match what actually renders.
          locale: const Locale('uz'),
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          supportedLocales: AppLocalizations.supportedLocales,
          home: const LoginScreen(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.widgetWithText(FilledButton, 'Kirish'));
    await tester.pumpAndSettle();

    expect(find.text('Bu maydon to\'ldirilishi shart.'), findsWidgets);
  });
}
