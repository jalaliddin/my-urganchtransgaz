import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/announcements/presentation/announcement_detail_screen.dart';
import '../../features/announcements/presentation/announcements_screen.dart';
import '../../features/auth/presentation/auth_controller.dart';
import '../../features/auth/presentation/forgot_password_screen.dart';
import '../../features/auth/presentation/login_screen.dart';
import '../../features/dashboard/presentation/dashboard_screen.dart';
import '../../features/documents/presentation/documents_screen.dart';
import '../../features/exams/presentation/exam_attempt_screen.dart';
import '../../features/exams/presentation/exams_screen.dart';
import '../../features/issues/presentation/create_issue_screen.dart';
import '../../features/issues/presentation/issue_detail_screen.dart';
import '../../features/issues/presentation/issues_screen.dart';
import '../../features/kpi/presentation/kpi_screen.dart';
import '../../features/more/presentation/more_screen.dart';
import '../../features/notifications/presentation/notifications_screen.dart';
import '../../features/profile/presentation/profile_screen.dart';
import '../../features/tasks/presentation/task_detail_screen.dart';
import '../../features/tasks/presentation/tasks_screen.dart';
import 'app_shell.dart';
import 'router_refresh_notifier.dart';
import 'splash_screen.dart';

final routerProvider = Provider<GoRouter>((ref) {
  final refreshNotifier = RouterRefreshNotifier(ref);

  return GoRouter(
    initialLocation: '/',
    refreshListenable: refreshNotifier,
    redirect: (context, state) {
      final authState = ref.read(authControllerProvider);
      final location = state.matchedLocation;
      const publicRoutes = ['/login', '/forgot-password'];

      if (authState.isLoading) {
        return location == '/splash' ? null : '/splash';
      }

      final isLoggedIn = authState.value != null;

      if (!isLoggedIn && !publicRoutes.contains(location)) {
        return '/login';
      }

      if (isLoggedIn && (publicRoutes.contains(location) || location == '/splash')) {
        return '/';
      }

      return null;
    },
    routes: [
      GoRoute(path: '/splash', builder: (context, state) => const SplashScreen()),
      GoRoute(path: '/login', builder: (context, state) => const LoginScreen()),
      GoRoute(path: '/forgot-password', builder: (context, state) => const ForgotPasswordScreen()),
      ShellRoute(
        builder: (context, state, child) => AppShell(child: child),
        routes: [
          GoRoute(path: '/', builder: (context, state) => const DashboardScreen()),
          GoRoute(path: '/tasks', builder: (context, state) => const TasksScreen()),
          GoRoute(path: '/issues', builder: (context, state) => const IssuesScreen()),
          GoRoute(path: '/more', builder: (context, state) => const MoreScreen()),
        ],
      ),
      GoRoute(
        path: '/tasks/:id',
        builder: (context, state) => TaskDetailScreen(id: int.parse(state.pathParameters['id']!)),
      ),
      GoRoute(path: '/profile', builder: (context, state) => const ProfileScreen()),
      GoRoute(path: '/documents', builder: (context, state) => const DocumentsScreen()),
      GoRoute(path: '/kpi', builder: (context, state) => const KpiScreen()),
      GoRoute(path: '/exams', builder: (context, state) => const ExamsScreen()),
      GoRoute(
        path: '/exams/:id/attempt',
        builder: (context, state) => ExamAttemptScreen(examId: int.parse(state.pathParameters['id']!)),
      ),
      GoRoute(path: '/issues/new', builder: (context, state) => const CreateIssueScreen()),
      GoRoute(
        path: '/issues/:id',
        builder: (context, state) => IssueDetailScreen(id: int.parse(state.pathParameters['id']!)),
      ),
      GoRoute(path: '/announcements', builder: (context, state) => const AnnouncementsScreen()),
      GoRoute(
        path: '/announcements/:id',
        builder: (context, state) => AnnouncementDetailScreen(id: int.parse(state.pathParameters['id']!)),
      ),
      GoRoute(path: '/notifications', builder: (context, state) => const NotificationsScreen()),
    ],
  );
});
