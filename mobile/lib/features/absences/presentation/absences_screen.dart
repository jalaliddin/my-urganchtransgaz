import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/formatting/dates.dart';
import '../../../core/theme/status_colors.dart';
import '../../../core/widgets/async_value_view.dart';
import '../../../core/widgets/status_pill.dart';
import '../../../core/widgets/responsive_body.dart';
import '../../../l10n/app_localizations.dart';
import '../domain/employee_absence.dart';
import 'absences_controller.dart';

class AbsencesScreen extends ConsumerWidget {
  const AbsencesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final absences = ref.watch(absencesControllerProvider);
    final balance = ref.watch(leaveBalanceProvider).value;

    return Scaffold(
      appBar: AppBar(title: Text(l10n.absencesTitle)),
      body: ResponsiveBody(
        child: RefreshIndicator(
          onRefresh: () => ref.read(absencesControllerProvider.notifier).refresh(),
          child: AsyncValueView(
            value: absences,
            onRetry: () => ref.invalidate(absencesControllerProvider),
            data: (context, items) => ListView(
              padding: const EdgeInsets.all(16),
              children: [
                if (balance != null) _BalanceCard(balance: balance),
                if (items.isEmpty)
                  SizedBox(height: 320, child: EmptyStateView(message: l10n.absencesNone, icon: Icons.event_busy_outlined))
                else
                  ...items.map((absence) => _AbsenceTile(absence: absence)),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _BalanceCard extends StatelessWidget {
  const _BalanceCard({required this.balance});

  final LeaveBalance balance;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);

    Widget figure(int value, String label, {bool emphasized = false}) => Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                '$value',
                style: theme.textTheme.headlineMedium?.copyWith(
                  fontWeight: emphasized ? FontWeight.w700 : FontWeight.w400,
                  color: emphasized ? theme.colorScheme.primary : null,
                ),
              ),
              Text(label, style: theme.textTheme.bodySmall),
            ],
          ),
        );

    return Card(
      margin: const EdgeInsets.only(bottom: 16),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(l10n.absencesBalanceTitle(balance.year), style: theme.textTheme.titleMedium),
            const SizedBox(height: 12),
            Row(
              children: [
                figure(balance.remainingDays, l10n.absencesRemaining, emphasized: true),
                figure(balance.usedDays, l10n.absencesUsed),
                figure(balance.entitlementDays, l10n.absencesEntitlement),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _AbsenceTile extends StatelessWidget {
  const _AbsenceTile({required this.absence});

  final EmployeeAbsence absence;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final cancelled = absence.state == 'cancelled';
    final color = cancelled ? scheme.outline : categoryColor(context, absence.category);
    final extras = [
      ?absence.destination,
      ?absence.documentNumber,
      if (absence.cancellationReason != null) l10n.absencesCancelledReason(absence.cancellationReason!),
    ];

    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 40,
              height: 40,
              alignment: Alignment.center,
              decoration: BoxDecoration(color: color.withValues(alpha: 0.14), borderRadius: BorderRadius.circular(12)),
              child: Text(
                categoryCode(absence.category),
                style: theme.textTheme.titleMedium!.copyWith(color: color, fontWeight: FontWeight.w700),
              ),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    absenceTypeLabel(l10n, absence.type),
                    style: theme.textTheme.titleSmall!.copyWith(
                      decoration: cancelled ? TextDecoration.lineThrough : null,
                      color: cancelled ? scheme.onSurfaceVariant : null,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    '${AppDates.short(context, absence.startDate)} — ${AppDates.short(context, absence.endDate)}, ${l10n.absencesDays(absence.days)}',
                    style: theme.textTheme.bodyMedium,
                  ),
                  for (final line in extras)
                    Text(line, style: theme.textTheme.bodySmall!.copyWith(color: scheme.onSurfaceVariant)),
                  const SizedBox(height: 10),
                  StatusPill(label: _stateLabel(l10n, absence.state), tone: _stateTone(absence.state)),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  StatusTone _stateTone(String state) => switch (state) {
        'current' => StatusTone.info,
        'upcoming' => StatusTone.warning,
        'cancelled' => StatusTone.danger,
        _ => StatusTone.neutral,
      };

  String _stateLabel(AppLocalizations l10n, String state) => switch (state) {
        'upcoming' => l10n.absenceStateUpcoming,
        'current' => l10n.absenceStateCurrent,
        'completed' => l10n.absenceStateCompleted,
        _ => l10n.absenceStateCancelled,
      };
}


/// The tabel letter a category is printed as — T/X/B/S, same as the web.
String categoryCode(AbsenceCategory category) => switch (category) {
      AbsenceCategory.vacation => 'T',
      AbsenceCategory.businessTrip => 'X',
      AbsenceCategory.sickLeave => 'B',
      AbsenceCategory.excused => 'S',
    };

Color categoryColor(BuildContext context, AbsenceCategory category) => switch (category) {
      AbsenceCategory.vacation => const Color(0xFF0288D1),
      AbsenceCategory.businessTrip => Theme.of(context).colorScheme.primary,
      AbsenceCategory.sickLeave => const Color(0xFFED6C02),
      AbsenceCategory.excused => const Color(0xFF7B6FA8),
    };

String absenceTypeLabel(AppLocalizations l10n, String type) => switch (type) {
      'annual_leave' => l10n.absenceTypeAnnualLeave,
      'unpaid_leave' => l10n.absenceTypeUnpaidLeave,
      'study_leave' => l10n.absenceTypeStudyLeave,
      'maternity_leave' => l10n.absenceTypeMaternityLeave,
      'childcare_leave' => l10n.absenceTypeChildcareLeave,
      'sick_leave' => l10n.absenceTypeSickLeave,
      'business_trip' => l10n.absenceTypeBusinessTrip,
      _ => l10n.absenceTypeOther,
    };
