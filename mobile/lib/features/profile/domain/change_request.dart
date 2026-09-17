class EmployeeChangeRequest {
  EmployeeChangeRequest({
    required this.id,
    required this.changes,
    required this.status,
    this.reviewComment,
    required this.createdAt,
  });

  final int id;
  final Map<String, dynamic> changes;
  final String status;
  final String? reviewComment;
  final String createdAt;

  factory EmployeeChangeRequest.fromJson(Map<String, dynamic> json) => EmployeeChangeRequest(
        id: json['id'] as int,
        changes: (json['changes'] as Map<String, dynamic>?) ?? {},
        status: json['status'] as String? ?? 'pending',
        reviewComment: json['review_comment'] as String?,
        createdAt: json['created_at'] as String? ?? '',
      );
}
