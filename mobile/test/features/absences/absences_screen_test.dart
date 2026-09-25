import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:urtg_mobile/features/absences/domain/employee_absence.dart';
import 'package:urtg_mobile/features/absences/presentation/absences_controller.dart';
import 'package:urtg_mobile/features/absences/presentation/absences_screen.dart';
import 'package:urtg_mobile/l10n/app_localizations.dart';

class _FakeAbsencesController extends AbsencesController {
  @override
  Future<List<EmployeeAbsence>> build() async => [
        EmployeeAbsence.fromJson({
          'id': 1,
          'type': 'annual_leave',
          'start_date': '2026-10-05',
          'end_date': '2026-10-18',
          'days': 14,
          'state': 'upcoming',
          'document_number': '145-K',
        }),
        EmployeeAbsence.fromJson({
          'id': 2,
          'type': 'business_trip',
          'start_date': '2026-09-22',
          'end_date': '2026-09-26',
          'days': 5,
          'state': 'cancelled',
          'destination': 'Toshkent',
          'cancellation_reason': 'Safar qoldirildi',
        }),
      ];
}

Widget _app() => ProviderScope(
      overrides: [
        absencesControllerProvider.overrideWith(_FakeAbsencesController.new),
        leaveBalanceProvider.overrideWith(
          (ref) async => LeaveBalance(year: 2026, entitlementDays: 21, usedDays: 14, remainingDays: 7),
        ),
      ],
      child: MaterialApp(
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: AppLocalizations.supportedLocales,
        locale: const Locale('uz'),
        home: const AbsencesScreen(),
      ),
    );

void main() {
  testWidgets('shows the leave balance and each absence with its period and tabel code', (tester) async {
    await tester.pumpWidget(_app());
    await tester.pumpAndSettle();

    expect(find.text("2026-yil yillik ta'tili"), findsOneWidget);
    expect(find.text('7'), findsOneWidget);
    expect(find.text("Yillik mehnat ta'tili"), findsOneWidget);
    expect(find.textContaining('05.10.2026 — 18.10.2026, 14 kun'), findsOneWidget);
    expect(find.text('T'), findsOneWidget);
    expect(find.text('X'), findsOneWidget);
  });

  testWidgets('shows why a cancelled absence was cancelled', (tester) async {
    await tester.pumpWidget(_app());
    await tester.pumpAndSettle();

    expect(find.textContaining('Bekor qilindi: Safar qoldirildi'), findsOneWidget);
    expect(find.text('Bekor qilingan'), findsOneWidget);
  });
}
