// ignore: unused_import
import 'package:intl/intl.dart' as intl;

import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for English (`en`).
class AppLocalizationsEn extends AppLocalizations {
  AppLocalizationsEn([String locale = 'en']) : super(locale);

  @override
  String get appName => 'URTG Employees';

  @override
  String get commonRetry => 'Retry';

  @override
  String get commonCancel => 'Cancel';

  @override
  String get commonSave => 'Save';

  @override
  String get commonClose => 'Close';

  @override
  String get commonLoading => 'Loading...';

  @override
  String get commonNoData => 'No data found';

  @override
  String get commonErrorGeneric => 'Something went wrong. Please try again.';

  @override
  String get commonLogout => 'Log out';

  @override
  String get commonConfirm => 'Confirm';

  @override
  String get commonYes => 'Yes';

  @override
  String get commonNo => 'No';

  @override
  String get commonOk => 'OK';

  @override
  String get commonSubmit => 'Submit';

  @override
  String get commonRequired => 'This field is required.';

  @override
  String get commonSend => 'Send';

  @override
  String get authLogin => 'Login';

  @override
  String get authLoginField => 'Login (email, username, or employee number)';

  @override
  String get authPassword => 'Password';

  @override
  String get authLoginButton => 'Sign in';

  @override
  String get authForgotPassword => 'Forgot your password?';

  @override
  String get authLoginError => 'Incorrect login or password.';

  @override
  String get authWelcomeBack => 'Welcome back';

  @override
  String get authServerAddress => 'Server address';

  @override
  String get authServerAddressHint => 'e.g. http://192.168.1.10:8000/api/v1';

  @override
  String get authForgotPasswordTitle => 'Reset password';

  @override
  String get authSendResetLink => 'Send reset link';

  @override
  String get authCheckYourEmail =>
      'A password reset link has been sent to your email. Open it on a computer to finish resetting your password.';

  @override
  String get navDashboard => 'Home';

  @override
  String get navAttendance => 'Attendance';

  @override
  String get navTasks => 'Tasks';

  @override
  String get navMore => 'More';

  @override
  String get navProfile => 'Profile';

  @override
  String get navDocuments => 'Documents';

  @override
  String get navKpi => 'KPI';

  @override
  String get navExams => 'Safety Exams';

  @override
  String get navLeaveRequests => 'Leave Requests';

  @override
  String get navAnnouncements => 'Announcements';

  @override
  String get navNotifications => 'Notifications';

  @override
  String get dashboardTitle => 'Home';

  @override
  String get dashboardWelcome => 'Welcome';

  @override
  String get dashboardTodayAttendance => 'Today\'s attendance';

  @override
  String get dashboardNotCheckedInYet => 'You haven\'t checked in yet';

  @override
  String dashboardCheckedInAt(Object time) {
    return 'Checked in at $time';
  }

  @override
  String dashboardCheckedOutAt(Object time) {
    return 'Checked out at $time';
  }

  @override
  String get dashboardLatestAnnouncements => 'Latest announcements';

  @override
  String get dashboardSeeAll => 'See all';

  @override
  String get dashboardMyTasks => 'My tasks';

  @override
  String get dashboardUpcomingTrip => 'Upcoming business trip';

  @override
  String get dashboardNoAnnouncements => 'No announcements yet';

  @override
  String get dashboardNoTasks => 'No active tasks';

  @override
  String get attendanceTitle => 'Attendance';

  @override
  String get attendanceTabToday => 'Today';

  @override
  String get attendanceTabHistory => 'History';

  @override
  String get attendanceCheckIn => 'Check in';

  @override
  String get attendanceCheckOut => 'Check out';

  @override
  String get attendanceCheckedIn => 'Checked in';

  @override
  String get attendanceCheckedOut => 'Checked out';

  @override
  String get attendanceStatus => 'Status';

  @override
  String get attendanceWorked => 'Worked';

  @override
  String get attendanceFrom => 'From';

  @override
  String get attendanceTo => 'To';

  @override
  String get attendanceNoRecords => 'No records for this period';

  @override
  String get profileTitle => 'My Profile';

  @override
  String get profileTabInfo => 'Info';

  @override
  String get profileTabRequests => 'My requests';

  @override
  String get profilePersonalInfo => 'Personal information';

  @override
  String get profileContactInfo => 'Contact information';

  @override
  String get profileSensitiveNotice =>
      'Changing these fields requires HR approval.';

  @override
  String get profilePendingNotice =>
      'Some changes are currently pending review.';

  @override
  String get profilePhoto => 'Photo';

  @override
  String get profileUploadPhoto => 'Upload photo';

  @override
  String get profileMyChangeRequests => 'My change requests';

  @override
  String get profileNoPendingRequests => 'No pending requests';

  @override
  String get profileFirstName => 'First name';

  @override
  String get profileLastName => 'Last name';

  @override
  String get profileMiddleName => 'Middle name';

  @override
  String get profilePhone => 'Phone';

  @override
  String get profileEmail => 'Email';

  @override
  String get profileAddress => 'Address';

  @override
  String get profileBirthDate => 'Date of birth';

  @override
  String get profileBirthPlace => 'Place of birth';

  @override
  String get profileGender => 'Gender';

  @override
  String get profileGenderMale => 'Male';

  @override
  String get profileGenderFemale => 'Female';

  @override
  String get profileOrganization => 'Organization';

  @override
  String get profileDepartment => 'Department';

  @override
  String get profilePosition => 'Position';

  @override
  String get profileEmployeeNumber => 'Employee number';

  @override
  String get profileHireDate => 'Hire date';

  @override
  String get profileSaveSuccess => 'Profile updated successfully.';

