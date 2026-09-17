import 'package:flutter_test/flutter_test.dart';
import 'package:urtg_mobile/core/models/employee.dart';
import 'package:urtg_mobile/core/models/user.dart';

void main() {
  group('Employee.fromJson', () {
    test('parses a full EmployeeResource payload with loaded relations', () {
      final employee = Employee.fromJson({
        'id': 1,
        'user_id': 5,
        'organization_id': 2,
        'organization': {'id': 2, 'name': 'Urganchtransgaz', 'short_name': 'UTG', 'code': 'UTG-000'},
        'department_id': 3,
        'department': {'id': 3, 'name': 'IT bo\'limi', 'code': 'IT'},
        'position_id': 4,
        'position': {'id': 4, 'title': 'Dasturchi'},
        'employee_number': 'EMP00001',
        'first_name': 'Ali',
        'last_name': 'Valiyev',
        'full_name': 'Valiyev Ali',
        'phone': '+998901234567',
        'email': 'ali@example.com',
        'status': 'active',
        'photo_url': 'http://localhost:8000/employees/1/photo',
      });

      expect(employee.id, 1);
      expect(employee.organization?.name, 'Urganchtransgaz');
      expect(employee.department?.code, 'IT');
      expect(employee.position?.title, 'Dasturchi');
      expect(employee.fullName, 'Valiyev Ali');
      expect(employee.status, 'active');
      expect(employee.photoUrl, isNotNull);
    });

    test('treats a not-eager-loaded relation (absent key) as null, not a parse error', () {
      final employee = Employee.fromJson({
        'id': 1,
        'employee_number': 'EMP00001',
        'first_name': 'Ali',
        'last_name': 'Valiyev',
        'full_name': 'Valiyev Ali',
        'status': 'active',
      });

      expect(employee.organization, isNull);
      expect(employee.department, isNull);
      expect(employee.position, isNull);
    });
  });

  group('User.fromJson', () {
    test('parses roles/permissions and computes hasCentralAccess', () {
      final hrUser = User.fromJson({
        'id': 1,
        'name': 'HR User',
        'status': 'active',
        'roles': ['hr'],
        'permissions': ['employees.view'],
      });

      final plainEmployee = User.fromJson({
        'id': 2,
        'name': 'Plain Employee',
        'status': 'active',
        'roles': ['employee'],
        'permissions': ['tasks.view'],
      });

      expect(hrUser.hasCentralAccess, isTrue);
      expect(hrUser.can('employees.view'), isTrue);
      expect(plainEmployee.hasCentralAccess, isFalse);
      expect(plainEmployee.hasRole('employee'), isTrue);
    });
  });
}
