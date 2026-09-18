import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/widgets/async_value_view.dart';
import '../../../core/widgets/responsive_body.dart';
import '../../../l10n/app_localizations.dart';
import '../domain/employee_kpi.dart';
import 'kpi_controller.dart';

class KpiScreen extends ConsumerWidget {
  const KpiScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final results = ref.watch(kpiControllerProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.kpiTitle)),
      body: ResponsiveBody(
        child: RefreshIndicator(
        onRefresh: () => ref.read(kpiControllerProvider.notifier).refresh(),
        child: AsyncValueView(
          value: results,
          onRetry: () => ref.invalidate(kpiControllerProvider),
          data: (context, items) {
            if (items.isEmpty) {
              return ListView(
                children: [
                  SizedBox(height: 400, child: EmptyStateView(message: l10n.kpiNone, icon: Icons.insights_outlined)),
                ],
              );
            }

            final byPeriod = <String, List<EmployeeKpi>>{};

            for (final item in items) {
              final key = item.period?.name ?? '';
              byPeriod.putIfAbsent(key, () => []).add(item);
            }

            return ListView(
              padding: const EdgeInsets.all(16),
              children: byPeriod.entries.map((entry) => _PeriodSection(period: entry.key, items: entry.value, l10n: l10n)).toList(),
            );
          },
        ),
      ),
      ),
    );
  }
}

class _PeriodSection extends StatelessWidget {
  const _PeriodSection({required this.period, required this.items, required this.l10n});

  final String period;
  final List<EmployeeKpi> items;
  final AppLocalizations l10n;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 16),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(period, style: Theme.of(context).textTheme.titleMedium),
            const Divider(),
            ...items.map(
              (item) => Padding(
                padding: const EdgeInsets.symmetric(vertical: 6),
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(item.indicator?.name ?? ''),
                          Text(
                            '${l10n.kpiTarget}: ${item.targetValue.toStringAsFixed(0)}'
                            '${item.actualValue != null ? ' · ${l10n.kpiActual}: ${item.actualValue!.toStringAsFixed(0)}' : ''}',
                            style: Theme.of(context).textTheme.bodySmall,
                          ),
                        ],
                      ),
                    ),
                    if (item.score != null)
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          Text(item.score!.toStringAsFixed(0), style: Theme.of(context).textTheme.titleMedium),
                          Text(l10n.kpiScore, style: Theme.of(context).textTheme.bodySmall),
                        ],
                      ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
