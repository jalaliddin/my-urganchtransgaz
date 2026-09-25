<?php

namespace App\Enums;

enum AbsenceType: string
{
    case AnnualLeave = 'annual_leave';
    case UnpaidLeave = 'unpaid_leave';
    case StudyLeave = 'study_leave';
    case MaternityLeave = 'maternity_leave';
    case ChildcareLeave = 'childcare_leave';
    case SickLeave = 'sick_leave';
    case BusinessTrip = 'business_trip';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::AnnualLeave => 'Yillik mehnat ta\'tili',
            self::UnpaidLeave => 'Haq to\'lanmaydigan ta\'til',
            self::StudyLeave => 'O\'quv ta\'tili',
            self::MaternityLeave => 'Homiladorlik va tug\'ish ta\'tili',
            self::ChildcareLeave => 'Bola parvarishi ta\'tili',
            self::SickLeave => 'Kasallik varaqasi',
            self::BusinessTrip => 'Xizmat safari',
            self::Other => 'Boshqa sababli yo\'qlik',
        };
    }

    /**
     * What a day covered by this absence is recorded as on the tabel.
     */
    public function attendanceStatus(): AttendanceStatus
    {
        return match ($this) {
            self::SickLeave => AttendanceStatus::SickLeave,
            self::BusinessTrip => AttendanceStatus::BusinessTrip,
            self::Other => AttendanceStatus::Excused,
            default => AttendanceStatus::Vacation,
        };
    }

    /**
     * The employee status shown while this absence is in effect — null
     * for "other", which has no matching status and leaves the employee
     * as they were.
     */
    public function employeeStatus(): ?EmployeeStatus
    {
        return match ($this) {
            self::SickLeave => EmployeeStatus::SickLeave,
            self::BusinessTrip => EmployeeStatus::BusinessTrip,
            self::Other => null,
            default => EmployeeStatus::Vacation,
        };
    }
}
