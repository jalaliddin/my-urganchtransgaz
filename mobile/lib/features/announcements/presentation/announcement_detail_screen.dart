import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';

import '../../../core/network/api_providers.dart';
import '../../../core/widgets/async_value_view.dart';
import '../../../core/widgets/responsive_body.dart';
import '../../../l10n/app_localizations.dart';
import '../data/announcements_repository.dart';
import 'announcements_controller.dart';

class AnnouncementDetailScreen extends ConsumerWidget {
  const AnnouncementDetailScreen({super.key, required this.id});

  final int id;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final detail = ref.watch(announcementDetailProvider(id));
    final client = ref.watch(apiClientProvider);
    final repository = ref.watch(announcementsRepositoryProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.announcementsTitle)),
      body: ResponsiveBody(
        child: AsyncValueView(
        value: detail,
        onRetry: () => ref.invalidate(announcementDetailProvider(id)),
        data: (context, announcement) {
          return SingleChildScrollView(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(announcement.title, style: Theme.of(context).textTheme.headlineSmall),
                const SizedBox(height: 4),
                if (announcement.authorName != null)
                  Text(
                    '${l10n.announcementsAuthor}: ${announcement.authorName}',
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                const SizedBox(height: 16),
                if (announcement.hasImage)
                  FutureBuilder<Map<String, String>>(
                    future: client.authHeaders(),
                    builder: (context, snapshot) {
                      if (!snapshot.hasData) {
                        return const SizedBox.shrink();
                      }

                      return ClipRRect(
                        borderRadius: BorderRadius.circular(8),
                        child: Image.network(
                          repository.imageUrl(announcement.id),
                          headers: snapshot.data,
                          fit: BoxFit.cover,
                          errorBuilder: (context, error, stackTrace) => const SizedBox.shrink(),
                        ),
                      );
                    },
                  ),
                if (announcement.hasImage) const SizedBox(height: 16),
                Text(announcement.content, style: Theme.of(context).textTheme.bodyLarge),
                if (announcement.hasAttachment) ...[
                  const SizedBox(height: 24),
                  OutlinedButton.icon(
                    icon: const Icon(Icons.attach_file),
                    label: Text(announcement.attachmentName ?? l10n.documentsDownload),
                    onPressed: () => _downloadAttachment(context, repository, announcement.id, announcement.attachmentName),
                  ),
                ],
              ],
            ),
          );
        },
      ),
      ),
    );
  }

  Future<void> _downloadAttachment(
    BuildContext context,
    AnnouncementsRepository repository,
    int announcementId,
    String? fileName,
  ) async {
    final messenger = ScaffoldMessenger.of(context);
    final l10n = AppLocalizations.of(context);

    try {
      final bytes = await repository.downloadAttachment(announcementId);
      final dir = await getTemporaryDirectory();
      final file = File('${dir.path}/${fileName ?? 'attachment'}');
      await file.writeAsBytes(bytes);
      await OpenFilex.open(file.path);
    } catch (_) {
      messenger.showSnackBar(SnackBar(content: Text(l10n.commonFileOpenError)));
    }
  }
}
