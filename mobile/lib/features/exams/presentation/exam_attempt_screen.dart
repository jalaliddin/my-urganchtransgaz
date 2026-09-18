import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/widgets/async_value_view.dart';
import '../../../core/widgets/responsive_body.dart';
import '../../../l10n/app_localizations.dart';
import '../domain/exam.dart';
import 'exams_controller.dart';

class ExamAttemptScreen extends ConsumerStatefulWidget {
  const ExamAttemptScreen({super.key, required this.examId});

  final int examId;

  @override
  ConsumerState<ExamAttemptScreen> createState() => _ExamAttemptScreenState();
}

class _ExamAttemptScreenState extends ConsumerState<ExamAttemptScreen> {
  bool _submitting = false;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final attemptState = ref.watch(examAttemptProvider(widget.examId));

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, result) {
        if (didPop) return;
        _confirmLeave(context);
      },
      child: Scaffold(
        appBar: AppBar(title: Text(l10n.examsTitle)),
        body: ResponsiveBody(
          child: AsyncValueView(
            value: attemptState,
            onRetry: () => ref.invalidate(examAttemptProvider(widget.examId)),
            data: (context, attempt) => _buildQuiz(context, attempt, l10n),
          ),
        ),
      ),
    );
  }

  Widget _buildQuiz(BuildContext context, ExamAttempt attempt, AppLocalizations l10n) {
    final controller = ref.read(examAttemptProvider(widget.examId).notifier);

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        ...attempt.questions.asMap().entries.map((entry) {
          final index = entry.key;
          final question = entry.value;
          final singleChoice = question.type != 'multiple_choice';

          return Card(
            margin: const EdgeInsets.only(bottom: 16),
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('${index + 1}. ${question.question}', style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: 8),
                  if (singleChoice)
                    RadioGroup<int>(
                      groupValue: controller.selectedFor(question.id).isEmpty ? null : controller.selectedFor(question.id).first,
                      onChanged: (value) => setState(
                        () => controller.toggleAnswer(question.id, value!, singleChoice: true),
                      ),
                      child: Column(
                        children: question.answers
                            .map(
                              (answer) => RadioListTile<int>(
                                value: answer.id,
                                title: Text(answer.answer),
                                contentPadding: EdgeInsets.zero,
                              ),
                            )
                            .toList(),
                      ),
                    )
                  else
                    ...question.answers.map(
                      (answer) => CheckboxListTile(
                        value: controller.selectedFor(question.id).contains(answer.id),
                        title: Text(answer.answer),
                        contentPadding: EdgeInsets.zero,
                        onChanged: (_) => setState(
                          () => controller.toggleAnswer(question.id, answer.id, singleChoice: false),
                        ),
                      ),
                    ),
                ],
              ),
            ),
          );
        }),
        FilledButton(
          onPressed: _submitting ? null : () => _confirmSubmit(context, controller, l10n),
          child: _submitting
              ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
              : Text(l10n.examsSubmit),
        ),
      ],
    );
  }

  Future<void> _confirmSubmit(BuildContext context, ExamAttemptController controller, AppLocalizations l10n) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(l10n.examsConfirmSubmitTitle),
        content: Text(l10n.examsConfirmSubmitText),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: Text(l10n.commonCancel)),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: Text(l10n.commonConfirm)),
        ],
      ),
    );

    if (confirmed != true || !mounted) {
      return;
    }

    setState(() => _submitting = true);

    try {
      final result = await controller.submit();

      if (context.mounted) {
        await _showResult(context, result, l10n);

        if (context.mounted) {
          Navigator.pop(context);
        }
      }
    } catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    } finally {
      if (mounted) {
        setState(() => _submitting = false);
      }
    }
  }

  Future<void> _showResult(BuildContext context, ExamAttempt result, AppLocalizations l10n) {
    return showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) => AlertDialog(
        title: Text(result.passed == true ? l10n.examsPassed : l10n.examsFailed),
        content: Text('${l10n.examsScore}: ${result.percentage?.toStringAsFixed(0) ?? '-'}%'),
        actions: [FilledButton(onPressed: () => Navigator.pop(context), child: Text(l10n.commonOk))],
      ),
    );
  }

  Future<void> _confirmLeave(BuildContext context) async {
    final l10n = AppLocalizations.of(context);

    final leave = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        content: Text(l10n.examsConfirmLeaveText),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: Text(l10n.commonNo)),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: Text(l10n.commonYes)),
        ],
      ),
    );

    if (leave == true && context.mounted) {
      Navigator.pop(context);
    }
  }
}
