import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/models/employee_contact.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/widgets/async_value_view.dart';
import '../../../l10n/app_localizations.dart';
import 'profile_controller.dart';

/// Emergency contacts and bank details — fully self-managed (spec Module
/// 5), and the emergency contact counts toward profile completion.
class ProfileContactsTab extends ConsumerWidget {
  const ProfileContactsTab({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final profile = ref.watch(profileControllerProvider);

    return AsyncValueView(
      value: profile,
      onRetry: () => ref.invalidate(profileControllerProvider),
      data: (context, employee) {
        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (employee.contacts.isEmpty)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 24),
                child: EmptyStateView(message: l10n.profileNoContacts, icon: Icons.contact_phone_outlined),
              )
            else
              Card(
                clipBehavior: Clip.antiAlias,
                child: Column(
                  children: [for (final contact in employee.contacts) _ContactTile(contact: contact)],
                ),
              ),
            const SizedBox(height: 16),
            OutlinedButton.icon(
              icon: const Icon(Icons.add),
              label: Text(l10n.profileAddContact),
              onPressed: () => showModalBottomSheet<void>(
                context: context,
                isScrollControlled: true,
                builder: (context) => const _AddContactSheet(),
              ),
            ),
          ],
        );
      },
    );
  }
}

class _ContactTile extends ConsumerWidget {
  const _ContactTile({required this.contact});

  final EmployeeContact contact;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final details = contact.isBank
        ? [contact.bankName, contact.bankAccountNumber]
        : [contact.relationship, contact.phone];

    return ListTile(
      leading: Icon(contact.isBank ? Icons.account_balance_outlined : Icons.contact_emergency_outlined),
      title: Text(contact.fullName),
      subtitle: Text(
        [contact.isBank ? l10n.profileBankInfo : l10n.profileEmergencyContact, ...details.whereType<String>().where((d) => d.isNotEmpty)]
            .join(' · '),
      ),
      trailing: IconButton(
        tooltip: l10n.commonDelete,
        icon: Icon(Icons.delete_outline, color: Theme.of(context).colorScheme.error),
        onPressed: contact.id == null ? null : () => _confirmDelete(context, ref, contact.id!),
      ),
    );
  }

  Future<void> _confirmDelete(BuildContext context, WidgetRef ref, int contactId) async {
    final l10n = AppLocalizations.of(context);
    final messenger = ScaffoldMessenger.of(context);

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        content: Text(l10n.profileDeleteContactConfirm),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: Text(l10n.commonNo)),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: Text(l10n.commonYes)),
        ],
      ),
    );

    if (confirmed != true) return;

    try {
      await ref.read(profileControllerProvider.notifier).removeContact(contactId);
    } on ApiException catch (e) {
      messenger.showSnackBar(SnackBar(content: Text(e.message)));
    }
  }
}

class _AddContactSheet extends ConsumerStatefulWidget {
  const _AddContactSheet();

  @override
  ConsumerState<_AddContactSheet> createState() => _AddContactSheetState();
}

class _AddContactSheetState extends ConsumerState<_AddContactSheet> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _relationshipController = TextEditingController();
  final _phoneController = TextEditingController();
  final _bankNameController = TextEditingController();
  final _accountController = TextEditingController();
  String _type = 'emergency';
  bool _isSaving = false;

  @override
  void dispose() {
    _nameController.dispose();
    _relationshipController.dispose();
    _phoneController.dispose();
    _bankNameController.dispose();
    _accountController.dispose();
    super.dispose();
  }

  String? _trimmedOrNull(TextEditingController controller) {
    final value = controller.text.trim();
    return value.isEmpty ? null : value;
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _isSaving = true);
    final isBank = _type == 'bank';

    try {
      await ref.read(profileControllerProvider.notifier).addContact(EmployeeContact(
            type: _type,
            fullName: _nameController.text.trim(),
            relationship: isBank ? null : _trimmedOrNull(_relationshipController),
            phone: isBank ? null : _trimmedOrNull(_phoneController),
            bankName: isBank ? _trimmedOrNull(_bankNameController) : null,
            bankAccountNumber: isBank ? _trimmedOrNull(_accountController) : null,
          ));

      if (mounted) Navigator.pop(context);
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _isSaving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final isBank = _type == 'bank';
    String? required(String? value) => (value == null || value.trim().isEmpty) ? l10n.commonRequired : null;

    return Padding(
      // Above the keyboard when it's open, else above the navigation bar.
      padding: EdgeInsets.fromLTRB(
        16,
        16,
        16,
        MediaQuery.viewInsetsOf(context).bottom + MediaQuery.paddingOf(context).bottom + 16,
      ),
      child: Form(
        key: _formKey,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(l10n.profileAddContact, style: Theme.of(context).textTheme.titleLarge),
              const SizedBox(height: 16),
              SegmentedButton<String>(
                segments: [
                  ButtonSegment(value: 'emergency', label: Text(l10n.profileEmergencyContact)),
                  ButtonSegment(value: 'bank', label: Text(l10n.profileBankInfo)),
                ],
                selected: {_type},
                onSelectionChanged: (selection) => setState(() => _type = selection.first),
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _nameController,
                decoration: InputDecoration(labelText: isBank ? l10n.profileAccountHolder : l10n.profileContactName),
                validator: required,
              ),
              const SizedBox(height: 12),
              if (isBank) ...[
                TextFormField(
                  controller: _bankNameController,
                  decoration: InputDecoration(labelText: l10n.profileBankName),
                  validator: required,
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _accountController,
                  decoration: InputDecoration(labelText: l10n.profileBankAccount),
                  keyboardType: TextInputType.number,
                  validator: required,
                ),
              ] else ...[
                TextFormField(
                  controller: _relationshipController,
                  decoration: InputDecoration(labelText: l10n.profileRelationship),
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _phoneController,
                  decoration: InputDecoration(labelText: l10n.profilePhone),
                  keyboardType: TextInputType.phone,
                  validator: required,
                ),
              ],
              const SizedBox(height: 20),
              FilledButton(
                onPressed: _isSaving ? null : _save,
                child: _isSaving
                    ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                    : Text(l10n.commonSave),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
