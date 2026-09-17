import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_providers.dart';
import '../data/announcements_repository.dart';
import '../domain/announcement.dart';

final announcementsRepositoryProvider = Provider<AnnouncementsRepository>((ref) {
  return AnnouncementsRepository(ref.watch(apiClientProvider));
});

class AnnouncementsController extends AsyncNotifier<List<Announcement>> {
  @override
  Future<List<Announcement>> build() async {
    final page = await ref.watch(announcementsRepositoryProvider).list();
    return page.items;
  }

  Future<void> refresh() async {
    ref.invalidateSelf();
    await future;
  }
}

final announcementsControllerProvider =
    AsyncNotifierProvider<AnnouncementsController, List<Announcement>>(AnnouncementsController.new);

/// The latest 3, for the Dashboard card — a thin derived view over the
/// same list rather than a second network call.
final latestAnnouncementsProvider = Provider<AsyncValue<List<Announcement>>>((ref) {
  final all = ref.watch(announcementsControllerProvider);
  return all.whenData((items) => items.take(3).toList());
});

final announcementDetailProvider = FutureProvider.family<Announcement, int>((ref, id) {
  return ref.watch(announcementsRepositoryProvider).show(id);
});
