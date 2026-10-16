import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/formatting/dates.dart';
import '../../../core/models/user.dart';
import '../../../core/theme/status_colors.dart';
import '../../../core/widgets/async_value_view.dart';
import '../../../core/widgets/detail_rows.dart';
import '../../../core/widgets/responsive_body.dart';
import '../../../core/widgets/section.dart';
import '../../../core/widgets/status_pill.dart';
import '../../../features/auth/presentation/auth_controller.dart';
import '../../../l10n/app_localizations.dart';
import '../domain/issue.dart';
import 'executor_picker.dart';
import 'issue_map.dart';
import 'issue_photos.dart';
import 'issues_controller.dart';

class IssueDetailScreen extends ConsumerStatefulWidget {
  const IssueDetailScreen({super.key, required this.id});

  final int id;

  @override
  ConsumerState<IssueDetailScreen> createState() => _IssueDetailScreenState();
}

class _IssueDetailScreenState extends ConsumerState<IssueDetailScreen> {
  final _commentController = TextEditingController();
  bool _postingComment = false;
  bool _assigning = false;

  @override
  void dispose() {
    _commentController.dispose();
    super.dispose();
  }

  Future<void> _postComment(IssueDetailController controller) async {
    final body = _commentController.text.trim();
    if (body.isEmpty) return;

    setState(() => _postingComment = true);
    try {
      await controller.addComment(body);
      _commentController.clear();
    } finally {
      if (mounted) {
        setState(() => _postingComment = false);
      }
    }
  }

