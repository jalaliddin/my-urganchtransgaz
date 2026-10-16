import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/formatting/dates.dart';
import '../../../core/theme/status_colors.dart';
import '../../../core/widgets/async_value_view.dart';
import '../../../core/widgets/responsive_body.dart';
import '../../../core/widgets/section.dart';
import '../../../core/widgets/status_pill.dart';
import '../../../features/auth/presentation/auth_controller.dart';
import '../../../l10n/app_localizations.dart';
import '../domain/issue.dart';
import 'issue_map.dart';
import 'issues_controller.dart';

// Above this width the map and the list sit side by side instead of
// stacked — enough room for both without either feeling cramped (a
// phone in landscape or a small split-screen window stays stacked).
const _wideLayoutMinWidth = 700.0;

class IssuesScreen extends ConsumerWidget {
  const IssuesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final issues = ref.watch(issuesControllerProvider);
    final controller = ref.read(issuesControllerProvider.notifier);
    final canSeeReport = ref.watch(authControllerProvider.select((user) => user.value?.can('issues.report') ?? false));

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.issuesTitle),
        actions: [
          if (canSeeReport)
            IconButton(
              icon: const Icon(Icons.insights_outlined),
              tooltip: l10n.issueReportTitle,
              onPressed: () => context.push('/issues/report'),
            ),
        ],
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(56),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 12),
            child: SizedBox(
              width: double.infinity,
              child: SegmentedButton<bool>(
                showSelectedIcon: false,
                segments: [
                  ButtonSegment(value: false, label: Text(l10n.issuesFilterOpen)),
                  ButtonSegment(value: true, label: Text(l10n.issuesFilterAll)),
                ],
                selected: {controller.showAll},
                onSelectionChanged: (selection) => controller.setShowAll(selection.first),
              ),
            ),
          ),
        ),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => context.push('/issues/new'),
        icon: const Icon(Icons.add_location_alt_outlined),
        label: Text(l10n.issuesReportIssue),
      ),
      body: LayoutBuilder(
        builder: (context, constraints) {
          final isWide = constraints.maxWidth > _wideLayoutMinWidth;

          return ResponsiveBody(
            // A stacked map+list column reads fine at 640px, but a
            // side-by-side pair needs the extra room or the map shrinks
            // to nothing — so widen the readable cap only in that case.
            maxWidth: isWide ? 1000 : 640,
            child: RefreshIndicator(
              onRefresh: controller.refresh,
              child: AsyncValueView(
                value: issues,
                onRetry: () => ref.invalidate(issuesControllerProvider),
                data: (context, items) {
                  if (items.isEmpty) {
                    return ListView(
                      children: [
                        SizedBox(height: 400, child: EmptyStateView(message: l10n.issuesNone, icon: Icons.map_outlined)),
                      ],
                    );
                  }

                  final markers = [
                    for (final issue in items)
                      IssueMapMarker(
                        id: issue.id,
                        point: LatLng(issue.latitude, issue.longitude),
                        title: issue.title,
                        isResolved: !issue.isOpen,
                        isStale: issue.isStale,
                      ),
                  ];

                  if (isWide) {
                    final paneHeight = constraints.maxHeight - 32;

                    return Padding(
                      padding: const EdgeInsets.all(16),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(
                            flex: 5,
                            child: IssueMap(
                              markers: markers,
                              onMarkerTap: (id) => context.push('/issues/$id'),
                              height: paneHeight,
                            ),
                          ),
                          const SizedBox(width: 16),
                          Expanded(
                            flex: 4,
                            child: SizedBox(
                              height: paneHeight,
                              child: ListView.separated(
                                itemCount: items.length,
                                separatorBuilder: (context, index) => const SizedBox(height: 10),
                                itemBuilder: (context, index) => _IssueTile(issue: items[index]),
                              ),
                            ),
                          ),
                        ],
                      ),
                    );
                  }

                  return ListView(
                    // Bottom room so the last card clears the extended FAB.
                    padding: const EdgeInsets.fromLTRB(16, 4, 16, 96),
                    children: [
                      IssueMap(
                        markers: markers,
                        onMarkerTap: (id) => context.push('/issues/$id'),
                        height: 220,
                      ),
                      const SizedBox(height: 16),
                      for (final issue in items) ...[_IssueTile(issue: issue), const SizedBox(height: 10)],
                    ],
                  );
                },
              ),
            ),
          );
        },
      ),
    );
  }
}

class _IssueTile extends StatelessWidget {
  const _IssueTile({required this.issue});

  final Issue issue;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final scheme = Theme.of(context).colorScheme;
    final text = Theme.of(context).textTheme;
    final status = StatusColors.of(context);
    final tone = issue.isOpen ? StatusTone.danger : StatusTone.success;
    final reported = AppDates.parse(issue.createdAt);
    final place = [issue.category?.name, issue.organization?.name].whereType<String>().join(', ');

    return Card(
      child: InkWell(
        onTap: () => context.push('/issues/${issue.id}'),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              IconBadge(
                icon: issue.isOpen ? Icons.location_on_outlined : Icons.task_alt,
                color: status.fg(tone),
                background: status.bg(tone),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(issue.title, maxLines: 2, overflow: TextOverflow.ellipsis, style: text.titleSmall),
                    if (place.isNotEmpty) ...[
                      const SizedBox(height: 2),
                      Text(
                        place,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: text.bodySmall!.copyWith(color: scheme.onSurfaceVariant),
                      ),
                    ],
                    const SizedBox(height: 10),
                    Wrap(
                      spacing: 8,
                      runSpacing: 6,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      children: [
                        StatusPill(label: issue.isOpen ? l10n.statusOpen : l10n.statusResolved, tone: tone),
                        if (issue.isOpen && issue.executors.isEmpty)
                          StatusPill(label: l10n.issuesNoExecutors, tone: StatusTone.warning, icon: Icons.person_off_outlined),
                        if (reported != null)
                          Text(
                            AppDates.moment(context, reported),
                            style: text.labelMedium!.copyWith(color: scheme.onSurfaceVariant, fontWeight: FontWeight.w500),
                          ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
