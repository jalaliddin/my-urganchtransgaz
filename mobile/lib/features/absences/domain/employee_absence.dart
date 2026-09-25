/// What a day covered by an absence counts as on the tabel — mirrors the
/// backend's AbsenceType::attendanceStatus().
enum AbsenceCategory { vacation, businessTrip, sickLeave, excused }

AbsenceCategory absenceCategoryOf(String type) => switch (type) {
      'sick_leave' => AbsenceCategory.sickLeave,
      'business_trip' => AbsenceCategory.businessTrip,
      'other' => AbsenceCategory.excused,
      _ => AbsenceCategory.vacation,
    };

class EmployeeAbsence {
  EmployeeAbsence({
    required this.id,
    required this.type,
    required this.startDate,
    required this.endDate,
    required this.days,
    required this.state,
    this.documentNumber,
    this.destination,
    this.notes,
    this.cancellationReason,
  });

  final int id;

  /// annual_leave, unpaid_leave, study_leave, maternity_leave,
  /// childcare_leave, sick_leave, business_trip, other.
  final String type;
  final DateTime startDate;
  final DateTime endDate;
  final int days;

  /// upcoming, current, completed, cancelled.
  final String state;
  final String? documentNumber;
  final String? destination;
  final String? notes;
  final String? cancellationReason;

  AbsenceCategory get category => absenceCategoryOf(type);

  factory EmployeeAbsence.fromJson(Map<String, dynamic> json) => EmployeeAbsence(
        id: json['id'] as int,
        type: json['type'] as String,
        startDate: DateTime.parse(json['start_date'] as String),
        endDate: DateTime.parse(json['end_date'] as String),
        days: json['days'] as int,
        state: json['state'] as String,
        documentNumber: json['document_number'] as String?,
        destination: json['destination'] as String?,
        notes: json['notes'] as String?,
        cancellationReason: json['cancellation_reason'] as String?,
      );
}

class LeaveBalance {
  LeaveBalance({
    required this.year,
    required this.entitlementDays,
    required this.usedDays,
    required this.remainingDays,
  });

  final int year;
  final int entitlementDays;
  final int usedDays;
  final int remainingDays;

  factory LeaveBalance.fromJson(Map<String, dynamic> json) => LeaveBalance(
        year: json['year'] as int,
        entitlementDays: json['entitlement_days'] as int,
        usedDays: json['used_days'] as int,
        remainingDays: json['remaining_days'] as int,
      );
}
