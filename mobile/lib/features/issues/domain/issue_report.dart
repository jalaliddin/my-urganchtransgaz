/// `GET /issues/report` — already scoped server-side to what the user may see.
class IssueReport {
  IssueReport({
    required this.totals,
    required this.byOrganization,
    required this.byCategory,
    required this.monthly,
    required this.points,
    required this.pointsTruncated,
    required this.staleAfterDays,
  });

  final IssueReportCounts totals;
  final List<IssueReportOrganization> byOrganization;
  final List<IssueReportCategory> byCategory;
  final List<IssueReportMonth> monthly;
  final List<IssueReportPoint> points;

  /// The server caps the map points; true when some were left off.
  final bool pointsTruncated;
  final int staleAfterDays;

  static List<T> _list<T>(dynamic value, T Function(Map<String, dynamic>) fromJson) =>
      (value as List<dynamic>? ?? []).map((e) => fromJson(e as Map<String, dynamic>)).toList();

  factory IssueReport.fromJson(Map<String, dynamic> json) => IssueReport(
        totals: IssueReportCounts.fromJson(json['totals'] as Map<String, dynamic>? ?? {}),
        byOrganization: _list(json['by_organization'], IssueReportOrganization.fromJson),
        byCategory: _list(json['by_category'], IssueReportCategory.fromJson),
        monthly: _list(json['monthly'], IssueReportMonth.fromJson),
        points: _list(json['points'], IssueReportPoint.fromJson),
        pointsTruncated: json['points_truncated'] as bool? ?? false,
        staleAfterDays: json['stale_after_days'] as int? ?? 7,
      );
}

class IssueReportCounts {
  IssueReportCounts({
    required this.total,
    required this.openCount,
    required this.resolvedCount,
    required this.staleOpenCount,
    required this.resolutionRate,
    this.avgResolutionHours,
  });

  final int total;
  final int openCount;
  final int resolvedCount;
  final int staleOpenCount;

  /// Percent of issues resolved, 0–100.
  final int resolutionRate;

  /// Null until something has been resolved.
  final double? avgResolutionHours;

  factory IssueReportCounts.fromJson(Map<String, dynamic> json) => IssueReportCounts(
        total: json['total'] as int? ?? 0,
        openCount: json['open_count'] as int? ?? 0,
        resolvedCount: json['resolved_count'] as int? ?? 0,
        staleOpenCount: json['stale_open_count'] as int? ?? 0,
        resolutionRate: json['resolution_rate'] as int? ?? 0,
        avgResolutionHours: (json['avg_resolution_hours'] as num?)?.toDouble(),
      );
}

class IssueReportOrganization {
  IssueReportOrganization({required this.name, required this.counts});

  final String name;
  final IssueReportCounts counts;

  factory IssueReportOrganization.fromJson(Map<String, dynamic> json) => IssueReportOrganization(
        name: json['organization_name'] as String? ?? '—',
        counts: IssueReportCounts.fromJson(json),
      );
}

class IssueReportCategory {
  IssueReportCategory({this.name, required this.total, required this.openCount, required this.resolvedCount});

  /// Null for issues filed without a category.
  final String? name;
  final int total;
  final int openCount;
  final int resolvedCount;

  factory IssueReportCategory.fromJson(Map<String, dynamic> json) => IssueReportCategory(
        name: json['category_name'] as String?,
        total: json['total'] as int? ?? 0,
        openCount: json['open_count'] as int? ?? 0,
        resolvedCount: json['resolved_count'] as int? ?? 0,
      );
}

class IssueReportMonth {
  IssueReportMonth({required this.month, required this.created, required this.resolved});

  /// `YYYY-MM`.
  final String month;
  final int created;
  final int resolved;

  factory IssueReportMonth.fromJson(Map<String, dynamic> json) => IssueReportMonth(
        month: json['month'] as String? ?? '',
        created: json['created'] as int? ?? 0,
        resolved: json['resolved'] as int? ?? 0,
      );
}

class IssueReportPoint {
  IssueReportPoint({
    required this.id,
    required this.title,
    required this.status,
    required this.latitude,
    required this.longitude,
    this.openDays,
  });

  final int id;
  final String title;
  final String status;
  final double latitude;
  final double longitude;

  /// Days open so far; null once resolved.
  final int? openDays;

  bool get isResolved => status != 'open';

  factory IssueReportPoint.fromJson(Map<String, dynamic> json) => IssueReportPoint(
        id: json['id'] as int,
        title: json['title'] as String? ?? '',
        status: json['status'] as String? ?? 'open',
        latitude: (json['latitude'] as num?)?.toDouble() ?? 0,
        longitude: (json['longitude'] as num?)?.toDouble() ?? 0,
        openDays: json['open_days'] as int?,
      );
}
