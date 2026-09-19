import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'locale_controller.dart';

/// Language names are always shown in their own language — someone who
/// picked the wrong language by accident has to be able to read the way
/// back, so these are deliberately not localized strings.
const _languageNames = {'uz': "O'zbekcha", 'ru': 'Русский'};

/// Uzbek | Russian, one tap, applied immediately and remembered.
class LanguageSwitcher extends ConsumerWidget {
  const LanguageSwitcher({super.key, this.expand = false});

  final bool expand;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final current = ref.watch(localeControllerProvider);

    final button = SegmentedButton<String>(
      showSelectedIcon: false,
      segments: [
        for (final locale in supportedAppLocales)
          ButtonSegment(
            value: locale.languageCode,
            label: Text(_languageNames[locale.languageCode]!),
          ),
      ],
      selected: {current.languageCode},
      onSelectionChanged: (selection) => ref
          .read(localeControllerProvider.notifier)
          .setLocale(Locale(selection.first)),
    );

    return expand ? SizedBox(width: double.infinity, child: button) : button;
  }
}
