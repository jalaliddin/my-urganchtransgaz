import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../domain/issue_report.dart';
import 'issues_controller.dart';

/// The report's time window — the web page's presets (it also takes a
/// typed date range; on a phone the presets cover what leaders check).
enum IssueReportPeriod { thisMonth, last3Months, last12Months, allTime }

/// `yyyy-MM-dd` start of [period] relative to [now], or null for all time.
String? issueReportDateFrom(IssueReportPeriod period, DateTime now) {
  final from = switch (period) {
    IssueReportPeriod.thisMonth => DateTime(now.year, now.month),
    IssueReportPeriod.last3Months => now.subtract(const Duration(days: 90)),
    IssueReportPeriod.last12Months => now.subtract(const Duration(days: 365)),
    IssueReportPeriod.allTime => null,
  };

  if (from == null) return null;

  return '${from.year.toString().padLeft(4, '0')}-${from.month.toString().padLeft(2, '0')}-${from.day.toString().padLeft(2, '0')}';
}

class IssueReportPeriodController extends Notifier<IssueReportPeriod> {
  @override
  IssueReportPeriod build() => IssueReportPeriod.last12Months;

  void select(IssueReportPeriod period) => state = period;
}

final issueReportPeriodProvider =
    NotifierProvider<IssueReportPeriodController, IssueReportPeriod>(IssueReportPeriodController.new);

final issueReportProvider = FutureProvider.autoDispose<IssueReport>((ref) {
  final period = ref.watch(issueReportPeriodProvider);

  return ref.watch(issuesRepositoryProvider).report(dateFrom: issueReportDateFrom(period, DateTime.now()));
});
