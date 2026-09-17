import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/widgets/async_value_view.dart';
import '../../../l10n/app_localizations.dart';
import 'attendance_controller.dart';

/// Shared between the Dashboard and the Attendance tab's "Today" view —
/// one card, one source of truth for "have I checked in/out yet."
class TodayAttendanceCard extends ConsumerWidget {
  const TodayAttendanceCard({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final today = ref.watch(myTodayAttendanceProvider);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: AsyncValueView(
          value: today,
          onRetry: () => ref.invalidate(myTodayAttendanceProvider),
          data: (context, record) {
            final checkedIn = record?.checkIn != null;
            final checkedOut = record?.checkOut != null;

            return Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Icon(Icons.fingerprint, color: Theme.of(context).colorScheme.primary),
                    const SizedBox(width: 8),
                    Text(l10n.dashboardTodayAttendance, style: Theme.of(context).textTheme.titleMedium),
                  ],
                ),
                const SizedBox(height: 12),
                if (!checkedIn)
                  Text(l10n.dashboardNotCheckedInYet)
                else ...[
                  Text(l10n.dashboardCheckedInAt(_time(record!.checkIn!))),
                  if (checkedOut) Text(l10n.dashboardCheckedOutAt(_time(record.checkOut!))),
                ],
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: FilledButton.icon(
                        icon: const Icon(Icons.login),
                        label: Text(l10n.attendanceCheckIn),
                        onPressed: checkedIn
                            ? null
                            : () => ref.read(myTodayAttendanceProvider.notifier).checkIn(),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: OutlinedButton.icon(
                        icon: const Icon(Icons.logout),
                        label: Text(l10n.attendanceCheckOut),
                        onPressed: (!checkedIn || checkedOut)
                            ? null
                            : () => ref.read(myTodayAttendanceProvider.notifier).checkOut(),
                      ),
                    ),
                  ],
                ),
              ],
            );
          },
        ),
      ),
    );
  }

  String _time(String isoDateTime) {
    final parsed = DateTime.tryParse(isoDateTime);

    if (parsed == null) {
      return isoDateTime;
    }

    return '${parsed.hour.toString().padLeft(2, '0')}:${parsed.minute.toString().padLeft(2, '0')}';
  }
}
