import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/models/employee.dart';
import '../../../core/network/api_providers.dart';
import '../../../core/widgets/async_value_view.dart';
import '../../../core/widgets/responsive_body.dart';
import '../../../l10n/app_localizations.dart';
import '../domain/change_request.dart';
import 'profile_controller.dart';

class ProfileScreen extends ConsumerStatefulWidget {
  const ProfileScreen({super.key});

  @override
  ConsumerState<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends ConsumerState<ProfileScreen> with SingleTickerProviderStateMixin {
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

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.profileTitle),
        bottom: TabBar(
          controller: _tabController,
          tabs: [Tab(text: l10n.profileTabInfo), Tab(text: l10n.profileTabRequests)],
        ),
      ),
      body: ResponsiveBody(
        child: TabBarView(
          controller: _tabController,
          children: const [_ProfileInfoTab(), _ProfileRequestsTab()],
        ),
      ),
    );
  }
}

class _ProfileInfoTab extends ConsumerStatefulWidget {
  const _ProfileInfoTab();

  @override
  ConsumerState<_ProfileInfoTab> createState() => _ProfileInfoTabState();
}

class _ProfileInfoTabState extends ConsumerState<_ProfileInfoTab> {
  final _formKey = GlobalKey<FormState>();
  final _phoneController = TextEditingController();
  final _emailController = TextEditingController();
  final _addressController = TextEditingController();
  Employee? _loadedFor;
  bool _isSaving = false;

  void _syncControllers(Employee employee) {
    if (identical(_loadedFor, employee)) return;
    _loadedFor = employee;
    _phoneController.text = employee.phone ?? '';
    _emailController.text = employee.email ?? '';
    _addressController.text = employee.address ?? '';
  }

  @override
  void dispose() {
    _phoneController.dispose();
    _emailController.dispose();
    _addressController.dispose();
    super.dispose();
  }

  Future<void> _pickPhoto() async {
    final picker = ImagePicker();
    final picked = await picker.pickImage(source: ImageSource.gallery, imageQuality: 85);

    if (picked == null) return;

    await ref.read(profileControllerProvider.notifier).uploadPhoto(picked.path, picked.name);
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _isSaving = true);
    final l10n = AppLocalizations.of(context);

    try {
      final pendingApproval = await ref.read(profileControllerProvider.notifier).submitChanges({
        'phone': _phoneController.text.trim(),
        'email': _emailController.text.trim(),
        'address': _addressController.text.trim(),
      });

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(pendingApproval ? l10n.profileSavePending : l10n.profileSaveSuccess)),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    } finally {
      if (mounted) {
        setState(() => _isSaving = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final profile = ref.watch(profileControllerProvider);
    final authHeaders = ref.watch(authHeadersProvider).value;

    return RefreshIndicator(
      onRefresh: () => ref.read(profileControllerProvider.notifier).refresh(),
      child: AsyncValueView(
        value: profile,
        onRetry: () => ref.invalidate(profileControllerProvider),
        data: (context, employee) {
          _syncControllers(employee);

          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Center(
                child: Stack(
                  children: [
                    CircleAvatar(
                      radius: 48,
                      backgroundImage: (employee.photoUrl != null && authHeaders != null)
                          ? NetworkImage(employee.photoUrl!, headers: authHeaders)
                          : null,
                      child: (employee.photoUrl == null || authHeaders == null) ? const Icon(Icons.person, size: 48) : null,
                    ),
                    Positioned(
                      right: 0,
                      bottom: 0,
                      child: CircleAvatar(
                        radius: 18,
                        child: IconButton(icon: const Icon(Icons.camera_alt, size: 18), onPressed: _pickPhoto),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              Center(child: Text(employee.fullName, style: Theme.of(context).textTheme.titleLarge)),
              Center(child: Text(employee.position?.title ?? employee.employeeNumber, style: Theme.of(context).textTheme.bodyMedium)),
              const SizedBox(height: 24),
              Card(
                color: Theme.of(context).colorScheme.surfaceContainerHighest,
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Row(
                    children: [
                      const Icon(Icons.info_outline, size: 20),
                      const SizedBox(width: 8),
                      Expanded(child: Text(l10n.profileSensitiveNotice, style: Theme.of(context).textTheme.bodySmall)),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 16),
              Form(
                key: _formKey,
                child: Column(
                  children: [
                    TextFormField(
                      controller: _phoneController,
                      decoration: InputDecoration(labelText: l10n.profilePhone),
                      keyboardType: TextInputType.phone,
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _emailController,
                      decoration: InputDecoration(labelText: l10n.profileEmail),
                      keyboardType: TextInputType.emailAddress,
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _addressController,
                      decoration: InputDecoration(labelText: l10n.profileAddress),
                      maxLines: 2,
                    ),
                    const SizedBox(height: 16),
                    FilledButton(
                      onPressed: _isSaving ? null : _submit,
                      child: _isSaving
                          ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                          : Text(l10n.commonSave),
                    ),
                  ],
                ),
              ),
              const Divider(height: 40),
              _InfoRow(label: l10n.profileEmployeeNumber, value: employee.employeeNumber),
              _InfoRow(label: l10n.profileOrganization, value: employee.organization?.name ?? '—'),
              _InfoRow(label: l10n.profileDepartment, value: employee.department?.name ?? '—'),
              _InfoRow(label: l10n.profileHireDate, value: employee.hireDate ?? '—'),
            ],
          );
        },
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    // Stacked, not a side-by-side row: some values here (organization
    // names in particular) run long enough that a `Row` with
    // spaceBetween leaves no room for a gap once the value wraps, and
    // the two run together illegibly — see the real seeded org name
    // "Urganch shahar gaz ta'minoti boshqarmasi" caught during
    // browser verification.
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: Theme.of(context).textTheme.bodySmall),
          Text(value, style: Theme.of(context).textTheme.bodyLarge),
        ],
      ),
    );
  }
}

class _ProfileRequestsTab extends ConsumerWidget {
  const _ProfileRequestsTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final requests = ref.watch(myChangeRequestsProvider);

    return RefreshIndicator(
      onRefresh: () async => ref.invalidate(myChangeRequestsProvider),
      child: requests.when(
        data: (items) {
          if (items.isEmpty) {
            return ListView(
              children: [SizedBox(height: 400, child: EmptyStateView(message: l10n.profileNoPendingRequests, icon: Icons.rule_folder_outlined))],
            );
          }

          return ListView.separated(
            itemCount: items.length,
            separatorBuilder: (context, index) => const Divider(height: 1),
            itemBuilder: (context, index) => _ChangeRequestTile(request: items[index]),
          );
        },
        error: (error, stackTrace) => Center(child: Text(l10n.commonErrorGeneric)),
        loading: () => const Center(child: CircularProgressIndicator()),
      ),
    );
  }
}

class _ChangeRequestTile extends StatelessWidget {
  const _ChangeRequestTile({required this.request});

  final EmployeeChangeRequest request;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    return ListTile(
      title: Text(request.changes.keys.join(', ')),
      subtitle: request.reviewComment != null ? Text(request.reviewComment!) : null,
      trailing: Chip(label: Text(_statusLabel(l10n, request.status))),
    );
  }

  String _statusLabel(AppLocalizations l10n, String status) {
    return switch (status) {
      'approved' => l10n.statusApproved,
      'rejected' => l10n.statusRejected,
      _ => l10n.statusPending,
    };
  }
}
