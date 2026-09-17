import 'package:flutter_test/flutter_test.dart';
import 'package:urtg_mobile/features/exams/domain/exam.dart';
import 'package:urtg_mobile/features/tasks/domain/task.dart';

void main() {
  group('Task.fromJson', () {
    test('parses a list-view task without comments/attachments/activities present', () {
      final task = Task.fromJson({
        'id': 10,
        'title': 'Hisobotni tayyorlash',
        'assignees': [],
        'priority': 'high',
        'status': 'in_progress',
        'progress': 40,
        'created_at': '2026-09-01T09:00:00Z',
      });

      expect(task.title, 'Hisobotni tayyorlash');
      expect(task.progress, 40);
      expect(task.comments, isEmpty);
      expect(task.attachments, isEmpty);
      expect(task.isActionable, isTrue);
    });

    test('a completed task is not actionable', () {
      final task = Task.fromJson({
        'id': 11,
        'title': 'Done',
        'assignees': [],
        'priority': 'normal',
        'status': 'completed',
        'progress': 100,
        'created_at': '2026-09-01T09:00:00Z',
      });

      expect(task.isActionable, isFalse);
    });

    test('parses a detail-view task with full comments/attachments', () {
      final task = Task.fromJson({
        'id': 12,
        'title': 'With comments',
        'assignees': [],
        'priority': 'normal',
        'status': 'new',
        'progress': 0,
        'created_at': '2026-09-01T09:00:00Z',
        'comments': [
          {'id': 1, 'user_name': 'Manager', 'body': 'Please hurry', 'created_at': '2026-09-01T10:00:00Z'},
        ],
        'attachments': [
          {'id': 2, 'original_name': 'file.pdf', 'file_size': 1024, 'download_url': 'http://x/download'},
        ],
      });

      expect(task.comments.single.body, 'Please hurry');
      expect(task.attachments.single.originalName, 'file.pdf');
    });
  });

  group('Exam.canAttempt', () {
    Exam examWithSummary({required bool passed, required int attemptsUsed, int attemptsAllowed = 3}) {
      return Exam.fromJson({
        'id': 1,
        'title': 'Xavfsizlik qoidalari',
        'duration_minutes': 30,
        'passing_score': 70,
        'attempts_allowed': attemptsAllowed,
        'status': 'active',
        'my_attempt_summary': {'attempts_used': attemptsUsed, 'passed': passed, 'last_status': 'completed'},
      });
    }

    test('can be attempted when never attempted before', () {
      final exam = Exam.fromJson({
        'id': 1,
        'title': 'New exam',
        'duration_minutes': 30,
        'passing_score': 70,
        'attempts_allowed': 3,
        'status': 'active',
      });

      expect(exam.canAttempt, isTrue);
    });

    test('cannot be attempted again once already passed', () {
      final exam = examWithSummary(passed: true, attemptsUsed: 1);

      expect(exam.canAttempt, isFalse);
    });

    test('cannot be attempted once attempts are exhausted', () {
      final exam = examWithSummary(passed: false, attemptsUsed: 3, attemptsAllowed: 3);

      expect(exam.canAttempt, isFalse);
    });

    test('can still be attempted with attempts remaining and not yet passed', () {
      final exam = examWithSummary(passed: false, attemptsUsed: 1, attemptsAllowed: 3);

      expect(exam.canAttempt, isTrue);
    });
  });

  group('ExamAttemptQuestion.fromJson', () {
    test('parses answers without an answer key, matching the employee-facing resource', () {
      final question = ExamAttemptQuestion.fromJson({
        'id': 1,
        'question': '2 + 2 = ?',
        'type': 'single_choice',
        'points': 1,
        'answers': [
          {'id': 1, 'answer': '3'},
          {'id': 2, 'answer': '4'},
        ],
      });

      expect(question.answers, hasLength(2));
      expect(question.answers.first.isCorrect, isNull);
    });
  });
}
