import '../../../core/models/employee.dart';

class TaskComment {
  TaskComment({required this.id, this.userName, required this.body, required this.createdAt});

  final int id;
  final String? userName;
  final String body;
  final String createdAt;

  factory TaskComment.fromJson(Map<String, dynamic> json) => TaskComment(
        id: json['id'] as int,
        userName: json['user_name'] as String?,
        body: json['body'] as String? ?? '',
        createdAt: json['created_at'] as String? ?? '',
      );
}

class TaskAttachment {
  TaskAttachment({
    required this.id,
    required this.originalName,
    this.fileSize,
    required this.downloadUrl,
  });

  final int id;
  final String originalName;
  final int? fileSize;
  final String downloadUrl;

  factory TaskAttachment.fromJson(Map<String, dynamic> json) => TaskAttachment(
        id: json['id'] as int,
        originalName: json['original_name'] as String? ?? '',
        fileSize: json['file_size'] as int?,
        downloadUrl: json['download_url'] as String? ?? '',
      );
}

class TaskActivity {
  TaskActivity({required this.id, required this.action, this.description, this.causerName, required this.createdAt});

  final int id;
  final String action;
  final String? description;
  final String? causerName;
  final String createdAt;

  factory TaskActivity.fromJson(Map<String, dynamic> json) => TaskActivity(
        id: json['id'] as int,
        action: json['action'] as String? ?? '',
        description: json['description'] as String?,
        causerName: json['causer_name'] as String?,
        createdAt: json['created_at'] as String? ?? '',
      );
}

class Task {
  Task({
    required this.id,
    required this.title,
    this.description,
    this.creatorName,
    required this.assignees,
    required this.priority,
    required this.status,
    this.startDate,
    this.dueDate,
    this.completedAt,
    required this.progress,
    this.result,
    required this.comments,
    required this.attachments,
    required this.activities,
    required this.createdAt,
  });

  final int id;
  final String title;
  final String? description;
  final String? creatorName;
  final List<Employee> assignees;
  final String priority;
  final String status;
  final String? startDate;
  final String? dueDate;
  final String? completedAt;
  final int progress;
  final String? result;
  final List<TaskComment> comments;
  final List<TaskAttachment> attachments;
  final List<TaskActivity> activities;
  final String createdAt;

  bool get isActionable => status == 'new' || status == 'in_progress' || status == 'overdue';

  factory Task.fromJson(Map<String, dynamic> json) => Task(
        id: json['id'] as int,
        title: json['title'] as String? ?? '',
        description: json['description'] as String?,
        creatorName: json['creator_name'] as String?,
        assignees: (json['assignees'] as List<dynamic>? ?? [])
            .map((e) => Employee.fromJson(e as Map<String, dynamic>))
            .toList(),
        priority: json['priority'] as String? ?? 'normal',
        status: json['status'] as String? ?? 'new',
        startDate: json['start_date'] as String?,
        dueDate: json['due_date'] as String?,
        completedAt: json['completed_at'] as String?,
        progress: json['progress'] as int? ?? 0,
        result: json['result'] as String?,
        comments: (json['comments'] as List<dynamic>? ?? [])
            .map((e) => TaskComment.fromJson(e as Map<String, dynamic>))
            .toList(),
        attachments: (json['attachments'] as List<dynamic>? ?? [])
            .map((e) => TaskAttachment.fromJson(e as Map<String, dynamic>))
            .toList(),
        activities: (json['activities'] as List<dynamic>? ?? [])
            .map((e) => TaskActivity.fromJson(e as Map<String, dynamic>))
            .toList(),
        createdAt: json['created_at'] as String? ?? '',
      );
}
