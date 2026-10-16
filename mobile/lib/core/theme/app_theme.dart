import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'status_colors.dart';

/// Same brand as the web app (`frontend/src/plugins/vuetify.ts`): navy
/// primary, with the corporate flame's blue and green as accents. Content
/// sits on white cards over a cool off-white page, separated by hairline
/// borders rather than shadows, and app bars take the page colour instead
/// of a solid navy band.
class AppTheme {
  AppTheme._();

  static const _navy = Color(0xFF1E3A5F);
  static const _fontFamily = 'Inter';

  static ThemeData light() {
    final base = ColorScheme.fromSeed(seedColor: _navy, brightness: Brightness.light);
    final colorScheme = base.copyWith(
      primary: _navy,
      onPrimary: Colors.white,
      primaryContainer: const Color(0xFFD9E5F4),
      onPrimaryContainer: const Color(0xFF0F2440),
      secondary: const Color(0xFF2F7FB5),
      secondaryContainer: const Color(0xFFDCEEFA),
      onSecondaryContainer: const Color(0xFF0B3A57),
      tertiary: const Color(0xFF5E8F1F),
      tertiaryContainer: const Color(0xFFE5F2D2),
      onTertiaryContainer: const Color(0xFF243A08),
      error: const Color(0xFFC62828),
      surface: const Color(0xFFF4F6F9),
      onSurface: const Color(0xFF16202C),
      onSurfaceVariant: const Color(0xFF55606E),
      surfaceContainerLowest: Colors.white,
      surfaceContainerLow: const Color(0xFFF0F3F7),
      surfaceContainer: const Color(0xFFEAEEF3),
      surfaceContainerHigh: const Color(0xFFE3E8EE),
      surfaceContainerHighest: const Color(0xFFDCE2E9),
      outline: const Color(0xFF8A95A3),
      outlineVariant: const Color(0xFFDDE3EA),
    );

    return _themeFrom(colorScheme, StatusColors.light);
  }

  static ThemeData dark() {
    final base = ColorScheme.fromSeed(seedColor: _navy, brightness: Brightness.dark);
    final colorScheme = base.copyWith(
      surface: const Color(0xFF0F141A),
      surfaceContainerLowest: const Color(0xFF161D25),
      surfaceContainerLow: const Color(0xFF19212A),
      surfaceContainer: const Color(0xFF1E2731),
      surfaceContainerHigh: const Color(0xFF253039),
      surfaceContainerHighest: const Color(0xFF2D3943),
      outlineVariant: const Color(0xFF2C3640),
      tertiary: const Color(0xFFA7D46A),
    );

    return _themeFrom(colorScheme, StatusColors.dark);
  }

  static TextTheme _textTheme(TextTheme base, ColorScheme colors) {
    TextStyle? style(TextStyle? s, double size, FontWeight weight, {double height = 1.3, double spacing = 0}) =>
        s?.copyWith(fontFamily: _fontFamily, fontSize: size, fontWeight: weight, height: height, letterSpacing: spacing);

    return base
        .copyWith(
          displaySmall: style(base.displaySmall, 34, FontWeight.w700, height: 1.15, spacing: -0.6),
          headlineMedium: style(base.headlineMedium, 28, FontWeight.w700, height: 1.2, spacing: -0.5),
          headlineSmall: style(base.headlineSmall, 24, FontWeight.w700, height: 1.25, spacing: -0.4),
          titleLarge: style(base.titleLarge, 20, FontWeight.w700, height: 1.3, spacing: -0.3),
          titleMedium: style(base.titleMedium, 16, FontWeight.w600, height: 1.35, spacing: -0.1),
          titleSmall: style(base.titleSmall, 14, FontWeight.w600, height: 1.35),
          bodyLarge: style(base.bodyLarge, 16, FontWeight.w400, height: 1.45),
          bodyMedium: style(base.bodyMedium, 14, FontWeight.w400, height: 1.45),
          bodySmall: style(base.bodySmall, 12.5, FontWeight.w400, height: 1.4),
          labelLarge: style(base.labelLarge, 14, FontWeight.w600, height: 1.3),
          labelMedium: style(base.labelMedium, 12.5, FontWeight.w600, height: 1.3),
          labelSmall: style(base.labelSmall, 11.5, FontWeight.w600, height: 1.3, spacing: 0.1),
        )
        .apply(bodyColor: colors.onSurface, displayColor: colors.onSurface);
  }

