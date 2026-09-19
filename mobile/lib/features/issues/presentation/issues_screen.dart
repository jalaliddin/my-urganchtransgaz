import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/widgets/async_value_view.dart';
import '../../../core/widgets/responsive_body.dart';
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

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.issuesTitle),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(48),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            child: SegmentedButton<bool>(
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
      floatingActionButton: FloatingActionButton(
        onPressed: () => context.push('/issues/new'),
        child: const Icon(Icons.add),
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
                                separatorBuilder: (context, index) => const SizedBox(height: 8),
                                itemBuilder: (context, index) => _IssueTile(issue: items[index]),
                              ),
                            ),
                          ),
                        ],
                      ),
                    );
                  }

                  return ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      IssueMap(
                        markers: markers,
                        onMarkerTap: (id) => context.push('/issues/$id'),
                        height: 220,
                      ),
                      const SizedBox(height: 16),
                      ...items.map((issue) => _IssueTile(issue: issue)),
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

    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        leading: Icon(Icons.location_on, color: issue.isOpen ? Colors.red.shade700 : Colors.green.shade700),
        title: Text(issue.title),
        subtitle: Text([issue.category?.name, issue.organization?.name].whereType<String>().join(' · ')),
        trailing: Chip(
          label: Text(issue.isOpen ? l10n.statusOpen : l10n.statusResolved),
          backgroundColor: issue.isOpen ? Colors.red.shade50 : Colors.green.shade50,
        ),
        onTap: () => context.push('/issues/${issue.id}'),
      ),
    );
  }
}
