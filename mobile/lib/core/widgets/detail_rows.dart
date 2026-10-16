import 'package:flutter/material.dart';

import '../formatting/dates.dart';

/// One labelled fact on a detail page — "Muddati: 16 oktabr 2026" — for use
/// inside a [GroupCard].
class InfoRow extends StatelessWidget {
  const InfoRow({super.key, required this.icon, required this.label, required this.value, this.trailing});

  final IconData icon;
  final String label;
  final String value;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final text = Theme.of(context).textTheme;

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      child: Row(
        children: [
          Icon(icon, size: 22, color: scheme.onSurfaceVariant),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label, style: text.bodySmall!.copyWith(color: scheme.onSurfaceVariant)),
                const SizedBox(height: 2),
                Text(value, style: text.bodyLarge!.copyWith(fontWeight: FontWeight.w500)),
              ],
            ),
          ),
          if (trailing != null) ...[const SizedBox(width: 8), trailing!],
        ],
      ),
    );
  }
}

/// A comment as a message: initials, author and time on top, then the text
/// in a soft bubble.
class CommentTile extends StatelessWidget {
  const CommentTile({super.key, required this.author, required this.body, this.createdAt});

  final String author;
  final String body;
  final DateTime? createdAt;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final text = Theme.of(context).textTheme;
    final initials = author
        .split(' ')
        .where((part) => part.isNotEmpty)
        .take(2)
        .map((part) => part[0].toUpperCase())
        .join();

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 10, 16, 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          CircleAvatar(
            radius: 16,
            backgroundColor: scheme.secondaryContainer,
            child: Text(initials, style: text.labelSmall!.copyWith(color: scheme.onSecondaryContainer)),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(author, maxLines: 1, overflow: TextOverflow.ellipsis, style: text.labelLarge),
                    ),
                    if (createdAt != null)
                      Text(
                        AppDates.moment(context, createdAt!),
                        style: text.labelSmall!.copyWith(color: scheme.onSurfaceVariant, fontWeight: FontWeight.w500),
                      ),
                  ],
                ),
                const SizedBox(height: 6),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                  decoration: BoxDecoration(
                    color: scheme.surfaceContainerLow,
                    borderRadius: const BorderRadius.only(
                      topRight: Radius.circular(14),
                      bottomLeft: Radius.circular(14),
                      bottomRight: Radius.circular(14),
                      topLeft: Radius.circular(4),
                    ),
                  ),
                  child: Text(body, style: text.bodyMedium),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// A pill text field with a round send button, for adding a comment.
class CommentComposer extends StatelessWidget {
  const CommentComposer({super.key, required this.controller, required this.hint, required this.onSend});

  final TextEditingController controller;
  final String hint;
  final VoidCallback onSend;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final pill = OutlineInputBorder(
      borderRadius: BorderRadius.circular(24),
      borderSide: BorderSide(color: scheme.outlineVariant),
    );

    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 8, 8, 12),
      child: Row(
        children: [
          Expanded(
            child: TextField(
              controller: controller,
              minLines: 1,
              maxLines: 4,
              textInputAction: TextInputAction.newline,
              decoration: InputDecoration(
                hintText: hint,
                isDense: true,
                fillColor: scheme.surfaceContainerLow,
                contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                border: pill,
                enabledBorder: pill,
                focusedBorder: pill.copyWith(borderSide: BorderSide(color: scheme.primary, width: 1.6)),
              ),
            ),
          ),
          const SizedBox(width: 8),
          IconButton.filled(
            onPressed: onSend,
            icon: const Icon(Icons.arrow_upward),
            style: IconButton.styleFrom(minimumSize: const Size(46, 46)),
          ),
        ],
      ),
    );
  }
}
