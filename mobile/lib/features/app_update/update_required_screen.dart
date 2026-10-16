import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_svg/flutter_svg.dart';

import '../../l10n/app_localizations.dart';
import 'app_update_controller.dart';

/// The only screen an outdated build can show — the router sends every
/// location here until the app is updated.
class UpdateRequiredScreen extends ConsumerWidget {
  const UpdateRequiredScreen({super.key});

  Future<void> _openStore(BuildContext context, WidgetRef ref, String storeUrl) async {
    final opened = await ref.read(storeLauncherProvider)(Uri.parse(storeUrl));

    if (!opened && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(AppLocalizations.of(context).appUpdateOpenFailed)),
      );
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final requirement = ref.watch(appUpdateControllerProvider);
    final version = ref.watch(appVersionLabelProvider).value;

    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(32),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  SvgPicture.asset('assets/images/logo.svg', height: 80),
                  const SizedBox(height: 28),
                  Icon(Icons.system_update_outlined, size: 40, color: theme.colorScheme.primary),
                  const SizedBox(height: 16),
                  Text(
                    l10n.appUpdateTitle,
                    textAlign: TextAlign.center,
                    style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 12),
                  Text(l10n.appUpdateBody, textAlign: TextAlign.center, style: theme.textTheme.bodyMedium),
                  if (version != null) ...[
                    const SizedBox(height: 12),
                    Text(
                      l10n.appUpdateCurrentVersion(version),
                      textAlign: TextAlign.center,
                      style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.outline),
                    ),
                  ],
                  const SizedBox(height: 28),
                  SizedBox(
                    width: double.infinity,
                    child: FilledButton.icon(
                      icon: const Icon(Icons.shop_outlined),
                      label: Text(l10n.appUpdateButton),
                      onPressed: requirement == null ? null : () => _openStore(context, ref, requirement.storeUrl),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
