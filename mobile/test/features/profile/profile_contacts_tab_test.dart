import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:urtg_mobile/core/models/employee.dart';
import 'package:urtg_mobile/features/profile/data/profile_repository.dart';
import 'package:urtg_mobile/features/profile/presentation/profile_contacts_tab.dart';
import 'package:urtg_mobile/features/profile/presentation/profile_controller.dart';
import 'package:urtg_mobile/l10n/app_localizations.dart';

/// Records every `PUT /profile` payload and, like the server, replaces the
/// contact list with whatever was sent (new ones get an id).
class _FakeProfileRepository implements ProfileRepository {
  _FakeProfileRepository(this._employee);

  Map<String, dynamic> _employee;
  final updates = <Map<String, dynamic>>[];

  @override
  Future<Employee> show() async => Employee.fromJson(_employee);

  @override
  Future<bool> update(Map<String, dynamic> fields) async {
    updates.add(fields);
    var nextId = 100;
    _employee = {
      ..._employee,
      'contacts': [
        for (final contact in fields['contacts'] as List<dynamic>) {...contact as Map<String, dynamic>, 'id': contact['id'] ?? nextId++},
      ],
    };

    return false;
  }

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

Map<String, dynamic> _employee() => {
      'id': 1,
      'employee_number': 'E-1',
      'first_name': 'Vali',
      'last_name': 'Aliyev',
      'full_name': 'Aliyev Vali',
      'status': 'active',
      'contacts': [
        {'id': 5, 'type': 'emergency', 'full_name': 'Aliyeva Nodira', 'relationship': 'Turmush o\'rtog\'i', 'phone': '+998901112233'},
        {'id': 6, 'type': 'bank', 'full_name': 'Aliyev Vali', 'bank_name': 'Xalq banki', 'bank_account_number': '20208000123456789'},
      ],
    };

Future<_FakeProfileRepository> _pump(WidgetTester tester) async {
  final repository = _FakeProfileRepository(_employee());

  await tester.pumpWidget(
    ProviderScope(
      overrides: [profileRepositoryProvider.overrideWithValue(repository)],
      child: MaterialApp(
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: AppLocalizations.supportedLocales,
        locale: const Locale('uz'),
        home: const Scaffold(body: ProfileContactsTab()),
      ),
    ),
  );
  await tester.pumpAndSettle();

  return repository;
}

void main() {
  testWidgets('lists the saved emergency contact and bank details', (tester) async {
    await _pump(tester);

    expect(find.text('Aliyeva Nodira'), findsOneWidget);
    expect(find.textContaining('Favqulodda aloqa'), findsOneWidget);
    expect(find.textContaining('Xalq banki'), findsOneWidget);
  });

  testWidgets('adding a contact sends every existing one back with its id, plus the new one', (tester) async {
    final repository = await _pump(tester);

    await tester.tap(find.text("Aloqa qo'shish"));
    await tester.pumpAndSettle();
    await tester.enterText(find.widgetWithText(TextFormField, 'F.I.Sh.'), 'Aliyev Anvar');
    await tester.enterText(find.widgetWithText(TextFormField, 'Telefon'), '+998907778899');
    await tester.tap(find.text('Saqlash'));
    await tester.pumpAndSettle();

    final sent = repository.updates.single['contacts'] as List<dynamic>;
    expect(sent.map((c) => (c as Map)['id']), [5, 6, null]);
    expect((sent.last as Map)['full_name'], 'Aliyev Anvar');
    expect(find.text('Aliyev Anvar'), findsOneWidget, reason: 'the list reloads with the new contact');
  });

  testWidgets('a bank record needs the bank and account number', (tester) async {
    final repository = await _pump(tester);

    await tester.tap(find.text("Aloqa qo'shish"));
    await tester.pumpAndSettle();
    await tester.tap(find.text("Bank ma'lumotlari").last);
    await tester.pumpAndSettle();
    await tester.enterText(find.widgetWithText(TextFormField, 'Hisob egasi'), 'Aliyev Vali');
    await tester.tap(find.text('Saqlash'));
    await tester.pumpAndSettle();

    expect(repository.updates, isEmpty);
    expect(find.text("Bu maydon to'ldirilishi shart."), findsNWidgets(2));
  });

  testWidgets('deleting a contact sends only the ones that remain', (tester) async {
    final repository = await _pump(tester);

    await tester.tap(find.byIcon(Icons.delete_outline).first);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Ha'));
    await tester.pumpAndSettle();

    final sent = repository.updates.single['contacts'] as List<dynamic>;
    expect(sent.map((c) => (c as Map)['id']), [6]);
    expect(find.text('Aliyeva Nodira'), findsNothing);
  });
}
