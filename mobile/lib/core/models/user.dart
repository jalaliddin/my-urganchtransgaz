import 'employee.dart';

class User {
  User({
    required this.id,
    required this.name,
    this.username,
    this.email,
    required this.status,
    required this.roles,
    required this.permissions,
    this.employee,
  });

  final int id;
  final String name;
  final String? username;
  final String? email;
  final String status;
  final List<String> roles;
  final List<String> permissions;
  final Employee? employee;

  factory User.fromJson(Map<String, dynamic> json) => User(
        id: json['id'] as int,
        name: json['name'] as String? ?? '',
        username: json['username'] as String?,
        email: json['email'] as String?,
        status: json['status'] as String? ?? 'active',
        roles: (json['roles'] as List<dynamic>? ?? []).map((e) => e.toString()).toList(),
        permissions: (json['permissions'] as List<dynamic>? ?? []).map((e) => e.toString()).toList(),
        employee: json['employee'] is Map<String, dynamic>
            ? Employee.fromJson(json['employee'] as Map<String, dynamic>)
            : null,
      );

  bool hasRole(String role) => roles.contains(role);

  bool can(String permission) => permissions.contains(permission);

  bool get hasCentralAccess =>
      hasRole('super-admin') ||
      hasRole('central-admin') ||
      hasRole('hr') ||
      hasRole('technical-policy');
}