  static ThemeData _themeFrom(ColorScheme colors, StatusColors status) {
    final isDark = colors.brightness == Brightness.dark;
    final base = ThemeData(useMaterial3: true, colorScheme: colors, fontFamily: _fontFamily);
    final text = _textTheme(base.textTheme, colors);
    final hairline = BorderSide(color: colors.outlineVariant);

    final overlay = SystemUiOverlayStyle(
      statusBarColor: Colors.transparent,
      statusBarIconBrightness: isDark ? Brightness.light : Brightness.dark,
      statusBarBrightness: isDark ? Brightness.dark : Brightness.light,
      systemNavigationBarColor: Colors.transparent,
      // Contrast enforcement is left at the system default, so 3-button nav
      // keeps its scrim over dark screens like the photo viewer.
      systemNavigationBarIconBrightness: isDark ? Brightness.light : Brightness.dark,
    );

    return base.copyWith(
      textTheme: text,
      scaffoldBackgroundColor: colors.surface,
      extensions: [status],
      appBarTheme: AppBarTheme(
        backgroundColor: colors.surface,
        foregroundColor: colors.onSurface,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: false,
        titleSpacing: 20,
        systemOverlayStyle: overlay,
        titleTextStyle: text.titleLarge,
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: colors.surfaceContainerLowest,
        surfaceTintColor: Colors.transparent,
        indicatorColor: colors.primaryContainer,
        elevation: 0,
        height: 68,
        labelTextStyle: WidgetStateProperty.resolveWith(
          (states) => text.labelMedium!.copyWith(
            color: states.contains(WidgetState.selected) ? colors.onSurface : colors.onSurfaceVariant,
            fontWeight: states.contains(WidgetState.selected) ? FontWeight.w700 : FontWeight.w500,
          ),
        ),
        iconTheme: WidgetStateProperty.resolveWith(
          (states) => IconThemeData(
            size: 24,
            color: states.contains(WidgetState.selected) ? colors.onPrimaryContainer : colors.onSurfaceVariant,
          ),
        ),
      ),
      navigationRailTheme: NavigationRailThemeData(
        backgroundColor: colors.surfaceContainerLowest,
        indicatorColor: colors.primaryContainer,
        selectedLabelTextStyle: text.labelLarge,
        unselectedLabelTextStyle: text.labelLarge!.copyWith(color: colors.onSurfaceVariant, fontWeight: FontWeight.w500),
      ),
      cardTheme: CardThemeData(
        elevation: 0,
        margin: EdgeInsets.zero,
        color: colors.surfaceContainerLowest,
        surfaceTintColor: Colors.transparent,
        clipBehavior: Clip.antiAlias,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18), side: hairline),
      ),
      listTileTheme: ListTileThemeData(
        contentPadding: const EdgeInsets.symmetric(horizontal: 16),
        minVerticalPadding: 12,
        iconColor: colors.onSurfaceVariant,
        titleTextStyle: text.bodyLarge!.copyWith(fontWeight: FontWeight.w500),
        subtitleTextStyle: text.bodyMedium!.copyWith(color: colors.onSurfaceVariant),
        leadingAndTrailingTextStyle: text.labelMedium!.copyWith(color: colors.onSurfaceVariant),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: colors.surfaceContainerLowest,
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: hairline),
        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: hairline),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: BorderSide(color: colors.primary, width: 1.6),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: BorderSide(color: colors.error),
        ),
        hintStyle: text.bodyLarge!.copyWith(color: colors.onSurfaceVariant),
        labelStyle: text.bodyLarge!.copyWith(color: colors.onSurfaceVariant),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          minimumSize: const Size(64, 52),
          padding: const EdgeInsets.symmetric(horizontal: 20),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
          textStyle: text.labelLarge!.copyWith(fontSize: 15),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          minimumSize: const Size(64, 52),
          padding: const EdgeInsets.symmetric(horizontal: 20),
          side: BorderSide(color: colors.outlineVariant),
          backgroundColor: colors.surfaceContainerLowest,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
          textStyle: text.labelLarge!.copyWith(fontSize: 15),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          textStyle: text.labelLarge,
        ),
      ),
      iconButtonTheme: IconButtonThemeData(
        style: IconButton.styleFrom(minimumSize: const Size(44, 44)),
      ),
      floatingActionButtonTheme: FloatingActionButtonThemeData(
        backgroundColor: colors.primary,
        foregroundColor: colors.onPrimary,
        elevation: 2,
        focusElevation: 2,
        hoverElevation: 3,
        highlightElevation: 2,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
        extendedTextStyle: text.labelLarge!.copyWith(fontSize: 15),
      ),
      chipTheme: ChipThemeData(
        backgroundColor: colors.surfaceContainerLowest,
        selectedColor: colors.primaryContainer,
        side: hairline,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        labelStyle: text.labelLarge!.copyWith(fontWeight: FontWeight.w500),
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
        showCheckmark: false,
      ),
      segmentedButtonTheme: SegmentedButtonThemeData(
        style: SegmentedButton.styleFrom(
          backgroundColor: colors.surfaceContainerLowest,
          selectedBackgroundColor: colors.primaryContainer,
          selectedForegroundColor: colors.onPrimaryContainer,
          side: BorderSide(color: colors.outlineVariant),
          textStyle: text.labelLarge,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
      ),
      tabBarTheme: TabBarThemeData(
        labelColor: colors.primary,
        unselectedLabelColor: colors.onSurfaceVariant,
        labelStyle: text.labelLarge,
        unselectedLabelStyle: text.labelLarge!.copyWith(fontWeight: FontWeight.w500),
        indicatorColor: colors.primary,
        indicatorSize: TabBarIndicatorSize.label,
        dividerColor: colors.outlineVariant,
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: colors.surfaceContainerLowest,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
        titleTextStyle: text.titleLarge,
      ),
      bottomSheetTheme: BottomSheetThemeData(
        backgroundColor: colors.surfaceContainerLowest,
        surfaceTintColor: Colors.transparent,
        showDragHandle: true,
        dragHandleColor: colors.outlineVariant,
        shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(28))),
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        contentTextStyle: text.bodyMedium!.copyWith(color: colors.onInverseSurface),
      ),
      dividerTheme: DividerThemeData(color: colors.outlineVariant, thickness: 1, space: 1),
      progressIndicatorTheme: ProgressIndicatorThemeData(
        color: colors.primary,
        linearTrackColor: colors.surfaceContainerHighest,
        circularTrackColor: Colors.transparent,
        linearMinHeight: 6,
        borderRadius: BorderRadius.circular(99),
      ),
      badgeTheme: BadgeThemeData(backgroundColor: colors.error, textStyle: text.labelSmall),
    );
  }
}
