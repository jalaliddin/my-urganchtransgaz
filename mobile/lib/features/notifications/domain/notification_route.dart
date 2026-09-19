/// Where tapping a notification should go, from the ids the server put in
/// its payload. Anything unrecognised falls back to the notification list
/// rather than to a screen that can't show it.
String notificationRoute(Map<String, dynamic> data) {
  int? id(String key) => data[key] is int ? data[key] as int : int.tryParse('${data[key] ?? ''}');

  final issueId = id('issue_id');
  if (issueId != null) return '/issues/$issueId';

  final taskId = id('task_id');
  if (taskId != null) return '/tasks/$taskId';

  final announcementId = id('announcement_id');
  if (announcementId != null) return '/announcements/$announcementId';

  if (id('exam_id') != null) return '/exams';
  if (id('document_id') != null) return '/documents';

  return '/notifications';
}
