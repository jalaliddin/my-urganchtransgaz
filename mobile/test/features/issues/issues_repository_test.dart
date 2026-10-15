import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:urtg_mobile/core/network/api_client.dart';
import 'package:urtg_mobile/features/issues/data/issues_repository.dart';

/// The real repository and ApiClient; the request is captured before it
/// leaves the device and answered as the server would.
(IssuesRepository, List<RequestOptions>) _repository() {
  final client = ApiClient();
  final sent = <RequestOptions>[];
  client.dio.interceptors.insert(
    0,
    InterceptorsWrapper(
      onRequest: (options, handler) {
        sent.add(options);
        handler.resolve(Response(
          requestOptions: options,
          statusCode: 201,
          data: {
            'data': {'id': 1, 'title': 'x', 'latitude': 41.5, 'longitude': 60.6, 'status': 'open'},
          },
        ));
      },
    ),
  );

  return (IssuesRepository(client), sent);
}

void main() {
  test('sends photos as multipart under photos[], with the other fields alongside', () async {
    final dir = Directory.systemTemp.createTempSync('issue_upload');
    final paths = [
      for (final name in ['a.jpg', 'b.jpg']) (File('${dir.path}/$name')..writeAsBytesSync([0xFF, 0xD8, 0xFF])).path,
    ];
    final (repository, sent) = _repository();

    await repository.create(
      title: 'Quvur',
      organizationId: 3,
      categoryId: 1,
      executorIds: [9, 10],
      latitude: 41.5,
      longitude: 60.6,
      photoPaths: paths,
    );

    final form = sent.single.data as FormData;
    expect(form.files.map((file) => file.key), ['photos[]', 'photos[]']);
    expect(form.files.map((file) => file.value.filename), ['photo_1.jpg', 'photo_2.jpg']);
    expect(
      form.fields.map((field) => '${field.key}=${field.value}'),
      containsAll(['title=Quvur', 'organization_id=3', 'executor_ids[]=9', 'executor_ids[]=10', 'latitude=41.5']),
    );
  });

  test('without photos the body stays plain JSON', () async {
    final (repository, sent) = _repository();

    await repository.create(title: 'Quvur', organizationId: 3, categoryId: 1, latitude: 41.5, longitude: 60.6);

    expect(sent.single.data, isA<Map<String, dynamic>>());
    expect((sent.single.data as Map)['title'], 'Quvur');
  });
}
