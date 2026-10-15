import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_providers.dart';
import '../../../l10n/app_localizations.dart';
import '../domain/issue.dart';

/// The issue's photos as thumbnails; a tap opens one full screen with
/// pinch-to-zoom. Photos sit behind auth, so the token rides along.
class IssuePhotos extends ConsumerWidget {
  const IssuePhotos({super.key, required this.photos});

  final List<IssuePhoto> photos;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final headers = ref.watch(authHeadersProvider).value;
    if (headers == null) return const SizedBox(height: 88);

    final l10n = AppLocalizations.of(context);

    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: [
        for (final photo in photos)
          Semantics(
            button: true,
            label: l10n.issuesOpenPhoto,
            child: InkWell(
              borderRadius: BorderRadius.circular(8),
              onTap: () => _open(context, photo, headers),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: Image.network(
                  photo.url,
                  headers: headers,
                  width: 88,
                  height: 88,
                  fit: BoxFit.cover,
                  cacheWidth: 264,
                  errorBuilder: (context, error, stackTrace) => Container(
                    width: 88,
                    height: 88,
                    color: Theme.of(context).colorScheme.surfaceContainerHighest,
                    child: const Icon(Icons.broken_image_outlined),
                  ),
                ),
              ),
            ),
          ),
      ],
    );
  }

  void _open(BuildContext context, IssuePhoto photo, Map<String, String> headers) {
    showDialog<void>(
      context: context,
      builder: (context) => Dialog.fullscreen(
        backgroundColor: Colors.black,
        child: Stack(
          children: [
            Positioned.fill(
              child: InteractiveViewer(
                maxScale: 5,
                child: Center(child: Image.network(photo.url, headers: headers, fit: BoxFit.contain)),
              ),
            ),
            SafeArea(
              child: Align(
                alignment: Alignment.topRight,
                child: IconButton(
                  color: Colors.white,
                  tooltip: MaterialLocalizations.of(context).closeButtonTooltip,
                  icon: const Icon(Icons.close),
                  onPressed: () => Navigator.of(context).pop(),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
