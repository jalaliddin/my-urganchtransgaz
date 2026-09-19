import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/auth/presentation/auth_controller.dart';
import '../../l10n/app_localizations.dart';
import '../layout/breakpoints.dart';

/// One destination, described once and rendered by whichever navigation
/// widget the current width calls for (bar or rail).
class AppDestination {
  const AppDestination({
    required this.path,
    required this.label,
    required this.icon,
    required this.selectedIcon,
  });

  final String path;
  final String label;
  final IconData icon;
  final IconData selectedIcon;
}

/// The four destinations people use every day get their own place;
/// everything else (Profile, Documents, KPI, Exams, Announcements,
/// Notifications, language) is one tap away behind "More". Issues appears
/// only for users who may use it.
List<AppDestination> buildDestinations(
  AppLocalizations l10n, {
  required bool showIssues,
}) => [
  AppDestination(
    path: '/',
    label: l10n.navDashboard,
    icon: Icons.home_outlined,
    selectedIcon: Icons.home,
  ),
  AppDestination(
    path: '/tasks',
    label: l10n.navTasks,
    icon: Icons.checklist_outlined,
    selectedIcon: Icons.checklist,
  ),
  if (showIssues)
    AppDestination(
      path: '/issues',
      label: l10n.issuesTitle,
      icon: Icons.map_outlined,
      selectedIcon: Icons.map,
    ),
  AppDestination(
    path: '/more',
    label: l10n.navMore,
    icon: Icons.menu,
    selectedIcon: Icons.menu_open,
  ),
];

int destinationIndexFor(List<AppDestination> destinations, String location) {
  final index = destinations.indexWhere(
    (destination) =>
        location == destination.path ||
        (destination.path != '/' && location.startsWith(destination.path)),
  );

  return index == -1 ? 0 : index;
}

/// Bottom [NavigationBar] on a phone-width window, a side [NavigationRail]
/// once there is room for one — chosen from the window width, with the
/// same destinations either way.
class AppShell extends ConsumerWidget {
  const AppShell({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final user = ref.watch(authControllerProvider).value;
    final destinations = buildDestinations(
      l10n,
      showIssues:
          user != null &&
          (user.can('issues.view') || user.can('issues.create')),
    );
    final currentIndex = destinationIndexFor(
      destinations,
      GoRouterState.of(context).uri.toString(),
    );
    final windowSize = windowSizeOf(context);

    void select(int index) => context.go(destinations[index].path);

    if (windowSize == WindowSize.compact) {
      return Scaffold(
        body: child,
        bottomNavigationBar: NavigationBar(
          selectedIndex: currentIndex,
          onDestinationSelected: select,
          destinations: [
            for (final destination in destinations)
              NavigationDestination(
                icon: Icon(destination.icon),
                selectedIcon: Icon(destination.selectedIcon),
                label: destination.label,
              ),
          ],
        ),
      );
    }

    return Scaffold(
      body: Row(
        children: [
          NavigationRail(
            selectedIndex: currentIndex,
            onDestinationSelected: select,
            extended: windowSize == WindowSize.expanded,
            labelType: windowSize == WindowSize.expanded
                ? NavigationRailLabelType.none
                : NavigationRailLabelType.all,
            destinations: [
              for (final destination in destinations)
                NavigationRailDestination(
                  icon: Icon(destination.icon),
                  selectedIcon: Icon(destination.selectedIcon),
                  label: Text(destination.label),
                ),
            ],
          ),
          const VerticalDivider(width: 1),
          Expanded(child: child),
        ],
      ),
    );
  }
}
