import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_providers.dart';
import '../data/leave_requests_repository.dart';
import '../domain/leave_request.dart';

final leaveRequestsRepositoryProvider = Provider<LeaveRequestsRepository>((ref) {
  return LeaveRequestsRepository(ref.watch(apiClientProvider));
});

class LeaveRequestsController extends AsyncNotifier<List<LeaveRequest>> {
  @override
  Future<List<LeaveRequest>> build() => ref.watch(leaveRequestsRepositoryProvider).list();

  Future<void> refresh() async {
    ref.invalidateSelf();
    await future;
  }

  Future<void> create({required String type, required String startDate, required String endDate, String? reason}) async {
    await ref.read(leaveRequestsRepositoryProvider).create(type: type, startDate: startDate, endDate: endDate, reason: reason);
    await refresh();
  }

  Future<void> cancel(int id) async {
    await ref.read(leaveRequestsRepositoryProvider).cancel(id);
    await refresh();
  }
}

final leaveRequestsControllerProvider =
    AsyncNotifierProvider<LeaveRequestsController, List<LeaveRequest>>(LeaveRequestsController.new);
