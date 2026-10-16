import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/network/api_client.dart';
import '../../core/network/api_providers.dart';

/// The server no longer accepts this build: everything is blocked until the
/// app is updated from [storeUrl].
class UpdateRequirement {
  const UpdateRequirement({required this.minBuild, required this.storeUrl});

  final int minBuild;
  final String storeUrl;

  static UpdateRequirement? fromJson(Map<String, dynamic> json) {
    final storeUrl = json['store_url'] as String?;
    if (storeUrl == null || storeUrl.isEmpty) return null;

    return UpdateRequirement(minBuild: json['min_build'] as int? ?? 0, storeUrl: storeUrl);
  }
}

/// This install's build number; overridable in tests.
final appBuildNumberProvider = FutureProvider<int>((ref) => ApiClient.appBuildNumber());

/// The version name shown on the update screen ("1.2.0 (12)").
final appVersionLabelProvider = FutureProvider<String>((ref) async {
  final info = await PackageInfo.fromPlatform();

  return '${info.version} (${info.buildNumber})';
});

/// Opens the store page; overridable in tests.
final storeLauncherProvider = Provider<Future<bool> Function(Uri)>(
  (ref) => (uri) => launchUrl(uri, mode: LaunchMode.externalApplication),
);

/// `null` while this build may be used. Set either by the launch check or by
/// any API call that comes back 426 — once set it stays: the only way out is
/// installing the update, which restarts the app.
class AppUpdateController extends Notifier<UpdateRequirement?> {
  @override
  UpdateRequirement? build() => null;

  /// Asks the server at launch, so the block shows before anyone logs in.
  /// Failing to reach it (offline) blocks nothing; the next API call will.
  Future<void> check() async {
    try {
      final response = await ref.read(apiClientProvider).dio.get<Map<String, dynamic>>('/app/version');
      final data = response.data!['data'] as Map<String, dynamic>;
      final requirement = UpdateRequirement.fromJson(data);
      final currentBuild = await ref.read(appBuildNumberProvider.future);

      if (requirement != null && requirement.minBuild > currentBuild) {
        state = requirement;
      }
    } catch (_) {
      // Leave the app usable; enforcement still happens server-side.
    }
  }

  /// From a 426 response body.
  void requireUpdate(Map<String, dynamic> body) {
    state = UpdateRequirement.fromJson(body) ?? state;
  }
}

final appUpdateControllerProvider = NotifierProvider<AppUpdateController, UpdateRequirement?>(AppUpdateController.new);
