import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:urtg_mobile/features/issues/data/issues_repository.dart';
import 'package:urtg_mobile/features/issues/domain/issue.dart';
import 'package:urtg_mobile/features/issues/presentation/create_issue_screen.dart';
import 'package:urtg_mobile/features/issues/presentation/issues_controller.dart';
import 'package:urtg_mobile/l10n/app_localizations.dart';

class _FakeIssuesRepository implements IssuesRepository {
  _FakeIssuesRepository({required this.canAssignExecutors});

  final bool canAssignExecutors;

  @override
  Future<IssueOptions> options() async => IssueOptions.fromJson({
        'organizations': [
          {'id': 3, 'name': 'Urganch shahar gaz ta\'minoti boshqarmasi', 'code': 'UTG-001', 'type': 'subordinate'},
        ],
        'categories': [
          {'id': 1, 'name': 'Gaz sizib chiqishi', 'code': 'gas_leak'},
        ],
        'can_assign_executors': canAssignExecutors,
      });

  @override
  Future<List<IssueExecutorCandidate>> executorCandidates(int organizationId) async => [
        IssueExecutorCandidate(id: 9, fullName: 'Valiyev Ali'),
      ];

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

Future<void> _pump(WidgetTester tester, {required bool canAssignExecutors}) async {
  tester.view.physicalSize = const Size(390, 844);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.reset);

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        issuesRepositoryProvider.overrideWithValue(_FakeIssuesRepository(canAssignExecutors: canAssignExecutors)),
      ],
      child: MaterialApp(
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: AppLocalizations.supportedLocales,
        locale: const Locale('uz'),
        home: const CreateIssueScreen(),
      ),
    ),
  );
  // Map tiles never load in a test; a few frames settle the options and candidates.
  await tester.pump();
  await tester.pump();
  await tester.pump();
}

void main() {
  testWidgets('a regular reporter gets no executor picker, only a note that Technical Policy assigns them', (tester) async {
    await _pump(tester, canAssignExecutors: false);

    expect(find.text('Ijrochilarni Texnik siyosat xizmati tayinlaydi.'), findsOneWidget);
    expect(find.text('Ijrochilarni tanlang'), findsNothing);
  });

  testWidgets('Technical Policy gets the executor picker and no note', (tester) async {
    await _pump(tester, canAssignExecutors: true);

    expect(find.text('Ijrochilarni tanlang'), findsOneWidget);
    expect(find.text('Ijrochilarni Texnik siyosat xizmati tayinlaydi.'), findsNothing);
  });
}
