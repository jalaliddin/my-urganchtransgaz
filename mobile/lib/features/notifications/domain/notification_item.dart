class NotificationItem {
  NotificationItem({
    required this.id,
    required this.type,
    this.title,
    this.message,
    required this.readAt,
    required this.createdAt,
  });

  final String id;
  final String type;
  final String? title;
  final String? message;
  final String? readAt;
  final String createdAt;

  bool get isRead => readAt != null;

  factory NotificationItem.fromJson(Map<String, dynamic> json) => NotificationItem(
        id: json['id'] as String,
        type: json['type'] as String? ?? '',
        title: json['title'] as String?,
        message: json['message'] as String?,
        readAt: json['read_at'] as String?,
        createdAt: json['created_at'] as String? ?? '',
      );
}
