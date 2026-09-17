import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_providers.dart';
import '../../auth/presentation/auth_controller.dart';
import '../data/attendance_repository.dart';
import '../domain/attendance_record.dart';

final attendanceRepositoryProvider = Provider<AttendanceRepository>((ref) {
  return AttendanceRepository(ref.watch(apiClientProvider));
});

/// The caller's own row out of the shared "today" board — see
/// `AttendanceRepository.today()` for why this isn't a single-record
/// endpoint.
class MyTodayAttendanceController extends AsyncNotifier<AttendanceRecord?> {
  @override
  Future<AttendanceRecord?> build() async {
    final user = await ref.watch(authControllerProvider.future);
    final employeeId = user?.employee?.id;

    if (employeeId == null) {
      return null;
    }

    final entries = await ref.watch(attendanceRepositoryProvider).today();

    for (final entry in entries) {
      if (entry.employeeId == employeeId) {
        return entry.attendance;
      }
    }

    return null;
  }

  Future<void> refresh() async {
    ref.invalidateSelf();
    await future;
  }

  Future<void> checkIn() async {
    state = const AsyncLoading();
    state = await AsyncValue.guard(() => ref.read(attendanceRepositoryProvider).checkIn());
  }

  Future<void> checkOut() async {
    state = const AsyncLoading();
    state = await AsyncValue.guard(() => ref.read(attendanceRepositoryProvider).checkOut());
  }
}

final myTodayAttendanceProvider =
    AsyncNotifierProvider<MyTodayAttendanceController, AttendanceRecord?>(MyTodayAttendanceController.new);

class AttendanceHistoryController extends AsyncNotifier<List<AttendanceRecord>> {
  DateTime _from = DateTime(DateTime.now().year, DateTime.now().month, 1);
  DateTime _to = DateTime.now();

  DateTime get from => _from;
  DateTime get to => _to;

  @override
  Future<List<AttendanceRecord>> build() async {
    final user = await ref.watch(authControllerProvider.future);
    final employeeId = user?.employee?.id;

    if (employeeId == null) {
      return [];
    }

    final page = await ref.watch(attendanceRepositoryProvider).history(
          employeeId: employeeId,
          from: _formatDate(_from),
          to: _formatDate(_to),
          page: 1,
        );

    return page.items;
  }

  Future<void> setRange(DateTime from, DateTime to) async {
    _from = from;
    _to = to;
    ref.invalidateSelf();
    await future;
  }

  String _formatDate(DateTime date) =>
      '${date.year.toString().padLeft(4, '0')}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
}

final attendanceHistoryProvider =
    AsyncNotifierProvider<AttendanceHistoryController, List<AttendanceRecord>>(AttendanceHistoryController.new);
