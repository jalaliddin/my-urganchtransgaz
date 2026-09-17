import 'package:dio/dio.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/network/paginated.dart';
import '../domain/attendance_record.dart';

class AttendanceRepository {
  AttendanceRepository(this._client);

  final ApiClient _client;

  Future<List<TodayAttendanceEntry>> today() async {
    try {
      final response = await _client.dio.get<Map<String, dynamic>>('/attendance/today');
      final data = response.data!['data'] as List<dynamic>;

      return data.map((e) => TodayAttendanceEntry.fromJson(e as Map<String, dynamic>)).toList();
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<AttendanceRecord> checkIn() async {
    try {
      final response = await _client.dio.post<Map<String, dynamic>>('/attendance/check-in');

      return AttendanceRecord.fromJson(response.data!['data'] as Map<String, dynamic>);
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<AttendanceRecord> checkOut() async {
    try {
      final response = await _client.dio.post<Map<String, dynamic>>('/attendance/check-out');

      return AttendanceRecord.fromJson(response.data!['data'] as Map<String, dynamic>);
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<Paginated<AttendanceRecord>> history({
    required int employeeId,
    String? from,
    String? to,
    int page = 1,
  }) async {
    try {
      final response = await _client.dio.get<Map<String, dynamic>>(
        '/attendance',
        queryParameters: {
          'filter[employee_id]': employeeId,
          'from': ?from,
          'to': ?to,
          'page': page,
        },
      );

      return Paginated.fromJson(response.data!, AttendanceRecord.fromJson);
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }
}
