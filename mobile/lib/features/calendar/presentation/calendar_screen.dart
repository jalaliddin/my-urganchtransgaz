import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/widgets/async_value_view.dart';
import '../../../core/widgets/responsive_body.dart';
import '../../../l10n/app_localizations.dart';
import '../../absences/domain/employee_absence.dart';
import '../../absences/presentation/absences_screen.dart';
import '../domain/calendar_event.dart';
import 'calendar_controller.dart';

DateTime _dateOnly(DateTime date) => DateTime(date.year, date.month, date.day);

/// Locale-aware where possible, falling back to a numeric form when the
/// device's bundled CLDR data doesn't cover the current locale — mirrors
/// the same defensive try/catch already used for dates on the dashboard
/// (see DashboardHero._today / TaskDueChip).
String _monthLabel(BuildContext context, DateTime month) {
  final localeName = Localizations.localeOf(context).toString();
  try {
    return DateFormat('MMMM yyyy', localeName).format(month);
  } catch (_) {
    return '${month.year}-${month.month.toString().padLeft(2, '0')}';
  }
}

String _weekdayLabel(BuildContext context, DateTime date) {
  final localeName = Localizations.localeOf(context).toString();
  try {
    return DateFormat('EEE', localeName).format(date);
  } catch (_) {
    const fallback = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    return fallback[date.weekday - 1];
  }
}

class CalendarScreen extends ConsumerStatefulWidget {
  const CalendarScreen({super.key});

  @override
  ConsumerState<CalendarScreen> createState() => _CalendarScreenState();
}

class _CalendarScreenState extends ConsumerState<CalendarScreen> {
  late DateTime _monthAnchor;
  late DateTime _selectedDate;

  @override
  void initState() {
    super.initState();
    final today = _dateOnly(DateTime.now());
    _monthAnchor = DateTime(today.year, today.month, 1);
    _selectedDate = today;
  }

  void _changeMonth(int delta) {
    setState(() => _monthAnchor = DateTime(_monthAnchor.year, _monthAnchor.month + delta, 1));
  }

  void _goToday() {
    final today = _dateOnly(DateTime.now());
    setState(() {
      _monthAnchor = DateTime(today.year, today.month, 1);
      _selectedDate = today;
    });
  }

  List<List<DateTime>> _weeks() {
    final firstOfMonth = DateTime(_monthAnchor.year, _monthAnchor.month, 1);
    // DateTime.weekday is Mon=1..Sun=7 already, so the grid starts Monday
    // with no Sun=0 remapping needed (unlike JavaScript's Date.getDay()).
    final startOffset = firstOfMonth.weekday - 1;
    final daysInMonth = DateTime(_monthAnchor.year, _monthAnchor.month + 1, 0).day;

    final cells = <DateTime>[];
    for (var i = startOffset; i > 0; i--) {
      cells.add(firstOfMonth.subtract(Duration(days: i)));
    }
    for (var day = 1; day <= daysInMonth; day++) {
      cells.add(DateTime(_monthAnchor.year, _monthAnchor.month, day));
    }
    while (cells.length % 7 != 0) {
      cells.add(cells.last.add(const Duration(days: 1)));
    }

    return [for (var i = 0; i < cells.length; i += 7) cells.sublist(i, i + 7)];
  }

  Color _eventColor(BuildContext context, CalendarEvent event) {
    final colorScheme = Theme.of(context).colorScheme;
    return switch (event.type) {
      CalendarEventType.taskDue => colorScheme.tertiary,
      CalendarEventType.examStart || CalendarEventType.examEnd => colorScheme.primary,
      CalendarEventType.announcement => colorScheme.secondary,
      CalendarEventType.absence => categoryColor(context, absenceCategoryOf(event.absenceType ?? '')),
    };
  }

