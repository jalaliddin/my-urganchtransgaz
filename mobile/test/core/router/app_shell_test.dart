import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:urtg_mobile/core/models/user.dart';
import 'package:urtg_mobile/core/router/app_shell.dart';
import 'package:urtg_mobile/features/auth/presentation/auth_controller.dart';
import 'package:urtg_mobile/l10n/app_localizations.dart';

class _FakeAuth extends AuthController {
  _FakeAuth(this._permissions);

  final List<String> _permissions;

  @override
  Future<User?> build() async =>
      User.fromJson({'id': 1, 'name': 'Vali', 'roles': [], 'permissions': _permissions});
}

Future<void> _pump(WidgetTester tester, {required Size size, List<String> permissions = const ['issues.create']}) async {
  tester.view.physicalSize = size;
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.reset);

  Widget page(String name) => Center(child: Text('page-$name'));

  final router = GoRouter(
    routes: [
      ShellRoute(
        builder: (context, state, child) => AppShell(child: child),
        routes: [
          GoRoute(path: '/', builder: (context, state) => page('home')),
          GoRoute(path: '/tasks', builder: (context, state) => page('tasks')),
          GoRoute(path: '/issues', builder: (context, state) => page('issues')),
          GoRoute(path: '/more', builder: (context, state) => page('more')),
        ],
      ),
    ],
  );

  await tester.pumpWidget(
    ProviderScope(
      overrides: [authControllerProvider.overrideWith(() => _FakeAuth(permissions))],
      child: MaterialApp.router(
        locale: const Locale('uz'),
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: AppLocalizations.supportedLocales,
        routerConfig: router,
      ),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('phone width: bottom bar with Home, Tasks, Issues, More — and no attendance', (tester) async {
    await _pump(tester, size: const Size(390, 844));

    expect(find.byType(NavigationBar), findsOneWidget);
    expect(find.byType(NavigationRail), findsNothing);

    final labels = tester.widgetList<NavigationDestination>(find.byType(NavigationDestination)).map((d) => d.label);

    expect(labels, ['Bosh sahifa', 'Topshiriqlar', 'Muammolar', 'Yana']);
    expect(find.byIcon(Icons.fingerprint_outlined), findsNothing);
  });

  testWidgets('tablet width and up: side rail instead of the bottom bar', (tester) async {
    await _pump(tester, size: const Size(700, 900));

    expect(find.byType(NavigationRail), findsOneWidget);
    expect(find.byType(NavigationBar), findsNothing);
    expect(tester.widget<NavigationRail>(find.byType(NavigationRail)).extended, isFalse);

    await _pump(tester, size: const Size(1200, 900));

    expect(tester.widget<NavigationRail>(find.byType(NavigationRail)).extended, isTrue);
  });

  testWidgets('the same destinations, in order, whichever navigation is shown', (tester) async {
    await _pump(tester, size: const Size(1200, 900));

    final labels = tester.widget<NavigationRail>(find.byType(NavigationRail)).destinations.map((d) => (d.label as Text).data);

    expect(labels, ['Bosh sahifa', 'Topshiriqlar', 'Muammolar', 'Yana']);
  });

  testWidgets('a user with no issue permissions gets no Issues tab', (tester) async {
    await _pump(tester, size: const Size(390, 844), permissions: const []);

    final labels = tester.widgetList<NavigationDestination>(find.byType(NavigationDestination)).map((d) => d.label);

    expect(labels, ['Bosh sahifa', 'Topshiriqlar', 'Yana']);
  });

  testWidgets('selecting a destination navigates and marks it selected', (tester) async {
    await _pump(tester, size: const Size(390, 844));

    await tester.tap(find.text('Muammolar'));
    await tester.pumpAndSettle();

    expect(find.text('page-issues'), findsOneWidget);
    expect(tester.widget<NavigationBar>(find.byType(NavigationBar)).selectedIndex, 2);
  });

  testWidgets('the selection survives a resize between bar and rail', (tester) async {
    await _pump(tester, size: const Size(390, 844));
    await tester.tap(find.text('Topshiriqlar'));
    await tester.pumpAndSettle();

    tester.view.physicalSize = const Size(1200, 900);
    await tester.pumpAndSettle();

    expect(find.text('page-tasks'), findsOneWidget);
    expect(tester.widget<NavigationRail>(find.byType(NavigationRail)).selectedIndex, 1);
  });
}
