class LeaveRequest {
  LeaveRequest({
    required this.id,
    required this.type,
    required this.startDate,
    required this.endDate,
    this.reason,
    required this.status,
    this.rejectionReason,
  });

  final int id;
  final String type;
  final String startDate;
  final String endDate;
  final String? reason;
  final String status;
  final String? rejectionReason;

  bool get isCancellable => status == 'pending' || status == 'department_approved';

  factory LeaveRequest.fromJson(Map<String, dynamic> json) => LeaveRequest(
        id: json['id'] as int,
        type: json['type'] as String? ?? 'other',
        startDate: json['start_date'] as String? ?? '',
        endDate: json['end_date'] as String? ?? '',
        reason: json['reason'] as String?,
        status: json['status'] as String? ?? 'pending',
        rejectionReason: json['rejection_reason'] as String?,
      );
}
