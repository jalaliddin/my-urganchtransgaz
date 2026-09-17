import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/models/employee.dart';
import '../../../core/network/api_providers.dart';
import '../../auth/presentation/auth_controller.dart';
import '../data/profile_repository.dart';
import '../domain/change_request.dart';

final profileRepositoryProvider = Provider<ProfileRepository>((ref) {
  return ProfileRepository(ref.watch(apiClientProvider));
});

class ProfileController extends AsyncNotifier<Employee> {
  @override
  Future<Employee> build() => ref.watch(profileRepositoryProvider).show();

  Future<void> refresh() async {
    ref.invalidateSelf();
    await future;
  }

  /// Returns true when the update created a pending change request rather
  /// than applying immediately, so the screen can show the right message.
  /// Named `submitChanges`, not `update` — `AsyncNotifier` already defines
  /// a built-in `update(fn)` for transforming the current state in place,
  /// and a same-named override with a different signature doesn't compile.
  Future<bool> submitChanges(Map<String, dynamic> fields) async {
    final createdChangeRequest = await ref.read(profileRepositoryProvider).update(fields);
    await refresh();
    ref.invalidate(myChangeRequestsProvider);

    return createdChangeRequest;
  }

  Future<void> uploadPhoto(String filePath, String fileName) async {
    await ref.read(profileRepositoryProvider).uploadPhoto(filePath, fileName);
    await refresh();
    ref.invalidate(authControllerProvider);
  }
}

final profileControllerProvider = AsyncNotifierProvider<ProfileController, Employee>(ProfileController.new);

final myChangeRequestsProvider = FutureProvider<List<EmployeeChangeRequest>>((ref) {
  return ref.watch(profileRepositoryProvider).myChangeRequests();
});
