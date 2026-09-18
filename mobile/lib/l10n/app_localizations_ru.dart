// ignore: unused_import
import 'package:intl/intl.dart' as intl;

import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for Russian (`ru`).
class AppLocalizationsRu extends AppLocalizations {
  AppLocalizationsRu([String locale = 'ru']) : super(locale);

  @override
  String get appName => 'My Urganchtransgaz';

  @override
  String get commonRetry => 'Повторить';

  @override
  String get commonCancel => 'Отмена';

  @override
  String get commonSave => 'Сохранить';

  @override
  String get commonClose => 'Закрыть';

  @override
  String get commonLoading => 'Загрузка...';

  @override
  String get commonNoData => 'Данные не найдены';

  @override
  String get commonErrorGeneric => 'Произошла ошибка. Попробуйте снова.';

  @override
  String get commonLogout => 'Выйти';

  @override
  String get commonConfirm => 'Подтвердить';

  @override
  String get commonYes => 'Да';

  @override
  String get commonNo => 'Нет';

  @override
  String get commonOk => 'ОК';

  @override
  String get commonSubmit => 'Отправить';

  @override
  String get commonRequired => 'Это поле обязательно.';

  @override
  String get commonSend => 'Отправить';

  @override
  String get authLogin => 'Вход';

  @override
  String get authLoginField =>
      'Логин (email, имя пользователя или табельный номер)';

  @override
  String get authPassword => 'Пароль';

  @override
  String get authLoginButton => 'Войти';

  @override
  String get authForgotPassword => 'Забыли пароль?';

  @override
  String get authLoginError => 'Неверный логин или пароль.';

  @override
  String get authWelcomeBack => 'С возвращением';

  @override
  String get authServerAddress => 'Адрес сервера';

  @override
  String get authServerAddressHint =>
      'Например: http://192.168.1.10:8000/api/v1';

  @override
  String get authForgotPasswordTitle => 'Восстановление пароля';

  @override
  String get authSendResetLink => 'Отправить ссылку';

  @override
  String get authCheckYourEmail =>
      'Ссылка для сброса пароля отправлена на ваш email. Откройте её на компьютере в браузере.';

  @override
  String get navDashboard => 'Главная';

  @override
  String get navAttendance => 'Посещаемость';

  @override
  String get navTasks => 'Задачи';

  @override
  String get navMore => 'Ещё';

  @override
  String get navProfile => 'Профиль';

  @override
  String get navDocuments => 'Документы';

  @override
  String get navKpi => 'KPI';

  @override
  String get navExams => 'Экзамены';

  @override
  String get navAnnouncements => 'Объявления';

  @override
  String get navNotifications => 'Уведомления';

  @override
  String get dashboardTitle => 'Главная';

  @override
  String get dashboardWelcome => 'Добро пожаловать';

  @override
  String get dashboardTodayAttendance => 'Посещаемость сегодня';

  @override
  String get dashboardNotCheckedInYet => 'Вы ещё не отметили приход';

  @override
  String dashboardCheckedInAt(Object time) {
    return 'Пришёл в: $time';
  }

  @override
  String dashboardCheckedOutAt(Object time) {
    return 'Ушёл в: $time';
  }

  @override
  String get dashboardLatestAnnouncements => 'Последние объявления';

  @override
  String get dashboardSeeAll => 'Смотреть все';

  @override
  String get dashboardMyTasks => 'Мои задачи';

  @override
  String get dashboardNoAnnouncements => 'Пока нет объявлений';

  @override
  String get dashboardNoTasks => 'Нет активных задач';

  @override
  String get attendanceTitle => 'Посещаемость';

  @override
  String get attendanceTabToday => 'Сегодня';

  @override
  String get attendanceTabHistory => 'История';

  @override
  String get attendanceCheckIn => 'Пришёл';

  @override
  String get attendanceCheckOut => 'Ушёл';

  @override
  String get attendanceCheckedIn => 'Приход отмечен';

  @override
  String get attendanceCheckedOut => 'Уход отмечен';

  @override
  String get attendanceStatus => 'Статус';

  @override
  String get attendanceWorked => 'Отработано';

  @override
  String get attendanceFrom => 'С';

  @override
  String get attendanceTo => 'По';