  Future<void> _confirmResolve(BuildContext context, IssueDetailController controller) async {
    final l10n = AppLocalizations.of(context);
    final noteController = TextEditingController();

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(l10n.issuesResolve),
        content: TextField(
          controller: noteController,
          decoration: InputDecoration(labelText: l10n.issuesResolutionNote),
          maxLines: 3,
          autofocus: true,
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: Text(l10n.commonCancel)),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: Text(l10n.issuesResolve)),
        ],
      ),
    );

    if (confirmed == true && noteController.text.trim().isNotEmpty) {
      await controller.resolve(noteController.text.trim());
    }
  }

  /// Technical Policy Service picks the executors from the issue's own
  /// organization; the chosen list replaces the current one.
  Future<void> _assignExecutors(Issue issue, IssueDetailController controller) async {
    final organizationId = issue.organization?.id;
    if (organizationId == null) return;

    setState(() => _assigning = true);
    try {
      final candidates = await ref.read(executorCandidatesProvider(organizationId).future);
      if (!mounted) return;

      final selected = await showExecutorPicker(
        context,
        candidates: candidates,
        initial: issue.executors.map((executor) => executor.id).toSet(),
      );
      if (selected == null || selected.isEmpty || !mounted) return;

      await controller.assignExecutors(selected.toList());
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(AppLocalizations.of(context).issuesExecutorsAssigned)));
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    } finally {
      if (mounted) {
        setState(() => _assigning = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final issueState = ref.watch(issueDetailProvider(widget.id));
    final controller = ref.read(issueDetailProvider(widget.id).notifier);
    final user = ref.watch(authControllerProvider).value;

    return Scaffold(
      appBar: AppBar(title: Text(l10n.issuesTitle)),
      body: ResponsiveBody(
        child: AsyncValueView(
          value: issueState,
          onRetry: () => ref.invalidate(issueDetailProvider(widget.id)),
          data: (context, issue) => _buildBody(context, issue, controller, user, l10n),
        ),
      ),
    );
  }

  Widget _buildBody(BuildContext context, Issue issue, IssueDetailController controller, User? user, AppLocalizations l10n) {
    final canResolve = (user?.can('issues.resolve') ?? false) && issue.isOpen;
    final canAssign = (user?.can('issues.assign') ?? false) && issue.isOpen;
    final text = Theme.of(context).textTheme;
    final scheme = Theme.of(context).colorScheme;
    final status = StatusColors.of(context);
    final tone = issue.isOpen ? StatusTone.danger : StatusTone.success;
    final resolvedAt = AppDates.parse(issue.resolvedAt);
    const gap = SizedBox(height: 16);

    return RefreshIndicator(
      onRefresh: controller.refresh,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
        children: [
          Text(issue.title, style: text.headlineSmall),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              StatusPill(label: issue.isOpen ? l10n.statusOpen : l10n.statusResolved, tone: tone),
              if (issue.category != null)
                StatusPill(label: issue.category!.name, tone: StatusTone.neutral, icon: Icons.sell_outlined),
            ],
          ),
          if (issue.description != null) ...[
            gap,
            Text(issue.description!, style: text.bodyLarge),
          ],
          if (issue.photos.isNotEmpty) ...[
            gap,
            IssuePhotos(photos: issue.photos),
          ],
          gap,
          IssueMap(
            markers: [
              IssueMapMarker(
                id: issue.id,
                point: LatLng(issue.latitude, issue.longitude),
                title: issue.title,
                isResolved: !issue.isOpen,
                isStale: issue.isStale,
              ),
            ],
            center: LatLng(issue.latitude, issue.longitude),
            zoom: 15,
            height: 220,
          ),
          if (!issue.isOpen) ...[
            gap,
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(color: status.bg(StatusTone.success), borderRadius: BorderRadius.circular(18)),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(Icons.task_alt, color: status.fg(StatusTone.success)),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(l10n.issuesResolutionNote, style: text.labelLarge!.copyWith(color: status.fg(StatusTone.success))),
                        const SizedBox(height: 4),
                        Text(issue.resolutionNote ?? '', style: text.bodyLarge),
                        const SizedBox(height: 6),
                        Text(
                          [
                            if (issue.resolvedByName != null) '${l10n.issuesResolvedBy}: ${issue.resolvedByName}',
                            if (resolvedAt != null) AppDates.moment(context, resolvedAt),
                          ].join('\n'),
                          style: text.bodySmall!.copyWith(color: scheme.onSurfaceVariant),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ],
          if (canAssign || canResolve) ...[
            gap,
            if (canResolve)
              FilledButton.icon(
                icon: const Icon(Icons.check_circle_outline),
                label: Text(l10n.issuesResolve),
                onPressed: () => _confirmResolve(context, controller),
              ),
            if (canAssign && canResolve) const SizedBox(height: 10),
            if (canAssign)
              OutlinedButton.icon(
                icon: _assigning
                    ? const SizedBox(height: 16, width: 16, child: CircularProgressIndicator(strokeWidth: 2))
                    : const Icon(Icons.group_add_outlined),
                label: Text(l10n.issuesAssignExecutors),
                onPressed: _assigning ? null : () => _assignExecutors(issue, controller),
              ),
          ],
          gap,
          GroupCard(
            dividerIndent: 54,
            children: [
              InfoRow(icon: Icons.person_outline, label: l10n.issuesReporter, value: issue.reporter?.fullName ?? '—'),
              InfoRow(
                icon: Icons.engineering_outlined,
                label: l10n.issuesExecutors,
                value: issue.executors.isEmpty ? l10n.issuesNoExecutors : issue.executors.map((e) => e.fullName).join(', '),
              ),
              if (issue.organization != null)
                InfoRow(icon: Icons.apartment_outlined, label: l10n.issuesOrganization, value: issue.organization!.name),
              if (issue.department != null)
                InfoRow(icon: Icons.account_tree_outlined, label: l10n.issuesDepartment, value: issue.department!.name),
              if (issue.objectName != null)
                InfoRow(icon: Icons.place_outlined, label: l10n.issuesObjectName, value: issue.objectName!),
            ],
          ),
          if (issue.activities.isNotEmpty) ...[
            SectionHeader(title: l10n.issuesTimeline),
            Card(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 4),
                child: Column(
                  children: [
                    for (var i = 0; i < issue.activities.length; i++)
                      _TimelineEntry(
                        title: issue.activities[i].description ?? '',
                        subtitle: [
                          issue.activities[i].causerName,
                          if (AppDates.parse(issue.activities[i].createdAt) case final at?) AppDates.moment(context, at),
                        ].whereType<String>().join(', '),
                        isLast: i == issue.activities.length - 1,
                      ),
                  ],
                ),
              ),
            ),
          ],
          SectionHeader(title: l10n.issuesComments),
          Card(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const SizedBox(height: 6),
                for (final comment in issue.comments)
                  CommentTile(
                    author: comment.userName ?? '',
                    body: comment.body,
                    createdAt: AppDates.parse(comment.createdAt),
                  ),
                CommentComposer(
                  controller: _commentController,
                  hint: l10n.issuesAddComment,
                  onSend: () {
                    if (!_postingComment) _postComment(controller);
                  },
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// One step of an issue's history: a dot on a vertical line, what happened,
/// and who did it when.
class _TimelineEntry extends StatelessWidget {
  const _TimelineEntry({required this.title, required this.subtitle, required this.isLast});

  final String title;
  final String subtitle;
  final bool isLast;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final text = Theme.of(context).textTheme;

    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SizedBox(
            width: 20,
            child: Column(
              children: [
                const SizedBox(height: 4),
                Container(
                  width: 10,
                  height: 10,
                  decoration: BoxDecoration(color: scheme.primary, shape: BoxShape.circle),
                ),
                if (!isLast)
                  Expanded(child: Container(width: 2, margin: const EdgeInsets.symmetric(vertical: 4), color: scheme.outlineVariant)),
              ],
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.only(bottom: 14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: text.bodyMedium!.copyWith(fontWeight: FontWeight.w500)),
                  if (subtitle.isNotEmpty) ...[
                    const SizedBox(height: 2),
                    Text(subtitle, style: text.bodySmall!.copyWith(color: scheme.onSurfaceVariant)),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
