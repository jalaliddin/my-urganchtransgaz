import 'package:dio/dio.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_exception.dart';
import '../domain/issue.dart';
import '../domain/issue_report.dart';

class IssuesRepository {
  IssuesRepository(this._client);

  final ApiClient _client;

  /// Leaders' report (`issues.report` permission). [dateFrom] is `yyyy-MM-dd`;
  /// null means since the beginning.
  Future<IssueReport> report({String? dateFrom}) async {
    try {
      final response = await _client.dio.get<Map<String, dynamic>>(
        '/issues/report',
        queryParameters: {'date_from': ?dateFrom},
      );

      return IssueReport.fromJson(response.data!['data'] as Map<String, dynamic>);
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

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

  Future<List<IssueExecutorCandidate>> executorCandidates(int organizationId) async {
    try {
      final response = await _client.dio.get<Map<String, dynamic>>(
        '/issues/executor-candidates',
        queryParameters: {'organization_id': organizationId},
      );
      final data = response.data!['data'] as List<dynamic>;

      return data.map((e) => IssueExecutorCandidate.fromJson(e as Map<String, dynamic>)).toList();
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
    required int organizationId,
    required int categoryId,
    List<int> executorIds = const [],
    required double latitude,
    required double longitude,
    List<String> photoPaths = const [],
  }) async {
    final fields = {
      'title': title,
      'description': ?description,
      'object_name': ?objectName,
      'organization_id': organizationId,
      'issue_category_id': categoryId,
      if (executorIds.isNotEmpty) 'executor_ids': executorIds,
      'latitude': latitude,
      'longitude': longitude,
    };

    try {
      final response = await _client.dio.post<Map<String, dynamic>>(
        '/issues',
        // Photos need multipart; without any, the same JSON body as before.
        data: photoPaths.isEmpty
            ? fields
            : FormData.fromMap({
                ...fields,
                'photos': [
                  for (final (index, path) in photoPaths.indexed)
                    await MultipartFile.fromFile(path, filename: 'photo_${index + 1}.jpg'),
                ],
              }, ListFormat.multiCompatible),
      );

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

  /// Replaces the issue's executors (`issues.assign` — Technical Policy).
  Future<Issue> assignExecutors(int id, List<int> executorIds) async {
    try {
      final response = await _client.dio.put<Map<String, dynamic>>('/issues/$id/executors', data: {
        'executor_ids': executorIds,
      });

      return Issue.fromJson(response.data!['data'] as Map<String, dynamic>);
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
