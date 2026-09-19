import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:urtg_mobile/features/notifications/background/notification_sync.dart';
import 'package:urtg_mobile/features/notifications/domain/notification_item.dart';
import 'package:urtg_mobile/features/notifications/domain/notification_route.dart';

NotificationItem _item(String id, {Map<String, dynamic> data = const {}}) =>
    NotificationItem(id: id, type: 'TaskAssigned', title: 'T$id', message: 'M$id', data: data, readAt: null, createdAt: '');

void main() {
  late SharedPreferences prefs;
  late List<NotificationItem> unread;
  late List<List<NotificationItem>> announced;
  late bool presentFails;

  NotificationSync makeSync() => NotificationSync(
        fetchUnread: () async => unread,
        present: (fresh) async {
          if (presentFails) throw Exception('cannot show');
          announced.add(fresh);
        },
        preferences: prefs,
      );

  setUp(() async {
    SharedPreferences.setMockInitialValues({});
    prefs = await SharedPreferences.getInstance();
    unread = [];
    announced = [];
    presentFails = false;
  });

  test('the first run only records a baseline and announces nothing', () async {
    unread = [_item('a'), _item('b')];

    expect(await makeSync().run(), 0);
    expect(announced, isEmpty);
    expect(prefs.getStringList(NotificationSync.announcedIdsKey), ['a', 'b']);
  });

  test('announces only notifications that arrived after the baseline', () async {
    unread = [_item('a')];
    await makeSync().run();

    unread = [_item('b'), _item('a')];

    expect(await makeSync().run(), 1);
    expect(announced.single.map((item) => item.id), ['b']);
  });

  test('never announces the same notification twice', () async {
    unread = [_item('a')];
    await makeSync().run();
    unread = [_item('b'), _item('a')];
    await makeSync().run();

    expect(await makeSync().run(), 0);
    expect(announced, hasLength(1));
  });

  test('a notification read in the app is dropped from the record', () async {
    unread = [_item('a'), _item('b')];
    await makeSync().run();

    unread = [_item('c'), _item('b')];
    await makeSync().run();

    expect(prefs.getStringList(NotificationSync.announcedIdsKey), ['c', 'b']);
  });

  test('a failed presentation is retried on the next run instead of lost', () async {
    unread = [_item('a')];
    await makeSync().run();

    unread = [_item('b'), _item('a')];
    presentFails = true;
    await expectLater(makeSync().run(), throwsException);

    presentFails = false;
    expect(await makeSync().run(), 1);
    expect(announced.single.single.id, 'b');
  });

  test('reset makes the next run a fresh baseline for the next account', () async {
    unread = [_item('a')];
    await makeSync().run();
    await NotificationSync.reset(prefs);

    unread = [_item('x'), _item('y')];

    expect(await makeSync().run(), 0);
    expect(announced, isEmpty);
  });

  group('notificationRoute', () {
    test('opens the thing the notification is about', () {
      expect(notificationRoute({'issue_id': 7}), '/issues/7');
      expect(notificationRoute({'task_id': 3}), '/tasks/3');
      expect(notificationRoute({'announcement_id': 12}), '/announcements/12');
      expect(notificationRoute({'exam_id': 4}), '/exams');
      expect(notificationRoute({'document_id': 9}), '/documents');
    });

    test('accepts ids the server sent as strings', () {
      expect(notificationRoute({'task_id': '5'}), '/tasks/5');
    });

    test('falls back to the notification list', () {
      expect(notificationRoute({}), '/notifications');
      expect(notificationRoute({'something_else': 1}), '/notifications');
    });
  });

  test('NotificationItem keeps the server payload', () {
    final item = NotificationItem.fromJson({
      'id': 'x',
      'type': 'TaskAssigned',
      'title': 'Yangi topshiriq',
      'message': 'msg',
      'data': {'task_id': 3},
      'read_at': null,
      'created_at': '2026-09-19T10:00:00Z',
    });

    expect(item.data['task_id'], 3);
    expect(NotificationItem.fromJson({'id': 'y', 'read_at': null}).data, isEmpty);
  });
}
