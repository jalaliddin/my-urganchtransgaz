import 'package:flutter/material.dart';

/// A section title over a group of content, with an optional trailing
/// action such as "Barchasini ko'rish".
class SectionHeader extends StatelessWidget {
  const SectionHeader({super.key, required this.title, this.actionLabel, this.onAction});

  final String title;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(4, 8, 0, 8),
      child: Row(
        children: [
          Expanded(child: Text(title, style: Theme.of(context).textTheme.titleMedium)),
          if (actionLabel != null && onAction != null)
            TextButton(
              onPressed: onAction,
              style: TextButton.styleFrom(visualDensity: VisualDensity.compact),
              child: Text(actionLabel!),
            ),
        ],
      ),
    );
  }
}

/// Rows stacked in one rounded card, divided by inset hairlines — the
/// grouped list style of system settings screens.
class GroupCard extends StatelessWidget {
  const GroupCard({super.key, required this.children, this.dividerIndent = 16});

  final List<Widget> children;

  /// Where dividers start, so they line up with the row text rather than
  /// running under a leading icon.
  final double dividerIndent;

  @override
  Widget build(BuildContext context) {
    final rows = <Widget>[];
    for (var i = 0; i < children.length; i++) {
      if (i > 0) rows.add(Divider(indent: dividerIndent, height: 1));
      rows.add(children[i]);
    }

    return Card(child: Column(mainAxisSize: MainAxisSize.min, children: rows));
  }
}

/// A leading icon in a soft tinted square, for list rows and tiles.
class IconBadge extends StatelessWidget {
  const IconBadge({super.key, required this.icon, this.color, this.background, this.size = 40});

  final IconData icon;
  final Color? color;
  final Color? background;
  final double size;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: background ?? scheme.primaryContainer,
        borderRadius: BorderRadius.circular(size * 0.3),
      ),
      child: Icon(icon, size: size * 0.55, color: color ?? scheme.onPrimaryContainer),
    );
  }
}
