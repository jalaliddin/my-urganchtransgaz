import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

/// Same brand as the web app (`frontend/src/plugins/vuetify.ts`): navy
/// primary in light mode, a lighter blue in dark mode — one product, one
/// look, regardless of which client renders it. The tertiary green comes
/// from the corporate logo's flame mark (`assets/images/logo.svg`) rather
/// than Material's auto-derived tertiary, so the two flame-adjacent colors
/// (blue, green) both trace back to the same brand asset.
class AppTheme {
  AppTheme._();

  static const _lightPrimary = Color(0xFF1E3A5F);
  static const _darkPrimary = Color(0xFF3B6EA5);
  static const _brandGreen = Color(0xFF9ACC48);
  static const _radius = 16.0;

  static ThemeData light() {
    final colorScheme = ColorScheme.fromSeed(
      seedColor: _lightPrimary,
      brightness: Brightness.light,
    ).copyWith(primary: _lightPrimary, tertiary: _brandGreen, tertiaryContainer: const Color(0xFFE3F1C8));

    return _themeFrom(colorScheme);
  }

  static ThemeData dark() {
    final colorScheme = ColorScheme.fromSeed(
      seedColor: _darkPrimary,
      brightness: Brightness.dark,
    ).copyWith(primary: _darkPrimary, tertiary: _brandGreen);

    return _themeFrom(colorScheme);
  }

  static ThemeData _themeFrom(ColorScheme colorScheme) {
    final base = ThemeData(useMaterial3: true, colorScheme: colorScheme);
    final textTheme = GoogleFonts.interTextTheme(base.textTheme).copyWith(
      titleLarge: GoogleFonts.inter(textStyle: base.textTheme.titleLarge, fontWeight: FontWeight.w700),
      titleMedium: GoogleFonts.inter(textStyle: base.textTheme.titleMedium, fontWeight: FontWeight.w600),
      headlineSmall: GoogleFonts.inter(textStyle: base.textTheme.headlineSmall, fontWeight: FontWeight.w700),
    );
    final outline = RoundedRectangleBorder(borderRadius: BorderRadius.circular(_radius));

    return base.copyWith(
      textTheme: textTheme,
      scaffoldBackgroundColor: colorScheme.surface,
      appBarTheme: AppBarTheme(
        backgroundColor: colorScheme.primary,
        foregroundColor: colorScheme.onPrimary,
        centerTitle: false,
        elevation: 0,
        scrolledUnderElevation: 2,
        titleTextStyle: GoogleFonts.inter(
          fontSize: 20,
          fontWeight: FontWeight.w700,
          color: colorScheme.onPrimary,
        ),
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: colorScheme.surface,
        indicatorColor: colorScheme.primaryContainer,
        elevation: 3,
        height: 64,
        labelTextStyle: WidgetStateProperty.resolveWith(
          (states) => GoogleFonts.inter(
            fontSize: 12,
            fontWeight: states.contains(WidgetState.selected) ? FontWeight.w600 : FontWeight.w500,
          ),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: colorScheme.outlineVariant),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: colorScheme.primary, width: 2),
        ),
        filled: true,
        fillColor: colorScheme.surfaceContainerHighest.withValues(alpha: 0.4),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          padding: const EdgeInsets.symmetric(vertical: 14),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          textStyle: GoogleFonts.inter(fontWeight: FontWeight.w600, fontSize: 15),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          padding: const EdgeInsets.symmetric(vertical: 14),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          textStyle: GoogleFonts.inter(fontWeight: FontWeight.w600),
        ),
      ),
      cardTheme: CardThemeData(
        elevation: 0,
        margin: EdgeInsets.zero,
        color: colorScheme.surfaceContainerLow,
        shape: outline,
      ),
      chipTheme: base.chipTheme.copyWith(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      ),
      dialogTheme: DialogThemeData(shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20))),
      bottomSheetTheme: BottomSheetThemeData(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
      ),
      snackBarTheme: SnackBarThemeData(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        behavior: SnackBarBehavior.floating,
      ),
      listTileTheme: ListTileThemeData(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        iconColor: colorScheme.primary,
      ),
      dividerTheme: DividerThemeData(color: colorScheme.outlineVariant.withValues(alpha: 0.5)),
    );
  }
}
