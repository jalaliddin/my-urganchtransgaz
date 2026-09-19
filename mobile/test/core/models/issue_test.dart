import 'package:flutter_test/flutter_test.dart';
import 'package:urtg_mobile/features/issues/domain/issue.dart';

void main() {
  group('Issue.fromJson', () {
    test('parses an open issue with reporter/organization/department loaded', () {
      final issue = Issue.fromJson({
        'id': 1,
        'reporter': {'id': 5, 'employee_number': 'EMP00005', 'first_name': 'Ali', 'last_name': 'Valiyev', 'full_name': 'Valiyev Ali', 'status': 'active'},
        'organization': {'id': 2, 'name': 'Filial 1'},
        'department': {'id': 8, 'name': 'Texnik xizmat guruhi'},
        'title': 'Gaz quvuri shikastlangan',
        'description': 'Quvurdan gaz hidi kelmoqda.',
        'object_name': '3-nasos stansiyasi',
        'latitude': 41.55,
        'longitude': 60.63,
        'status': 'open',
        'comments': [],
        'activities': [
          {'id': 1, 'action': 'created', 'description': 'Muammo qayd etildi.', 'created_at': '2026-09-17T17:33:15Z'},
        ],
        'created_at': '2026-09-17T17:33:15Z',
      });

      expect(issue.title, 'Gaz quvuri shikastlangan');
      expect(issue.isOpen, isTrue);
      expect(issue.reporter?.fullName, 'Valiyev Ali');
      expect(issue.organization?.name, 'Filial 1');
      expect(issue.department?.name, 'Texnik xizmat guruhi');
      expect(issue.latitude, 41.55);
      expect(issue.longitude, 60.63);
      expect(issue.activities, hasLength(1));
    });

    test('a resolved issue is not open and carries the resolution note', () {
      final issue = Issue.fromJson({
        'id': 2,
        'title': 'Resolved issue',
        'latitude': 41.5,
        'longitude': 60.6,
        'status': 'resolved',
        'resolution_note': 'Ta\'mirlandi.',
        'resolved_by_name': 'Technical policy',
        'resolved_at': '2026-09-17T18:00:00Z',
        'comments': [],
        'activities': [],
        'created_at': '2026-09-17T17:33:15Z',
      });

      expect(issue.isOpen, isFalse);
      expect(issue.resolutionNote, 'Ta\'mirlandi.');
      expect(issue.resolvedByName, 'Technical policy');
    });

    test('treats an integer latitude/longitude (no decimal point) as valid num', () {
      final issue = Issue.fromJson({
        'id': 3,
        'title': 'Integer coords',
        'latitude': 41,
        'longitude': 60,
        'status': 'open',
        'comments': [],
        'activities': [],
        'created_at': '2026-09-17T17:33:15Z',
      });

      expect(issue.latitude, 41.0);
      expect(issue.longitude, 60.0);
    });
  });

  group('Issue create-form models', () {
    test('IssueOptions parses organizations, categories and the choose-responsible flag', () {
      final options = IssueOptions.fromJson({
        'organizations': [
          {'id': 3, 'name': 'Urganch shahar gaz ta\'minoti boshqarmasi', 'code': 'UTG-001'},
        ],
        'categories': [
          {'id': 1, 'name': 'Gaz sizib chiqishi', 'code': 'gas_leak'},
        ],
        'must_choose_responsible': true,
      });

      expect(options.organizations.single.id, 3);
      expect(options.categories.single.code, 'gas_leak');
      expect(options.mustChooseResponsible, isTrue);
    });

    test('IssueResponsibleCandidate joins position and department into a subtitle', () {
      final candidate = IssueResponsibleCandidate.fromJson({
        'id': 9,
        'full_name': 'Valiyev Ali',
        'department': 'Texnik xizmat guruhi',
        'position': 'Bo\'lim boshlig\'i',
      });

      expect(candidate.subtitle, 'Bo\'lim boshlig\'i · Texnik xizmat guruhi');
    });

    test('Issue carries its category and responsible employee', () {
      final issue = Issue.fromJson({
        'id': 4,
        'title': 'Gaz hidi',
        'latitude': 41.5,
        'longitude': 60.6,
        'status': 'open',
        'category': {'id': 1, 'name': 'Gaz sizib chiqishi', 'code': 'gas_leak'},
        'responsible': {'id': 9, 'employee_number': 'EMP00009', 'first_name': 'Ali', 'last_name': 'Valiyev', 'full_name': 'Valiyev Ali', 'status': 'active'},
        'comments': [],
        'activities': [],
        'created_at': '2026-09-19T10:00:00Z',
      });

      expect(issue.category?.name, 'Gaz sizib chiqishi');
      expect(issue.responsible?.fullName, 'Valiyev Ali');
    });
  });
}
