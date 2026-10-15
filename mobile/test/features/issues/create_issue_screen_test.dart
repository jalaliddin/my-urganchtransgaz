import 'dart:convert';
import 'dart:io';

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

Future<void> _pump(WidgetTester tester, {required bool canAssignExecutors, Future<String?> Function()? capture}) async {
  tester.view.physicalSize = const Size(390, 844);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.reset);

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        issuesRepositoryProvider.overrideWithValue(_FakeIssuesRepository(canAssignExecutors: canAssignExecutors)),
        if (capture != null) issuePhotoCaptureProvider.overrideWithValue(capture),
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

  testWidgets('photos come from the camera, show as thumbnails, and stop at five', (tester) async {
    // A 1×1 PNG standing in for what the camera wrote.
    final photo = File('${Directory.systemTemp.createTempSync('issue_photo').path}/shot.png')
      ..writeAsBytesSync(base64Decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
    var cameraOpened = 0;
    await _pump(tester, canAssignExecutors: false, capture: () async {
      cameraOpened++;
      return photo.path;
    });

    final takePhoto = find.text('Rasm olish');
    expect(find.text("Ixtiyoriy · 5 tagacha, faqat kamera orqali"), findsOneWidget);

    await tester.ensureVisible(takePhoto);
    await tester.tap(takePhoto);
    await tester.pump();
    expect(cameraOpened, 1);
    expect(find.byType(Image), findsWidgets);
    expect(find.byTooltip('Rasmni olib tashlash'), findsOneWidget);

    await tester.tap(find.byTooltip('Rasmni olib tashlash'));
    await tester.pump();
    expect(find.byTooltip('Rasmni olib tashlash'), findsNothing);

    for (var i = 0; i < 5; i++) {
      await tester.ensureVisible(takePhoto);
      await tester.tap(takePhoto);
      await tester.pump();
    }
    expect(find.byTooltip('Rasmni olib tashlash'), findsNWidgets(5));
    expect(takePhoto, findsNothing, reason: 'the server takes at most five');
  });

  testWidgets('backing out of the camera adds nothing', (tester) async {
    await _pump(tester, canAssignExecutors: false, capture: () async => null);

    await tester.ensureVisible(find.text('Rasm olish'));
    await tester.tap(find.text('Rasm olish'));
    await tester.pump();

    expect(find.byTooltip('Rasmni olib tashlash'), findsNothing);
  });
}
