import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_providers.dart';
import '../data/kpi_repository.dart';
import '../domain/employee_kpi.dart';

final kpiRepositoryProvider = Provider<KpiRepository>((ref) {
  return KpiRepository(ref.watch(apiClientProvider));
});

class KpiController extends AsyncNotifier<List<EmployeeKpi>> {
  @override
  Future<List<EmployeeKpi>> build() => ref.watch(kpiRepositoryProvider).myResults();

  Future<void> refresh() async {
    ref.invalidateSelf();
    await future;
  }
}

final kpiControllerProvider = AsyncNotifierProvider<KpiController, List<EmployeeKpi>>(KpiController.new);
