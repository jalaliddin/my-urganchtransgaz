import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';

import '../../../core/formatting/dates.dart';
import '../../../core/theme/status_colors.dart';
import '../../../core/widgets/async_value_view.dart';
import '../../../core/widgets/detail_rows.dart';
import '../../../core/widgets/responsive_body.dart';
import '../../../core/widgets/section.dart';
import '../../../core/widgets/status_pill.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/utils/uploads.dart';
import '../../../l10n/app_localizations.dart';
import '../domain/task.dart';
import 'task_category_chip.dart';
import 'task_labels.dart';
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
    final scheme = Theme.of(context).colorScheme;
    final text = Theme.of(context).textTheme;
    final status = StatusColors.of(context);
    final due = AppDates.parse(task.dueDate);
    final priorityTone = taskPriorityTone(task.priority);
    const gap = SizedBox(height: 16);

    return RefreshIndicator(
      onRefresh: () => controller.refresh(),
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
        children: [
          Text(task.title, style: text.headlineSmall),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              StatusPill(label: taskStatusLabel(l10n, task.status), tone: taskStatusTone(task.status)),
              StatusPill(
                label: taskPriorityLabel(l10n, task.priority),
                tone: priorityTone ?? StatusTone.neutral,
                icon: Icons.flag_outlined,
              ),
              if (task.category != null) TaskCategoryChip(category: task.category!),
            ],
          ),
          gap,
          GroupCard(
            dividerIndent: 54,
            children: [
              if (due != null)
                InfoRow(
                  icon: Icons.event_outlined,
                  label: l10n.tasksDueDate,
                  value: AppDates.long(context, due),
                  trailing: task.isActionable
                      ? StatusPill(
                          label: AppDates.due(context, due),
                          tone: AppDates.daysFromToday(due) < 0
                              ? StatusTone.danger
                              : AppDates.daysFromToday(due) <= 1
                                  ? StatusTone.warning
                                  : StatusTone.neutral,
                        )
                      : null,
                ),
              if (task.creatorName != null)
                InfoRow(icon: Icons.person_outline, label: l10n.tasksCreator, value: task.creatorName!),
              if (task.assignees.isNotEmpty)
                InfoRow(
                  icon: Icons.groups_outlined,
                  label: l10n.tasksAssignees,
                  value: task.assignees.map((e) => e.fullName).join(', '),
                ),
            ],
          ),
          if (task.description != null) ...[
            gap,
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Text(task.description!, style: text.bodyLarge),
              ),
            ),
          ],
          SectionHeader(title: l10n.tasksProgress),
          Card(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 14, 16, 16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      Text(
                        '${progress.round()}%',
                        style: text.headlineMedium!.copyWith(fontFeatures: const [FontFeature.tabularFigures()]),
                      ),
                      const Spacer(),
                      if (_pendingProgress != null)
                        FilledButton.tonal(
                          style: FilledButton.styleFrom(minimumSize: const Size(0, 40)),
                          onPressed: () async {
                            await controller.updateProgress(_pendingProgress!.round());
                            setState(() => _pendingProgress = null);
                          },
                          child: Text(l10n.tasksUpdateProgress),
                        ),
                    ],
                  ),
                  Slider(
                    value: progress,
                    min: 0,
                    max: 100,
                    divisions: 20,
                    label: '${progress.round()}%',
                    onChanged: task.isActionable ? (value) => setState(() => _pendingProgress = value) : null,
                  ),
                  if (task.isActionable) ...[
                    const SizedBox(height: 4),
                    FilledButton.icon(
                      onPressed: () => _confirmComplete(context, controller, l10n),
                      icon: const Icon(Icons.check_circle_outline),
                      label: Text(l10n.tasksMarkComplete),
                    ),
                  ],
                  if (task.result != null) ...[
                    const SizedBox(height: 16),
                    Text(l10n.tasksResult, style: text.labelLarge!.copyWith(color: scheme.onSurfaceVariant)),
                    const SizedBox(height: 4),
                    Text(task.result!, style: text.bodyLarge),
                  ],
                ],
              ),
            ),
          ),
          SectionHeader(title: l10n.tasksAttachments),
          Card(
            child: Column(
              children: [
                for (final attachment in task.attachments) ...[
                  ListTile(
                    leading: IconBadge(
                      icon: Icons.description_outlined,
                      color: status.fg(StatusTone.info),
                      background: status.bg(StatusTone.info),
                    ),
                    title: Text(attachment.originalName, maxLines: 1, overflow: TextOverflow.ellipsis),
                    trailing: Icon(Icons.download_outlined, color: scheme.onSurfaceVariant),
                    onTap: () => _openAttachment(attachment.downloadUrl, attachment.originalName),
                  ),
                  const Divider(indent: 72),
                ],
                ListTile(
                  leading: IconBadge(icon: Icons.attach_file, color: scheme.primary, background: scheme.primaryContainer),
                  title: Text(l10n.documentsChooseFile, style: text.bodyLarge!.copyWith(color: scheme.primary, fontWeight: FontWeight.w600)),
                  onTap: () => _uploadAttachment(context, task.id),
                ),
              ],
            ),
          ),
          SectionHeader(title: l10n.tasksComments),
          Card(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const SizedBox(height: 6),
                for (final comment in task.comments)
                  CommentTile(
                    author: comment.userName ?? '',
                    body: comment.body,
                    createdAt: AppDates.parse(comment.createdAt),
                  ),
                CommentComposer(
                  controller: _commentController,
                  hint: l10n.tasksAddComment,
                  onSend: () async {
                    final text = _commentController.text.trim();
                    if (text.isEmpty) return;
                    _commentController.clear();
                    await controller.addComment(text);
                  },
                ),
              ],
            ),
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
    final messenger = ScaffoldMessenger.of(context);
    final l10n = AppLocalizations.of(context);
    // Only what the server accepts, so a picked file is never refused later.
    final file = await FilePicker.pickFile(type: FileType.custom, allowedExtensions: documentExtensions);
    final path = file?.path;

    if (file == null || path == null) {
      return;
    }

    try {
      await ref.read(tasksRepositoryProvider).uploadAttachment(taskId, path, file.name);
      await ref.read(taskDetailProvider(taskId).notifier).refresh();
      messenger.showSnackBar(SnackBar(content: Text(l10n.commonFileUploaded)));
    } catch (e) {
      messenger.showSnackBar(SnackBar(content: Text(e is ApiException ? e.userMessage : l10n.commonUploadFailed)));
    }
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
}
