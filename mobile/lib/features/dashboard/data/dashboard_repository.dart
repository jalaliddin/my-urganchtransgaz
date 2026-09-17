import 'package:dio/dio.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_exception.dart';
import '../domain/business_trip.dart';

class DashboardRepository {
  DashboardRepository(this._client);

  final ApiClient _client;

  Future<List<BusinessTrip>> upcomingBusinessTrips() async {
    try {
      final response = await _client.dio.get<Map<String, dynamic>>('/business-trips/upcoming');
      final data = response.data!['data'] as List<dynamic>;

      return data.map((e) => BusinessTrip.fromJson(e as Map<String, dynamic>)).toList();
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }
}
