import 'package:flutter/material.dart';

/// Shown only for the brief moment `authControllerProvider` takes to
/// check for a stored token at app launch — the router redirects away
/// from this the instant that resolves.
class SplashScreen extends StatelessWidget {
  const SplashScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return const Scaffold(body: Center(child: CircularProgressIndicator()));
  }
}
