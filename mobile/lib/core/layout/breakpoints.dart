import 'package:flutter/widgets.dart';

/// Material 3 window size classes. Layout decisions are made from the
/// width the app actually has — never from "is this a phone" — so a
/// resized desktop window, a tablet and an unfolded foldable all behave
/// the same way.
enum WindowSize { compact, medium, expanded }

WindowSize windowSizeForWidth(double width) {
  if (width < 600) return WindowSize.compact;
  if (width < 840) return WindowSize.medium;

  return WindowSize.expanded;
}

WindowSize windowSizeOf(BuildContext context) =>
    windowSizeForWidth(MediaQuery.sizeOf(context).width);
