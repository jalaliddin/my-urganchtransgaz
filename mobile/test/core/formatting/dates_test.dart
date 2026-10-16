import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:urtg_mobile/core/formatting/dates.dart';
import 'package:urtg_mobile/l10n/app_localizations.dart';

/// Renders [build]'s text under real Uzbek localizations.
Future<String> _render(WidgetTester tester, String Function(BuildContext context) build) async {
  late String result;
  await tester.pumpWidget(
    MaterialApp(
      locale: const Locale('uz'),
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: AppLocalizations.supportedLocales,
      home: Builder(builder: (context) {
        result = build(context);
        return const SizedBox();
      }),
    ),
  );

  return result;
}

DateTime _daysFromNow(int days, {int hour = 12}) {
  final now = DateTime.now();
  return DateTime(now.year, now.month, now.day + days, hour);
}

void main() {
  testWidgets('a deadline reads relative to today', (tester) async {
    expect(await _render(tester, (c) => AppDates.due(c, _daysFromNow(0))), 'Bugun');
    expect(await _render(tester, (c) => AppDates.due(c, _daysFromNow(1))), 'Ertaga');
    expect(await _render(tester, (c) => AppDates.due(c, _daysFromNow(4))), "4 kundan so'ng");
    expect(await _render(tester, (c) => AppDates.due(c, _daysFromNow(-2))), '2 kun kechikdi');
  });

  testWidgets('a deadline more than a week out shows its date instead', (tester) async {
    final date = _daysFromNow(30);

    expect(await _render(tester, (c) => AppDates.due(c, date)), await _render(tester, (c) => AppDates.short(c, date)));
  });

  testWidgets('counts whole calendar days, whatever the time of day', (tester) async {
    // Late tonight is still today; just after midnight is already tomorrow.
    expect(AppDates.daysFromToday(_daysFromNow(0, hour: 23)), 0);
    expect(AppDates.daysFromToday(_daysFromNow(1, hour: 0)), 1);
  });

  testWidgets('a moment today or yesterday names the day before the time', (tester) async {
    expect(await _render(tester, (c) => AppDates.moment(c, _daysFromNow(0, hour: 9))), 'Bugun, 09:00');
    expect(await _render(tester, (c) => AppDates.moment(c, _daysFromNow(-1, hour: 18))), 'Kecha, 18:00');
  });

  test('parse reads ISO strings and treats missing values as absent', () {
    expect(AppDates.parse(null), isNull);
    expect(AppDates.parse(''), isNull);
    expect(AppDates.parse('not a date'), isNull);
    expect(AppDates.parse('2026-10-16')!.day, 16);
  });
}
