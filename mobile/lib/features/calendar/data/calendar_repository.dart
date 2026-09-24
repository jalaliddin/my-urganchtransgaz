import '../../../core/network/paginated.dart';
import '../../announcements/domain/announcement.dart';
import '../../exams/domain/exam.dart';
import '../../tasks/domain/task.dart';
import '../domain/calendar_event.dart';

/// Aggregates from the same repositories each source screen already uses
/// (tasks/exams/announcements) instead of a dedicated backend endpoint —
/// this inherits each module's existing employee-scoped visibility
/// exactly as already enforced there (see each repository's own list()),
/// with no separate authorization logic to keep in sync. Mirrors the web
/// app's own Reports/Calendar page, which takes the same approach.
///
/// There is no date-ranged vacation/business-trip record to place on a
/// calendar (Employee.status is a point-in-time state, not a date range —
/// the app's old Leave Requests/Business Trips module, which did track
/// date ranges, was deliberately removed), so those are intentionally
/// not included here.
///
/// Takes plain fetch functions rather than the repositories themselves —
/// same shape as `NotificationSync`'s `fetchUnread`/`present` — so the
/// date/grouping logic below is unit-testable with in-memory fakes,
/// without mocking Dio for three different endpoints.
class CalendarRepository {
  CalendarRepository({
    required Future<List<Task>> Function() fetchTasks,
    required Future<List<Exam>> Function() fetchExams,
    required Future<Paginated<Announcement>> Function() fetchAnnouncements,
  })  : _fetchTasks = fetchTasks,
        _fetchExams = fetchExams,
        _fetchAnnouncements = fetchAnnouncements;

  final Future<List<Task>> Function() _fetchTasks;
  final Future<List<Exam>> Function() _fetchExams;
  final Future<Paginated<Announcement>> Function() _fetchAnnouncements;

  Future<List<CalendarEvent>> events() async {
    // Each call starts its request immediately (async methods run
    // eagerly up to their first await) — assigning the Futures before
    // awaiting any of them runs all three requests concurrently despite
    // each being typed and awaited on its own line below.
    final tasksFuture = _fetchTasks();
    final examsFuture = _fetchExams();
    final announcementsFuture = _fetchAnnouncements();

    final tasks = await tasksFuture;
    final exams = await examsFuture;
    final announcementsPage = await announcementsFuture;

    final events = <CalendarEvent>[];

    for (final task in tasks) {
      final due = _parseDate(task.dueDate);
      if (due != null) {
        events.add(CalendarEvent(date: due, title: task.title, type: CalendarEventType.taskDue, route: '/tasks/${task.id}'));
      }
    }

    for (final exam in exams) {
      final start = _parseDate(exam.startDate);
      if (start != null) {
        events.add(CalendarEvent(date: start, title: exam.title, type: CalendarEventType.examStart, route: '/exams'));
      }
      final end = _parseDate(exam.endDate);
      if (end != null) {
        events.add(CalendarEvent(date: end, title: exam.title, type: CalendarEventType.examEnd, route: '/exams'));
      }
    }

    for (final announcement in announcementsPage.items) {
      final publishAt = _parseDate(announcement.publishAt);
      if (publishAt != null) {
        events.add(CalendarEvent(
          date: publishAt,
          title: announcement.title,
          type: CalendarEventType.announcement,
          route: '/announcements/${announcement.id}',
        ));
      }
    }

    return events;
  }

  DateTime? _parseDate(String? raw) {
    if (raw == null) return null;
    final parsed = DateTime.tryParse(raw);
    if (parsed == null) return null;

    return DateTime(parsed.year, parsed.month, parsed.day);
  }
}
