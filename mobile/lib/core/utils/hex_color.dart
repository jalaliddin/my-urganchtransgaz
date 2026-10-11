import 'dart:ui';

/// `#RRGGBB` (as admins set it on the web) to an opaque [Color]; null for
/// anything else, so a bad value falls back to the theme instead of
/// throwing.
Color? colorFromHex(String? hex) {
  final digits = hex?.trim().replaceFirst('#', '');
  if (digits == null || digits.length != 6) return null;

  final value = int.tryParse(digits, radix: 16);

  return value == null ? null : Color(0xFF000000 | value);
}
