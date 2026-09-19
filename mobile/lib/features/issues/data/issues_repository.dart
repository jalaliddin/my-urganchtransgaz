import 'package:dio/dio.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_exception.dart';
import '../domain/issue.dart';

class IssuesRepository {
  IssuesRepository(this._client);

  final ApiClient _client;

  Future<List<Issue>> list({bool openOnly = true}) async {
    try {
      final response = await _client.dio.get<Map<String, dynamic>>(
        '/issues',
        queryParameters: {
          'per_page': 100,
          if (openOnly) 'filter[status]': 'open',
        },
      );
      final data = response.data!['data'] as List<dynamic>;

      return data.map((e) => Issue.fromJson(e as Map<String, dynamic>)).toList();
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<IssueOptions> options() async {
    try {
      final response = await _client.dio.get<Map<String, dynamic>>('/issues/options');

      return IssueOptions.fromJson(response.data!['data'] as Map<String, dynamic>);
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<List<IssueResponsibleCandidate>> responsibleCandidates(int organizationId) async {
    try {
      final response = await _client.dio.get<Map<String, dynamic>>(
        '/issues/responsible-candidates',
        queryParameters: {'organization_id': organizationId},
      );
      final data = response.data!['data'] as List<dynamic>;

      return data.map((e) => IssueResponsibleCandidate.fromJson(e as Map<String, dynamic>)).toList();
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<Issue> show(int id) async {
    try {
      final response = await _client.dio.get<Map<String, dynamic>>('/issues/$id');

      return Issue.fromJson(response.data!['data'] as Map<String, dynamic>);
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<Issue> create({
    required String title,
    String? description,
    String? objectName,
    int? organizationId,
    required int categoryId,
    int? responsibleEmployeeId,
    required double latitude,
    required double longitude,
  }) async {
    try {
      final response = await _client.dio.post<Map<String, dynamic>>('/issues', data: {
        'title': title,
        'description': ?description,
        'object_name': ?objectName,
        'organization_id': ?organizationId,
        'issue_category_id': categoryId,
        'responsible_employee_id': ?responsibleEmployeeId,
        'latitude': latitude,
        'longitude': longitude,
      });

      return Issue.fromJson(response.data!['data'] as Map<String, dynamic>);
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<void> addComment(int issueId, String body) async {
    try {
      await _client.dio.post<void>('/issues/$issueId/comments', data: {'body': body});
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<Issue> resolve(int id, String resolutionNote) async {
    try {
      final response = await _client.dio.post<Map<String, dynamic>>('/issues/$id/resolve', data: {
        'resolution_note': resolutionNote,
      });

      return Issue.fromJson(response.data!['data'] as Map<String, dynamic>);
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }
}
