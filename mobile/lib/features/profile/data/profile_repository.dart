import 'package:dio/dio.dart';

import '../../../core/models/employee.dart';
import '../../../core/network/api_client.dart';
import '../../../core/network/api_exception.dart';
import '../domain/change_request.dart';

class ProfileRepository {
  ProfileRepository(this._client);

  final ApiClient _client;

  Future<Employee> show() async {
    try {
      final response = await _client.dio.get<Map<String, dynamic>>('/profile');

      return Employee.fromJson(response.data!['data'] as Map<String, dynamic>);
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  /// Direct-apply fields (phone/email/address) take effect immediately;
  /// official-record fields the same endpoint routes into a pending
  /// change request server-side (`SubmitProfileUpdateAction`) — the
  /// client just submits whatever changed either way. Returns whether a
  /// change request was created, so the UI can show the right message.
  Future<bool> update(Map<String, dynamic> fields) async {
    try {
      final response = await _client.dio.put<Map<String, dynamic>>('/profile', data: fields);
      final data = response.data!['data'] as Map<String, dynamic>;

      return data['change_request'] != null;
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<void> uploadPhoto(String filePath, String fileName) async {
    try {
      await _client.dio.post<void>(
        '/profile/photo',
        data: FormData.fromMap({'photo': await MultipartFile.fromFile(filePath, filename: fileName)}),
      );
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<List<EmployeeChangeRequest>> myChangeRequests() async {
    try {
      final response = await _client.dio.get<Map<String, dynamic>>('/change-requests');
      final data = response.data!['data'] as List<dynamic>;

      return data.map((e) => EmployeeChangeRequest.fromJson(e as Map<String, dynamic>)).toList();
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }
}
