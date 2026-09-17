import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'api_client.dart';

final apiClientProvider = Provider<ApiClient>((ref) {
  throw UnimplementedError('apiClientProvider must be overridden in main()');
});

/// Headers for authenticated `Image.network`/`NetworkImage` requests
/// (employee photo, announcement image) — every file in this app lives
/// behind Sanctum auth, never a public URL, so the Bearer token has to
/// ride along with the image request too.
final authHeadersProvider = FutureProvider<Map<String, String>>((ref) {
  return ref.watch(apiClientProvider).authHeaders();
});
