import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:urtg_mobile/features/documents/domain/employee_document.dart';
import 'package:urtg_mobile/features/documents/presentation/documents_controller.dart';
import 'package:urtg_mobile/features/documents/presentation/documents_screen.dart';
import 'package:urtg_mobile/l10n/app_localizations.dart';

class _FakeDocuments extends DocumentsController {
  @override
  Future<List<EmployeeDocument>> build() async => [];
}

void main() {
  testWidgets('the upload sheet keeps Save above the system navigation bar', (tester) async {
    final insets = FakeViewPadding(bottom: 48 * tester.view.devicePixelRatio);
    tester.view.padding = insets;
    tester.view.viewPadding = insets;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          documentsControllerProvider.overrideWith(_FakeDocuments.new),
          documentTypesProvider.overrideWith((ref) async => [DocumentType(id: 1, name: 'Pasport', requiresExpiry: false)]),
        ],
        child: MaterialApp(
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          supportedLocales: AppLocalizations.supportedLocales,
          locale: const Locale('uz'),
          home: const DocumentsScreen(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('Hujjat yuklash'));
    await tester.pumpAndSettle();

    final screenHeight = tester.view.physicalSize.height / tester.view.devicePixelRatio;

    expect(tester.getRect(find.widgetWithText(FilledButton, 'Saqlash')).bottom, lessThanOrEqualTo(screenHeight - 48));
  });
}
