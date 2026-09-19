class NotificationItem {
  NotificationItem({
    required this.id,
    required this.type,
    this.title,
    this.message,
    this.data = const {},
    required this.readAt,
    required this.createdAt,
  });

  final String id;
  final String type;
  final String? title;
  final String? message;

  /// The raw payload the server attached — ids of the thing the
  /// notification is about (`task_id`, `issue_id`, ...), used to open it.
  final Map<String, dynamic> data;
  final String? readAt;
  final String createdAt;

  bool get isRead => readAt != null;

  factory NotificationItem.fromJson(Map<String, dynamic> json) => NotificationItem(
        id: json['id'] as String,
        type: json['type'] as String? ?? '',
        title: json['title'] as String?,
        message: json['message'] as String?,
        data: json['data'] is Map<String, dynamic> ? json['data'] as Map<String, dynamic> : const {},
        readAt: json['read_at'] as String?,
        createdAt: json['created_at'] as String? ?? '',
      );
}
