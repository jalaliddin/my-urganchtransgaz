import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_providers.dart';
import '../../auth/presentation/auth_controller.dart';
import '../data/documents_repository.dart';
import '../domain/employee_document.dart';

final documentsRepositoryProvider = Provider<DocumentsRepository>((ref) {
  return DocumentsRepository(ref.watch(apiClientProvider));
});

final documentTypesProvider = FutureProvider<List<DocumentType>>((ref) {
  return ref.watch(documentsRepositoryProvider).documentTypes();
});

class DocumentsController extends AsyncNotifier<List<EmployeeDocument>> {
  @override
  Future<List<EmployeeDocument>> build() async {
    final user = await ref.watch(authControllerProvider.future);
    final employeeId = user?.employee?.id;

    if (employeeId == null) {
      return [];
    }

    return ref.watch(documentsRepositoryProvider).list(employeeId);
  }

  Future<void> refresh() async {
    ref.invalidateSelf();
    await future;
  }
}

final documentsControllerProvider =
    AsyncNotifierProvider<DocumentsController, List<EmployeeDocument>>(DocumentsController.new);
