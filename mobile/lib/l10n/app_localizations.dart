import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:intl/intl.dart' as intl;

import 'app_localizations_ru.dart';
import 'app_localizations_uz.dart';

// ignore_for_file: type=lint

/// Callers can lookup localized strings with an instance of AppLocalizations
/// returned by `AppLocalizations.of(context)`.
///
/// Applications need to include `AppLocalizations.delegate()` in their app's
/// `localizationDelegates` list, and the locales they support in the app's
/// `supportedLocales` list. For example:
///
/// ```dart
/// import 'l10n/app_localizations.dart';
///
/// return MaterialApp(
///   localizationsDelegates: AppLocalizations.localizationsDelegates,
///   supportedLocales: AppLocalizations.supportedLocales,
///   home: MyApplicationHome(),
/// );
/// ```
///
/// ## Update pubspec.yaml
///
/// Please make sure to update your pubspec.yaml to include the following
/// packages:
///
/// ```yaml
/// dependencies:
///   # Internationalization support.
///   flutter_localizations:
///     sdk: flutter
///   intl: any # Use the pinned version from flutter_localizations
///
///   # Rest of dependencies
/// ```
///
/// ## iOS Applications
///
/// iOS applications define key application metadata, including supported
/// locales, in an Info.plist file that is built into the application bundle.
/// To configure the locales supported by your app, you’ll need to edit this
/// file.
///
/// First, open your project’s ios/Runner.xcworkspace Xcode workspace file.
/// Then, in the Project Navigator, open the Info.plist file under the Runner
/// project’s Runner folder.
///
/// Next, select the Information Property List item, select Add Item from the
/// Editor menu, then select Localizations from the pop-up menu.
///
/// Select and expand the newly-created Localizations item then, for each
/// locale your application supports, add a new item and select the locale
/// you wish to add from the pop-up menu in the Value field. This list should
/// be consistent with the languages listed in the AppLocalizations.supportedLocales
/// property.
abstract class AppLocalizations {
  AppLocalizations(String locale)
    : localeName = intl.Intl.canonicalizedLocale(locale.toString());

  final String localeName;