  @override
  String get attendanceNoRecords => 'Нет записей за этот период';

  @override
  String get profileTitle => 'Мой профиль';

  @override
  String get profileTabInfo => 'Информация';

  @override
  String get profileTabRequests => 'Мои запросы';

  @override
  String get profilePersonalInfo => 'Личная информация';

  @override
  String get profileContactInfo => 'Контактная информация';

  @override
  String get profileSensitiveNotice =>
      'Изменение этих полей требует утверждения HR.';

  @override
  String get profilePendingNotice =>
      'Некоторые изменения сейчас находятся на рассмотрении.';

  @override
  String get profilePhoto => 'Фото';

  @override
  String get profileUploadPhoto => 'Загрузить фото';

  @override
  String get profileMyChangeRequests => 'Мои запросы';

  @override
  String get profileNoPendingRequests => 'Нет ожидающих запросов';

  @override
  String get profileFirstName => 'Имя';

  @override
  String get profileLastName => 'Фамилия';

  @override
  String get profileMiddleName => 'Отчество';

  @override
  String get profilePhone => 'Телефон';

  @override
  String get profileEmail => 'Email';

  @override
  String get profileAddress => 'Адрес';

  @override
  String get profileBirthDate => 'Дата рождения';

  @override
  String get profileBirthPlace => 'Место рождения';

  @override
  String get profileGender => 'Пол';

  @override
  String get profileGenderMale => 'Мужской';

  @override
  String get profileGenderFemale => 'Женский';

  @override
  String get profileOrganization => 'Организация';

  @override
  String get profileDepartment => 'Отдел';

  @override
  String get profilePosition => 'Должность';

  @override
  String get profileEmployeeNumber => 'Табельный номер';

  @override
  String get profileHireDate => 'Дата приёма';

  @override
  String get profileSaveSuccess => 'Профиль успешно обновлён.';

  @override
  String get profileSavePending =>
      'Профиль обновлён. Некоторые изменения ожидают утверждения.';

  @override
  String get documentsTitle => 'Документы';

  @override
  String get documentsUpload => 'Загрузить документ';

  @override
  String get documentsType => 'Тип документа';

  @override
  String get documentsDocTitle => 'Название';

  @override
  String get documentsNumber => 'Номер документа';

  @override
  String get documentsIssueDate => 'Дата выдачи';

  @override
  String get documentsExpiryDate => 'Срок действия';

  @override
  String get documentsFile => 'Файл';

  @override
  String get documentsChooseFile => 'Выбрать файл';

  @override
  String get documentsDownload => 'Скачать';

  @override
  String get documentsNoExpiry => 'Без срока';

  @override
  String get documentsNone => 'Нет документов';

  @override
  String get tasksTitle => 'Задачи';

  @override
  String get tasksFieldTitle => 'Название';

  @override
  String get tasksDescription => 'Описание';

  @override
  String get tasksAssignees => 'Исполнители';

  @override
  String get tasksPriority => 'Приоритет';

  @override
  String get tasksDueDate => 'Срок';

  @override
  String get tasksProgress => 'Выполнение';

  @override
  String get tasksUpdateProgress => 'Обновить';

  @override
  String get tasksResult => 'Результат';

  @override
  String get tasksMarkComplete => 'Отметить выполненной';

  @override
  String get tasksComments => 'Комментарии';

  @override
  String get tasksAddComment => 'Написать комментарий';

  @override
  String get tasksAttachments => 'Файлы';

  @override
  String get tasksCreator => 'Создатель';

  @override
  String get tasksNone => 'Нет активных задач';

  @override
  String get kpiTitle => 'KPI';

  @override
  String get kpiMyResults => 'Мои результаты';

  @override
  String get kpiPeriod => 'Период';

  @override
  String get kpiIndicator => 'Показатель';

  @override
  String get kpiTarget => 'План';

  @override
  String get kpiActual => 'Факт';

  @override
  String get kpiScore => 'Балл';

  @override
  String get kpiWeight => 'Вес';

  @override
  String get kpiNone => 'Пока нет результатов';

  @override
  String get examsTitle => 'Экзамены по охране труда';

  @override
  String get examsTabAvailable => 'Доступные';

  @override
  String get examsTabResults => 'Мои результаты';

