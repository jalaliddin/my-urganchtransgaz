import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:urtg_mobile/core/models/user.dart';
import 'package:urtg_mobile/features/announcements/domain/announcement.dart';
import 'package:urtg_mobile/features/announcements/presentation/announcements_controller.dart';
import 'package:urtg_mobile/features/auth/presentation/auth_controller.dart';
import 'package:urtg_mobile/features/dashboard/presentation/dashboard_providers.dart';
import 'package:urtg_mobile/features/dashboard/presentation/dashboard_screen.dart';
import 'package:urtg_mobile/features/dashboard/presentation/dashboard_sections.dart';
import 'package:urtg_mobile/features/exams/domain/exam.dart';
import 'package:urtg_mobile/features/exams/presentation/exams_controller.dart';
import 'package:urtg_mobile/features/notifications/presentation/notifications_controller.dart';
import 'package:urtg_mobile/features/tasks/domain/task.dart';
import 'package:urtg_mobile/features/tasks/presentation/tasks_controller.dart';
import 'package:urtg_mobile/l10n/app_localizations.dart';

class _FakeAuth extends AuthController {
  _FakeAuth(this._user);

  final User? _user;

  @override
  Future<User?> build() async => _user;
}

class _FakeTasks extends TasksController {
  _FakeTasks(this._tasks);

  final List<Task> _tasks;

  @override
  Future<List<Task>> build() async => _tasks;
}

class _FakeExams extends ExamsController {
  @override
  Future<List<Exam>> build() async => [
        Exam.fromJson({
          'id': 1,
          'title': 'Xavfsizlik imtihoni',
          'duration_minutes': 30,
          'passing_score': 70,
          'attempts_allowed': 2,
          'status': 'active',
        }),
      ];
}

class _FakeAnnouncements extends AnnouncementsController {
  @override
  Future<List<Announcement>> build() async => [
        Announcement.fromJson({
          'id': 5,
          'title': "Yangi ish tartibi",
          'content': "Dushanbadan boshlab",
          'priority': 'normal',
          'status': 'published',
          'created_at': '2026-09-18T09:00:00Z',
        }),
      ];
}

User _user({List<String> permissions = const ['issues.view', 'issues.create']}) => User.fromJson({
      'id': 1,
      'name': 'Aliyev Vali',
      'roles': ['employee'],
      'permissions': permissions,
      'employee': {
        'id': 1,
        'employee_number': 'E-1',
        'first_name': 'Vali',
        'last_name': 'Aliyev',
        'full_name': 'Aliyev Vali',
        'status': 'active',
      },
    });

List<Task> _tasks() => [
      Task.fromJson({
        'id': 11,
        'title': 'Hisobotni yuborish',
        'assignees': [],
        'status': 'in_progress',
        'progress': 60,
        'due_date': DateTime.now().add(const Duration(days: 1)).toIso8601String(),
        'created_at': '2026-09-01T09:00:00Z',
      }),
      Task.fromJson({
        'id': 12,
        'title': "Kechikkan topshiriq",
        'assignees': [],
        'status': 'overdue',
        'progress': 10,
        'due_date': DateTime.now().subtract(const Duration(days: 2)).toIso8601String(),
        'created_at': '2026-09-01T09:00:00Z',
      }),
    ];

