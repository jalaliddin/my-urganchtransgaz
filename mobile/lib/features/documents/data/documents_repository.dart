import 'package:dio/dio.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_exception.dart';
import '../domain/employee_document.dart';

class DocumentsRepository {
  DocumentsRepository(this._client);

  final ApiClient _client;

  Future<List<DocumentType>> documentTypes() async {
    try {
      final response = await _client.dio.get<Map<String, dynamic>>('/document-types');
      final data = response.data!['data'] as List<dynamic>;

      return data.map((e) => DocumentType.fromJson(e as Map<String, dynamic>)).toList();
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<List<EmployeeDocument>> list(int employeeId) async {
    try {
      final response = await _client.dio.get<Map<String, dynamic>>(
        '/documents',
        queryParameters: {'filter[employee_id]': employeeId},
      );
      final data = response.data!['data'] as List<dynamic>;

      return data.map((e) => EmployeeDocument.fromJson(e as Map<String, dynamic>)).toList();
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<void> upload({
    required int documentTypeId,
    required String title,
    required String filePath,
    required String fileName,
    String? documentNumber,
    String? issueDate,
    String? expiryDate,
  }) async {
    try {
      await _client.dio.post<void>(
        '/documents',
        data: FormData.fromMap({
          'document_type_id': documentTypeId,
          'title': title,
          'document_number': ?documentNumber,
          'issue_date': ?issueDate,
          'expiry_date': ?expiryDate,
          'file': await MultipartFile.fromFile(filePath, filename: fileName),
        }),
      );
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<List<int>> download(String downloadUrl) => _client.downloadBytes(downloadUrl);
}
