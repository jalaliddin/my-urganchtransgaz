import '../../../core/theme/status_colors.dart';
import '../../../l10n/app_localizations.dart';

String taskStatusLabel(AppLocalizations l10n, String status) {
  return switch (status) {
    'new' => l10n.statusNew,
    'in_progress' => l10n.statusInProgress,
    'waiting' => l10n.statusWaiting,
    'completed' => l10n.statusCompleted,
    'cancelled' => l10n.statusCancelled,
    'overdue' => l10n.statusOverdue,
    _ => status,
  };
}

StatusTone taskStatusTone(String status) {
  return switch (status) {
    'overdue' => StatusTone.danger,
    'waiting' => StatusTone.warning,
    'in_progress' => StatusTone.info,
    'completed' => StatusTone.success,
    'new' => StatusTone.info,
    _ => StatusTone.neutral,
  };
}

String taskPriorityLabel(AppLocalizations l10n, String priority) {
  return switch (priority) {
    'low' => l10n.statusLow,
    'high' => l10n.statusHigh,
    'urgent' => l10n.statusUrgent,
    _ => l10n.statusNormal,
  };
}

/// Only high and urgent are worth flagging in a list; normal and low are
/// the quiet default.
StatusTone? taskPriorityTone(String priority) {
  return switch (priority) {
    'urgent' => StatusTone.danger,
    'high' => StatusTone.warning,
    _ => null,
  };
}