  @override
  String get examsDuration => 'Длительность';

  @override
  String get examsMinutes => 'мин.';

  @override
  String get examsPassingScore => 'Проходной балл';

  @override
  String get examsAttemptsAllowed => 'Допустимые попытки';

  @override
  String get examsStart => 'Начать';

  @override
  String get examsSubmit => 'Завершить';

  @override
  String get examsScore => 'Баллы';

  @override
  String get examsBestScore => 'Лучший результат';

  @override
  String get examsAttemptsUsed => 'Попытки';

  @override
  String get examsNotTaken => 'Не пройден';

  @override
  String get examsPassed => 'Сдал';

  @override
  String get examsFailed => 'Не сдал';

  @override
  String get examsTrue => 'Верно';

  @override
  String get examsFalse => 'Неверно';

  @override
  String get examsConfirmSubmitTitle => 'Завершить экзамен';

  @override
  String get examsConfirmSubmitText =>
      'После отправки ответы изменить нельзя. Продолжить?';

  @override
  String get examsConfirmLeaveText =>
      'Выйти из экзамена? Ваши ответы не будут сохранены.';

  @override
  String get examsNone => 'Нет доступных экзаменов';

  @override
  String get issuesTitle => 'Проблемы';

  @override
  String get issuesReportIssue => 'Новая проблема';

  @override
  String get issuesIssueTitle => 'Заголовок';

  @override
  String get issuesDescription => 'Описание';

  @override
  String get issuesObjectName => 'Название объекта/участка';

  @override
  String get issuesUseMyLocation => 'Использовать моё местоположение';

  @override
  String get issuesMapPickHint => 'Отметьте место на карте';

  @override
  String get issuesReporter => 'Кто сообщил';

  @override
  String get issuesDepartment => 'Отдел';

  @override
  String get issuesFilterOpen => 'Открытые';

  @override
  String get issuesFilterAll => 'Все';

  @override
  String get issuesTimeline => 'История';

  @override
  String get issuesComments => 'Комментарии';

  @override
  String get issuesAddComment => 'Написать комментарий';

  @override
  String get issuesResolve => 'Устранить';

  @override
  String get issuesResolutionNote => 'Ответ (для устранения)';

  @override
  String get issuesResolvedBy => 'Устранил';

  @override
  String get issuesNone => 'Проблемы не найдены';

  @override
  String get announcementsTitle => 'Объявления';

  @override
  String get announcementsNone => 'Нет объявлений';

  @override
  String get announcementsAuthor => 'Автор';

  @override
  String get notificationsTitle => 'Уведомления';

  @override
  String get notificationsMarkAllRead => 'Отметить всё как прочитанное';

  @override
  String get notificationsNone => 'Нет уведомлений';

  @override
  String get statusActive => 'Активен';

  @override
  String get statusInactive => 'Неактивен';

  @override
  String get statusVacation => 'В отпуске';

  @override
  String get statusBusinessTrip => 'В командировке';

  @override
  String get statusSickLeave => 'На больничном';

  @override
  String get statusTerminated => 'Уволен';

  @override
  String get statusPending => 'На рассмотрении';

  @override
  String get statusDepartmentApproved => 'Утверждено отделом';

  @override
  String get statusApproved => 'Утверждено';

  @override
  String get statusRejected => 'Отклонено';

  @override
  String get statusPresent => 'Пришёл';

  @override
  String get statusLate => 'Опоздал';

  @override
  String get statusEarlyLeave => 'Ушёл раньше';

  @override
  String get statusAbsent => 'Отсутствовал';

  @override
  String get statusNew => 'Новая';

  @override
  String get statusInProgress => 'В процессе';

  @override
  String get statusWaiting => 'Ожидает подтверждения';

  @override
  String get statusCompleted => 'Выполнена';

  @override
  String get statusCancelled => 'Отменена';

  @override
  String get statusOverdue => 'Просрочена';

  @override
  String get statusLow => 'Низкий';

  @override
  String get statusNormal => 'Обычный';

  @override
  String get statusHigh => 'Высокий';

  @override
  String get statusUrgent => 'Срочный';

  @override
  String get statusOpen => 'Открыта';

  @override
  String get statusResolved => 'Устранена';
}
