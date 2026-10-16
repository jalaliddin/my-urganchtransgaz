import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/models/employee.dart';
import '../../../core/network/api_providers.dart';
import '../../../core/formatting/dates.dart';
import '../../../core/theme/status_colors.dart';
import '../../../core/widgets/async_value_view.dart';
import '../../../core/widgets/detail_rows.dart';
import '../../../core/widgets/responsive_body.dart';
import '../../../core/widgets/section.dart';
import '../../../core/widgets/status_pill.dart';
import '../../../core/network/api_exception.dart';
import '../../../l10n/app_localizations.dart';
import '../domain/change_request.dart';
import 'profile_contacts_tab.dart';
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
    _tabController = TabController(length: 3, vsync: this);
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
          tabs: [Tab(text: l10n.profileTabInfo), Tab(text: l10n.profileTabContacts), Tab(text: l10n.profileTabRequests)],
        ),
      ),
      body: ResponsiveBody(
        child: TabBarView(
          controller: _tabController,
          children: const [_ProfileInfoTab(), ProfileContactsTab(), _ProfileRequestsTab()],
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
    final messenger = ScaffoldMessenger.of(context);
    final l10n = AppLocalizations.of(context);
    // A camera original is 3–8 MB; the server keeps only a 512 px square,
    // so a much smaller copy uploads faster and always fits its limit.
    final picked = await ImagePicker().pickImage(
      source: ImageSource.gallery,
      maxWidth: 1600,
      maxHeight: 1600,
      imageQuality: 85,
    );

    if (picked == null) return;

    try {
      await ref.read(profileControllerProvider.notifier).uploadPhoto(picked.path, picked.name);
      messenger.showSnackBar(SnackBar(content: Text(l10n.commonFileUploaded)));
    } catch (e) {
      messenger.showSnackBar(SnackBar(content: Text(e is ApiException ? e.userMessage : l10n.commonUploadFailed)));
    }
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

          final scheme = Theme.of(context).colorScheme;
          final text = Theme.of(context).textTheme;
          final status = StatusColors.of(context);
          final hireDate = AppDates.parse(employee.hireDate);
          final hasPhoto = employee.photoUrl != null && authHeaders != null;

          return ListView(
            padding: const EdgeInsets.fromLTRB(16, 20, 16, 32),
            children: [
              Center(
                child: Stack(
                  clipBehavior: Clip.none,
                  children: [
                    CircleAvatar(
                      radius: 48,
                      backgroundColor: scheme.primaryContainer,
                      backgroundImage: hasPhoto ? NetworkImage(employee.photoUrl!, headers: authHeaders) : null,
                      child: hasPhoto ? null : Icon(Icons.person, size: 48, color: scheme.onPrimaryContainer),
                    ),
                    Positioned(
                      right: -4,
                      bottom: -4,
                      child: IconButton.filled(
                        tooltip: l10n.profileUploadPhoto,
                        onPressed: _pickPhoto,
                        icon: const Icon(Icons.photo_camera_outlined, size: 20),
                        style: IconButton.styleFrom(
                          minimumSize: const Size(40, 40),
                          side: BorderSide(color: scheme.surface, width: 3),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              Text(employee.fullName, textAlign: TextAlign.center, style: text.titleLarge),
              const SizedBox(height: 2),
              Text(
                employee.position?.title ?? employee.employeeNumber,
                textAlign: TextAlign.center,
                style: text.bodyMedium!.copyWith(color: scheme.onSurfaceVariant),
              ),
              SectionHeader(title: l10n.profileContactInfo),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(color: status.bg(StatusTone.info), borderRadius: BorderRadius.circular(14)),
                child: Row(
                  children: [
                    Icon(Icons.info_outline, size: 20, color: status.fg(StatusTone.info)),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(l10n.profileSensitiveNotice, style: text.bodySmall!.copyWith(color: status.fg(StatusTone.info))),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 12),
              Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    TextFormField(
                      controller: _phoneController,
                      decoration: InputDecoration(labelText: l10n.profilePhone, prefixIcon: const Icon(Icons.phone_outlined)),
                      keyboardType: TextInputType.phone,
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _emailController,
                      decoration: InputDecoration(labelText: l10n.profileEmail, prefixIcon: const Icon(Icons.mail_outline)),
                      keyboardType: TextInputType.emailAddress,
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _addressController,
                      decoration: InputDecoration(labelText: l10n.profileAddress, prefixIcon: const Icon(Icons.home_outlined)),
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
              SectionHeader(title: l10n.profilePersonalInfo),
              GroupCard(
                dividerIndent: 54,
                children: [
                  InfoRow(icon: Icons.badge_outlined, label: l10n.profileEmployeeNumber, value: employee.employeeNumber),
                  InfoRow(icon: Icons.apartment_outlined, label: l10n.profileOrganization, value: employee.organization?.name ?? '—'),
                  InfoRow(icon: Icons.account_tree_outlined, label: l10n.profileDepartment, value: employee.department?.name ?? '—'),
                  InfoRow(
                    icon: Icons.event_available_outlined,
                    label: l10n.profileHireDate,
                    value: hireDate == null ? '—' : AppDates.long(context, hireDate),
                  ),
                ],
              ),
            ],
          );
        },
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
            padding: const EdgeInsets.all(16),
            itemCount: items.length,
            separatorBuilder: (context, index) => const SizedBox(height: 10),
            itemBuilder: (context, index) => _ChangeRequestTile(request: items[index]),
          );
        },
        error: (error, stackTrace) => Center(child: Text(l10n.commonErrorGeneric)),
        loading: () => const SkeletonList(),
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

    return Card(
      child: ListTile(
        title: Text(request.changes.keys.join(', ')),
        subtitle: request.reviewComment != null ? Text(request.reviewComment!) : null,
        trailing: StatusPill(
          label: _statusLabel(l10n, request.status),
          tone: switch (request.status) {
            'approved' => StatusTone.success,
            'rejected' => StatusTone.danger,
            _ => StatusTone.warning,
          },
        ),
      ),
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
