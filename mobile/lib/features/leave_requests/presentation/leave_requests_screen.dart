import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/widgets/async_value_view.dart';
import '../../../l10n/app_localizations.dart';
import '../domain/leave_request.dart';
import 'leave_requests_controller.dart';

class LeaveRequestsScreen extends ConsumerWidget {
  const LeaveRequestsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final requests = ref.watch(leaveRequestsControllerProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.leaveRequestsTitle)),
      floatingActionButton: FloatingActionButton(
        onPressed: () => showModalBottomSheet(
          context: context,
          isScrollControlled: true,
          builder: (context) => const _CreateLeaveRequestSheet(),
        ),
        child: const Icon(Icons.add),
      ),
      body: RefreshIndicator(
        onRefresh: () => ref.read(leaveRequestsControllerProvider.notifier).refresh(),
        child: AsyncValueView(
          value: requests,
          onRetry: () => ref.invalidate(leaveRequestsControllerProvider),
          data: (context, items) {
            if (items.isEmpty) {
              return ListView(
                children: [SizedBox(height: 400, child: EmptyStateView(message: l10n.leaveRequestsNone, icon: Icons.event_busy_outlined))],
              );
            }

            return ListView.separated(
              itemCount: items.length,
              separatorBuilder: (context, index) => const Divider(height: 1),
              itemBuilder: (context, index) => _LeaveRequestTile(request: items[index]),
            );
          },
        ),
      ),
    );
  }
}

class _LeaveRequestTile extends ConsumerWidget {
  const _LeaveRequestTile({required this.request});

  final LeaveRequest request;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);

    return ListTile(
      title: Text(_typeLabel(l10n, request.type)),
      subtitle: Text('${request.startDate} — ${request.endDate}'),
      trailing: request.isCancellable
          ? IconButton(
              icon: const Icon(Icons.close),
              tooltip: l10n.leaveRequestsCancel,
              onPressed: () => ref.read(leaveRequestsControllerProvider.notifier).cancel(request.id),
            )
          : Chip(label: Text(_statusLabel(l10n, request.status))),
    );
  }

  String _typeLabel(AppLocalizations l10n, String type) {
    return switch (type) {
      'vacation' => l10n.leaveRequestsVacation,
      'business_trip' => l10n.leaveRequestsBusinessTrip,
      'sick_leave' => l10n.leaveRequestsSickLeave,
      _ => l10n.leaveRequestsOther,
    };
  }

  String _statusLabel(AppLocalizations l10n, String status) {
    return switch (status) {
      'pending' => l10n.statusPending,
      'department_approved' => l10n.statusDepartmentApproved,
      'approved' => l10n.statusApproved,
      'rejected' => l10n.statusRejected,
      'cancelled' => l10n.statusCancelled,
      _ => status,
    };
  }
}

class _CreateLeaveRequestSheet extends ConsumerStatefulWidget {
  const _CreateLeaveRequestSheet();

  @override
  ConsumerState<_CreateLeaveRequestSheet> createState() => _CreateLeaveRequestSheetState();
}

class _CreateLeaveRequestSheetState extends ConsumerState<_CreateLeaveRequestSheet> {
  String _type = 'vacation';
  DateTime? _startDate;
  DateTime? _endDate;
  final _reasonController = TextEditingController();
  bool _isSubmitting = false;

  @override
  void dispose() {
    _reasonController.dispose();
    super.dispose();
  }

  Future<void> _pickDate({required bool isStart}) async {
    final picked = await showDatePicker(
      context: context,
      initialDate: DateTime.now(),
      firstDate: DateTime.now().subtract(const Duration(days: 1)),
      lastDate: DateTime.now().add(const Duration(days: 365)),
    );

    if (picked != null) {
      setState(() => isStart ? _startDate = picked : _endDate = picked);
    }
  }

  Future<void> _submit() async {
    if (_startDate == null || _endDate == null) {
      return;
    }

    setState(() => _isSubmitting = true);

    try {
      await ref.read(leaveRequestsControllerProvider.notifier).create(
            type: _type,
            startDate: _format(_startDate!),
            endDate: _format(_endDate!),
            reason: _reasonController.text.trim().isEmpty ? null : _reasonController.text.trim(),
          );

      if (mounted) {
        Navigator.pop(context);
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    } finally {
      if (mounted) {
        setState(() => _isSubmitting = false);
      }
    }
  }

  String _format(DateTime date) =>
      '${date.year.toString().padLeft(4, '0')}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    return Padding(
      padding: EdgeInsets.only(left: 16, right: 16, top: 16, bottom: MediaQuery.of(context).viewInsets.bottom + 16),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(l10n.leaveRequestsCreate, style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 16),
          DropdownButtonFormField<String>(
            initialValue: _type,
            decoration: InputDecoration(labelText: l10n.leaveRequestsType),
            items: [
              DropdownMenuItem(value: 'vacation', child: Text(l10n.leaveRequestsVacation)),
              DropdownMenuItem(value: 'business_trip', child: Text(l10n.leaveRequestsBusinessTrip)),
              DropdownMenuItem(value: 'sick_leave', child: Text(l10n.leaveRequestsSickLeave)),
              DropdownMenuItem(value: 'other', child: Text(l10n.leaveRequestsOther)),
            ],
            onChanged: (value) => setState(() => _type = value ?? _type),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: () => _pickDate(isStart: true),
                  child: Text(_startDate == null ? l10n.leaveRequestsStartDate : _format(_startDate!)),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: OutlinedButton(
                  onPressed: () => _pickDate(isStart: false),
                  child: Text(_endDate == null ? l10n.leaveRequestsEndDate : _format(_endDate!)),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _reasonController,
            decoration: InputDecoration(labelText: l10n.leaveRequestsReason),
            maxLines: 2,
          ),
          const SizedBox(height: 16),
          FilledButton(
            onPressed: _isSubmitting ? null : _submit,
            child: _isSubmitting
                ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                : Text(l10n.commonSubmit),
          ),
        ],
      ),
    );
  }
}
