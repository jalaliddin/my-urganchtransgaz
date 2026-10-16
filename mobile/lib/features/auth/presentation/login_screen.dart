import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:go_router/go_router.dart';

import '../../../core/locale/language_switcher.dart';
import '../../../core/network/api_config.dart';
import '../../../core/network/api_providers.dart';
import '../../../l10n/app_localizations.dart';
import 'auth_controller.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _loginController = TextEditingController();
  final _passwordController = TextEditingController();
  late final TextEditingController _serverController;
  bool _obscurePassword = true;
  bool _showServerField = false;

  @override
  void initState() {
    super.initState();
    _serverController = TextEditingController(text: ref.read(apiClientProvider).baseUrl);
  }

  @override
  void dispose() {
    _loginController.dispose();
    _passwordController.dispose();
    _serverController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    final serverUrl = _serverController.text.trim();

    if (ApiConfig.allowServerOverride && serverUrl.isNotEmpty) {
      await ref.read(apiClientProvider).setBaseUrl(serverUrl);
    }

    await ref
        .read(authControllerProvider.notifier)
        .login(_loginController.text.trim(), _passwordController.text);
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final authState = ref.watch(authControllerProvider);
    final isLoading = authState.isLoading;

    ref.listen(authControllerProvider, (previous, next) {
      if (next.hasError && !next.isLoading) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(next.error.toString().replaceFirst('ApiException: ', ''))),
        );
      }
    });

    return Scaffold(
      appBar: AppBar(
        automaticallyImplyLeading: false,
        actions: const [Padding(padding: EdgeInsets.only(right: 16), child: LanguageSwitcher())],
      ),
      body: SafeArea(
        top: false,
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(24, 0, 24, 24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Form(
                key: _formKey,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    SvgPicture.asset('assets/images/logo.svg', height: 76),
                    const SizedBox(height: 24),
                    Text(
                      l10n.appName,
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.headlineSmall,
                    ),
                    const SizedBox(height: 4),
                    Text(
                      l10n.appCorporatePortal,
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                            color: Theme.of(context).colorScheme.onSurfaceVariant,
                          ),
                    ),
                    const SizedBox(height: 8),
                    const SizedBox(height: 32),
                    Text(l10n.authWelcomeBack, style: Theme.of(context).textTheme.titleMedium),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _loginController,
                      textInputAction: TextInputAction.next,
                      decoration: InputDecoration(
                        labelText: l10n.authLoginField,
                        prefixIcon: const Icon(Icons.person_outline),
                      ),
                      validator: (value) => (value == null || value.trim().isEmpty) ? l10n.commonRequired : null,
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _passwordController,
                      obscureText: _obscurePassword,
                      textInputAction: TextInputAction.done,
                      onFieldSubmitted: (_) => _submit(),
                      decoration: InputDecoration(
                        labelText: l10n.authPassword,
                        prefixIcon: const Icon(Icons.lock_outline),
                        suffixIcon: IconButton(
                          icon: Icon(_obscurePassword ? Icons.visibility_outlined : Icons.visibility_off_outlined),
                          onPressed: () => setState(() => _obscurePassword = !_obscurePassword),
                        ),
                      ),
                      validator: (value) => (value == null || value.isEmpty) ? l10n.commonRequired : null,
                    ),
                    const SizedBox(height: 8),
                    Align(
                      alignment: Alignment.centerRight,
                      child: TextButton(
                        onPressed: () => context.push('/forgot-password'),
                        child: Text(l10n.authForgotPassword),
                      ),
                    ),
                    const SizedBox(height: 8),
                    FilledButton(
                      onPressed: isLoading ? null : _submit,
                      child: isLoading
                          ? const SizedBox(
                              height: 20,
                              width: 20,
                              child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                            )
                          : Text(l10n.authLoginButton),
                    ),
                    // Development builds only — see ApiConfig.allowServerOverride.
                    if (ApiConfig.allowServerOverride) ...[
                      const SizedBox(height: 8),
                      TextButton.icon(
                        onPressed: () => setState(() => _showServerField = !_showServerField),
                        icon: const Icon(Icons.dns_outlined, size: 18),
                        label: Text(l10n.authServerAddress),
                      ),
                    ],
                    if (ApiConfig.allowServerOverride && _showServerField)
                      Padding(
                        padding: const EdgeInsets.only(top: 8),
                        child: TextFormField(
                          controller: _serverController,
                          keyboardType: TextInputType.url,
                          decoration: InputDecoration(
                            labelText: l10n.authServerAddress,
                            hintText: l10n.authServerAddressHint,
                          ),
                        ),
                      ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
