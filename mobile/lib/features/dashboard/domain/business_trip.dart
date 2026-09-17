/// Read-only, dashboard-only: the mobile scope doesn't include creating
/// or managing business trips (HR/admin-only on the web), just showing
/// the caller's own upcoming ones.
class BusinessTrip {
  BusinessTrip({required this.id, required this.destination, required this.startDate, required this.endDate});

  final int id;
  final String destination;
  final String startDate;
  final String endDate;

  factory BusinessTrip.fromJson(Map<String, dynamic> json) => BusinessTrip(
        id: json['id'] as int,
        destination: json['destination'] as String? ?? '',
        startDate: json['start_date'] as String? ?? '',
        endDate: json['end_date'] as String? ?? '',
      );
}
