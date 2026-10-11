import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:urtg_mobile/core/models/user.dart';
import 'package:urtg_mobile/core/network/paginated.dart';
import 'package:urtg_mobile/features/auth/presentation/auth_controller.dart';
import 'package:urtg_mobile/features/tasks/data/tasks_repository.dart';
import 'package:urtg_mobile/features/tasks/domain/task.dart';
import 'package:urtg_mobile/features/tasks/domain/task_filters.dart';
import 'package:urtg_mobile/features/tasks/presentation/tasks_controller.dart';
import 'package:urtg_mobile/features/tasks/presentation/tasks_screen.dart';
import 'package:urtg_mobile/l10n/app_localizations.dart';

const _perPage = 10;

/// The real controllers and screen against an in-memory backend: filters
/// by status/category/search and pages the way `GET /tasks` does.
class _FakeTasksRepository implements TasksRepository {
  _FakeTasksRepository(this.tasks);

  final List<Task> tasks;

  Iterable<Task> _matching(TaskFilters filters, {required bool byStatus}) => tasks.where((task) =>
      (!byStatus || filters.status == null || task.status == filters.status) &&
      (filters.categoryId == null || task.category?.id == filters.categoryId) &&
      task.title.toLowerCase().contains(filters.search.trim().toLowerCase()));

  @override
  Future<Paginated<Task>> page(TaskFilters filters, {int page = 1}) async {
    final matching = _matching(filters, byStatus: true).toList();
    final lastPage = matching.isEmpty ? 1 : (matching.length / _perPage).ceil();

    return Paginated(
      items: matching.skip((page - 1) * _perPage).take(_perPage).toList(),
      currentPage: page,
      lastPage: lastPage,
      total: matching.length,
    );
  }

  @override
  Future<TaskSummary> summary(TaskFilters filters) async {
    final byStatus = <String, int>{};
    final matching = _matching(filters, byStatus: false);
    for (final task in matching) {
      byStatus[task.status] = (byStatus[task.status] ?? 0) + 1;
    }

    return TaskSummary(total: matching.length, byStatus: byStatus);
  }

  @override
  Future<List<TaskCategory>> categories() async => [_repairs, _reports];

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

class _FakeAuth extends AuthController {
  _FakeAuth(this._user);

  final User _user;

  @override
  Future<User?> build() async => _user;
}

final _repairs = TaskCategory(id: 1, name: "Ta'mirlash", color: '#D03B3B');
final _reports = TaskCategory(id: 2, name: 'Hisobot', color: '#1E88E5');

/// 25 tasks: every fifth is overdue, the rest in progress; odd ones are repairs.
List<Task> _tasks() => [
      for (var i = 1; i <= 25; i++)
        Task.fromJson({
          'id': i,
          'title': 'Topshiriq $i',
          'assignees': [],
          'status': i % 5 == 0 ? 'overdue' : 'in_progress',
          'progress': 0,
          'category': i.isOdd ? {'id': 1, 'name': "Ta'mirlash", 'color': '#D03B3B'} : {'id': 2, 'name': 'Hisobot', 'color': '#1E88E5'},
          'created_at': '2026-09-01T09:00:00Z',
        }),
    ];

User _user({List<String> permissions = const []}) => User.fromJson({
      'id': 1,
      'name': 'Aliyev Vali',
      'roles': ['employee'],
      'permissions': permissions,
    });

/// The status tabs scroll sideways, and only the chips near the viewport
/// are built — bring one into view before looking for it.
Future<Finder> _statusTab(WidgetTester tester, String label) async {
  final finder = find.text(label);
  await tester.scrollUntilVisible(
    finder,
    150,
    scrollable: find.ancestor(of: find.byType(ChoiceChip).first, matching: find.byType(Scrollable)).first,
  );
  // Built isn't the same as on screen: scroll it fully in before tapping.
  await tester.ensureVisible(finder);
  await tester.pumpAndSettle();

  return finder;
}

Future<void> _pump(WidgetTester tester, {List<Task>? tasks, User? user}) async {
  tester.view.physicalSize = const Size(390, 844);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.reset);

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        tasksRepositoryProvider.overrideWithValue(_FakeTasksRepository(tasks ?? _tasks())),
        authControllerProvider.overrideWith(() => _FakeAuth(user ?? _user())),
      ],
      child: MaterialApp(
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: AppLocalizations.supportedLocales,
        locale: const Locale('uz'),
        home: const TasksScreen(),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('lists the first page with each task\'s category, and counts every status tab', (tester) async {
    await _pump(tester);

    expect(find.text('Topshiriq 1'), findsOneWidget);
    expect(find.text('Topshiriq 11'), findsNothing, reason: 'only the first page of 10 is loaded');
    expect(find.text("Ta'mirlash"), findsWidgets);
    expect(find.text('Barchasi  25'), findsOneWidget);
    expect(await _statusTab(tester, "Muddati o'tgan  5"), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('a status tab narrows the list to that status', (tester) async {
    await _pump(tester);

    await tester.tap(await _statusTab(tester, "Muddati o'tgan  5"));
    await tester.pumpAndSettle();

    expect(find.text('Topshiriq 5'), findsOneWidget);
    expect(find.text('Topshiriq 1'), findsNothing);
  });

  testWidgets('scrolling to the end loads the next page', (tester) async {
    await _pump(tester);

    await tester.scrollUntilVisible(find.text('Topshiriq 25'), 300, scrollable: find.byType(Scrollable).last);
    await tester.pumpAndSettle();

    expect(find.text('Topshiriq 25'), findsOneWidget);
  });

  testWidgets('search narrows the list and the tab counts after a pause', (tester) async {
    await _pump(tester);

    await tester.enterText(find.byType(TextField), 'Topshiriq 2');
    await tester.pump(const Duration(milliseconds: 500));
    await tester.pumpAndSettle();

    // "Topshiriq 2" and "Topshiriq 20".."Topshiriq 25".
    expect(find.text('Barchasi  7'), findsOneWidget);
    expect(find.text('Topshiriq 1'), findsNothing);
  });

  testWidgets('says nothing matches, rather than "no tasks", when filters exclude everything', (tester) async {
    await _pump(tester);

    await tester.enterText(find.byType(TextField), 'mavjud emas');
    await tester.pump(const Duration(milliseconds: 500));
    await tester.pumpAndSettle();

    expect(find.text("Bu filtrlar bo'yicha topshiriq topilmadi"), findsOneWidget);
  });

  testWidgets('shows the plain empty state when the user has no tasks at all', (tester) async {
    await _pump(tester, tasks: []);

    expect(find.byIcon(Icons.checklist_outlined), findsOneWidget);
  });

  testWidgets('hides "assigned to me / created by me" from someone who cannot create tasks', (tester) async {
    await _pump(tester);

    expect(find.text('Barcha topshiriqlar'), findsNothing);
  });

  testWidgets('offers "assigned to me / created by me" to someone who can create tasks', (tester) async {
    await _pump(tester, user: _user(permissions: ['tasks.create']));

    expect(find.text('Barcha topshiriqlar'), findsOneWidget);
  });
}
