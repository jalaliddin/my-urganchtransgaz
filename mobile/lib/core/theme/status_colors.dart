import 'package:flutter/material.dart';

/// One meaning per colour, used the same way on every screen: an open leak,
/// an overdue task and a rejected document are all [danger]; anything due
/// soon or waiting on review is [warning]; work in progress is [info];
/// finished or approved is [success].
enum StatusTone { danger, warning, info, success, neutral }

class StatusColors extends ThemeExtension<StatusColors> {
  const StatusColors({required this.foreground, required this.background});

  final Map<StatusTone, Color> foreground;
  final Map<StatusTone, Color> background;

  static const light = StatusColors(
    foreground: {
      StatusTone.danger: Color(0xFFB42318),
      StatusTone.warning: Color(0xFF9A5B00),
      StatusTone.info: Color(0xFF1D5FA8),
      StatusTone.success: Color(0xFF237A3B),
      StatusTone.neutral: Color(0xFF55606E),
    },
    background: {
      StatusTone.danger: Color(0xFFFDECEA),
      StatusTone.warning: Color(0xFFFFF2DC),
      StatusTone.info: Color(0xFFE3EEFB),
      StatusTone.success: Color(0xFFE5F4E8),
      StatusTone.neutral: Color(0xFFEAEEF3),
    },
  );

  static const dark = StatusColors(
    foreground: {
      StatusTone.danger: Color(0xFFFFB4AB),
      StatusTone.warning: Color(0xFFFFCF8A),
      StatusTone.info: Color(0xFFA9CBF5),
      StatusTone.success: Color(0xFF9DDCA9),
      StatusTone.neutral: Color(0xFFBEC7D1),
    },
    background: {
      StatusTone.danger: Color(0xFF4A1713),
      StatusTone.warning: Color(0xFF45300A),
      StatusTone.info: Color(0xFF16304F),
      StatusTone.success: Color(0xFF14361D),
      StatusTone.neutral: Color(0xFF2A333D),
    },
  );

  Color fg(StatusTone tone) => foreground[tone]!;

  Color bg(StatusTone tone) => background[tone]!;

  static StatusColors of(BuildContext context) => Theme.of(context).extension<StatusColors>() ?? light;

  @override
  StatusColors copyWith({Map<StatusTone, Color>? foreground, Map<StatusTone, Color>? background}) =>
      StatusColors(foreground: foreground ?? this.foreground, background: background ?? this.background);

  @override
  StatusColors lerp(ThemeExtension<StatusColors>? other, double t) {
    if (other is! StatusColors) return this;

    return StatusColors(
      foreground: {for (final tone in StatusTone.values) tone: Color.lerp(fg(tone), other.fg(tone), t)!},
      background: {for (final tone in StatusTone.values) tone: Color.lerp(bg(tone), other.bg(tone), t)!},
    );
  }
}
