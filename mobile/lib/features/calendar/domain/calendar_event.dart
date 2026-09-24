enum CalendarEventType { taskDue, examStart, examEnd, announcement }

/// One dated item on the calendar — a task due date, an exam start/end
/// date, or an announcement publish date. `route` is where tapping the
/// event should navigate (the source screen already showing the record).
class CalendarEvent {
  CalendarEvent({
    required this.date,
    required this.title,
    required this.type,
    required this.route,
  });

  /// A calendar date only (`yyyy-MM-dd`), never a full timestamp — events
  /// are grouped by day, so time-of-day is deliberately discarded.
  final DateTime date;
  final String title;
  final CalendarEventType type;
  final String route;
}
