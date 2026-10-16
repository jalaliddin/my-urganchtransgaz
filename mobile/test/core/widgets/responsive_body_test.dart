import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:urtg_mobile/core/widgets/responsive_body.dart';

/// Android 15+ draws every app under the system bars and reports them,
/// plus any display cutout, as the window's padding.
Future<void> _pump(WidgetTester tester, {required Size size, required FakeViewPadding insets}) async {
  tester.view.physicalSize = size;
  tester.view.devicePixelRatio = 1;
  tester.view.padding = insets;
  tester.view.viewPadding = insets;
  addTearDown(tester.view.reset);

  await tester.pumpWidget(
    MaterialApp(
      home: Scaffold(
        appBar: AppBar(title: const Text('Sahifa')),
        body: const ResponsiveBody(child: SizedBox.expand(key: Key('content'))),
      ),
    ),
  );
}

void main() {
  testWidgets('keeps the content above the system navigation bar', (tester) async {
    await _pump(tester, size: const Size(390, 844), insets: const FakeViewPadding(top: 24, bottom: 48));

    expect(tester.getRect(find.byKey(const Key('content'))).bottom, 844 - 48);
  });

  testWidgets('in landscape, keeps the content clear of the cutout and the side navigation bar', (tester) async {
    await _pump(tester, size: const Size(640, 360), insets: const FakeViewPadding(left: 32, right: 48));

    final content = tester.getRect(find.byKey(const Key('content')));

    expect(content.left, 32);
    expect(content.right, 640 - 48);
  });
}
