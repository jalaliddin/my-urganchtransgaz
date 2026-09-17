class Announcement {
  Announcement({
    required this.id,
    required this.title,
    required this.content,
    required this.hasImage,
    required this.hasAttachment,
    this.attachmentName,
    this.authorName,
    required this.priority,
    required this.status,
    this.publishAt,
    this.isRead,
    required this.createdAt,
  });

  final int id;
  final String title;
  final String content;
  final bool hasImage;
  final bool hasAttachment;
  final String? attachmentName;
  final String? authorName;
  final String priority;
  final String status;
  final String? publishAt;
  final bool? isRead;
  final String createdAt;

  factory Announcement.fromJson(Map<String, dynamic> json) => Announcement(
        id: json['id'] as int,
        title: json['title'] as String? ?? '',
        content: json['content'] as String? ?? '',
        hasImage: json['has_image'] as bool? ?? false,
        hasAttachment: json['has_attachment'] as bool? ?? false,
        attachmentName: json['attachment_name'] as String?,
        authorName: json['author_name'] as String?,
        priority: json['priority'] as String? ?? 'normal',
        status: json['status'] as String? ?? 'published',
        publishAt: json['publish_at'] as String?,
        isRead: json['is_read'] as bool?,
        createdAt: json['created_at'] as String? ?? '',
      );
}