  String _eventLabel(AppLocalizations l10n, CalendarEventType type) {
    return switch (type) {
      CalendarEventType.taskDue => l10n.calendarTaskDue,
      CalendarEventType.examStart => l10n.calendarExamStart,
      CalendarEventType.examEnd => l10n.calendarExamEnd,
      CalendarEventType.announcement => l10n.calendarAnnouncement,
      CalendarEventType.absence => l10n.calendarAbsence,
    };
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final eventsAsync = ref.watch(calendarControllerProvider);
    final today = _dateOnly(DateTime.now());

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.calendarTitle),
        actions: [
          TextButton(onPressed: _goToday, child: Text(l10n.calendarToday)),
        ],
      ),
      body: ResponsiveBody(
        maxWidth: 720,
        child: RefreshIndicator(
          onRefresh: () => ref.read(calendarControllerProvider.notifier).refresh(),
          child: AsyncValueView(
            value: eventsAsync,
            onRetry: () => ref.invalidate(calendarControllerProvider),
            data: (context, events) {
              final byDate = <DateTime, List<CalendarEvent>>{};
              for (final event in events) {
                byDate.putIfAbsent(event.date, () => []).add(event);
              }

              final selectedEvents = byDate[_selectedDate] ?? const <CalendarEvent>[];

              return ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      IconButton(onPressed: () => _changeMonth(-1), icon: const Icon(Icons.chevron_left)),
                      Text(_monthLabel(context, _monthAnchor), style: Theme.of(context).textTheme.titleMedium),
                      IconButton(onPressed: () => _changeMonth(1), icon: const Icon(Icons.chevron_right)),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      for (var i = 0; i < 7; i++)
                        Expanded(
                          child: Center(
                            child: Text(
                              _weekdayLabel(context, DateTime(2026, 1, 5 + i)),
                              style: Theme.of(context)
                                  .textTheme
                                  .labelSmall
                                  ?.copyWith(color: Theme.of(context).colorScheme.onSurfaceVariant),
                            ),
                          ),
                        ),
                    ],
                  ),
                  for (final week in _weeks())
                    Row(
                      children: [
                        for (final day in week)
                          Expanded(
                            child: _DayCell(
                              day: day,
                              inMonth: day.month == _monthAnchor.month,
                              isToday: day == today,
                              isSelected: day == _selectedDate,
                              dotColors: (byDate[day] ?? const [])
                                  .map((e) => _eventColor(context, e))
                                  .toSet()
                                  .toList(),
                              onTap: () => setState(() => _selectedDate = day),
                            ),
                          ),
                      ],
                    ),
                  const SizedBox(height: 16),
                  Text(
                    DateFormat('yyyy-MM-dd').format(_selectedDate),
                    style: Theme.of(context).textTheme.titleSmall,
                  ),
                  const SizedBox(height: 8),
                  if (selectedEvents.isEmpty)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 24),
                      child: EmptyStateView(message: l10n.calendarNoEvents, icon: Icons.event_busy_outlined),
                    )
                  else
                    Card(
                      clipBehavior: Clip.antiAlias,
                      child: Column(
                        children: [
                          for (final event in selectedEvents)
                            ListTile(
                              leading: CircleAvatar(radius: 6, backgroundColor: _eventColor(context, event)),
                              title: Text(
                                event.absenceType != null ? absenceTypeLabel(l10n, event.absenceType!) : event.title,
                              ),
                              subtitle: Text(
                                event.absenceType != null && event.title.isNotEmpty ? event.title : _eventLabel(l10n, event.type),
                              ),
                              onTap: () => context.push(event.route),
                            ),
                        ],
                      ),
                    ),
                ],
              );
            },
          ),
        ),
      ),
    );
  }
}

class _DayCell extends StatelessWidget {
  const _DayCell({
    required this.day,
    required this.inMonth,
    required this.isToday,
    required this.isSelected,
    required this.dotColors,
    required this.onTap,
  });

  final DateTime day;
  final bool inMonth;
  final bool isToday;
  final bool isSelected;
  final List<Color> dotColors;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final colorScheme = Theme.of(context).colorScheme;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        margin: const EdgeInsets.all(2),
        padding: const EdgeInsets.symmetric(vertical: 8),
        decoration: BoxDecoration(
          color: isSelected ? colorScheme.primaryContainer : null,
          borderRadius: BorderRadius.circular(12),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              '${day.day}',
              style: TextStyle(
                fontWeight: isToday ? FontWeight.w700 : FontWeight.w400,
                color: !inMonth
                    ? colorScheme.onSurfaceVariant.withValues(alpha: 0.4)
                    : isToday
                        ? colorScheme.primary
                        : isSelected
                            ? colorScheme.onPrimaryContainer
                            : colorScheme.onSurface,
              ),
            ),
            const SizedBox(height: 3),
            SizedBox(
              height: 6,
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  for (final color in dotColors.take(3))
                    Container(
                      width: 5,
                      height: 5,
                      margin: const EdgeInsets.symmetric(horizontal: 1),
                      decoration: BoxDecoration(color: color, shape: BoxShape.circle),
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
