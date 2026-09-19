import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Overridden in `main()` with the instance awaited before `runApp` — the
/// chosen language is needed on the very first frame, so reading it must
/// not be asynchronous.
final sharedPreferencesProvider = Provider<SharedPreferences>((ref) {
  throw UnimplementedError(
    'sharedPreferencesProvider must be overridden in main()',
  );
});

/// The two languages the app is offered in. Uzbek is first because it is
/// the default: with nothing stored, the user sees Uzbek regardless of
/// what the phone's system language is.
const supportedAppLocales = [Locale('uz'), Locale('ru')];

const localePreferenceKey = 'app_locale';

/// Reads the stored language code without needing Riverpod — the
/// background notification isolate has no provider container.
Locale localeFromPreferences(SharedPreferences prefs) {
  final code = prefs.getString(localePreferenceKey);

  return supportedAppLocales.firstWhere(
    (locale) => locale.languageCode == code,
    orElse: () => supportedAppLocales.first,
  );
}

class LocaleController extends Notifier<Locale> {
  @override
  Locale build() => localeFromPreferences(ref.watch(sharedPreferencesProvider));

  Future<void> setLocale(Locale locale) async {
    state = locale;
    await ref
        .read(sharedPreferencesProvider)
        .setString(localePreferenceKey, locale.languageCode);
  }
}

final localeControllerProvider = NotifierProvider<LocaleController, Locale>(
  LocaleController.new,
);
