import 'package:dio/dio.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_exception.dart';
import '../domain/employee_kpi.dart';

class KpiRepository {
  KpiRepository(this._client);

  final ApiClient _client;

  Future<List<EmployeeKpi>> myResults() async {
    try {
      final response = await _client.dio.get<Map<String, dynamic>>('/kpi/my');
      final data = response.data!['data'] as List<dynamic>;

      return data.map((e) => EmployeeKpi.fromJson(e as Map<String, dynamic>)).toList();
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }
}
