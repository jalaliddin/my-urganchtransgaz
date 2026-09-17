import 'department.dart';
import 'organization.dart';
import 'position.dart';

class Employee {
  Employee({
    required this.id,
    this.userId,
    this.organization,
    this.department,
    this.position,
    required this.employeeNumber,
    required this.firstName,
    required this.lastName,
    this.middleName,
    required this.fullName,
    this.birthDate,
    this.birthPlace,
    this.gender,
    this.phone,
    this.email,
    this.corporateEmail,
    this.address,
    this.employmentType,
    this.hireDate,
    this.terminationDate,
    this.photoUrl,
    required this.status,
  });

  final int id;
  final int? userId;
  final Organization? organization;
  final Department? department;
  final Position? position;
  final String employeeNumber;
  final String firstName;
  final String lastName;
  final String? middleName;
  final String fullName;
  final String? birthDate;
  final String? birthPlace;
  final String? gender;
  final String? phone;
  final String? email;
  final String? corporateEmail;
  final String? address;
  final String? employmentType;
  final String? hireDate;
  final String? terminationDate;
  final String? photoUrl;
  final String status;

  factory Employee.fromJson(Map<String, dynamic> json) => Employee(
        id: json['id'] as int,
        userId: json['user_id'] as int?,
        organization: json['organization'] is Map<String, dynamic>
            ? Organization.fromJson(json['organization'] as Map<String, dynamic>)
            : null,
        department: json['department'] is Map<String, dynamic>
            ? Department.fromJson(json['department'] as Map<String, dynamic>)
            : null,
        position: json['position'] is Map<String, dynamic>
            ? Position.fromJson(json['position'] as Map<String, dynamic>)
            : null,
        employeeNumber: json['employee_number'] as String? ?? '',
        firstName: json['first_name'] as String? ?? '',
        lastName: json['last_name'] as String? ?? '',
        middleName: json['middle_name'] as String?,
        fullName: json['full_name'] as String? ?? '',
        birthDate: json['birth_date'] as String?,
        birthPlace: json['birth_place'] as String?,
        gender: json['gender'] as String?,
        phone: json['phone'] as String?,
        email: json['email'] as String?,
        corporateEmail: json['corporate_email'] as String?,
        address: json['address'] as String?,
        employmentType: json['employment_type'] as String?,
        hireDate: json['hire_date'] as String?,
        terminationDate: json['termination_date'] as String?,
        photoUrl: json['photo_url'] as String?,
        status: json['status'] as String? ?? 'active',
      );
}
