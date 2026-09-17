import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../l10n/app_localizations.dart';

/// The four most-used destinations get their own tab; everything else
/// (Profile, Documents, KPI, Exams, Leave Requests, Announcements,
/// Notifications) lives one tap away behind "More" — keeping the bottom
/// bar itself simple is a big part of what "easy to use" means here.
class AppShell extends StatelessWidget {
  const AppShell({super.key, required this.child});

  final Widget child;

  static const _tabs = ['/', '/attendance', '/tasks', '/more'];

  int _indexFor(String location) {
    final index = _tabs.indexWhere((tab) => location == tab || (tab != '/' && location.startsWith(tab)));
    return index == -1 ? 0 : index;
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final location = GoRouterState.of(context).uri.toString();
    final currentIndex = _indexFor(location);

    return Scaffold(
      body: child,
      bottomNavigationBar: NavigationBar(
        selectedIndex: currentIndex,
        onDestinationSelected: (index) => context.go(_tabs[index]),
        destinations: [
          NavigationDestination(icon: const Icon(Icons.home_outlined), selectedIcon: const Icon(Icons.home), label: l10n.navDashboard),
          NavigationDestination(icon: const Icon(Icons.fingerprint_outlined), selectedIcon: const Icon(Icons.fingerprint), label: l10n.navAttendance),
          NavigationDestination(icon: const Icon(Icons.checklist_outlined), selectedIcon: const Icon(Icons.checklist), label: l10n.navTasks),
          NavigationDestination(icon: const Icon(Icons.more_horiz), selectedIcon: const Icon(Icons.more_horiz), label: l10n.navMore),
        ],
      ),
    );
  }
}
