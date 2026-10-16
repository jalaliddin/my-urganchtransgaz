import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/formatting/dates.dart';
import '../../../core/theme/status_colors.dart';
import '../../../core/widgets/async_value_view.dart';
import '../../../core/widgets/responsive_body.dart';
import '../../../core/widgets/section.dart';
import '../../../core/widgets/status_pill.dart';
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
    if (exams.isEmpty) {
      return ListView(
        children: [SizedBox(height: 300, child: EmptyStateView(message: emptyMessage, icon: Icons.school_outlined))],
      );
    }

    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: exams.length,
      separatorBuilder: (context, index) => const SizedBox(height: 10),
      itemBuilder: (context, index) => _ExamCard(exam: exams[index], showResults: showResults),
    );
  }
}

class _ExamCard extends StatelessWidget {
  const _ExamCard({required this.exam, required this.showResults});

  final Exam exam;
  final bool showResults;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final scheme = Theme.of(context).colorScheme;
    final text = Theme.of(context).textTheme;
    final status = StatusColors.of(context);
    final summary = exam.myAttemptSummary;
    final ends = AppDates.parse(exam.endDate);

    Widget fact(IconData icon, String value) => Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 16, color: scheme.onSurfaceVariant),
            const SizedBox(width: 4),
            Text(value, style: text.labelMedium!.copyWith(color: scheme.onSurfaceVariant, fontWeight: FontWeight.w500)),
          ],
        );

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                IconBadge(
                  icon: Icons.school_outlined,
                  color: status.fg(StatusTone.success),
                  background: status.bg(StatusTone.success),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(exam.title, style: text.titleSmall),
                      const SizedBox(height: 8),
                      Wrap(
                        spacing: 14,
                        runSpacing: 6,
                        children: [
                          fact(Icons.timer_outlined, '${exam.durationMinutes} ${l10n.examsMinutes}'),
                          fact(Icons.verified_outlined, '${l10n.examsPassingScore}: ${exam.passingScore}%'),
                          if (ends != null && !showResults) fact(Icons.event_outlined, AppDates.due(context, ends)),
                        ],
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            if (showResults && summary != null)
              Row(
                children: [
                  StatusPill(
                    label: summary.passed ? l10n.examsPassed : l10n.examsFailed,
                    tone: summary.passed ? StatusTone.success : StatusTone.danger,
                  ),
                  const Spacer(),
                  if (summary.bestPercentage != null)
                    Text(
                      '${summary.bestPercentage!.round()}%',
                      style: text.titleLarge!.copyWith(fontFeatures: const [FontFeature.tabularFigures()]),
                    ),
                ],
              )
            else
              FilledButton.icon(
                onPressed: () => context.push('/exams/${exam.id}/attempt'),
                icon: const Icon(Icons.play_arrow_rounded),
                label: Text(l10n.examsStart),
              ),
          ],
        ),
      ),
    );
  }
}
