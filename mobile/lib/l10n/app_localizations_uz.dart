// ignore: unused_import
import 'package:intl/intl.dart' as intl;

import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for Uzbek (`uz`).
class AppLocalizationsUz extends AppLocalizations {
  AppLocalizationsUz([String locale = 'uz']) : super(locale);

  @override
  String get appName => 'My Urganchtransgaz';

  @override
  String get appCorporatePortal => 'Urganchtransgaz Korporativ Portali';

  @override
  String get commonRetry => 'Qayta urinish';

  @override
  String get commonCancel => 'Bekor qilish';

  @override
  String get commonSave => 'Saqlash';

  @override
  String get commonClose => 'Yopish';

  @override
  String get commonLoading => 'Yuklanmoqda...';

  @override
  String get commonNoData => 'Ma\'lumot topilmadi';

  @override
  String get commonErrorGeneric => 'Xatolik yuz berdi. Qayta urinib ko\'ring.';

  @override
  String get commonFileOpenError => 'Faylni ochib bo\'lmadi.';

  @override
  String get commonLogout => 'Tizimdan chiqish';

  @override
  String get commonConfirm => 'Tasdiqlash';

  @override
  String get commonYes => 'Ha';

  @override
  String get commonNo => 'Yo\'q';

  @override
  String get commonOk => 'OK';

  @override
  String get commonSubmit => 'Yuborish';

  @override
  String get commonRequired => 'Bu maydon to\'ldirilishi shart.';

  @override
  String get commonSend => 'Yuborish';

  @override
  String get authLogin => 'Kirish';

  @override
  String get authLoginField =>
      'Login (email, foydalanuvchi nomi yoki tabel raqami)';

  @override
  String get authPassword => 'Parol';

  @override
  String get authLoginButton => 'Kirish';

  @override
  String get authForgotPassword => 'Parolni unutdingizmi?';

  @override
  String get authLoginError => 'Login yoki parol noto\'g\'ri.';

  @override
  String get authWelcomeBack => 'Xush kelibsiz';

  @override
  String get authServerAddress => 'Server manzili';

  @override
  String get authServerAddressHint =>
      'Masalan: http://192.168.1.10:8000/api/v1';

  @override
  String get authForgotPasswordTitle => 'Parolni tiklash';

  @override
  String get authSendResetLink => 'Havola yuborish';

  @override
  String get authCheckYourEmail =>
      'Parolni tiklash havolasi emailingizga yuborildi. Havolani ochish uchun kompyuterdan veb-saytga o\'ting.';

  @override
  String get navDashboard => 'Bosh sahifa';

  @override
  String get navAttendance => 'Davomat';

  @override
  String get navTasks => 'Topshiriqlar';

  @override
  String get navMore => 'Yana';

  @override
  String get navProfile => 'Profil';

  @override
  String get navDocuments => 'Hujjatlar';

  @override
  String get navKpi => 'KPI';

  @override
  String get navExams => 'Imtihonlar';

  @override
  String get navAnnouncements => 'E\'lonlar';

  @override
  String get navNotifications => 'Bildirishnomalar';

  @override
  String get dashboardTitle => 'Bosh sahifa';

  @override
  String get dashboardWelcome => 'Xush kelibsiz';

  @override
  String get dashboardTodayAttendance => 'Bugungi davomat';

  @override
  String get dashboardNotCheckedInYet =>
      'Siz hali kelmadingiz deb belgilangansiz';

  @override
  String dashboardCheckedInAt(Object time) {
    return 'Kelgan vaqt: $time';
  }

  @override
  String dashboardCheckedOutAt(Object time) {
    return 'Ketgan vaqt: $time';
  }

  @override
  String get dashboardLatestAnnouncements => 'So\'nggi e\'lonlar';

  @override
  String get dashboardSeeAll => 'Barchasini ko\'rish';

  @override
  String get dashboardMyTasks => 'Mening topshiriqlarim';

  @override
  String get dashboardNoAnnouncements => 'Hozircha e\'lonlar yo\'q';

  @override
  String get dashboardNoTasks => 'Faol topshiriqlar yo\'q';

  @override
  String get attendanceTitle => 'Davomat';

  @override
  String get attendanceTabToday => 'Bugun';

  @override
  String get attendanceTabHistory => 'Tarix';

  @override
  String get attendanceCheckIn => 'Keldim';

  @override
  String get attendanceCheckOut => 'Ketdim';

  @override
  String get attendanceCheckedIn => 'Kelganingiz qayd etildi';

  @override
  String get attendanceCheckedOut => 'Ketganingiz qayd etildi';

  @override
  String get attendanceStatus => 'Holat';

  @override
  String get attendanceWorked => 'Ishlangan vaqt';

  @override
  String get attendanceFrom => 'Dan';

  @override
  String get attendanceTo => 'Gacha';

