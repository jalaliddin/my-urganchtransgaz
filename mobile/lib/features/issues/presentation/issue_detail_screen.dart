import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/models/user.dart';
import '../../../core/widgets/async_value_view.dart';
import '../../../features/auth/presentation/auth_controller.dart';
import '../../../l10n/app_localizations.dart';
import '../domain/issue.dart';
import 'issue_map.dart';
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

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final issueState = ref.watch(issueDetailProvider(widget.id));
    final controller = ref.read(issueDetailProvider(widget.id).notifier);
    final user = ref.watch(authControllerProvider).value;

    return Scaffold(
      appBar: AppBar(title: Text(l10n.issuesTitle)),
      body: AsyncValueView(
        value: issueState,
        onRetry: () => ref.invalidate(issueDetailProvider(widget.id)),
        data: (context, issue) => _buildBody(context, issue, controller, user, l10n),
      ),
    );
  }

  Widget _buildBody(BuildContext context, Issue issue, IssueDetailController controller, User? user, AppLocalizations l10n) {
    final canResolve = (user?.can('issues.resolve') ?? false) && issue.isOpen;

    return RefreshIndicator(
      onRefresh: controller.refresh,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(issue.title, style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 8),
          Chip(
            label: Text(issue.isOpen ? l10n.statusOpen : l10n.statusResolved),
            backgroundColor: issue.isOpen ? Colors.red.shade50 : Colors.green.shade50,
          ),
          if (issue.description != null) ...[
            const SizedBox(height: 12),
            Text(issue.description!),
          ],
          const SizedBox(height: 16),
          IssueMap(
            markers: [IssueMapMarker(id: issue.id, point: LatLng(issue.latitude, issue.longitude), title: issue.title, isResolved: !issue.isOpen)],
            center: LatLng(issue.latitude, issue.longitude),
            zoom: 15,
            height: 240,
          ),
          if (!issue.isOpen) ...[
            const SizedBox(height: 16),
            Text(l10n.issuesResolutionNote, style: Theme.of(context).textTheme.titleSmall),
            Text(issue.resolutionNote ?? ''),
            Text(
              '${l10n.issuesResolvedBy}: ${issue.resolvedByName ?? ''} · ${issue.resolvedAt ?? ''}',
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
          if (canResolve) ...[
            const SizedBox(height: 16),
            FilledButton.icon(
              icon: const Icon(Icons.check_circle_outline),
              label: Text(l10n.issuesResolve),
              onPressed: () => _confirmResolve(context, controller),
            ),
          ],
          const Divider(height: 32),
          _InfoRow(icon: Icons.person_outline, label: l10n.issuesReporter, value: issue.reporter?.fullName ?? '—'),
          if (issue.organization != null)
            _InfoRow(icon: Icons.apartment_outlined, label: issue.organization!.name, value: ''),
          if (issue.department != null)
            _InfoRow(icon: Icons.account_tree_outlined, label: l10n.issuesDepartment, value: issue.department!.name),
          if (issue.objectName != null)
            _InfoRow(icon: Icons.place_outlined, label: l10n.issuesObjectName, value: issue.objectName!),
          const Divider(height: 32),
          Text(l10n.issuesTimeline, style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 8),
          ...issue.activities.map(
            (activity) => Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(activity.description ?? ''),
                  Text(activity.createdAt, style: Theme.of(context).textTheme.bodySmall),
                ],
              ),
            ),
          ),
          const Divider(height: 32),
          Text(l10n.issuesComments, style: Theme.of(context).textTheme.titleMedium),
          ...issue.comments.map(
            (comment) => ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.person_outline),
              title: Text(comment.userName ?? ''),
              subtitle: Text(comment.body),
            ),
          ),
          Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _commentController,
                  decoration: InputDecoration(hintText: l10n.issuesAddComment),
                ),
              ),
              IconButton(
                icon: _postingComment
                    ? const SizedBox(height: 16, width: 16, child: CircularProgressIndicator(strokeWidth: 2))
                    : const Icon(Icons.send),
                onPressed: _postingComment ? null : () => _postComment(controller),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.icon, required this.label, required this.value});

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        children: [
          Icon(icon, size: 20, color: Theme.of(context).colorScheme.outline),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (value.isNotEmpty) Text(value),
                Text(label, style: Theme.of(context).textTheme.bodySmall),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
