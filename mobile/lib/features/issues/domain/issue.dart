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

class IssueCategory {
  IssueCategory({required this.id, required this.name, required this.code});

  final int id;
  final String name;
  final String code;

  factory IssueCategory.fromJson(Map<String, dynamic> json) => IssueCategory(
        id: json['id'] as int,
        name: json['name'] as String? ?? '',
        code: json['code'] as String? ?? '',
      );
}

class IssueOrganizationOption {
  IssueOrganizationOption({required this.id, required this.name, this.isCentral = false});

  final int id;
  final String name;
  final bool isCentral;

  factory IssueOrganizationOption.fromJson(Map<String, dynamic> json) => IssueOrganizationOption(
        id: json['id'] as int,
        name: json['name'] as String? ?? '',
        isCentral: json['type'] == 'central',
      );
}

/// What the report form offers this user — decided by the server so the
/// app never re-derives role rules: the organizations they may file
/// against (every one for company-wide roles, only their own otherwise),
/// the active categories, and who to suggest as the first executor.
class IssueOptions {
  IssueOptions({required this.organizations, required this.categories, this.defaultExecutorId});

  final List<IssueOrganizationOption> organizations;
  final List<IssueCategory> categories;
  final int? defaultExecutorId;

  factory IssueOptions.fromJson(Map<String, dynamic> json) => IssueOptions(
        organizations: (json['organizations'] as List<dynamic>? ?? [])
            .map((e) => IssueOrganizationOption.fromJson(e as Map<String, dynamic>))
            .toList(),
        categories: (json['categories'] as List<dynamic>? ?? [])
            .map((e) => IssueCategory.fromJson(e as Map<String, dynamic>))
            .toList(),
        defaultExecutorId: json['default_executor_id'] as int?,
      );
}

class IssueExecutorCandidate {
  IssueExecutorCandidate({required this.id, required this.fullName, this.department, this.position});

  final int id;
  final String fullName;
  final String? department;
  final String? position;

  String get subtitle => [position, department].whereType<String>().join(' · ');

  factory IssueExecutorCandidate.fromJson(Map<String, dynamic> json) => IssueExecutorCandidate(
        id: json['id'] as int,
        fullName: json['full_name'] as String? ?? '',
        department: json['department'] as String?,
        position: json['position'] as String?,
      );
}

class Issue {
  Issue({
    required this.id,
    this.reporter,
    this.organization,
    this.department,
    this.category,
    required this.executors,
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
  final IssueCategory? category;
  final List<Employee> executors;
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
        category: json['category'] is Map<String, dynamic>
            ? IssueCategory.fromJson(json['category'] as Map<String, dynamic>)
            : null,
        executors: (json['executors'] as List<dynamic>? ?? [])
            .map((e) => Employee.fromJson(e as Map<String, dynamic>))
            .toList(),
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
