import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:urtg_mobile/core/models/user.dart';
import 'package:urtg_mobile/core/network/api_client.dart';
import 'package:urtg_mobile/core/network/api_providers.dart';
import 'package:urtg_mobile/core/router/app_router.dart';
import 'package:urtg_mobile/core/storage/secure_storage.dart';
import 'package:urtg_mobile/features/app_update/app_update_controller.dart';
import 'package:urtg_mobile/features/auth/presentation/auth_controller.dart';
import 'package:urtg_mobile/l10n/app_localizations.dart';

const _storeUrl = 'https://play.google.com/store/apps/details?id=uz.urtg.urtg_mobile';

/// Answers every request the way the server would, after the real
/// interceptors have run, and remembers what was sent.
class _FakeServer implements HttpClientAdapter {
  _FakeServer({required this.status, required this.body});

  final int status;
  final Map<String, dynamic> body;
  final sent = <RequestOptions>[];

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    sent.add(options);

    return ResponseBody.fromString(jsonEncode(body), status, headers: {
      Headers.contentTypeHeader: [Headers.jsonContentType],
    });
  }

  @override
  void close({bool force = false}) {}
}

class _Unreachable implements HttpClientAdapter {
  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) =>
      throw DioException.connectionError(requestOptions: options, reason: 'offline');

  @override
  void close({bool force = false}) {}
}

ApiClient _client(HttpClientAdapter adapter) => ApiClient()..dio.httpClientAdapter = adapter;

ProviderContainer _container(ApiClient client, {int build = 12}) {
  final container = ProviderContainer(overrides: [
    apiClientProvider.overrideWithValue(client),
    appBuildNumberProvider.overrideWith((ref) async => build),
  ]);
  addTearDown(container.dispose);

  return container;
}

class _LoggedOut extends AuthController {
  @override
  Future<User?> build() async => null;
}

class _Required extends AppUpdateController {
  @override
  UpdateRequirement? build() => const UpdateRequirement(minBuild: 13, storeUrl: _storeUrl);
}

void main() {
  setUpAll(() {
    FlutterSecureStorage.setMockInitialValues({});
    PackageInfo.setMockInitialValues(
      appName: 'My Urganchtransgaz',
      packageName: 'uz.urtg.urtg_mobile',
      version: '1.3.0',
      buildNumber: '13',
      buildSignature: '',
    );
  });

  group('ApiClient', () {
    test('sends this build number and the platform with every request', () async {
      final server = _FakeServer(status: 200, body: {'data': {}});

      await _client(server).dio.get<dynamic>('/auth/me');

      expect(server.sent.single.headers['X-App-Build'], '13');
      expect(server.sent.single.headers['X-App-Platform'], isNotEmpty);
    });

    test('hands a 426 answer to onUpdateRequired with the server\'s body', () async {
      final client = _client(_FakeServer(status: 426, body: {
        'success': false,
        'message': 'Ilovaning yangi versiyasi chiqdi.',
        'min_build': 14,
        'store_url': _storeUrl,
      }));
      Map<String, dynamic>? received;
      client.onUpdateRequired = (body) => received = body;

      await expectLater(client.dio.get<dynamic>('/tasks'), throwsA(isA<DioException>()));

      expect(received?['min_build'], 14);
      expect(received?['store_url'], _storeUrl);
    });
  });

  group('AppUpdateController.check', () {
    test('requires an update when the server\'s minimum is above this build', () async {
      final container = _container(
        _client(_FakeServer(status: 200, body: {'data': {'min_build': 13, 'store_url': _storeUrl}})),
        build: 12,
      );

      await container.read(appUpdateControllerProvider.notifier).check();

      final requirement = container.read(appUpdateControllerProvider);
      expect(requirement?.minBuild, 13);
      expect(requirement?.storeUrl, _storeUrl);
    });

    test('lets this build through when it is the minimum', () async {
      final container = _container(
        _client(_FakeServer(status: 200, body: {'data': {'min_build': 12, 'store_url': _storeUrl}})),
        build: 12,
      );

      await container.read(appUpdateControllerProvider.notifier).check();

      expect(container.read(appUpdateControllerProvider), isNull);
    });

    test('blocks nothing when the server can\'t be reached', () async {
      final container = _container(_client(_Unreachable()), build: 12);

      await container.read(appUpdateControllerProvider.notifier).check();

      expect(container.read(appUpdateControllerProvider), isNull);
    });
  });

  group('AuthController at launch', () {
    Future<String?> tokenAfterLaunch(int status) async {
      FlutterSecureStorage.setMockInitialValues({'urtg_token': 'session-token'});
      final container = _container(_client(_FakeServer(status: status, body: {'success': false, 'message': 'x'})));

      await container.read(authControllerProvider.future);

      return SecureStorage.readToken();
    }

    test('keeps the session when the build is merely outdated (426), so the updated app opens logged in', () async {
      expect(await tokenAfterLaunch(426), 'session-token');
    });

    test('still drops a session the server rejects (401)', () async {
      expect(await tokenAfterLaunch(401), isNull);
    });
  });

  group('the update screen', () {
    Future<List<Uri>> pumpApp(WidgetTester tester, {bool launchSucceeds = true}) async {
      final opened = <Uri>[];
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            authControllerProvider.overrideWith(_LoggedOut.new),
            appUpdateControllerProvider.overrideWith(_Required.new),
            storeLauncherProvider.overrideWithValue((uri) async {
              opened.add(uri);
              return launchSucceeds;
            }),
          ],
          child: Consumer(
            builder: (context, ref, _) => MaterialApp.router(
              routerConfig: ref.watch(routerProvider),
              localizationsDelegates: AppLocalizations.localizationsDelegates,
              supportedLocales: AppLocalizations.supportedLocales,
              locale: const Locale('uz'),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      return opened;
    }

    testWidgets('replaces every screen, login included, for an outdated build', (tester) async {
      await pumpApp(tester);

      expect(find.text('Yangi versiya chiqdi'), findsOneWidget);
      expect(find.text('Sizdagi versiya: 1.3.0 (13)'), findsOneWidget);
      expect(find.text('Kirish'), findsNothing);
    });

    testWidgets('opens the store page from the update button', (tester) async {
      final opened = await pumpApp(tester);

      await tester.tap(find.text('Yangilash'));
      await tester.pump();

      expect(opened.single, Uri.parse(_storeUrl));
    });

    testWidgets('says how to update by hand when the store can\'t be opened', (tester) async {
      await pumpApp(tester, launchSucceeds: false);

      await tester.tap(find.text('Yangilash'));
      await tester.pump();

      expect(find.text('Play Market\'ni ochib bo\'lmadi. Ilovani Play Market orqali qo\'lda yangilang.'), findsOneWidget);
    });
  });
}
