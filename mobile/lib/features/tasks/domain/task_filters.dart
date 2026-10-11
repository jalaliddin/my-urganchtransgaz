/// Which of the visible tasks to list. `status` picks the tab; the rest
/// narrow every tab alike, so the per-tab counts use them too.
class TaskFilters {
  const TaskFilters({this.status, this.categoryId, this.mine = 'all', this.search = ''});

  /// One of the backend's task statuses, or null for every status.
  final String? status;
  final int? categoryId;

  /// `all`, `assigned` (to me) or `created` (by me).
  final String mine;
  final String search;

  TaskFilters copyWith({String? Function()? status, int? Function()? categoryId, String? mine, String? search}) {
    return TaskFilters(
      status: status != null ? status() : this.status,
      categoryId: categoryId != null ? categoryId() : this.categoryId,
      mine: mine ?? this.mine,
      search: search ?? this.search,
    );
  }

  /// Query parameters for `GET /tasks` (with the status) or
  /// `GET /tasks/summary` (without it).
  Map<String, dynamic> toQuery({required bool includeStatus}) {
    final term = search.trim();

    return {
      if (includeStatus && status != null) 'filter[status]': status,
      'filter[task_category_id]': ?categoryId,
      if (mine != 'all') 'filter[mine]': mine,
      if (term.isNotEmpty) 'filter[search]': term,
    };
  }

  @override
  bool operator ==(Object other) =>
      other is TaskFilters &&
      other.status == status &&
      other.categoryId == categoryId &&
      other.mine == mine &&
      other.search == search;

  @override
  int get hashCode => Object.hash(status, categoryId, mine, search);
}