  @override
  String get attendanceNoRecords => 'Bu davr uchun yozuvlar yo\'q';

  @override
  String get profileTitle => 'Mening profilim';

  @override
  String get profileTabInfo => 'Ma\'lumotlar';

  @override
  String get profileTabRequests => 'So\'rovlarim';

  @override
  String get profilePersonalInfo => 'Shaxsiy ma\'lumotlar';

  @override
  String get profileContactInfo => 'Aloqa ma\'lumotlari';

  @override
  String get profileSensitiveNotice =>
      'Bu maydonlarni o\'zgartirish HR tasdig\'ini talab qiladi.';

  @override
  String get profilePendingNotice =>
      'Ba\'zi o\'zgarishlar hozirda ko\'rib chiqilmoqda.';

  @override
  String get profilePhoto => 'Rasm';

  @override
  String get profileUploadPhoto => 'Rasm yuklash';

  @override
  String get profileMyChangeRequests => 'Mening so\'rovlarim';

  @override
  String get profileNoPendingRequests => 'Kutilayotgan so\'rovlar yo\'q';

  @override
  String get profileFirstName => 'Ismi';

  @override
  String get profileLastName => 'Familiyasi';

  @override
  String get profileMiddleName => 'Sharifi';

  @override
  String get profilePhone => 'Telefon';

  @override
  String get profileEmail => 'Email';

  @override
  String get profileAddress => 'Manzil';

  @override
  String get profileBirthDate => 'Tug\'ilgan sana';

  @override
  String get profileBirthPlace => 'Tug\'ilgan joyi';

  @override
  String get profileGender => 'Jinsi';

  @override
  String get profileGenderMale => 'Erkak';

  @override
  String get profileGenderFemale => 'Ayol';

  @override
  String get profileOrganization => 'Tashkilot';

  @override
  String get profileDepartment => 'Bo\'lim';

  @override
  String get profilePosition => 'Lavozim';

  @override
  String get profileEmployeeNumber => 'Tabel raqami';

  @override
  String get profileHireDate => 'Ishga qabul sanasi';

  @override
  String get profileSaveSuccess => 'Profil muvaffaqiyatli yangilandi.';

  @override
  String get profileSavePending =>
      'Profil yangilandi. Ba\'zi o\'zgarishlar tasdiqlashni kutmoqda.';

  @override
  String get documentsTitle => 'Hujjatlar';

  @override
  String get documentsUpload => 'Hujjat yuklash';

  @override
  String get documentsType => 'Hujjat turi';

  @override
  String get documentsDocTitle => 'Nomi';

  @override
  String get documentsNumber => 'Hujjat raqami';

  @override
  String get documentsIssueDate => 'Berilgan sana';

  @override
  String get documentsExpiryDate => 'Amal qilish muddati';

  @override
  String get documentsFile => 'Fayl';

  @override
  String get documentsChooseFile => 'Faylni tanlash';

  @override
  String get documentsDownload => 'Yuklab olish';

  @override
  String get documentsNoExpiry => 'Muddatsiz';

  @override
  String get documentsNone => 'Hujjatlar yo\'q';

  @override
  String get tasksTitle => 'Topshiriqlar';

  @override
  String get tasksFieldTitle => 'Sarlavha';

  @override
  String get tasksDescription => 'Tavsif';

  @override
  String get tasksAssignees => 'Bajaruvchilar';

  @override
  String get tasksPriority => 'Muhimlik';

  @override
  String get tasksDueDate => 'Muddati';

  @override
  String get tasksProgress => 'Bajarilishi';

  @override
  String get tasksUpdateProgress => 'Yangilash';

  @override
  String get tasksResult => 'Natija';

  @override
  String get tasksMarkComplete => 'Bajarildi deb belgilash';

  @override
  String get tasksComments => 'Izohlar';

  @override
  String get tasksAddComment => 'Izoh yozing';

  @override
  String get tasksAttachments => 'Fayllar';

  @override
  String get tasksCreator => 'Yaratuvchi';

  @override
  String get tasksNone => 'Faol topshiriqlar yo\'q';

  @override
  String get kpiTitle => 'KPI';

  @override
  String get kpiMyResults => 'Mening natijalarim';

  @override
  String get kpiPeriod => 'Davr';

  @override
  String get kpiIndicator => 'Ko\'rsatkich';

  @override
  String get kpiTarget => 'Reja';

  @override
  String get kpiActual => 'Bajarilgan';

  @override
  String get kpiScore => 'Ball';

  @override
  String get kpiWeight => 'Og\'irlik';

  @override
  String get kpiNone => 'Hali natijalar yo\'q';

  @override
  String get examsTitle => 'Imtihonlar';

  @override
  String get examsTabAvailable => 'Mavjud';

  @override
  String get examsTabResults => 'Natijalarim';

  @override
  String get examsDuration => 'Davomiyligi';

