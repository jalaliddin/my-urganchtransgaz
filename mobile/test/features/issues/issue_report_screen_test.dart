import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:urtg_mobile/core/models/user.dart';
import 'package:urtg_mobile/features/auth/presentation/auth_controller.dart';
import 'package:urtg_mobile/features/issues/data/issues_repository.dart';
import 'package:urtg_mobile/features/issues/domain/issue.dart';
import 'package:urtg_mobile/features/issues/domain/issue_report.dart';
import 'package:urtg_mobile/features/issues/presentation/issue_report_controller.dart';
import 'package:urtg_mobile/features/issues/presentation/issue_report_screen.dart';
import 'package:urtg_mobile/features/issues/presentation/issues_controller.dart';
import 'package:urtg_mobile/features/issues/presentation/issues_screen.dart';
import 'package:urtg_mobile/l10n/app_localizations.dart';

class _FakeIssuesRepository implements IssuesRepository {
  _FakeIssuesRepository(this._report);

  final IssueReport _report;
  final requestedFrom = <String?>[];

  @override
  Future<IssueReport> report({String? dateFrom}) async {
    requestedFrom.add(dateFrom);

    return _report;
  }

  @override
  Future<List<Issue>> list({bool openOnly = true}) async => [];

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

class _FakeAuth extends AuthController {
  _FakeAuth(this._user);

  final User _user;

  @override
  Future<User?> build() async => _user;
}

/// The shape `GET /issues/report` returns under `data`.
IssueReport _report({int total = 10}) => IssueReport.fromJson({
      'totals': {
        'total': total,
        'open_count': 4,
        'resolved_count': 6,
        'stale_open_count': 1,
        'resolution_rate': 60,
        'avg_resolution_hours': 60.0,
      },
      'by_organization': [
        {
          'organization_name': 'Urganch tarmog\'i',
          'total': 7,
          'open_count': 3,
          'resolved_count': 4,
          'stale_open_count': 1,
          'resolution_rate': 57,
          'avg_resolution_hours': 36.0,
        },
      ],
      'by_category': [
        {'category_name': 'Gaz sizishi', 'total': 6, 'open_count': 2, 'resolved_count': 4},
        {'category_name': null, 'total': 4, 'open_count': 2, 'resolved_count': 2},
      ],
      'monthly': [
        {'month': '2026-08', 'created': 3, 'resolved': 2},
        {'month': '2026-09', 'created': 7, 'resolved': 4},
      ],
      'points': [
        {'id': 1, 'title': 'Quvur', 'status': 'open', 'latitude': 41.55, 'longitude': 60.63, 'open_days': 12},
        {'id': 2, 'title': 'Klapan', 'status': 'resolved', 'latitude': 41.38, 'longitude': 60.36, 'open_days': null},
      ],
      'points_truncated': true,
      'stale_after_days': 7,
    });

User _user({List<String> permissions = const []}) => User.fromJson({
      'id': 1,
      'name': 'Aliyev Vali',
      'roles': ['department-manager'],
      'permissions': permissions,
    });

Future<_FakeIssuesRepository> _pump(
  WidgetTester tester, {
  required Widget home,
  IssueReport? report,
  User? user,
  Size size = const Size(390, 844),
}) async {
  tester.view.physicalSize = size;
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.reset);

  final repository = _FakeIssuesRepository(report ?? _report());
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        issuesRepositoryProvider.overrideWithValue(repository),
        authControllerProvider.overrideWith(() => _FakeAuth(user ?? _user())),
      ],
      child: MaterialApp(
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: AppLocalizations.supportedLocales,
        locale: const Locale('uz'),
        home: home,
      ),
    ),
  );
  // Map tiles never load in a test; a couple of frames is all the report needs.
  await tester.pump();
  await tester.pump();

  return repository;
}

void main() {
  test('periods start where the web presets do', () {
    final now = DateTime(2026, 10, 11);

    expect(issueReportDateFrom(IssueReportPeriod.thisMonth, now), '2026-10-01');
    expect(issueReportDateFrom(IssueReportPeriod.last3Months, now), '2026-07-13');
    expect(issueReportDateFrom(IssueReportPeriod.last12Months, now), '2025-10-11');
    expect(issueReportDateFrom(IssueReportPeriod.allTime, now), isNull);
  });

  testWidgets('shows the totals, breakdowns and monthly table on a phone', (tester) async {
    await _pump(tester, home: const IssueReportScreen());

    expect(find.text('Jami muammolar'), findsWidgets);
    expect(find.text('60%'), findsOneWidget);
    expect(find.text('2.5 kun'), findsOneWidget, reason: '60 hours is past two days, so it reads in days');
    expect(find.text('7 kundan ortiq ochiq'), findsOneWidget);
    expect(find.textContaining("eng so'nggi 2 ta"), findsOneWidget);

    // The period chips scroll too; the report is the vertical list.
    final report = find.byWidgetPredicate((widget) => widget is Scrollable && widget.axisDirection == AxisDirection.down);
    await tester.scrollUntilVisible(find.text('Kategoriyasiz'), 200, scrollable: report);
    expect(find.text('Gaz sizishi'), findsOneWidget);

    await tester.scrollUntilVisible(find.text('2026-09'), 200, scrollable: report);
    expect(find.text("Urganch tarmog'i"), findsOneWidget);
    expect(find.textContaining("O'rtacha bartaraf etish vaqti: 36 soat"), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('a period chip refetches from that period\'s start', (tester) async {
    final repository = await _pump(tester, home: const IssueReportScreen());
    expect(repository.requestedFrom, hasLength(1));
    expect(repository.requestedFrom.single, isNotNull, reason: 'the default is the last 12 months');

    // The chips scroll sideways; bring the last one in before tapping it.
    await tester.ensureVisible(find.text('Hammasi'));
    await tester.pump();
    await tester.tap(find.text('Hammasi'));
    await tester.pump();
    await tester.pump();

    expect(repository.requestedFrom.last, isNull);
  });

  testWidgets('puts the category and organization breakdowns side by side on a wide window', (tester) async {
    await _pump(tester, home: const IssueReportScreen(), size: const Size(1280, 1600));

    final categories = tester.getTopLeft(find.text('Kategoriyalar kesimida'));
    final organizations = tester.getTopLeft(find.text('Tashkilotlar kesimida'));
    expect(categories.dy, organizations.dy);
    expect(categories.dx, lessThan(organizations.dx));
    expect(tester.takeException(), isNull);
  });

  testWidgets('says so when the period has no issues', (tester) async {
    await _pump(tester, home: const IssueReportScreen(), report: _report(total: 0));

    expect(find.text("Tanlangan davrda muammolar yo'q"), findsOneWidget);
  });

  testWidgets('the issues screen links to the report only for users who may see it', (tester) async {
    await _pump(tester, home: const IssuesScreen());
    expect(find.byTooltip('Muammolar hisoboti'), findsNothing);
  });

  testWidgets('the issues screen links to the report for leaders', (tester) async {
    await _pump(tester, home: const IssuesScreen(), user: _user(permissions: ['issues.report']));
    expect(find.byTooltip('Muammolar hisoboti'), findsOneWidget);
  });
}
