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
  IssueOrganizationOption({required this.id, required this.name});

  final int id;
  final String name;

  factory IssueOrganizationOption.fromJson(Map<String, dynamic> json) =>
      IssueOrganizationOption(id: json['id'] as int, name: json['name'] as String? ?? '');
}

/// What the report form offers this user — decided by the server so the
/// app never re-derives role rules: which organizations they may file
/// against, the categories, and whether they must name a responsible
/// employee (a department-manager is responsible for what they report).
class IssueOptions {
  IssueOptions({required this.organizations, required this.categories, required this.mustChooseResponsible});

  final List<IssueOrganizationOption> organizations;
  final List<IssueCategory> categories;
  final bool mustChooseResponsible;

  factory IssueOptions.fromJson(Map<String, dynamic> json) => IssueOptions(
        organizations: (json['organizations'] as List<dynamic>? ?? [])
            .map((e) => IssueOrganizationOption.fromJson(e as Map<String, dynamic>))
            .toList(),
        categories: (json['categories'] as List<dynamic>? ?? [])
            .map((e) => IssueCategory.fromJson(e as Map<String, dynamic>))
            .toList(),
        mustChooseResponsible: json['must_choose_responsible'] as bool? ?? false,
      );
}

class IssueResponsibleCandidate {
  IssueResponsibleCandidate({required this.id, required this.fullName, this.department, this.position});

  final int id;
  final String fullName;
  final String? department;
  final String? position;

  String get subtitle => [position, department].whereType<String>().join(' · ');

  factory IssueResponsibleCandidate.fromJson(Map<String, dynamic> json) => IssueResponsibleCandidate(
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
    this.responsible,
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
  final Employee? responsible;
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
        responsible: json['responsible'] is Map<String, dynamic>
            ? Employee.fromJson(json['responsible'] as Map<String, dynamic>)
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
