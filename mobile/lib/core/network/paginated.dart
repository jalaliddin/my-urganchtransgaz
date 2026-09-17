/// Mirrors the `{data: [...], meta: {current_page, per_page, total,
/// last_page}}` shape every paginated `index()` endpoint returns (see
/// `ApiResponses::paginationMeta()` in the backend).
class Paginated<T> {
  Paginated({required this.items, required this.currentPage, required this.lastPage, required this.total});

  final List<T> items;
  final int currentPage;
  final int lastPage;
  final int total;

  bool get hasMore => currentPage < lastPage;

  factory Paginated.fromJson(Map<String, dynamic> json, T Function(Map<String, dynamic>) fromJson) {
    final data = (json['data'] as List<dynamic>? ?? [])
        .map((e) => fromJson(e as Map<String, dynamic>))
        .toList();
    final meta = json['meta'] as Map<String, dynamic>? ?? {};

    return Paginated(
      items: data,
      currentPage: meta['current_page'] as int? ?? 1,
      lastPage: meta['last_page'] as int? ?? 1,
      total: meta['total'] as int? ?? data.length,
    );
  }
}
