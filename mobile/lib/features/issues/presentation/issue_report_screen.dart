import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/layout/breakpoints.dart';
import '../../../core/widgets/async_value_view.dart';
import '../../../core/widgets/responsive_body.dart';
import '../../../l10n/app_localizations.dart';
import '../domain/issue_report.dart';
import 'issue_map.dart';
import 'issue_report_controller.dart';

// The web report's status colors — always paired with an icon and a label.
const _openColor = Color(0xFFD03B3B);
const _staleColor = Color(0xFF8F1D1D);
const _resolvedColor = Color(0xFF0CA30C);

/// The web "Muammolar xaritasi hisoboti" for leaders on the go: totals, the
/// map of every point, and the per-category / per-organization / monthly
/// breakdowns. Already scoped server-side to what the user may see.
class IssueReportScreen extends ConsumerWidget {
  const IssueReportScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final period = ref.watch(issueReportPeriodProvider);
    final report = ref.watch(issueReportProvider);

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.issueReportTitle),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(52),
          child: _PeriodChips(
            selected: period,
            onSelected: ref.read(issueReportPeriodProvider.notifier).select,
          ),
        ),
      ),
      body: Column(
        children: [
          // Switching the period keeps the old numbers on screen while the
          // new ones load, so say that they are being replaced.
          if (report.isLoading && report.hasValue) const LinearProgressIndicator(minHeight: 2),
          Expanded(
            child: ResponsiveBody(
              maxWidth: 1000,
              child: RefreshIndicator(
                onRefresh: () => ref.refresh(issueReportProvider.future),
                child: AsyncValueView(
                  value: report,
                  onRetry: () => ref.invalidate(issueReportProvider),
                  data: (context, data) => _ReportBody(report: data, period: period),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _PeriodChips extends StatelessWidget {
  const _PeriodChips({required this.selected, required this.onSelected});

  final IssueReportPeriod selected;
  final void Function(IssueReportPeriod) onSelected;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final labels = {
      IssueReportPeriod.thisMonth: l10n.issueReportPeriodMonth,
      IssueReportPeriod.last3Months: l10n.issueReportPeriod3Months,
      IssueReportPeriod.last12Months: l10n.issueReportPeriod12Months,
      IssueReportPeriod.allTime: l10n.issueReportPeriodAll,
    };

    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
      child: Row(
        children: [
          for (final entry in labels.entries) ...[
            ChoiceChip(
              label: Text(entry.value),
              selected: entry.key == selected,
              onSelected: (_) => onSelected(entry.key),
            ),
            const SizedBox(width: 8),
          ],
        ],
      ),
    );
  }
}

class _ReportBody extends StatelessWidget {
  const _ReportBody({required this.report, required this.period});

  final IssueReport report;
  final IssueReportPeriod period;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    if (report.totals.total == 0) {
      return ListView(
        children: [
          SizedBox(height: 400, child: EmptyStateView(message: l10n.issueReportEmpty, icon: Icons.map_outlined)),
        ],
      );
    }

    return LayoutBuilder(
      builder: (context, constraints) {
        final size = windowSizeForWidth(constraints.maxWidth);
        final categories = _CategorySection(rows: report.byCategory);
        final organizations = _OrganizationSection(rows: report.byOrganization, staleAfterDays: report.staleAfterDays);

        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            _Totals(report: report, columns: size == WindowSize.compact ? 2 : 3),
            const SizedBox(height: 16),
            if (report.points.isNotEmpty) ...[
              IssueMap(
                // A new period brings a new set of points to frame.
                key: ValueKey(period),
                markers: [
                  for (final point in report.points)
                    IssueMapMarker(
                      id: point.id,
                      point: LatLng(point.latitude, point.longitude),
                      title: point.title,
                      isResolved: point.isResolved,
                      isStale: !point.isResolved && (point.openDays ?? 0) > report.staleAfterDays,
                    ),
                ],
                center: LatLng(report.points.first.latitude, report.points.first.longitude),
                fitMarkers: true,
                onMarkerTap: (id) => context.push('/issues/$id'),
                height: size == WindowSize.compact ? 260 : 380,
              ),
              if (report.pointsTruncated)
                Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: Text(
                    l10n.issueReportPointsTruncated(report.points.length),
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ),
              const SizedBox(height: 16),
            ],
            if (size == WindowSize.expanded)
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(child: categories),
                  const SizedBox(width: 16),
                  Expanded(child: organizations),
                ],
              )
            else ...[
              categories,
              const SizedBox(height: 16),
              organizations,
            ],
            const SizedBox(height: 16),
            _MonthlySection(rows: report.monthly),
          ],
        );
      },
    );
  }
}

/// "{n} soat" under two days, "{n} kun" (one decimal) above — the web's rule.
String formatResolutionTime(AppLocalizations l10n, double? hours) {
  if (hours == null) return '—';
  if (hours < 48) return l10n.issueReportHours(_trim(hours));

  return l10n.issueReportDays(_trim(hours / 24));
}

String _trim(double value) {
  final rounded = (value * 10).round() / 10;

  return rounded == rounded.roundToDouble() ? rounded.toInt().toString() : rounded.toString();
}

class _Totals extends StatelessWidget {
  const _Totals({required this.report, required this.columns});

