import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../features/auth/presentation/auth_controller.dart';

/// Bridges Riverpod's [authControllerProvider] to go_router's
/// `refreshListenable`, so a login/logout anywhere in the app immediately
/// re-runs the router's `redirect` callback instead of waiting for the
/// next navigation event to notice the session changed.
class RouterRefreshNotifier extends ChangeNotifier {
  RouterRefreshNotifier(Ref ref) {
    // Riverpod's own `ref.listen` already only fires this callback when
    // the provider's AsyncValue actually changes, so no extra equality
    // check is needed here — an earlier version compared `previous?.value
    // != next.value` to try to avoid "redundant" notifications, but
    // `AsyncLoading().value` and `AsyncData(null).value` are BOTH `null`,
    // so that check silently swallowed the loading-to-resolved transition
    // whenever the resolved session turned out to be "logged out" (no
    // stored token) — the most common case on a fresh install — leaving
    // the app stuck on the splash screen forever. Caught via real-browser
    // verification, not the test suite.
    ref.listen(authControllerProvider, (previous, next) {
      notifyListeners();
    });
  }
}
