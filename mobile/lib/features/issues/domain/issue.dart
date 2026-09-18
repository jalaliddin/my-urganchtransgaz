import '../../../core/models/department.dart';
import '../../../core/models/employee.dart';
import '../../../core/models/organization.dart';

class IssueActivity {
  IssueActivity({required this.id, required this.action, this.description, this.causerName, required this.createdAt});

  final int id;
  final String action;
  final String? description;
  final String? causerName;
  final String createdAt;

  factory IssueActivity.fromJson(Map<String, dynamic> json) => IssueActivity(
        id: json['id'] as int,
        action: json['action'] as String? ?? '',
        description: json['description'] as String?,
        causerName: json['causer_name'] as String?,
        createdAt: json['created_at'] as String? ?? '',
      );
}

class IssueComment {
  IssueComment({required this.id, this.userName, required this.body, required this.createdAt});

  final int id;
  final String? userName;
  final String body;
  final String createdAt;

  factory IssueComment.fromJson(Map<String, dynamic> json) => IssueComment(
        id: json['id'] as int,
        userName: json['user_name'] as String?,
        body: json['body'] as String? ?? '',
        createdAt: json['created_at'] as String? ?? '',
      );
}

class Issue {
  Issue({
    required this.id,
    this.reporter,
    this.organization,
    this.department,
    required this.title,
    this.description,
    this.objectName,
    required this.latitude,
    required this.longitude,
    required this.status,
    this.resolutionNote,
    this.resolvedByName,
    this.resolvedAt,
    required this.comments,
    required this.activities,
    required this.createdAt,
  });

  final int id;
  final Employee? reporter;
  final Organization? organization;
  final Department? department;
  final String title;
  final String? description;
  final String? objectName;
  final double latitude;
  final double longitude;
  final String status;
  final String? resolutionNote;
  final String? resolvedByName;
  final String? resolvedAt;
  final List<IssueComment> comments;
  final List<IssueActivity> activities;
  final String createdAt;

  bool get isOpen => status == 'open';

  factory Issue.fromJson(Map<String, dynamic> json) => Issue(
        id: json['id'] as int,
        reporter: json['reporter'] is Map<String, dynamic>
            ? Employee.fromJson(json['reporter'] as Map<String, dynamic>)
            : null,
        organization: json['organization'] is Map<String, dynamic>
            ? Organization.fromJson(json['organization'] as Map<String, dynamic>)
            : null,
        department: json['department'] is Map<String, dynamic>
            ? Department.fromJson(json['department'] as Map<String, dynamic>)
            : null,
        title: json['title'] as String? ?? '',
        description: json['description'] as String?,
        objectName: json['object_name'] as String?,
        latitude: (json['latitude'] as num).toDouble(),
        longitude: (json['longitude'] as num).toDouble(),
        status: json['status'] as String? ?? 'open',
        resolutionNote: json['resolution_note'] as String?,
        resolvedByName: json['resolved_by_name'] as String?,
        resolvedAt: json['resolved_at'] as String?,
        comments: (json['comments'] as List<dynamic>? ?? [])
            .map((e) => IssueComment.fromJson(e as Map<String, dynamic>))
            .toList(),
        activities: (json['activities'] as List<dynamic>? ?? [])
            .map((e) => IssueActivity.fromJson(e as Map<String, dynamic>))
            .toList(),
        createdAt: json['created_at'] as String? ?? '',
      );
}
