import 'package:flutter/widgets.dart';
import 'package:intl/intl.dart';

import '../../l10n/app_localizations.dart';

/// Dates the way people say them in the app's language: "16 okt",
/// "16 октября 2026", "Bugun, 09:30" — never a raw ISO string.
class AppDates {
  AppDates._();

  static String _locale(BuildContext context) => Localizations.localeOf(context).languageCode;

  static DateTime? parse(String? raw) => raw == null || raw.isEmpty ? null : DateTime.tryParse(raw)?.toLocal();

  static DateTime _day(DateTime date) => DateTime(date.year, date.month, date.day);

  /// Whole days from today to [date]; negative once it has passed.
  static int daysFromToday(DateTime date) => _day(date).difference(_day(DateTime.now())).inDays;

  /// "16 okt" this year, "16 okt 2025" otherwise.
  static String short(BuildContext context, DateTime date) {
    final pattern = date.year == DateTime.now().year ? 'd MMM' : 'd MMM y';
    return DateFormat(pattern, _locale(context)).format(date);
  }

  /// "16 oktabr 2026".
  static String long(BuildContext context, DateTime date) => DateFormat('d MMMM y', _locale(context)).format(date);

  static String time(BuildContext context, DateTime date) => DateFormat('HH:mm', _locale(context)).format(date);

  /// "Bugun, 09:30", "Kecha, 18:05", or "14 okt, 11:00".
  static String moment(BuildContext context, DateTime date) {
    final l10n = AppLocalizations.of(context);
    final days = daysFromToday(date);
    final day = switch (days) {
      0 => l10n.dateToday,
      -1 => l10n.dateYesterday,
      _ => short(context, date),
    };

    return '$day, ${time(context, date)}';
  }

  /// A deadline relative to today: "Bugun", "Ertaga", "3 kundan so'ng",
  /// "2 kun kechikdi"; past a week out, the plain date.
  static String due(BuildContext context, DateTime date) {
    final l10n = AppLocalizations.of(context);
    final days = daysFromToday(date);

    return switch (days) {
      0 => l10n.dateToday,
      1 => l10n.dateTomorrow,
      < 0 => l10n.dateDaysOverdue(-days),
      <= 7 => l10n.dateInDays(days),
      _ => short(context, date),
    };
  }

  /// Heading for a list grouped by day: "Bugun", "Kecha", or the date.
  static String dayHeading(BuildContext context, DateTime date) {
    final l10n = AppLocalizations.of(context);

    return switch (daysFromToday(date)) {
      0 => l10n.dateToday,
      -1 => l10n.dateYesterday,
      _ => long(context, date),
    };
  }
}
