import 'package:dio/dio.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_exception.dart';
import '../domain/employee_absence.dart';

class AbsencesRepository {
  AbsencesRepository(this._client);

  final ApiClient _client;

  /// Newest first. `state: 'active'` leaves out cancelled records.
  Future<List<EmployeeAbsence>> list(int employeeId, {String? state}) async {
    try {
      final response = await _client.dio.get<Map<String, dynamic>>(
        '/absences',
        queryParameters: {
          'filter[employee_id]': employeeId,
          'filter[state]': ?state,
          'per_page': 100,
        },
      );
      final data = response.data!['data'] as List<dynamic>;

      return data.map((e) => EmployeeAbsence.fromJson(e as Map<String, dynamic>)).toList();
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<LeaveBalance> balance(int employeeId) async {
    try {
      final response = await _client.dio.get<Map<String, dynamic>>('/employees/$employeeId/leave-balance');

      return LeaveBalance.fromJson(response.data!['data'] as Map<String, dynamic>);
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }
}
