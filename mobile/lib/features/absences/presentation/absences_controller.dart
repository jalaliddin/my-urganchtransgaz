import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_providers.dart';
import '../../auth/presentation/auth_controller.dart';
import '../data/absences_repository.dart';
import '../domain/employee_absence.dart';

final absencesRepositoryProvider = Provider<AbsencesRepository>((ref) {
  return AbsencesRepository(ref.watch(apiClientProvider));
});

/// The signed-in employee's own leave, sick leave and business trips,
/// recorded by HR — this app only reads them.
class AbsencesController extends AsyncNotifier<List<EmployeeAbsence>> {
  @override
  Future<List<EmployeeAbsence>> build() async {
    final user = await ref.watch(authControllerProvider.future);
    final employeeId = user?.employee?.id;

    if (employeeId == null) {
      return [];
    }

    return ref.watch(absencesRepositoryProvider).list(employeeId);
  }

  Future<void> refresh() async {
    ref.invalidateSelf();
    ref.invalidate(leaveBalanceProvider);
    await future;
  }
}

final absencesControllerProvider =
    AsyncNotifierProvider<AbsencesController, List<EmployeeAbsence>>(AbsencesController.new);

final leaveBalanceProvider = FutureProvider<LeaveBalance?>((ref) async {
  final user = await ref.watch(authControllerProvider.future);
  final employeeId = user?.employee?.id;

  if (employeeId == null) {
    return null;
  }

  return ref.watch(absencesRepositoryProvider).balance(employeeId);
});