  static AppLocalizations of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations)!;
  }

  static const LocalizationsDelegate<AppLocalizations> delegate =
      _AppLocalizationsDelegate();

  /// A list of this localizations delegate along with the default localizations
  /// delegates.
  ///
  /// Returns a list of localizations delegates containing this delegate along with
  /// GlobalMaterialLocalizations.delegate, GlobalCupertinoLocalizations.delegate,
  /// and GlobalWidgetsLocalizations.delegate.
  ///
  /// Additional delegates can be added by appending to this list in
  /// MaterialApp. This list does not have to be used at all if a custom list
  /// of delegates is preferred or required.
  static const List<LocalizationsDelegate<dynamic>> localizationsDelegates =
      <LocalizationsDelegate<dynamic>>[
        delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
      ];

  /// A list of this localizations delegate's supported locales.
  static const List<Locale> supportedLocales = <Locale>[
    Locale('ru'),
    Locale('uz'),
  ];

  /// No description provided for @appName.
  ///
  /// In uz, this message translates to:
  /// **'My Urganchtransgaz'**
  String get appName;

  /// No description provided for @appCorporatePortal.
  ///
  /// In uz, this message translates to:
  /// **'Urganchtransgaz Korporativ Portali'**
  String get appCorporatePortal;

  /// No description provided for @commonRetry.
  ///
  /// In uz, this message translates to:
  /// **'Qayta urinish'**
  String get commonRetry;

  /// No description provided for @commonCancel.
  ///
  /// In uz, this message translates to:
  /// **'Bekor qilish'**
  String get commonCancel;

  /// No description provided for @commonSave.
  ///
  /// In uz, this message translates to:
  /// **'Saqlash'**
  String get commonSave;

  /// No description provided for @commonClose.
  ///
  /// In uz, this message translates to:
  /// **'Yopish'**
  String get commonClose;

  /// No description provided for @commonLoading.
  ///
  /// In uz, this message translates to:
  /// **'Yuklanmoqda...'**
  String get commonLoading;

  /// No description provided for @commonNoData.
  ///
  /// In uz, this message translates to:
  /// **'Ma\'lumot topilmadi'**
  String get commonNoData;

  /// No description provided for @commonErrorGeneric.
  ///
  /// In uz, this message translates to:
  /// **'Xatolik yuz berdi. Qayta urinib ko\'ring.'**
  String get commonErrorGeneric;

  /// No description provided for @commonFileOpenError.
  ///
  /// In uz, this message translates to:
  /// **'Faylni ochib bo\'lmadi.'**
  String get commonFileOpenError;

  /// No description provided for @commonLogout.
  ///
  /// In uz, this message translates to:
  /// **'Tizimdan chiqish'**
  String get commonLogout;

  /// No description provided for @commonConfirm.
  ///
  /// In uz, this message translates to:
  /// **'Tasdiqlash'**
  String get commonConfirm;

  /// No description provided for @commonYes.
  ///
  /// In uz, this message translates to:
  /// **'Ha'**
  String get commonYes;

  /// No description provided for @commonNo.
  ///
  /// In uz, this message translates to:
  /// **'Yo\'q'**
  String get commonNo;

  /// No description provided for @commonOk.
  ///
  /// In uz, this message translates to:
  /// **'OK'**
  String get commonOk;

  /// No description provided for @commonSubmit.
  ///
  /// In uz, this message translates to:
  /// **'Yuborish'**
  String get commonSubmit;

  /// No description provided for @commonRequired.
  ///
  /// In uz, this message translates to:
  /// **'Bu maydon to\'ldirilishi shart.'**
  String get commonRequired;

  /// No description provided for @commonSend.
  ///
  /// In uz, this message translates to:
  /// **'Yuborish'**
  String get commonSend;

  /// No description provided for @authLogin.
  ///
  /// In uz, this message translates to:
  /// **'Kirish'**
  String get authLogin;

  /// No description provided for @authLoginField.
  ///
  /// In uz, this message translates to:
  /// **'Login (email, foydalanuvchi nomi yoki tabel raqami)'**
  String get authLoginField;

  /// No description provided for @authPassword.
  ///
  /// In uz, this message translates to:
  /// **'Parol'**
  String get authPassword;

  /// No description provided for @authLoginButton.
  ///
  /// In uz, this message translates to:
  /// **'Kirish'**
  String get authLoginButton;

  /// No description provided for @authForgotPassword.
  ///
  /// In uz, this message translates to:
  /// **'Parolni unutdingizmi?'**
  String get authForgotPassword;

  /// No description provided for @authLoginError.
  ///
  /// In uz, this message translates to:
  /// **'Login yoki parol noto\'g\'ri.'**
  String get authLoginError;

  /// No description provided for @authWelcomeBack.
  ///
  /// In uz, this message translates to:
  /// **'Xush kelibsiz'**
  String get authWelcomeBack;

  /// No description provided for @authServerAddress.
  ///
  /// In uz, this message translates to:
  /// **'Server manzili'**
  String get authServerAddress;

  /// No description provided for @authServerAddressHint.
  ///
  /// In uz, this message translates to:
  /// **'Masalan: http://192.168.1.10:8000/api/v1'**
  String get authServerAddressHint;

  /// No description provided for @authForgotPasswordTitle.
  ///
  /// In uz, this message translates to:
  /// **'Parolni tiklash'**
  String get authForgotPasswordTitle;

  /// No description provided for @authSendResetLink.
  ///
  /// In uz, this message translates to:
  /// **'Havola yuborish'**
  String get authSendResetLink;

  /// No description provided for @authCheckYourEmail.
  ///
  /// In uz, this message translates to:
  /// **'Parolni tiklash havolasi emailingizga yuborildi. Havolani ochish uchun kompyuterdan veb-saytga o\'ting.'**
  String get authCheckYourEmail;

  /// No description provided for @navDashboard.
  ///
  /// In uz, this message translates to:
  /// **'Bosh sahifa'**
  String get navDashboard;

  /// No description provided for @navAttendance.
  ///
  /// In uz, this message translates to:
  /// **'Davomat'**
  String get navAttendance;

  /// No description provided for @navTasks.
  ///
  /// In uz, this message translates to:
  /// **'Topshiriqlar'**
  String get navTasks;

  /// No description provided for @navMore.
  ///
  /// In uz, this message translates to:
  /// **'Yana'**
  String get navMore;

  /// No description provided for @navProfile.
  ///
  /// In uz, this message translates to:
  /// **'Profil'**
  String get navProfile;

  /// No description provided for @navDocuments.
  ///
  /// In uz, this message translates to:
  /// **'Hujjatlar'**
  String get navDocuments;

  /// No description provided for @navKpi.
  ///
  /// In uz, this message translates to:
  /// **'KPI'**
  String get navKpi;

  /// No description provided for @navExams.
  ///
  /// In uz, this message translates to:
  /// **'Imtihonlar'**
  String get navExams;

  /// No description provided for @navAnnouncements.
  ///
  /// In uz, this message translates to:
  /// **'E\'lonlar'**
  String get navAnnouncements;

  /// No description provided for @navNotifications.
  ///
  /// In uz, this message translates to:
  /// **'Bildirishnomalar'**
  String get navNotifications;

  /// No description provided for @dashboardTitle.
  ///
  /// In uz, this message translates to:
  /// **'Bosh sahifa'**
  String get dashboardTitle;

  /// No description provided for @dashboardWelcome.
  ///
  /// In uz, this message translates to:
  /// **'Xush kelibsiz'**
  String get dashboardWelcome;

  /// No description provided for @dashboardTodayAttendance.
  ///
  /// In uz, this message translates to:
  /// **'Bugungi davomat'**
  String get dashboardTodayAttendance;

  /// No description provided for @dashboardNotCheckedInYet.
  ///
  /// In uz, this message translates to:
  /// **'Siz hali kelmadingiz deb belgilangansiz'**
  String get dashboardNotCheckedInYet;

  /// No description provided for @dashboardCheckedInAt.
  ///
  /// In uz, this message translates to:
  /// **'Kelgan vaqt: {time}'**
  String dashboardCheckedInAt(Object time);

  /// No description provided for @dashboardCheckedOutAt.
  ///
  /// In uz, this message translates to:
  /// **'Ketgan vaqt: {time}'**
  String dashboardCheckedOutAt(Object time);

  /// No description provided for @dashboardLatestAnnouncements.
  ///
  /// In uz, this message translates to:
  /// **'So\'nggi e\'lonlar'**
  String get dashboardLatestAnnouncements;

  /// No description provided for @dashboardSeeAll.
  ///
  /// In uz, this message translates to:
  /// **'Barchasini ko\'rish'**
  String get dashboardSeeAll;

  /// No description provided for @dashboardMyTasks.
  ///
  /// In uz, this message translates to:
  /// **'Mening topshiriqlarim'**
  String get dashboardMyTasks;

  /// No description provided for @dashboardNoAnnouncements.
  ///
  /// In uz, this message translates to:
  /// **'Hozircha e\'lonlar yo\'q'**
  String get dashboardNoAnnouncements;

  /// No description provided for @dashboardNoTasks.
  ///
  /// In uz, this message translates to:
  /// **'Faol topshiriqlar yo\'q'**
  String get dashboardNoTasks;

  /// No description provided for @attendanceTitle.
  ///
  /// In uz, this message translates to:
  /// **'Davomat'**
  String get attendanceTitle;

  /// No description provided for @attendanceTabToday.
  ///
  /// In uz, this message translates to:
  /// **'Bugun'**
  String get attendanceTabToday;

  /// No description provided for @attendanceTabHistory.
  ///
  /// In uz, this message translates to:
  /// **'Tarix'**
  String get attendanceTabHistory;

  /// No description provided for @attendanceCheckIn.
  ///
  /// In uz, this message translates to:
  /// **'Keldim'**
  String get attendanceCheckIn;

  /// No description provided for @attendanceCheckOut.
  ///
  /// In uz, this message translates to:
  /// **'Ketdim'**
  String get attendanceCheckOut;

  /// No description provided for @attendanceCheckedIn.
  ///
  /// In uz, this message translates to:
  /// **'Kelganingiz qayd etildi'**
  String get attendanceCheckedIn;

  /// No description provided for @attendanceCheckedOut.
  ///
  /// In uz, this message translates to:
  /// **'Ketganingiz qayd etildi'**
  String get attendanceCheckedOut;

  /// No description provided for @attendanceStatus.
  ///
  /// In uz, this message translates to:
  /// **'Holat'**
  String get attendanceStatus;

  /// No description provided for @attendanceWorked.
  ///
  /// In uz, this message translates to:
  /// **'Ishlangan vaqt'**
  String get attendanceWorked;

  /// No description provided for @attendanceFrom.
  ///
  /// In uz, this message translates to:
  /// **'Dan'**
  String get attendanceFrom;

  /// No description provided for @attendanceTo.
  ///
  /// In uz, this message translates to:
  /// **'Gacha'**
  String get attendanceTo;

  /// No description provided for @attendanceNoRecords.
  ///
  /// In uz, this message translates to:
  /// **'Bu davr uchun yozuvlar yo\'q'**
  String get attendanceNoRecords;

  /// No description provided for @profileTitle.
  ///
  /// In uz, this message translates to:
  /// **'Mening profilim'**
  String get profileTitle;

  /// No description provided for @profileTabInfo.
  ///
  /// In uz, this message translates to:
  /// **'Ma\'lumotlar'**
  String get profileTabInfo;

  /// No description provided for @profileTabRequests.
  ///
  /// In uz, this message translates to:
  /// **'So\'rovlarim'**
  String get profileTabRequests;

  /// No description provided for @profilePersonalInfo.
  ///
  /// In uz, this message translates to:
  /// **'Shaxsiy ma\'lumotlar'**
  String get profilePersonalInfo;

  /// No description provided for @profileContactInfo.
  ///
  /// In uz, this message translates to:
  /// **'Aloqa ma\'lumotlari'**
  String get profileContactInfo;

  /// No description provided for @profileSensitiveNotice.
  ///
  /// In uz, this message translates to:
  /// **'Bu maydonlarni o\'zgartirish HR tasdig\'ini talab qiladi.'**
  String get profileSensitiveNotice;

  /// No description provided for @profilePendingNotice.
  ///
  /// In uz, this message translates to:
  /// **'Ba\'zi o\'zgarishlar hozirda ko\'rib chiqilmoqda.'**
  String get profilePendingNotice;

  /// No description provided for @profilePhoto.
  ///
  /// In uz, this message translates to:
  /// **'Rasm'**
  String get profilePhoto;

  /// No description provided for @profileUploadPhoto.
  ///
  /// In uz, this message translates to:
  /// **'Rasm yuklash'**
  String get profileUploadPhoto;

  /// No description provided for @profileMyChangeRequests.
  ///
  /// In uz, this message translates to:
  /// **'Mening so\'rovlarim'**
  String get profileMyChangeRequests;

  /// No description provided for @profileNoPendingRequests.
  ///
  /// In uz, this message translates to:
  /// **'Kutilayotgan so\'rovlar yo\'q'**
  String get profileNoPendingRequests;

  /// No description provided for @profileFirstName.
  ///
  /// In uz, this message translates to:
  /// **'Ismi'**
  String get profileFirstName;

  /// No description provided for @profileLastName.
  ///
  /// In uz, this message translates to:
  /// **'Familiyasi'**
  String get profileLastName;

  /// No description provided for @profileMiddleName.
  ///
  /// In uz, this message translates to:
  /// **'Sharifi'**
  String get profileMiddleName;

  /// No description provided for @profilePhone.
  ///
  /// In uz, this message translates to:
  /// **'Telefon'**
  String get profilePhone;

  /// No description provided for @profileEmail.
  ///
  /// In uz, this message translates to:
  /// **'Email'**
  String get profileEmail;

  /// No description provided for @profileAddress.
  ///
  /// In uz, this message translates to:
  /// **'Manzil'**
  String get profileAddress;

  /// No description provided for @profileBirthDate.
  ///
  /// In uz, this message translates to:
  /// **'Tug\'ilgan sana'**
  String get profileBirthDate;

  /// No description provided for @profileBirthPlace.
  ///
  /// In uz, this message translates to:
  /// **'Tug\'ilgan joyi'**
  String get profileBirthPlace;

  /// No description provided for @profileGender.
  ///
  /// In uz, this message translates to:
  /// **'Jinsi'**
  String get profileGender;

  /// No description provided for @profileGenderMale.
  ///
  /// In uz, this message translates to:
  /// **'Erkak'**
  String get profileGenderMale;

  /// No description provided for @profileGenderFemale.
  ///
  /// In uz, this message translates to:
  /// **'Ayol'**
  String get profileGenderFemale;

  /// No description provided for @profileOrganization.
  ///
  /// In uz, this message translates to:
  /// **'Tashkilot'**
  String get profileOrganization;

  /// No description provided for @profileDepartment.
  ///
  /// In uz, this message translates to:
  /// **'Bo\'lim'**
  String get profileDepartment;

  /// No description provided for @profilePosition.
  ///
  /// In uz, this message translates to:
  /// **'Lavozim'**
  String get profilePosition;

  /// No description provided for @profileEmployeeNumber.
  ///
  /// In uz, this message translates to:
  /// **'Tabel raqami'**
  String get profileEmployeeNumber;

  /// No description provided for @profileHireDate.
  ///
  /// In uz, this message translates to:
  /// **'Ishga qabul sanasi'**
  String get profileHireDate;

  /// No description provided for @profileSaveSuccess.
  ///
  /// In uz, this message translates to:
  /// **'Profil muvaffaqiyatli yangilandi.'**
  String get profileSaveSuccess;

  /// No description provided for @profileSavePending.
  ///
  /// In uz, this message translates to:
  /// **'Profil yangilandi. Ba\'zi o\'zgarishlar tasdiqlashni kutmoqda.'**
  String get profileSavePending;

  /// No description provided for @documentsTitle.
  ///
  /// In uz, this message translates to:
  /// **'Hujjatlar'**
  String get documentsTitle;

  /// No description provided for @documentsUpload.
  ///
  /// In uz, this message translates to:
  /// **'Hujjat yuklash'**
  String get documentsUpload;

  /// No description provided for @documentsType.
  ///
  /// In uz, this message translates to:
  /// **'Hujjat turi'**
  String get documentsType;

  /// No description provided for @documentsDocTitle.
  ///
  /// In uz, this message translates to:
  /// **'Nomi'**
  String get documentsDocTitle;

  /// No description provided for @documentsNumber.
  ///
  /// In uz, this message translates to:
  /// **'Hujjat raqami'**
  String get documentsNumber;

  /// No description provided for @documentsIssueDate.
  ///
  /// In uz, this message translates to:
  /// **'Berilgan sana'**
  String get documentsIssueDate;

  /// No description provided for @documentsExpiryDate.
  ///
  /// In uz, this message translates to:
  /// **'Amal qilish muddati'**
  String get documentsExpiryDate;

  /// No description provided for @documentsFile.
  ///
  /// In uz, this message translates to:
  /// **'Fayl'**
  String get documentsFile;

  /// No description provided for @documentsChooseFile.
  ///
  /// In uz, this message translates to:
  /// **'Faylni tanlash'**
  String get documentsChooseFile;

  /// No description provided for @documentsDownload.
  ///
  /// In uz, this message translates to:
  /// **'Yuklab olish'**
  String get documentsDownload;

  /// No description provided for @documentsNoExpiry.
  ///
  /// In uz, this message translates to:
  /// **'Muddatsiz'**
  String get documentsNoExpiry;

  /// No description provided for @documentsNone.
  ///
  /// In uz, this message translates to:
  /// **'Hujjatlar yo\'q'**
  String get documentsNone;

  /// No description provided for @tasksTitle.
  ///
  /// In uz, this message translates to:
  /// **'Topshiriqlar'**
  String get tasksTitle;

  /// No description provided for @tasksFieldTitle.
  ///
  /// In uz, this message translates to:
  /// **'Sarlavha'**
  String get tasksFieldTitle;

  /// No description provided for @tasksDescription.
  ///
  /// In uz, this message translates to:
  /// **'Tavsif'**
  String get tasksDescription;

  /// No description provided for @tasksAssignees.
  ///
  /// In uz, this message translates to:
  /// **'Bajaruvchilar'**
  String get tasksAssignees;

  /// No description provided for @tasksPriority.
  ///
  /// In uz, this message translates to:
  /// **'Muhimlik'**
  String get tasksPriority;

  /// No description provided for @tasksDueDate.
  ///
  /// In uz, this message translates to:
  /// **'Muddati'**
  String get tasksDueDate;

  /// No description provided for @tasksProgress.
  ///
  /// In uz, this message translates to:
  /// **'Bajarilishi'**
  String get tasksProgress;

  /// No description provided for @tasksUpdateProgress.
  ///
  /// In uz, this message translates to:
  /// **'Yangilash'**
  String get tasksUpdateProgress;

  /// No description provided for @tasksResult.
  ///
  /// In uz, this message translates to:
  /// **'Natija'**
  String get tasksResult;

  /// No description provided for @tasksMarkComplete.
  ///
  /// In uz, this message translates to:
  /// **'Bajarildi deb belgilash'**
  String get tasksMarkComplete;

  /// No description provided for @tasksComments.
  ///
  /// In uz, this message translates to:
  /// **'Izohlar'**
  String get tasksComments;

  /// No description provided for @tasksAddComment.
  ///
  /// In uz, this message translates to:
  /// **'Izoh yozing'**
  String get tasksAddComment;

  /// No description provided for @tasksAttachments.
  ///
  /// In uz, this message translates to:
  /// **'Fayllar'**
  String get tasksAttachments;

  /// No description provided for @tasksCreator.
  ///
  /// In uz, this message translates to:
  /// **'Yaratuvchi'**
  String get tasksCreator;

  /// No description provided for @tasksNone.
  ///
  /// In uz, this message translates to:
  /// **'Faol topshiriqlar yo\'q'**
  String get tasksNone;

  /// No description provided for @kpiTitle.
  ///
  /// In uz, this message translates to:
  /// **'KPI'**
  String get kpiTitle;

  /// No description provided for @kpiMyResults.
  ///
  /// In uz, this message translates to:
  /// **'Mening natijalarim'**
  String get kpiMyResults;

  /// No description provided for @kpiPeriod.
  ///
  /// In uz, this message translates to:
  /// **'Davr'**
  String get kpiPeriod;

  /// No description provided for @kpiIndicator.
  ///
  /// In uz, this message translates to:
  /// **'Ko\'rsatkich'**
  String get kpiIndicator;

  /// No description provided for @kpiTarget.
  ///
  /// In uz, this message translates to:
  /// **'Reja'**
  String get kpiTarget;

  /// No description provided for @kpiActual.
  ///
  /// In uz, this message translates to:
  /// **'Bajarilgan'**
  String get kpiActual;

  /// No description provided for @kpiScore.
  ///
  /// In uz, this message translates to:
  /// **'Ball'**
  String get kpiScore;

  /// No description provided for @kpiWeight.
  ///
  /// In uz, this message translates to:
  /// **'Og\'irlik'**
  String get kpiWeight;

  /// No description provided for @kpiNone.
  ///
  /// In uz, this message translates to:
  /// **'Hali natijalar yo\'q'**
  String get kpiNone;

  /// No description provided for @examsTitle.
  ///
  /// In uz, this message translates to:
  /// **'Imtihonlar'**
  String get examsTitle;

  /// No description provided for @examsTabAvailable.
  ///
  /// In uz, this message translates to:
  /// **'Mavjud'**
  String get examsTabAvailable;

  /// No description provided for @examsTabResults.
  ///
  /// In uz, this message translates to:
  /// **'Natijalarim'**
  String get examsTabResults;

  /// No description provided for @examsDuration.
  ///
  /// In uz, this message translates to:
  /// **'Davomiyligi'**
  String get examsDuration;

  /// No description provided for @examsMinutes.
  ///
  /// In uz, this message translates to:
  /// **'daqiqa'**
  String get examsMinutes;

  /// No description provided for @examsPassingScore.
  ///
  /// In uz, this message translates to:
  /// **'O\'tish balli'**
  String get examsPassingScore;

  /// No description provided for @examsAttemptsAllowed.
  ///
  /// In uz, this message translates to:
  /// **'Ruxsat etilgan urinishlar'**
  String get examsAttemptsAllowed;

  /// No description provided for @examsStart.
  ///
  /// In uz, this message translates to:
  /// **'Boshlash'**
  String get examsStart;

  /// No description provided for @examsSubmit.
  ///
  /// In uz, this message translates to:
  /// **'Yakunlash'**
  String get examsSubmit;

  /// No description provided for @examsScore.
  ///
  /// In uz, this message translates to:
  /// **'Ball'**
  String get examsScore;

  /// No description provided for @examsBestScore.
  ///
  /// In uz, this message translates to:
  /// **'Eng yaxshi natija'**
  String get examsBestScore;

  /// No description provided for @examsAttemptsUsed.
  ///
  /// In uz, this message translates to:
  /// **'Urinishlar'**
  String get examsAttemptsUsed;

  /// No description provided for @examsNotTaken.
  ///
  /// In uz, this message translates to:
  /// **'Topshirilmagan'**
  String get examsNotTaken;

  /// No description provided for @examsPassed.
  ///
  /// In uz, this message translates to:
  /// **'O\'tdi'**
  String get examsPassed;

  /// No description provided for @examsFailed.
  ///
  /// In uz, this message translates to:
  /// **'O\'ta olmadi'**
  String get examsFailed;

  /// No description provided for @examsTrue.
  ///
  /// In uz, this message translates to:
  /// **'To\'g\'ri'**
  String get examsTrue;

  /// No description provided for @examsFalse.
  ///
  /// In uz, this message translates to:
  /// **'Noto\'g\'ri'**
  String get examsFalse;

  /// No description provided for @examsConfirmSubmitTitle.
  ///
  /// In uz, this message translates to:
  /// **'Imtihonni yakunlash'**
  String get examsConfirmSubmitTitle;

  /// No description provided for @examsConfirmSubmitText.
  ///
  /// In uz, this message translates to:
  /// **'Javoblarni yuborgandan so\'ng ularni o\'zgartirib bo\'lmaydi. Davom etasizmi?'**
  String get examsConfirmSubmitText;

  /// No description provided for @examsConfirmLeaveText.
  ///
  /// In uz, this message translates to:
  /// **'Imtihondan chiqmoqchimisiz? Javoblaringiz saqlanmaydi.'**
  String get examsConfirmLeaveText;

  /// No description provided for @examsNone.
  ///
  /// In uz, this message translates to:
  /// **'Mavjud imtihonlar yo\'q'**
  String get examsNone;

  /// No description provided for @issuesTitle.
  ///
  /// In uz, this message translates to:
  /// **'Muammolar'**
  String get issuesTitle;

  /// No description provided for @issuesReportIssue.
  ///
  /// In uz, this message translates to:
  /// **'Yangi muammo'**
  String get issuesReportIssue;

  /// No description provided for @issuesIssueTitle.
  ///
  /// In uz, this message translates to:
  /// **'Sarlavha'**
  String get issuesIssueTitle;

  /// No description provided for @issuesDescription.
  ///
  /// In uz, this message translates to:
  /// **'Tavsif'**
  String get issuesDescription;

  /// No description provided for @issuesObjectName.
  ///
  /// In uz, this message translates to:
  /// **'Obyekt/hudud nomi'**
  String get issuesObjectName;

  /// No description provided for @issuesUseMyLocation.
  ///
  /// In uz, this message translates to:
  /// **'Joriy joylashuvimdan foydalanish'**
  String get issuesUseMyLocation;

  /// No description provided for @issuesMapPickHint.
  ///
  /// In uz, this message translates to:
  /// **'Xaritada joyni bosib belgilang'**
  String get issuesMapPickHint;

  /// No description provided for @issuesLocationError.
  ///
  /// In uz, this message translates to:
  /// **'Joylashuvni aniqlab bo\'lmadi. GPS yoqilganini tekshiring.'**
  String get issuesLocationError;

  /// No description provided for @issuesReporter.
  ///
  /// In uz, this message translates to:
  /// **'Xabar bergan'**
  String get issuesReporter;

  /// No description provided for @issuesDepartment.
  ///
  /// In uz, this message translates to:
  /// **'Bo\'lim'**
  String get issuesDepartment;

  /// No description provided for @issuesFilterOpen.
  ///
  /// In uz, this message translates to:
  /// **'Ochiq'**
  String get issuesFilterOpen;

  /// No description provided for @issuesFilterAll.
  ///
  /// In uz, this message translates to:
  /// **'Barchasi'**
  String get issuesFilterAll;

  /// No description provided for @issuesTimeline.
  ///
  /// In uz, this message translates to:
  /// **'Tarix'**
  String get issuesTimeline;

  /// No description provided for @issuesComments.
  ///
  /// In uz, this message translates to:
  /// **'Izohlar'**
  String get issuesComments;

  /// No description provided for @issuesAddComment.
  ///
  /// In uz, this message translates to:
  /// **'Izoh yozing'**
  String get issuesAddComment;

  /// No description provided for @issuesResolve.
  ///
  /// In uz, this message translates to:
  /// **'Hal qilish'**
  String get issuesResolve;

  /// No description provided for @issuesResolutionNote.
  ///
  /// In uz, this message translates to:
  /// **'Javob (bartaraf etish uchun)'**
  String get issuesResolutionNote;

  /// No description provided for @issuesResolvedBy.
  ///
  /// In uz, this message translates to:
  /// **'Bartaraf etdi'**
  String get issuesResolvedBy;

  /// No description provided for @issuesNone.
  ///
  /// In uz, this message translates to:
  /// **'Muammolar topilmadi'**
  String get issuesNone;

  /// No description provided for @issuesCategory.
  ///
  /// In uz, this message translates to:
  /// **'Kategoriya'**
  String get issuesCategory;

  /// No description provided for @issuesOrganization.
  ///
  /// In uz, this message translates to:
  /// **'Quyi tashkilot'**
  String get issuesOrganization;

  /// No description provided for @issuesResponsible.
  ///
  /// In uz, this message translates to:
  /// **'Mas\'ul xodim'**
  String get issuesResponsible;

  /// No description provided for @issuesResponsibleSelf.
  ///
  /// In uz, this message translates to:
  /// **'Siz o\'zingiz mas\'ul bo\'lasiz'**
  String get issuesResponsibleSelf;

  /// No description provided for @issuesPickOrganizationFirst.
  ///
  /// In uz, this message translates to:
  /// **'Avval quyi tashkilotni tanlang'**
  String get issuesPickOrganizationFirst;

  /// No description provided for @issuesNoCandidates.
  ///
  /// In uz, this message translates to:
  /// **'Bu tashkilotda mas\'ul bo\'la oladigan xodim topilmadi'**
  String get issuesNoCandidates;

  /// No description provided for @announcementsTitle.
  ///
  /// In uz, this message translates to:
  /// **'E\'lonlar'**
  String get announcementsTitle;

  /// No description provided for @announcementsNone.
  ///
  /// In uz, this message translates to:
  /// **'E\'lonlar yo\'q'**
  String get announcementsNone;

  /// No description provided for @announcementsAuthor.
  ///
  /// In uz, this message translates to:
  /// **'Muallif'**
  String get announcementsAuthor;

  /// No description provided for @notificationsTitle.
  ///
  /// In uz, this message translates to:
  /// **'Bildirishnomalar'**
  String get notificationsTitle;

  /// No description provided for @notificationsMarkAllRead.
  ///
  /// In uz, this message translates to:
  /// **'Barchasini o\'qilgan deb belgilash'**
  String get notificationsMarkAllRead;

  /// No description provided for @notificationsNone.
  ///
  /// In uz, this message translates to:
  /// **'Bildirishnomalar yo\'q'**
  String get notificationsNone;

  /// No description provided for @statusActive.
  ///
  /// In uz, this message translates to:
  /// **'Faol'**
  String get statusActive;

  /// No description provided for @statusInactive.
  ///
  /// In uz, this message translates to:
  /// **'Nofaol'**
  String get statusInactive;

  /// No description provided for @statusVacation.
  ///
  /// In uz, this message translates to:
  /// **'Ta\'tilda'**
  String get statusVacation;

  /// No description provided for @statusBusinessTrip.
  ///
  /// In uz, this message translates to:
  /// **'Xizmat safarida'**
  String get statusBusinessTrip;

  /// No description provided for @statusSickLeave.
  ///
  /// In uz, this message translates to:
  /// **'Bemor varaqasida'**
  String get statusSickLeave;

  /// No description provided for @statusTerminated.
  ///
  /// In uz, this message translates to:
  /// **'Ishdan bo\'shatilgan'**
  String get statusTerminated;

  /// No description provided for @statusPending.
  ///
  /// In uz, this message translates to:
  /// **'Kutilmoqda'**
  String get statusPending;

  /// No description provided for @statusDepartmentApproved.
  ///
  /// In uz, this message translates to:
  /// **'Bo\'lim tomonidan tasdiqlangan'**
  String get statusDepartmentApproved;

  /// No description provided for @statusApproved.
  ///
  /// In uz, this message translates to:
  /// **'Tasdiqlangan'**
  String get statusApproved;

  /// No description provided for @statusRejected.
  ///
  /// In uz, this message translates to:
  /// **'Rad etilgan'**
  String get statusRejected;

  /// No description provided for @statusPresent.
  ///
  /// In uz, this message translates to:
  /// **'Keldi'**
  String get statusPresent;

  /// No description provided for @statusLate.
  ///
  /// In uz, this message translates to:
  /// **'Kechikdi'**
  String get statusLate;

  /// No description provided for @statusEarlyLeave.
  ///
  /// In uz, this message translates to:
  /// **'Erta ketdi'**
  String get statusEarlyLeave;

  /// No description provided for @statusAbsent.
  ///
  /// In uz, this message translates to:
  /// **'Kelmadi'**
  String get statusAbsent;

  /// No description provided for @statusNew.
  ///
  /// In uz, this message translates to:
  /// **'Yangi'**
  String get statusNew;

  /// No description provided for @statusInProgress.
  ///
  /// In uz, this message translates to:
  /// **'Jarayonda'**
  String get statusInProgress;

  /// No description provided for @statusWaiting.
  ///
  /// In uz, this message translates to:
  /// **'Tasdiqlash kutilmoqda'**
  String get statusWaiting;

  /// No description provided for @statusCompleted.
  ///
  /// In uz, this message translates to:
  /// **'Bajarildi'**
  String get statusCompleted;

  /// No description provided for @statusCancelled.
  ///
  /// In uz, this message translates to:
  /// **'Bekor qilindi'**
  String get statusCancelled;

  /// No description provided for @statusOverdue.
  ///
  /// In uz, this message translates to:
  /// **'Muddati o\'tgan'**
  String get statusOverdue;

  /// No description provided for @statusLow.
  ///
  /// In uz, this message translates to:
  /// **'Past'**
  String get statusLow;

  /// No description provided for @statusNormal.
  ///
  /// In uz, this message translates to:
  /// **'O\'rtacha'**
  String get statusNormal;

  /// No description provided for @statusHigh.
  ///
  /// In uz, this message translates to:
  /// **'Yuqori'**
  String get statusHigh;

  /// No description provided for @statusUrgent.
  ///
  /// In uz, this message translates to:
  /// **'Shoshilinch'**
  String get statusUrgent;

  /// No description provided for @statusOpen.
  ///
  /// In uz, this message translates to:
  /// **'Ochiq'**
  String get statusOpen;

  /// No description provided for @statusResolved.
  ///
  /// In uz, this message translates to:
  /// **'Bartaraf etilgan'**
  String get statusResolved;
}

class _AppLocalizationsDelegate
    extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  Future<AppLocalizations> load(Locale locale) {
    return SynchronousFuture<AppLocalizations>(lookupAppLocalizations(locale));
  }

  @override
  bool isSupported(Locale locale) =>
      <String>['ru', 'uz'].contains(locale.languageCode);

  @override
  bool shouldReload(_AppLocalizationsDelegate old) => false;
}

AppLocalizations lookupAppLocalizations(Locale locale) {
  // Lookup logic when only language code is specified.
  switch (locale.languageCode) {
    case 'ru':
      return AppLocalizationsRu();
    case 'uz':
      return AppLocalizationsUz();
  }

  throw FlutterError(
    'AppLocalizations.delegate failed to load unsupported locale "$locale". This is likely '
    'an issue with the localizations generation tool. Please file an issue '
    'on GitHub with a reproducible sample app and the gen-l10n configuration '
    'that was used.',
  );
}
