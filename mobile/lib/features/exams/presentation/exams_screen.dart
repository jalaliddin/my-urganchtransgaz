import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/widgets/async_value_view.dart';
import '../../../core/widgets/responsive_body.dart';
import '../../../l10n/app_localizations.dart';
import '../domain/exam.dart';
import 'exams_controller.dart';

class ExamsScreen extends ConsumerStatefulWidget {
  const ExamsScreen({super.key});

  @override
  ConsumerState<ExamsScreen> createState() => _ExamsScreenState();
}

class _ExamsScreenState extends ConsumerState<ExamsScreen> with SingleTickerProviderStateMixin {
  late final TabController _tabController;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final exams = ref.watch(examsControllerProvider);

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.examsTitle),
        bottom: TabBar(
          controller: _tabController,
          tabs: [Tab(text: l10n.examsTabAvailable), Tab(text: l10n.examsTabResults)],
        ),
      ),
      body: ResponsiveBody(
        child: RefreshIndicator(
        onRefresh: () => ref.read(examsControllerProvider.notifier).refresh(),
        child: AsyncValueView(
          value: exams,
          onRetry: () => ref.invalidate(examsControllerProvider),
          data: (context, items) {
            final available = items.where((e) => e.canAttempt).toList();
            final withResults = items.where((e) => e.myAttemptSummary != null).toList();

            return TabBarView(
              controller: _tabController,
              children: [
                _ExamList(exams: available, emptyMessage: l10n.examsNone, showResults: false),
                _ExamList(exams: withResults, emptyMessage: l10n.examsNone, showResults: true),
              ],
            );
          },
        ),
      ),
      ),
    );
  }
}

class _ExamList extends StatelessWidget {
  const _ExamList({required this.exams, required this.emptyMessage, required this.showResults});

  final List<Exam> exams;
  final String emptyMessage;
  final bool showResults;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    if (exams.isEmpty) {
      return ListView(
        children: [SizedBox(height: 300, child: EmptyStateView(message: emptyMessage, icon: Icons.school_outlined))],
      );
    }

    return ListView.separated(
      itemCount: exams.length,
      separatorBuilder: (context, index) => const Divider(height: 1),
      itemBuilder: (context, index) {
        final exam = exams[index];
        final summary = exam.myAttemptSummary;

        return ListTile(
          title: Text(exam.title),
          subtitle: Text('${l10n.examsDuration}: ${exam.durationMinutes} ${l10n.examsMinutes} · ${l10n.examsPassingScore}: ${exam.passingScore}%'),
          trailing: showResults && summary != null
              ? Chip(
                  backgroundColor: summary.passed ? Colors.green.shade100 : Colors.red.shade100,
                  label: Text(summary.passed ? l10n.examsPassed : l10n.examsFailed),
                )
              : FilledButton(
                  onPressed: () => context.push('/exams/${exam.id}/attempt'),
                  child: Text(l10n.examsStart),
                ),
        );
      },
    );
  }
}