  final IssueReport report;
  final int columns;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final primary = Theme.of(context).colorScheme.primary;
    final totals = report.totals;
    final tiles = [
      _Tile(icon: Icons.report_outlined, color: primary, value: '${totals.total}', label: l10n.issueReportTotal),
      _Tile(icon: Icons.error_outline, color: _openColor, value: '${totals.openCount}', label: l10n.issueReportOpen),
      _Tile(
        icon: Icons.priority_high,
        color: _staleColor,
        value: '${totals.staleOpenCount}',
        label: l10n.issueReportStale(report.staleAfterDays),
      ),
      _Tile(
        icon: Icons.check_circle_outline,
        color: _resolvedColor,
        value: '${totals.resolvedCount}',
        label: l10n.issueReportResolved,
      ),
      _Tile(icon: Icons.percent, color: primary, value: '${totals.resolutionRate}%', label: l10n.issueReportResolutionRate),
      _Tile(
        icon: Icons.schedule,
        color: primary,
        value: formatResolutionTime(l10n, totals.avgResolutionHours),
        label: l10n.issueReportAvgResolution,
      ),
    ];

    return LayoutBuilder(
      builder: (context, constraints) {
        const spacing = 8.0;
        final width = (constraints.maxWidth - spacing * (columns - 1)) / columns;

        return Wrap(
          spacing: spacing,
          runSpacing: spacing,
          children: [for (final tile in tiles) SizedBox(width: width, child: tile)],
        );
      },
    );
  }
}

class _Tile extends StatelessWidget {
  const _Tile({required this.icon, required this.color, required this.value, required this.label});

  final IconData icon;
  final Color color;
  final String value;
  final String label;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;

    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, size: 20, color: color),
            const SizedBox(height: 8),
            Text(value, style: textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
            Text(
              label,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: textTheme.bodySmall?.copyWith(color: Theme.of(context).colorScheme.onSurfaceVariant),
            ),
          ],
        ),
      ),
    );
  }
}

class _Section extends StatelessWidget {
  const _Section({required this.title, required this.children});

  final String title;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 12),
            ...children,
          ],
        ),
      ),
    );
  }
}

/// One row of a breakdown: name and number as text, and a single-hue bar
/// whose length is [fraction] of the longest row.
class _BarRow extends StatelessWidget {
  const _BarRow({required this.label, required this.value, required this.fraction, this.caption});

  final String label;
  final String value;
  final double fraction;
  final String? caption;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(child: Text(label, maxLines: 2, overflow: TextOverflow.ellipsis)),
              const SizedBox(width: 8),
              Text(value, style: theme.textTheme.titleSmall),
            ],
          ),
          const SizedBox(height: 4),
          ClipRRect(
            borderRadius: BorderRadius.circular(4),
            child: LinearProgressIndicator(
              value: fraction.clamp(0, 1),
              minHeight: 6,
              color: theme.colorScheme.primary,
              backgroundColor: theme.colorScheme.surfaceContainerHighest,
            ),
          ),
          if (caption != null) ...[
            const SizedBox(height: 4),
            Text(caption!, style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant)),
          ],
        ],
      ),
    );
  }
}

class _CategorySection extends StatelessWidget {
  const _CategorySection({required this.rows});

  final List<IssueReportCategory> rows;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final max = rows.fold<int>(0, (max, row) => row.total > max ? row.total : max);

    return _Section(
      title: l10n.issueReportByCategory,
      children: [
        for (final row in rows)
          _BarRow(
            label: row.name ?? l10n.issueReportNoCategory,
            value: '${row.total}',
            fraction: max == 0 ? 0 : row.total / max,
            caption: '${l10n.issueReportOpen}: ${row.openCount} · ${l10n.issueReportResolved}: ${row.resolvedCount}',
          ),
      ],
    );
  }
}

/// Per organization the bar is its resolution rate — the number leaders
/// compare across organizations — with the counts underneath.
class _OrganizationSection extends StatelessWidget {
  const _OrganizationSection({required this.rows, required this.staleAfterDays});

  final List<IssueReportOrganization> rows;
  final int staleAfterDays;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    return _Section(
      title: l10n.issueReportByOrganization,
      children: [
        for (final row in rows)
          _BarRow(
            label: row.name,
            value: '${row.counts.resolutionRate}%',
            fraction: row.counts.resolutionRate / 100,
            caption: [
              '${l10n.issueReportTotal}: ${row.counts.total}',
              '${l10n.issueReportOpen}: ${row.counts.openCount}',
              '${l10n.issueReportStale(staleAfterDays)}: ${row.counts.staleOpenCount}',
              '${l10n.issueReportAvgResolution}: ${formatResolutionTime(l10n, row.counts.avgResolutionHours)}',
            ].join(' · '),
          ),
      ],
    );
  }
}

class _MonthlySection extends StatelessWidget {
  const _MonthlySection({required this.rows});

  final List<IssueReportMonth> rows;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final header = theme.textTheme.labelMedium?.copyWith(color: theme.colorScheme.onSurfaceVariant);

    TableRow row(List<String> cells, {TextStyle? style}) => TableRow(
          children: [
            for (final (index, cell) in cells.indexed)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 6),
                child: Text(cell, style: style, textAlign: index == 0 ? TextAlign.start : TextAlign.end),
              ),
          ],
        );

    return _Section(
      title: l10n.issueReportMonthly,
      children: [
        Table(
          columnWidths: const {0: FlexColumnWidth(1.2), 1: FlexColumnWidth(), 2: FlexColumnWidth()},
          border: TableBorder(horizontalInside: BorderSide(color: theme.dividerColor)),
          children: [
            row([l10n.issueReportMonth, l10n.issueReportCreated, l10n.issueReportResolved], style: header),
            for (final month in rows) row([month.month, '${month.created}', '${month.resolved}']),
          ],
        ),
      ],
    );
  }
}
