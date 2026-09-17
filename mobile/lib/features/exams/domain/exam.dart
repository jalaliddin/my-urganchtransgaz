class MyAttemptSummary {
  MyAttemptSummary({
    required this.attemptsUsed,
    this.bestPercentage,
    required this.passed,
    this.lastStatus,
  });

  final int attemptsUsed;
  final double? bestPercentage;
  final bool passed;
  final String? lastStatus;

  factory MyAttemptSummary.fromJson(Map<String, dynamic> json) => MyAttemptSummary(
        attemptsUsed: json['attempts_used'] as int? ?? 0,
        bestPercentage: (json['best_percentage'] as num?)?.toDouble(),
        passed: json['passed'] as bool? ?? false,
        lastStatus: json['last_status'] as String?,
      );
}

class Exam {
  Exam({
    required this.id,
    required this.title,
    this.description,
    required this.durationMinutes,
    required this.passingScore,
    required this.attemptsAllowed,
    this.startDate,
    this.endDate,
    required this.status,
    this.myAttemptSummary,
  });

  final int id;
  final String title;
  final String? description;
  final int durationMinutes;
  final int passingScore;
  final int attemptsAllowed;
  final String? startDate;
  final String? endDate;
  final String status;
  final MyAttemptSummary? myAttemptSummary;

  bool get canAttempt =>
      status == 'active' &&
      (myAttemptSummary == null ||
          (!myAttemptSummary!.passed && myAttemptSummary!.attemptsUsed < attemptsAllowed));

  factory Exam.fromJson(Map<String, dynamic> json) => Exam(
        id: json['id'] as int,
        title: json['title'] as String? ?? '',
        description: json['description'] as String?,
        durationMinutes: json['duration_minutes'] as int? ?? 0,
        passingScore: json['passing_score'] as int? ?? 0,
        attemptsAllowed: json['attempts_allowed'] as int? ?? 1,
        startDate: json['start_date'] as String?,
        endDate: json['end_date'] as String?,
        status: json['status'] as String? ?? 'active',
        myAttemptSummary: json['my_attempt_summary'] is Map<String, dynamic>
            ? MyAttemptSummary.fromJson(json['my_attempt_summary'] as Map<String, dynamic>)
            : null,
      );
}

class ExamAnswerOption {
  ExamAnswerOption({required this.id, required this.answer, this.isCorrect, this.wasSelected});

  final int id;
  final String answer;
  final bool? isCorrect;
  final bool? wasSelected;

  factory ExamAnswerOption.fromJson(Map<String, dynamic> json) => ExamAnswerOption(
        id: json['id'] as int,
        answer: json['answer'] as String? ?? '',
        isCorrect: json['is_correct'] as bool?,
        wasSelected: json['was_selected'] as bool?,
      );
}

class ExamAttemptQuestion {
  ExamAttemptQuestion({required this.id, required this.question, required this.type, required this.points, required this.answers});

  final int id;
  final String question;
  final String type;
  final int points;
  final List<ExamAnswerOption> answers;

  factory ExamAttemptQuestion.fromJson(Map<String, dynamic> json) => ExamAttemptQuestion(
        id: json['id'] as int,
        question: json['question'] as String? ?? '',
        type: json['type'] as String? ?? 'single_choice',
        points: json['points'] as int? ?? 1,
        answers: (json['answers'] as List<dynamic>? ?? [])
            .map((e) => ExamAnswerOption.fromJson(e as Map<String, dynamic>))
            .toList(),
      );
}

class ExamAttempt {
  ExamAttempt({
    required this.id,
    required this.examId,
    this.score,
    this.percentage,
    this.passed,
    required this.status,
    this.startedAt,
    this.completedAt,
    required this.questions,
  });

  final int id;
  final int examId;
  final int? score;
  final double? percentage;
  final bool? passed;
  final String status;
  final String? startedAt;
  final String? completedAt;
  final List<ExamAttemptQuestion> questions;

  factory ExamAttempt.fromJson(Map<String, dynamic> json) => ExamAttempt(
        id: json['id'] as int,
        examId: json['exam_id'] as int,
        score: json['score'] as int?,
        percentage: (json['percentage'] as num?)?.toDouble(),
        passed: json['passed'] as bool?,
        status: json['status'] as String? ?? 'in_progress',
        startedAt: json['started_at'] as String?,
        completedAt: json['completed_at'] as String?,
        questions: (json['questions'] as List<dynamic>? ?? [])
            .map((e) => ExamAttemptQuestion.fromJson(e as Map<String, dynamic>))
            .toList(),
      );
}
