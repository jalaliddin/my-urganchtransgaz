import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../absences/presentation/absences_controller.dart';
import '../../announcements/presentation/announcements_controller.dart';
import '../../auth/presentation/auth_controller.dart';
import '../../exams/presentation/exams_controller.dart';
import '../../tasks/presentation/tasks_controller.dart';
import '../data/calendar_repository.dart';
import '../domain/calendar_event.dart';

final calendarRepositoryProvider = Provider<CalendarRepository>((ref) {
  return CalendarRepository(
    fetchTasks: ref.watch(tasksRepositoryProvider).list,
    fetchExams: ref.watch(examsRepositoryProvider).list,
    fetchAnnouncements: ref.watch(announcementsRepositoryProvider).list,
    fetchAbsences: () async {
      final employeeId = (await ref.read(authControllerProvider.future))?.employee?.id;
      if (employeeId == null) return [];

      return ref.read(absencesRepositoryProvider).list(employeeId, state: 'active');
    },
  );
});

class CalendarController extends AsyncNotifier<List<CalendarEvent>> {
  @override
  Future<List<CalendarEvent>> build() => ref.watch(calendarRepositoryProvider).events();

  Future<void> refresh() async {
    ref.invalidateSelf();
    await future;
  }
}

final calendarControllerProvider = AsyncNotifierProvider<CalendarController, List<CalendarEvent>>(CalendarController.new);