  @override
  String get profileSavePending =>
      'Profile updated. Some changes are pending approval.';

  @override
  String get documentsTitle => 'Documents';

  @override
  String get documentsUpload => 'Upload document';

  @override
  String get documentsType => 'Document type';

  @override
  String get documentsDocTitle => 'Title';

  @override
  String get documentsNumber => 'Document number';

  @override
  String get documentsIssueDate => 'Issue date';

  @override
  String get documentsExpiryDate => 'Expiry date';

  @override
  String get documentsFile => 'File';

  @override
  String get documentsChooseFile => 'Choose file';

  @override
  String get documentsDownload => 'Download';

  @override
  String get documentsNoExpiry => 'No expiry';

  @override
  String get documentsNone => 'No documents';

  @override
  String get tasksTitle => 'Tasks';

  @override
  String get tasksFieldTitle => 'Title';

  @override
  String get tasksDescription => 'Description';

  @override
  String get tasksAssignees => 'Assignees';

  @override
  String get tasksPriority => 'Priority';

  @override
  String get tasksDueDate => 'Due date';

  @override
  String get tasksProgress => 'Progress';

  @override
  String get tasksUpdateProgress => 'Update';

  @override
  String get tasksResult => 'Result';

  @override
  String get tasksMarkComplete => 'Mark complete';

  @override
  String get tasksComments => 'Comments';

  @override
  String get tasksAddComment => 'Write a comment';

  @override
  String get tasksAttachments => 'Attachments';

  @override
  String get tasksCreator => 'Creator';

  @override
  String get tasksNone => 'No active tasks';

  @override
  String get kpiTitle => 'KPI';

  @override
  String get kpiMyResults => 'My results';

  @override
  String get kpiPeriod => 'Period';

  @override
  String get kpiIndicator => 'Indicator';

  @override
  String get kpiTarget => 'Target';

  @override
  String get kpiActual => 'Actual';

  @override
  String get kpiScore => 'Score';

  @override
  String get kpiWeight => 'Weight';

  @override
  String get kpiNone => 'No results yet';

  @override
  String get examsTitle => 'Safety Exams';

  @override
  String get examsTabAvailable => 'Available';

  @override
  String get examsTabResults => 'My results';

  @override
  String get examsDuration => 'Duration';

  @override
  String get examsMinutes => 'min';

  @override
  String get examsPassingScore => 'Passing score';

  @override
  String get examsAttemptsAllowed => 'Attempts allowed';

  @override
  String get examsStart => 'Start';

  @override
  String get examsSubmit => 'Submit';

  @override
  String get examsScore => 'Score';

  @override
  String get examsBestScore => 'Best score';

  @override
  String get examsAttemptsUsed => 'Attempts';

  @override
  String get examsNotTaken => 'Not taken';

  @override
  String get examsPassed => 'Passed';

  @override
  String get examsFailed => 'Failed';

  @override
  String get examsTrue => 'True';

  @override
  String get examsFalse => 'False';

  @override
  String get examsConfirmSubmitTitle => 'Submit exam';

  @override
  String get examsConfirmSubmitText =>
      'Once submitted, answers can\'t be changed. Continue?';

  @override
  String get examsConfirmLeaveText =>
      'Leave the exam? Your answers won\'t be saved.';

  @override
  String get examsNone => 'No available exams';

  @override
  String get leaveRequestsTitle => 'Leave Requests';

  @override
  String get leaveRequestsType => 'Type';

  @override
  String get leaveRequestsStartDate => 'Start date';

  @override
  String get leaveRequestsEndDate => 'End date';

  @override
  String get leaveRequestsReason => 'Reason';

  @override
  String get leaveRequestsCreate => 'Submit request';

  @override
  String get leaveRequestsCancel => 'Cancel';

  @override
  String get leaveRequestsNone => 'No requests';

  @override
  String get leaveRequestsVacation => 'Vacation';

  @override
  String get leaveRequestsBusinessTrip => 'Business trip';

  @override
  String get leaveRequestsSickLeave => 'Sick leave';

  @override
  String get leaveRequestsOther => 'Other';

  @override
  String get announcementsTitle => 'Announcements';

  @override
  String get announcementsNone => 'No announcements';

  @override
  String get announcementsAuthor => 'Author';

  @override
  String get notificationsTitle => 'Notifications';

  @override
  String get notificationsMarkAllRead => 'Mark all as read';

  @override
  String get notificationsNone => 'No notifications';

  @override
  String get statusActive => 'Active';

  @override
  String get statusInactive => 'Inactive';

  @override
  String get statusVacation => 'On vacation';

  @override
  String get statusBusinessTrip => 'Business trip';

  @override
  String get statusSickLeave => 'Sick leave';

  @override
  String get statusTerminated => 'Terminated';

  @override
  String get statusPending => 'Pending';

  @override
  String get statusDepartmentApproved => 'Approved by department';

  @override
  String get statusApproved => 'Approved';

  @override
  String get statusRejected => 'Rejected';

  @override
  String get statusPresent => 'Present';

  @override
  String get statusLate => 'Late';

  @override
  String get statusEarlyLeave => 'Left early';

  @override
  String get statusAbsent => 'Absent';

  @override
  String get statusNew => 'New';

  @override
  String get statusInProgress => 'In progress';

  @override
  String get statusWaiting => 'Awaiting approval';

  @override
  String get statusCompleted => 'Completed';

  @override
  String get statusCancelled => 'Cancelled';

  @override
  String get statusOverdue => 'Overdue';

  @override
  String get statusLow => 'Low';

  @override
  String get statusNormal => 'Normal';

  @override
  String get statusHigh => 'High';

  @override
  String get statusUrgent => 'Urgent';
}
