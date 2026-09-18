import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';

import '../../../core/widgets/async_value_view.dart';
import '../../../core/widgets/responsive_body.dart';
import '../../../l10n/app_localizations.dart';
import '../domain/task.dart';
import 'tasks_controller.dart';

class TaskDetailScreen extends ConsumerStatefulWidget {
  const TaskDetailScreen({super.key, required this.id});

  final int id;

  @override
  ConsumerState<TaskDetailScreen> createState() => _TaskDetailScreenState();
}

class _TaskDetailScreenState extends ConsumerState<TaskDetailScreen> {
  final _commentController = TextEditingController();
  double? _pendingProgress;

  @override
  void dispose() {
    _commentController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final taskState = ref.watch(taskDetailProvider(widget.id));

    return Scaffold(
      appBar: AppBar(title: Text(l10n.tasksTitle)),
      body: ResponsiveBody(
        child: AsyncValueView(
          value: taskState,
          onRetry: () => ref.invalidate(taskDetailProvider(widget.id)),
          data: (context, task) => _buildBody(context, task, l10n),
        ),
      ),
    );
  }

  Widget _buildBody(BuildContext context, Task task, AppLocalizations l10n) {
    final controller = ref.read(taskDetailProvider(widget.id).notifier);
    final progress = _pendingProgress ?? task.progress.toDouble();

    return RefreshIndicator(
      onRefresh: () => controller.refresh(),
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(task.title, style: Theme.of(context).textTheme.headlineSmall),
          const SizedBox(height: 4),
          Wrap(
            spacing: 8,
            children: [
              Chip(label: Text(_statusLabel(l10n, task.status))),
              Chip(label: Text(_priorityLabel(l10n, task.priority))),
              if (task.dueDate != null) Chip(avatar: const Icon(Icons.event, size: 16), label: Text(task.dueDate!)),
            ],
          ),
          if (task.description != null) ...[
            const SizedBox(height: 16),
            Text(task.description!),
          ],
          if (task.creatorName != null) ...[
            const SizedBox(height: 12),
            Text('${l10n.tasksCreator}: ${task.creatorName}', style: Theme.of(context).textTheme.bodySmall),
          ],
          if (task.assignees.isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(
              '${l10n.tasksAssignees}: ${task.assignees.map((e) => e.fullName).join(', ')}',
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
          const Divider(height: 32),
          Text(l10n.tasksProgress, style: Theme.of(context).textTheme.titleMedium),
          Row(
            children: [
              Expanded(
                child: Slider(
                  value: progress,
                  min: 0,
                  max: 100,
                  divisions: 20,
                  label: '${progress.round()}%',
                  onChanged: task.isActionable ? (value) => setState(() => _pendingProgress = value) : null,
                ),
              ),
              SizedBox(width: 48, child: Text('${progress.round()}%', textAlign: TextAlign.end)),
            ],
          ),
          if (task.isActionable)
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: _pendingProgress == null
                        ? null
                        : () async {
                            await controller.updateProgress(_pendingProgress!.round());
                            setState(() => _pendingProgress = null);
                          },
                    child: Text(l10n.tasksUpdateProgress),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: FilledButton(
                    onPressed: () => _confirmComplete(context, controller, l10n),
                    child: Text(l10n.tasksMarkComplete),
                  ),
                ),
              ],
            ),
          if (task.result != null) ...[
            const SizedBox(height: 12),
            Text(l10n.tasksResult, style: Theme.of(context).textTheme.titleSmall),
            Text(task.result!),
          ],
          const Divider(height: 32),
          Text(l10n.tasksAttachments, style: Theme.of(context).textTheme.titleMedium),
          ...task.attachments.map(
            (attachment) => ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.attach_file),
              title: Text(attachment.originalName),
              onTap: () => _openAttachment(attachment.downloadUrl, attachment.originalName),
            ),
          ),
          OutlinedButton.icon(
            icon: const Icon(Icons.upload_file),
            label: Text(l10n.documentsChooseFile),
            onPressed: () => _uploadAttachment(context, task.id),
          ),
          const Divider(height: 32),
          Text(l10n.tasksComments, style: Theme.of(context).textTheme.titleMedium),
          ...task.comments.map(
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
                  decoration: InputDecoration(hintText: l10n.tasksAddComment),
                ),
              ),
              IconButton(
                icon: const Icon(Icons.send),
                onPressed: () async {
                  final text = _commentController.text.trim();
                  if (text.isEmpty) return;
                  _commentController.clear();
                  await controller.addComment(text);
                },
              ),
            ],
          ),
        ],
      ),
    );
  }

  Future<void> _confirmComplete(BuildContext context, TaskDetailController controller, AppLocalizations l10n) async {
    final resultController = TextEditingController();

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(l10n.tasksMarkComplete),
        content: TextField(
          controller: resultController,
          decoration: InputDecoration(labelText: l10n.tasksResult),
          maxLines: 3,
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: Text(l10n.commonCancel)),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: Text(l10n.commonConfirm)),
        ],
      ),
    );

    if (confirmed == true) {
      await controller.complete(result: resultController.text.trim().isEmpty ? null : resultController.text.trim());
    }
  }

  Future<void> _uploadAttachment(BuildContext context, int taskId) async {
    final files = await FilePicker.pickFiles();

    if (files.isEmpty) {
      return;
    }

    final path = files.first.path;
    final name = files.first.name;

    if (path == null) {
      return;
    }

    final repository = ref.read(tasksRepositoryProvider);
    await repository.uploadAttachment(taskId, path, name);
    await ref.read(taskDetailProvider(taskId).notifier).refresh();
  }

  Future<void> _openAttachment(String downloadUrl, String fileName) async {
    final messenger = ScaffoldMessenger.of(context);
    final l10n = AppLocalizations.of(context);

    try {
      final bytes = await ref.read(tasksRepositoryProvider).downloadAttachment(downloadUrl);
      final dir = await getTemporaryDirectory();
      final file = File('${dir.path}/$fileName');
      await file.writeAsBytes(bytes);
      await OpenFilex.open(file.path);
    } catch (_) {
      messenger.showSnackBar(SnackBar(content: Text(l10n.commonFileOpenError)));
    }
  }

  String _statusLabel(AppLocalizations l10n, String status) {
    return switch (status) {
      'new' => l10n.statusNew,
      'in_progress' => l10n.statusInProgress,
      'waiting' => l10n.statusWaiting,
      'completed' => l10n.statusCompleted,
      'cancelled' => l10n.statusCancelled,
      'overdue' => l10n.statusOverdue,
      _ => status,
    };
  }

  String _priorityLabel(AppLocalizations l10n, String priority) {
    return switch (priority) {
      'low' => l10n.statusLow,
      'high' => l10n.statusHigh,
      'urgent' => l10n.statusUrgent,
      _ => l10n.statusNormal,
    };
  }
}