Future<void> _pump(
  WidgetTester tester, {
  required Size size,
  User? user,
  List<Task>? tasks,
  Locale locale = const Locale('uz'),
}) async {
  tester.view.physicalSize = size;
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.reset);

  final router = GoRouter(
    routes: [
      GoRoute(path: '/', builder: (context, state) => const DashboardScreen()),
      GoRoute(path: '/issues/new', builder: (context, state) => const Scaffold(body: Text('create-issue-page'))),
      GoRoute(path: '/tasks', builder: (context, state) => const Scaffold(body: Text('tasks-page'))),
      GoRoute(path: '/tasks/:id', builder: (context, state) => const Scaffold(body: Text('task-detail-page'))),
    ],
  );

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        authControllerProvider.overrideWith(() => _FakeAuth(user ?? _user())),
        tasksControllerProvider.overrideWith(() => _FakeTasks(tasks ?? _tasks())),
        examsControllerProvider.overrideWith(_FakeExams.new),
        announcementsControllerProvider.overrideWith(_FakeAnnouncements.new),
        unreadNotificationsCountProvider.overrideWith((ref) async => 4),
        openIssuesCountProvider.overrideWith((ref) async => 3),
      ],
      child: MaterialApp.router(
        locale: locale,
        theme: ThemeData(useMaterial3: true),
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: AppLocalizations.supportedLocales,
        routerConfig: router,
      ),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('phone: greeting, counts, the report action, due tasks and announcements', (tester) async {
    await _pump(tester, size: const Size(390, 844));

    expect(find.text('Vali'), findsOneWidget);
    // Two actionable tasks, three open issues (fake), one exam to take.
    expect(find.text('2'), findsOneWidget);
    expect(find.text('3'), findsOneWidget);
    expect(find.text('1'), findsOneWidget);
    expect(find.text('Muammo qayd etish'), findsOneWidget);
    expect(find.text('Hisobotni yuborish'), findsOneWidget);
    expect(find.text("Yangi ish tartibi"), findsOneWidget);
    expect(find.text('4'), findsOneWidget, reason: 'unread badge on the bell');
    expect(tester.takeException(), isNull);
  });

  testWidgets('phone: quick actions come above the task list in one column', (tester) async {
    await _pump(tester, size: const Size(390, 844));

    final quick = tester.getTopLeft(find.byType(QuickActions));
    final due = tester.getTopLeft(find.byType(DueSoonSection));

    expect(quick.dy, lessThan(due.dy));
    expect(quick.dx, due.dx);
  });

  testWidgets('wide window: two columns, nothing overflows', (tester) async {
    await _pump(tester, size: const Size(1200, 800));

    final quick = tester.getTopLeft(find.byType(QuickActions));
    final due = tester.getTopLeft(find.byType(DueSoonSection));

    expect(due.dx, lessThan(quick.dx), reason: 'tasks in the wide column, actions beside them');
    expect(tester.takeException(), isNull);
  });

  testWidgets('wide window: content is capped, not stretched edge to edge', (tester) async {
    await _pump(tester, size: const Size(1800, 900));

    final right = tester.getTopRight(find.byType(AnnouncementsSection)).dx;
    final left = tester.getTopLeft(find.byType(DueSoonSection)).dx;

    expect(right - left, lessThanOrEqualTo(1100));
  });

  testWidgets('very narrow phone does not overflow', (tester) async {
    await _pump(tester, size: const Size(320, 640));

    expect(tester.takeException(), isNull);
  });

  testWidgets('the report action opens the new-issue screen', (tester) async {
    await _pump(tester, size: const Size(390, 844));

    await tester.tap(find.text('Muammo qayd etish'));
    await tester.pumpAndSettle();

    expect(find.text('create-issue-page'), findsOneWidget);
  });

  testWidgets('without issue permissions there is no report action or issues count', (tester) async {
    await _pump(tester, size: const Size(390, 844), user: _user(permissions: const []));

    expect(find.text('Muammo qayd etish'), findsNothing);
    expect(find.text('Ochiq muammolar'), findsNothing);
  });

  testWidgets('a task card opens that task', (tester) async {
    await _pump(tester, size: const Size(390, 844));

    await tester.tap(find.text('Hisobotni yuborish'));
    await tester.pumpAndSettle();

    expect(find.text('task-detail-page'), findsOneWidget);
  });

  testWidgets('says so when nothing is due', (tester) async {
    await _pump(tester, size: const Size(390, 844), tasks: const []);

    expect(find.text("Hammasi joyida — shoshilinch topshiriq yo'q"), findsOneWidget);
  });

  testWidgets('renders in Russian', (tester) async {
    await _pump(tester, size: const Size(390, 844), locale: const Locale('ru'));

    expect(find.text('Сообщить о проблеме'), findsOneWidget);
    expect(find.text('Muammo qayd etish'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('there is no attendance anywhere on the dashboard', (tester) async {
    await _pump(tester, size: const Size(390, 844));

    expect(find.byIcon(Icons.fingerprint), findsNothing);
    expect(find.textContaining('Davomat'), findsNothing);
  });
}
