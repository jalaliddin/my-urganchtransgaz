import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:urtg_mobile/features/attendance/domain/attendance_record.dart';
import 'package:urtg_mobile/features/attendance/presentation/attendance_controller.dart';
import 'package:urtg_mobile/features/attendance/presentation/today_attendance_card.dart';
import 'package:urtg_mobile/l10n/app_localizations.dart';

class _NotCheckedInController extends MyTodayAttendanceController {
  @override
  Future<AttendanceRecord?> build() async => null;
}

class _CheckedInController extends MyTodayAttendanceController {
  @override
  Future<AttendanceRecord?> build() async => AttendanceRecord.fromJson({
        'id': 1,
        'employee_id': 1,
        'date': '2026-09-16',
        'check_in': '2026-09-16T09:00:00',
        'status': 'present',
      });
}

void main() {
  testWidgets('shows "not checked in yet" and an enabled check-in button before check-in', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [myTodayAttendanceProvider.overrideWith(_NotCheckedInController.new)],
        child: MaterialApp(
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          supportedLocales: AppLocalizations.supportedLocales,
          home: const Scaffold(body: TodayAttendanceCard()),
        ),
      ),
    );
    await tester.pumpAndSettle();

    final checkInButton = tester.widget<FilledButton>(find.byType(FilledButton));
    expect(checkInButton.onPressed, isNotNull);

    final checkOutButton = tester.widget<OutlinedButton>(find.byType(OutlinedButton));
    expect(checkOutButton.onPressed, isNull);
  });

  testWidgets('disables check-in and enables check-out once checked in', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [myTodayAttendanceProvider.overrideWith(_CheckedInController.new)],
        child: MaterialApp(
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          supportedLocales: AppLocalizations.supportedLocales,
          home: const Scaffold(body: TodayAttendanceCard()),
        ),
      ),
    );
    await tester.pumpAndSettle();

    final checkInButton = tester.widget<FilledButton>(find.byType(FilledButton));
    expect(checkInButton.onPressed, isNull);

    final checkOutButton = tester.widget<OutlinedButton>(find.byType(OutlinedButton));
    expect(checkOutButton.onPressed, isNotNull);
  });
}
