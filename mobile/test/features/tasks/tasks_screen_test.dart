import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:urtg_mobile/features/tasks/domain/task.dart';
import 'package:urtg_mobile/features/tasks/presentation/tasks_controller.dart';
import 'package:urtg_mobile/features/tasks/presentation/tasks_screen.dart';
import 'package:urtg_mobile/l10n/app_localizations.dart';

class _FakeTasksController extends TasksController {
  @override
  Future<List<Task>> build() async => [
        Task.fromJson({
          'id': 1,
          'title': 'Hisobotni yuborish',
          'assignees': [],
          'priority': 'high',
          'status': 'in_progress',
          'progress': 60,
          'created_at': '2026-09-01T09:00:00Z',
        }),
        Task.fromJson({
          'id': 2,
          'title': 'Yig\'ilishga tayyorgarlik',
          'assignees': [],
          'priority': 'normal',
          'status': 'new',
          'progress': 0,
          'created_at': '2026-09-01T09:00:00Z',
        }),
      ];
}

class _FakeEmptyTasksController extends TasksController {
  @override
  Future<List<Task>> build() async => [];
}

void main() {
  testWidgets('renders every task title returned by the controller', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [tasksControllerProvider.overrideWith(_FakeTasksController.new)],
        child: MaterialApp(
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          supportedLocales: AppLocalizations.supportedLocales,
          home: const TasksScreen(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Hisobotni yuborish'), findsOneWidget);
    expect(find.text('Yig\'ilishga tayyorgarlik'), findsOneWidget);
  });

  testWidgets('shows the empty state when there are no tasks', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [tasksControllerProvider.overrideWith(_FakeEmptyTasksController.new)],
        child: MaterialApp(
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          supportedLocales: AppLocalizations.supportedLocales,
          home: const TasksScreen(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.byIcon(Icons.checklist_outlined), findsOneWidget);
  });
}
