import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_providers.dart';
import '../data/dashboard_repository.dart';
import '../domain/business_trip.dart';

final dashboardRepositoryProvider = Provider<DashboardRepository>((ref) {
  return DashboardRepository(ref.watch(apiClientProvider));
});

final upcomingBusinessTripProvider = FutureProvider<BusinessTrip?>((ref) async {
  final trips = await ref.watch(dashboardRepositoryProvider).upcomingBusinessTrips();
  return trips.isEmpty ? null : trips.first;
});
