import 'package:flutter/material.dart';

import '../theme/status_colors.dart';

/// A small tonal label for a record's state: "Jarayonda", "Ochiq",
/// "Tasdiqlandi". Colour comes from [StatusTone], so the same meaning looks
/// the same everywhere.
class StatusPill extends StatelessWidget {
  const StatusPill({super.key, required this.label, required this.tone, this.icon});

  final String label;
  final StatusTone tone;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final colors = StatusColors.of(context);
    final foreground = colors.fg(tone);

    return DecoratedBox(
      decoration: BoxDecoration(color: colors.bg(tone), borderRadius: BorderRadius.circular(99)),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (icon != null) ...[
              Icon(icon, size: 14, color: foreground),
              const SizedBox(width: 4),
            ] else ...[
              Container(
                width: 6,
                height: 6,
                decoration: BoxDecoration(color: foreground, shape: BoxShape.circle),
              ),
              const SizedBox(width: 6),
            ],
            Flexible(
              child: Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.labelMedium!.copyWith(color: foreground),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
