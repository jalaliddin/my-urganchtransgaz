import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/models/user.dart';
import '../../../core/network/api_providers.dart';
import '../../../core/storage/secure_storage.dart';
import '../data/auth_repository.dart';

final authRepositoryProvider = Provider<AuthRepository>((ref) {
  return AuthRepository(ref.watch(apiClientProvider));
});

/// `null` data means "logged out" — there's no separate boolean, the
/// presence of a [User] *is* the session, mirroring how the web app's
/// `authStore` treats a missing token.
class AuthController extends AsyncNotifier<User?> {
  @override
  Future<User?> build() async {
    final client = ref.watch(apiClientProvider);
    await client.restoreBaseUrl();

    final token = await SecureStorage.readToken();

    if (token == null) {
      return null;
    }

    try {
      return await ref.read(authRepositoryProvider).me();
    } catch (_) {
      await SecureStorage.deleteToken();
      return null;
    }
  }

  Future<void> login(String login, String password) async {
    state = const AsyncLoading();
    state = await AsyncValue.guard(() => ref.read(authRepositoryProvider).login(login, password));
  }

  Future<void> logout() async {
    await ref.read(authRepositoryProvider).logout();
    state = const AsyncData(null);
  }

  /// Called by [ApiClient.onUnauthorized] on any 401 — the token is
  /// already invalid server-side, so there's no point calling
  /// `/auth/logout` again; just clear the local session.
  void forceLogout() {
    SecureStorage.deleteToken();
    state = const AsyncData(null);
  }

  Future<void> refresh() async {
    state = await AsyncValue.guard(() => ref.read(authRepositoryProvider).me());
  }
}

final authControllerProvider = AsyncNotifierProvider<AuthController, User?>(AuthController.new);
