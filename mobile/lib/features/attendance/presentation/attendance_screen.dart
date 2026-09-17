import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/widgets/async_value_view.dart';
import '../../../l10n/app_localizations.dart';
import '../domain/attendance_record.dart';
import 'attendance_controller.dart';
import 'today_attendance_card.dart';

class AttendanceScreen extends ConsumerStatefulWidget {
  const AttendanceScreen({super.key});

  @override
  ConsumerState<AttendanceScreen> createState() => _AttendanceScreenState();
}

class _AttendanceScreenState extends ConsumerState<AttendanceScreen> with SingleTickerProviderStateMixin {
  late final TabController _tabController;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.attendanceTitle),
        bottom: TabBar(
          controller: _tabController,
          tabs: [Tab(text: l10n.attendanceTabToday), Tab(text: l10n.attendanceTabHistory)],
        ),
      ),
      body: TabBarView(
        controller: _tabController,
        children: const [_TodayTab(), _HistoryTab()],
      ),
    );
  }
}

class _TodayTab extends ConsumerWidget {
  const _TodayTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return RefreshIndicator(
      onRefresh: () => ref.read(myTodayAttendanceProvider.notifier).refresh(),
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: const [TodayAttendanceCard()],
      ),
    );
  }
}

class _HistoryTab extends ConsumerWidget {
  const _HistoryTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final history = ref.watch(attendanceHistoryProvider);
    final controller = ref.read(attendanceHistoryProvider.notifier);

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  icon: const Icon(Icons.date_range_outlined),
                  label: Text('${_format(controller.from)} — ${_format(controller.to)}'),
                  onPressed: () async {
                    final range = await showDateRangePicker(
                      context: context,
                      firstDate: DateTime(2020),
                      lastDate: DateTime.now(),
                      initialDateRange: DateTimeRange(start: controller.from, end: controller.to),
                    );

                    if (range != null) {
                      await controller.setRange(range.start, range.end);
                    }
                  },
                ),
              ),
            ],
          ),
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: () => controller.setRange(controller.from, controller.to),
            child: AsyncValueView(
              value: history,
              onRetry: () => ref.invalidate(attendanceHistoryProvider),
              data: (context, records) {
                if (records.isEmpty) {
                  return ListView(
                    children: [
                      SizedBox(height: 300, child: EmptyStateView(message: l10n.attendanceNoRecords, icon: Icons.event_busy_outlined)),
                    ],
                  );
                }

                return ListView.separated(
                  itemCount: records.length,
                  separatorBuilder: (context, index) => const Divider(height: 1),
                  itemBuilder: (context, index) => _RecordTile(record: records[index]),
                );
              },
            ),
          ),
        ),
      ],
    );
  }

  String _format(DateTime date) => '${date.day.toString().padLeft(2, '0')}.${date.month.toString().padLeft(2, '0')}.${date.year}';
}

class _RecordTile extends StatelessWidget {
  const _RecordTile({required this.record});

  final AttendanceRecord record;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final worked = record.workedMinutes;

    return ListTile(
      leading: CircleAvatar(backgroundColor: _statusColor(context, record.status), child: Icon(_statusIcon(record.status), color: Colors.white, size: 18)),
      title: Text(record.date),
      subtitle: Text(
        worked != null ? '${l10n.attendanceWorked}: ${worked ~/ 60}h ${worked % 60}m' : _statusLabel(l10n, record.status),
      ),
      trailing: Text(_statusLabel(l10n, record.status)),
    );
  }

  Color _statusColor(BuildContext context, String status) {
    return switch (status) {
      'present' => Colors.green,
      'late' || 'early_leave' => Colors.orange,
      'absent' => Colors.red,
      _ => Theme.of(context).colorScheme.primary,
    };
  }

  IconData _statusIcon(String status) {
    return switch (status) {
      'present' => Icons.check,
      'absent' => Icons.close,
      _ => Icons.access_time,
    };
  }

  String _statusLabel(AppLocalizations l10n, String status) {
    return switch (status) {
      'present' => l10n.statusPresent,
      'late' => l10n.statusLate,
      'early_leave' => l10n.statusEarlyLeave,
      'absent' => l10n.statusAbsent,
      'vacation' => l10n.statusVacation,
      'business_trip' => l10n.statusBusinessTrip,
      'sick_leave' => l10n.statusSickLeave,
      _ => status,
    };
  }
}
