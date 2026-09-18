import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';

import '../../../core/widgets/async_value_view.dart';
import '../../../core/widgets/responsive_body.dart';
import '../../../l10n/app_localizations.dart';
import '../domain/employee_document.dart';
import 'documents_controller.dart';

class DocumentsScreen extends ConsumerWidget {
  const DocumentsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final documents = ref.watch(documentsControllerProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.documentsTitle)),
      floatingActionButton: FloatingActionButton(
        onPressed: () => _showUploadSheet(context, ref),
        child: const Icon(Icons.add),
      ),
      body: ResponsiveBody(
        child: RefreshIndicator(
        onRefresh: () => ref.read(documentsControllerProvider.notifier).refresh(),
        child: AsyncValueView(
          value: documents,
          onRetry: () => ref.invalidate(documentsControllerProvider),
          data: (context, items) {
            if (items.isEmpty) {
              return ListView(
                children: [
                  SizedBox(height: 400, child: EmptyStateView(message: l10n.documentsNone, icon: Icons.folder_open_outlined)),
                ],
              );
            }

            return ListView.separated(
              itemCount: items.length,
              separatorBuilder: (context, index) => const Divider(height: 1),
              itemBuilder: (context, index) => _DocumentTile(document: items[index]),
            );
          },
        ),
      ),
      ),
    );
  }

  void _showUploadSheet(BuildContext context, WidgetRef ref) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      builder: (context) => const _UploadDocumentSheet(),
    );
  }
}

class _DocumentTile extends ConsumerWidget {
  const _DocumentTile({required this.document});

  final EmployeeDocument document;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);

    return ListTile(
      leading: Icon(_statusIcon(document.status), color: _statusColor(context, document.status)),
      title: Text(document.title),
      subtitle: Text(document.documentTypeName ?? ''),
      trailing: Text(document.expiryDate ?? l10n.documentsNoExpiry, style: Theme.of(context).textTheme.bodySmall),
      onTap: () => _download(context, ref, document),
    );
  }

  IconData _statusIcon(String status) {
    return switch (status) {
      'approved' => Icons.check_circle,
      'rejected' => Icons.cancel,
      _ => Icons.hourglass_empty,
    };
  }

  Color _statusColor(BuildContext context, String status) {
    return switch (status) {
      'approved' => Colors.green,
      'rejected' => Colors.red,
      _ => Colors.orange,
    };
  }

  Future<void> _download(BuildContext context, WidgetRef ref, EmployeeDocument document) async {
    final messenger = ScaffoldMessenger.of(context);

    try {
      final bytes = await ref.read(documentsRepositoryProvider).download(document.downloadUrl);
      final dir = await getTemporaryDirectory();
      final file = File('${dir.path}/${document.title}');
      await file.writeAsBytes(bytes);
      await OpenFilex.open(file.path);
    } catch (_) {
      messenger.showSnackBar(const SnackBar(content: Text('Faylni ochib bo\'lmadi.')));
    }
  }
}

class _UploadDocumentSheet extends ConsumerStatefulWidget {
  const _UploadDocumentSheet();

  @override
  ConsumerState<_UploadDocumentSheet> createState() => _UploadDocumentSheetState();
}

class _UploadDocumentSheetState extends ConsumerState<_UploadDocumentSheet> {
  final _titleController = TextEditingController();
  int? _documentTypeId;
  String? _filePath;
  String? _fileName;
  bool _isSubmitting = false;

  @override
  void dispose() {
    _titleController.dispose();
    super.dispose();
  }

  Future<void> _pickFile() async {
    final files = await FilePicker.pickFiles(type: FileType.custom, allowedExtensions: ['pdf', 'jpg', 'jpeg', 'png']);

    if (files.isEmpty) {
      return;
    }

    setState(() {
      _filePath = files.first.path;
      _fileName = files.first.name;
    });
  }

  Future<void> _submit() async {
    if (_documentTypeId == null || _filePath == null || _titleController.text.trim().isEmpty) {
      return;
    }

    setState(() => _isSubmitting = true);

    try {
      await ref.read(documentsRepositoryProvider).upload(
            documentTypeId: _documentTypeId!,
            title: _titleController.text.trim(),
            filePath: _filePath!,
            fileName: _fileName!,
          );
      await ref.read(documentsControllerProvider.notifier).refresh();

      if (mounted) {
        Navigator.pop(context);
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    } finally {
      if (mounted) {
        setState(() => _isSubmitting = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final types = ref.watch(documentTypesProvider);

    return Padding(
      padding: EdgeInsets.only(
        left: 16,
        right: 16,
        top: 16,
        bottom: MediaQuery.of(context).viewInsets.bottom + 16,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(l10n.documentsUpload, style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 16),
          types.when(
            data: (typeList) => DropdownButtonFormField<int>(
              initialValue: _documentTypeId,
              decoration: InputDecoration(labelText: l10n.documentsType),
              items: typeList.map((type) => DropdownMenuItem(value: type.id, child: Text(type.name))).toList(),
              onChanged: (value) => setState(() => _documentTypeId = value),
            ),
            loading: () => const LinearProgressIndicator(),
            error: (error, stackTrace) => Text(l10n.commonErrorGeneric),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _titleController,
            decoration: InputDecoration(labelText: l10n.documentsDocTitle),
          ),
          const SizedBox(height: 12),
          OutlinedButton.icon(
            icon: const Icon(Icons.attach_file),
            label: Text(_fileName ?? l10n.documentsChooseFile),
            onPressed: _pickFile,
          ),
          const SizedBox(height: 16),
          FilledButton(
            onPressed: _isSubmitting ? null : _submit,
            child: _isSubmitting
                ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                : Text(l10n.commonSave),
          ),
        ],
      ),
    );
  }
}