  @override
  String get examsMinutes => 'daqiqa';

  @override
  String get examsPassingScore => 'O\'tish balli';

  @override
  String get examsAttemptsAllowed => 'Ruxsat etilgan urinishlar';

  @override
  String get examsStart => 'Boshlash';

  @override
  String get examsSubmit => 'Yakunlash';

  @override
  String get examsScore => 'Ball';

  @override
  String get examsBestScore => 'Eng yaxshi natija';

  @override
  String get examsAttemptsUsed => 'Urinishlar';

  @override
  String get examsNotTaken => 'Topshirilmagan';

  @override
  String get examsPassed => 'O\'tdi';

  @override
  String get examsFailed => 'O\'ta olmadi';

  @override
  String get examsTrue => 'To\'g\'ri';

  @override
  String get examsFalse => 'Noto\'g\'ri';

  @override
  String get examsConfirmSubmitTitle => 'Imtihonni yakunlash';

  @override
  String get examsConfirmSubmitText =>
      'Javoblarni yuborgandan so\'ng ularni o\'zgartirib bo\'lmaydi. Davom etasizmi?';

  @override
  String get examsConfirmLeaveText =>
      'Imtihondan chiqmoqchimisiz? Javoblaringiz saqlanmaydi.';

  @override
  String get examsNone => 'Mavjud imtihonlar yo\'q';

  @override
  String get issuesTitle => 'Muammolar';

  @override
  String get issuesReportIssue => 'Yangi muammo';

  @override
  String get issuesIssueTitle => 'Sarlavha';

  @override
  String get issuesDescription => 'Tavsif';

  @override
  String get issuesObjectName => 'Obyekt/hudud nomi';

  @override
  String get issuesUseMyLocation => 'Joriy joylashuvimdan foydalanish';

  @override
  String get issuesMapPickHint => 'Xaritada joyni bosib belgilang';

  @override
  String get issuesLocationError =>
      'Joylashuvni aniqlab bo\'lmadi. GPS yoqilganini tekshiring.';

  @override
  String get issuesReporter => 'Xabar bergan';

  @override
  String get issuesDepartment => 'Bo\'lim';

  @override
  String get issuesFilterOpen => 'Ochiq';

  @override
  String get issuesFilterAll => 'Barchasi';

  @override
  String get issuesTimeline => 'Tarix';

  @override
  String get issuesComments => 'Izohlar';

  @override
  String get issuesAddComment => 'Izoh yozing';

  @override
  String get issuesResolve => 'Hal qilish';

  @override
  String get issuesResolutionNote => 'Javob (bartaraf etish uchun)';

  @override
  String get issuesResolvedBy => 'Bartaraf etdi';

  @override
  String get issuesNone => 'Muammolar topilmadi';

  @override
  String get announcementsTitle => 'E\'lonlar';

  @override
  String get announcementsNone => 'E\'lonlar yo\'q';

  @override
  String get announcementsAuthor => 'Muallif';

  @override
  String get notificationsTitle => 'Bildirishnomalar';

  @override
  String get notificationsMarkAllRead => 'Barchasini o\'qilgan deb belgilash';

  @override
  String get notificationsNone => 'Bildirishnomalar yo\'q';

  @override
  String get statusActive => 'Faol';

  @override
  String get statusInactive => 'Nofaol';

  @override
  String get statusVacation => 'Ta\'tilda';

  @override
  String get statusBusinessTrip => 'Xizmat safarida';

  @override
  String get statusSickLeave => 'Bemor varaqasida';

  @override
  String get statusTerminated => 'Ishdan bo\'shatilgan';

  @override
  String get statusPending => 'Kutilmoqda';

  @override
  String get statusDepartmentApproved => 'Bo\'lim tomonidan tasdiqlangan';

  @override
  String get statusApproved => 'Tasdiqlangan';

  @override
  String get statusRejected => 'Rad etilgan';

  @override
  String get statusPresent => 'Keldi';

  @override
  String get statusLate => 'Kechikdi';

  @override
  String get statusEarlyLeave => 'Erta ketdi';

  @override
  String get statusAbsent => 'Kelmadi';

  @override
  String get statusNew => 'Yangi';

  @override
  String get statusInProgress => 'Jarayonda';

  @override
  String get statusWaiting => 'Tasdiqlash kutilmoqda';

  @override
  String get statusCompleted => 'Bajarildi';

  @override
  String get statusCancelled => 'Bekor qilindi';

  @override
  String get statusOverdue => 'Muddati o\'tgan';

  @override
  String get statusLow => 'Past';

  @override
  String get statusNormal => 'O\'rtacha';

  @override
  String get statusHigh => 'Yuqori';

  @override
  String get statusUrgent => 'Shoshilinch';

  @override
  String get statusOpen => 'Ochiq';

  @override
  String get statusResolved => 'Bartaraf etilgan';
}
