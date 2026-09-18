import 'package:flutter/material.dart';

/// Centers [child] and caps its width once the window is wider than a
/// phone — a tablet, a foldable unfolded, or this app running resized in a
/// desktop window. Below [maxWidth] this is a no-op (the child just fills
/// the screen as it always did); above it, the content stays a readable
/// column instead of stretching edge-to-edge, per Flutter's own adaptive-
/// layout guidance (base the decision on the window's actual width via
/// `LayoutBuilder`, never on a guessed "this is a phone/tablet" check).
///
/// Wrap a screen's `body:` with this — not the whole `Scaffold` — so the
/// `AppBar`/bottom nav chrome still spans the full window width and only
/// the scrollable content column is constrained.
class ResponsiveBody extends StatelessWidget {
  const ResponsiveBody({super.key, required this.child, this.maxWidth = 640});

  final Widget child;
  final double maxWidth;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        if (constraints.maxWidth <= maxWidth) {
          return child;
        }

        return Center(
          child: ConstrainedBox(
            constraints: BoxConstraints(maxWidth: maxWidth),
            child: child,
          ),
        );
      },
    );
  }
}
