import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/models/employee.dart';
import '../../../core/models/employee_contact.dart';
import '../../../core/network/api_providers.dart';
import '../../auth/presentation/auth_controller.dart';
import '../data/profile_repository.dart';
import '../domain/change_request.dart';
import '../domain/profile_completion.dart';

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
    ref.invalidate(profileCompletionProvider);

    return createdChangeRequest;
  }

  /// The server replaces the whole contact list, so every kept contact goes
  /// back with its id alongside the new one.
  Future<void> addContact(EmployeeContact contact) async {
    final current = state.value?.contacts ?? const <EmployeeContact>[];
    await submitChanges({'contacts': [...current, contact].map((c) => c.toJson()).toList()});
  }

  Future<void> removeContact(int contactId) async {
    final current = state.value?.contacts ?? const <EmployeeContact>[];
    await submitChanges({'contacts': current.where((c) => c.id != contactId).map((c) => c.toJson()).toList()});
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

/// Shown on the dashboard; refreshed whenever the profile is saved.
final profileCompletionProvider = FutureProvider.autoDispose<ProfileCompletion>((ref) {
  return ref.watch(profileRepositoryProvider).completion();
});
