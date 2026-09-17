import 'package:dio/dio.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_exception.dart';
import '../domain/leave_request.dart';

class LeaveRequestsRepository {
  LeaveRequestsRepository(this._client);

  final ApiClient _client;

  /// `GET /leave-requests` always includes the caller's own requests
  /// regardless of role (see `LeaveRequestController::index()`), so no
  /// extra filter is needed for a plain employee's "my requests" view.
  Future<List<LeaveRequest>> list() async {
    try {
      final response = await _client.dio.get<Map<String, dynamic>>('/leave-requests');
      final data = response.data!['data'] as List<dynamic>;

      return data.map((e) => LeaveRequest.fromJson(e as Map<String, dynamic>)).toList();
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<void> create({
    required String type,
    required String startDate,
    required String endDate,
    String? reason,
  }) async {
    try {
      await _client.dio.post<void>('/leave-requests', data: {
        'type': type,
        'start_date': startDate,
        'end_date': endDate,
        'reason': ?reason,
      });
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<void> cancel(int id) async {
    try {
      await _client.dio.post<void>('/leave-requests/$id/cancel');
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }
}
