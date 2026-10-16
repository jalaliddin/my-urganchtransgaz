import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../l10n/app_localizations.dart';
import '../network/api_exception.dart';

/// One consistent loading / error+retry / data rendering for every screen
/// backed by an [AsyncValue] — so a failed request always looks and
/// behaves the same way across features instead of each screen inventing
/// its own spinner/error widget.
class AsyncValueView<T> extends StatelessWidget {
  const AsyncValueView({
    super.key,
    required this.value,
    required this.data,
    this.onRetry,
  });

  final AsyncValue<T> value;
  final Widget Function(BuildContext context, T data) data;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    return value.when(
      data: (value) => data(context, value),
      error: (error, stackTrace) => _ErrorView(error: error, onRetry: onRetry),
      loading: () => const SkeletonList(),
      skipLoadingOnReload: true,
    );
  }
}

/// Placeholder rows shaped like the content that's coming, gently pulsing,
/// instead of a lone spinner on an empty page.
class SkeletonList extends StatefulWidget {
  const SkeletonList({super.key = const ValueKey('async-loading'), this.rows = 5});

  final int rows;

  @override
  State<SkeletonList> createState() => _SkeletonListState();
}

class _SkeletonListState extends State<SkeletonList> with SingleTickerProviderStateMixin {
  late final AnimationController _pulse = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 900),
  )..repeat(reverse: true);

  @override
  void dispose() {
    _pulse.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    Widget bar(double widthFactor, double height) => FractionallySizedBox(
          widthFactor: widthFactor,
          child: Container(
            height: height,
            decoration: BoxDecoration(
              color: scheme.surfaceContainerHigh,
              borderRadius: BorderRadius.circular(6),
            ),
          ),
        );

    return FadeTransition(
      opacity: Tween<double>(begin: 0.55, end: 1).animate(_pulse),
      child: ListView.separated(
        physics: const NeverScrollableScrollPhysics(),
        padding: const EdgeInsets.all(16),
        itemCount: widget.rows,
        separatorBuilder: (context, index) => const SizedBox(height: 12),
        itemBuilder: (context, index) => Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Row(
              children: [
                Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(
                    color: scheme.surfaceContainerHigh,
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      bar(index.isEven ? 0.8 : 0.6, 14),
                      const SizedBox(height: 10),
                      bar(0.4, 10),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _ErrorView extends StatelessWidget {
  const _ErrorView({required this.error, this.onRetry});

  final Object error;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final message = error is ApiException ? (error as ApiException).message : l10n.commonErrorGeneric;
    final scheme = Theme.of(context).colorScheme;

    return _CenteredMessage(
      icon: Icons.cloud_off_outlined,
      iconColor: scheme.error,
      iconBackground: scheme.errorContainer,
      message: message,
      action: onRetry == null
          ? null
          : FilledButton.tonalIcon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh),
              label: Text(l10n.commonRetry),
            ),
    );
  }
}

/// A centered empty-state, used wherever a list loaded fine but has zero
/// items.
class EmptyStateView extends StatelessWidget {
  const EmptyStateView({super.key, required this.message, this.icon = Icons.inbox_outlined});

  final String message;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return _CenteredMessage(
      icon: icon,
      iconColor: scheme.onSurfaceVariant,
      iconBackground: scheme.surfaceContainerHigh,
      message: message,
    );
  }
}

class _CenteredMessage extends StatelessWidget {
  const _CenteredMessage({
    required this.icon,
    required this.iconColor,
    required this.iconBackground,
    required this.message,
    this.action,
  });

  final IconData icon;
  final Color iconColor;
  final Color iconBackground;
  final String message;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 72,
              height: 72,
              decoration: BoxDecoration(color: iconBackground, shape: BoxShape.circle),
              child: Icon(icon, size: 34, color: iconColor),
            ),
            const SizedBox(height: 16),
            Text(
              message,
              textAlign: TextAlign.center,
              style: text.bodyLarge!.copyWith(color: Theme.of(context).colorScheme.onSurfaceVariant),
            ),
            if (action != null) ...[
              const SizedBox(height: 20),
              action!,
            ],
          ],
        ),
      ),
    );
  }
}
