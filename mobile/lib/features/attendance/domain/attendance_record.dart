class AttendanceRecord {
  AttendanceRecord({
    required this.id,
    required this.employeeId,
    required this.date,
    this.checkIn,
    this.checkOut,
    this.workedMinutes,
    required this.status,
  });

  final int id;
  final int employeeId;
  final String date;
  final String? checkIn;
  final String? checkOut;
  final int? workedMinutes;
  final String status;

  factory AttendanceRecord.fromJson(Map<String, dynamic> json) => AttendanceRecord(
        id: json['id'] as int,
        employeeId: json['employee_id'] as int,
        date: json['date'] as String? ?? '',
        checkIn: json['check_in'] as String?,
        checkOut: json['check_out'] as String?,
        workedMinutes: json['worked_minutes'] as int?,
        status: json['status'] as String? ?? '',
      );
}

/// One row of the scoped "today" board (`TodayAttendanceResource`) — the
/// mobile dashboard/attendance-today screen finds the caller's own entry
/// in this list by `employeeId` rather than assuming the endpoint returns
/// only "me" (it's shared with the web app's broader HR/manager board).
class TodayAttendanceEntry {
  TodayAttendanceEntry({
    required this.employeeId,
    required this.employeeNumber,
    required this.fullName,
    this.attendance,
  });

  final int employeeId;
  final String employeeNumber;
  final String fullName;
  final AttendanceRecord? attendance;

  factory TodayAttendanceEntry.fromJson(Map<String, dynamic> json) => TodayAttendanceEntry(
        employeeId: json['employee_id'] as int,
        employeeNumber: json['employee_number'] as String? ?? '',
        fullName: json['full_name'] as String? ?? '',
        attendance: json['attendance'] is Map<String, dynamic>
            ? AttendanceRecord.fromJson(json['attendance'] as Map<String, dynamic>)
            : null,
      );
}
