import 'package:flutter_test/flutter_test.dart';
import 'package:urtg_mobile/core/network/paginated.dart';
import 'package:urtg_mobile/features/absences/domain/employee_absence.dart';
import 'package:urtg_mobile/features/announcements/domain/announcement.dart';
import 'package:urtg_mobile/features/calendar/data/calendar_repository.dart';
import 'package:urtg_mobile/features/calendar/domain/calendar_event.dart';
import 'package:urtg_mobile/features/exams/domain/exam.dart';
import 'package:urtg_mobile/features/tasks/domain/task.dart';

Task _task({required int id, String? dueDate}) => Task.fromJson({
      'id': id,
      'title': 'Task $id',
      'assignees': [],
      'priority': 'normal',
      'status': 'new',
      'progress': 0,
      'created_at': '2026-09-01T09:00:00Z',
      'due_date': ?dueDate,
    });

Exam _exam({required int id, String? startDate, String? endDate}) => Exam.fromJson({
      'id': id,
      'title': 'Exam $id',
      'start_date': ?startDate,
      'end_date': ?endDate,
    });

Announcement _announcement({required int id, String? publishAt}) => Announcement.fromJson({
      'id': id,
      'title': 'Announcement $id',
      'publish_at': ?publishAt,
    });

CalendarRepository _repository({
  List<Task> tasks = const [],
  List<Exam> exams = const [],
  List<Announcement> announcements = const [],
  List<EmployeeAbsence> absences = const [],
}) {
  return CalendarRepository(
    fetchTasks: () async => tasks,
    fetchExams: () async => exams,
    fetchAnnouncements: () async => Paginated(items: announcements, currentPage: 1, lastPage: 1, total: announcements.length),
    fetchAbsences: () async => absences,
  );
}

void main() {
  test('ignores a task/exam/announcement with no date at all', () async {
    final repository = _repository(
      tasks: [_task(id: 1)],
      exams: [_exam(id: 2)],
      announcements: [_announcement(id: 3)],
    );

    expect(await repository.events(), isEmpty);
  });

  test('a task with a due date becomes one taskDue event pointing at its detail route', () async {
    final repository = _repository(tasks: [_task(id: 5, dueDate: '2026-09-27')]);

    final events = await repository.events();

    expect(events, hasLength(1));
    expect(events.single.type, CalendarEventType.taskDue);
    expect(events.single.date, DateTime(2026, 9, 27));
    expect(events.single.route, '/tasks/5');
  });

  test('an exam with both a start and end date becomes two separate events', () async {
    final repository = _repository(exams: [_exam(id: 7, startDate: '2026-09-29', endDate: '2026-10-06')]);

    final events = await repository.events();

    expect(events, hasLength(2));
    expect(events.map((e) => e.type), containsAll([CalendarEventType.examStart, CalendarEventType.examEnd]));
    expect(events.firstWhere((e) => e.type == CalendarEventType.examStart).date, DateTime(2026, 9, 29));
    expect(events.firstWhere((e) => e.type == CalendarEventType.examEnd).date, DateTime(2026, 10, 6));
  });

  test('an announcement publish timestamp is truncated to a calendar date, discarding time-of-day', () async {
    final repository = _repository(announcements: [_announcement(id: 9, publishAt: '2026-09-24T15:52:00Z')]);

    final events = await repository.events();

    expect(events.single.date, DateTime(2026, 9, 24));
  });

  test('an unparseable date string is skipped rather than crashing', () async {
    final repository = _repository(tasks: [_task(id: 11, dueDate: 'not-a-date')]);

    expect(await repository.events(), isEmpty);
  });

  test('aggregates all three sources together in one list', () async {
    final repository = _repository(
      tasks: [_task(id: 1, dueDate: '2026-09-27')],
      exams: [_exam(id: 2, startDate: '2026-09-29')],
      announcements: [_announcement(id: 3, publishAt: '2026-09-24T00:00:00Z')],
    );

    final events = await repository.events();

    expect(events, hasLength(3));
  });

  test('an absence becomes one event per day it covers, across a month boundary', () async {
    final repository = _repository(absences: [
      EmployeeAbsence.fromJson({
        'id': 9,
        'type': 'business_trip',
        'start_date': '2026-09-29',
        'end_date': '2026-10-02',
        'days': 4,
        'state': 'upcoming',
        'destination': 'Toshkent',
      }),
    ]);

    final events = await repository.events();

    expect(events.map((e) => e.date), [
      DateTime(2026, 9, 29),
      DateTime(2026, 9, 30),
      DateTime(2026, 10, 1),
      DateTime(2026, 10, 2),
    ]);
    expect(events.every((e) => e.type == CalendarEventType.absence && e.absenceType == 'business_trip'), isTrue);
    expect(events.first.route, '/absences');
    expect(events.first.title, 'Toshkent');
  });
}
