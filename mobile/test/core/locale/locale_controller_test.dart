import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:urtg_mobile/core/locale/language_switcher.dart';
import 'package:urtg_mobile/core/locale/locale_controller.dart';
import 'package:urtg_mobile/l10n/app_localizations.dart';

Future<ProviderContainer> _container(Map<String, Object> stored) async {
  SharedPreferences.setMockInitialValues(stored);
  final prefs = await SharedPreferences.getInstance();
  final container = ProviderContainer(overrides: [sharedPreferencesProvider.overrideWithValue(prefs)]);
  addTearDown(container.dispose);

  return container;
}

void main() {
  test('defaults to Uzbek when nothing was chosen', () async {
    final container = await _container({});

    expect(container.read(localeControllerProvider), const Locale('uz'));
  });

  test('an unsupported stored language falls back to Uzbek', () async {
    final container = await _container({localePreferenceKey: 'en'});

    expect(container.read(localeControllerProvider), const Locale('uz'));
  });

  test('offers only Uzbek and Russian', () {
    expect(supportedAppLocales.map((locale) => locale.languageCode), ['uz', 'ru']);
    expect(AppLocalizations.supportedLocales.map((locale) => locale.languageCode).toSet(), {'uz', 'ru'});
  });

  test('a chosen language is remembered across launches', () async {
    final container = await _container({});
    await container.read(localeControllerProvider.notifier).setLocale(const Locale('ru'));

    expect(container.read(localeControllerProvider), const Locale('ru'));

    final relaunched = ProviderContainer(
      overrides: [sharedPreferencesProvider.overrideWithValue(await SharedPreferences.getInstance())],
    );
    addTearDown(relaunched.dispose);

    expect(relaunched.read(localeControllerProvider), const Locale('ru'));
  });

  testWidgets('tapping the switcher re-renders the app in the other language', (tester) async {
    SharedPreferences.setMockInitialValues({});
    final prefs = await SharedPreferences.getInstance();

    await tester.pumpWidget(
      ProviderScope(
        overrides: [sharedPreferencesProvider.overrideWithValue(prefs)],
        child: Consumer(
          builder: (context, ref, _) => MaterialApp(
            locale: ref.watch(localeControllerProvider),
            localizationsDelegates: AppLocalizations.localizationsDelegates,
            supportedLocales: AppLocalizations.supportedLocales,
            home: Scaffold(
              body: Column(
                children: [
                  const LanguageSwitcher(),
                  Builder(builder: (context) => Text(AppLocalizations.of(context).navTasks)),
                ],
              ),
            ),
          ),
        ),
      ),
    );

    expect(find.text('Topshiriqlar'), findsOneWidget);

    await tester.tap(find.text('Русский'));
    await tester.pumpAndSettle();

    expect(find.text('Задачи'), findsOneWidget);
    expect(find.text('Topshiriqlar'), findsNothing);
    expect(prefs.getString(localePreferenceKey), 'ru');
  });
}
