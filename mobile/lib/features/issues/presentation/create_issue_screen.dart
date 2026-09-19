import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/widgets/responsive_body.dart';
import '../../../l10n/app_localizations.dart';
import '../../auth/presentation/auth_controller.dart';
import '../domain/issue.dart';
import 'issue_map.dart';
import 'issues_controller.dart';

class CreateIssueScreen extends ConsumerStatefulWidget {
  const CreateIssueScreen({super.key});

  @override
  ConsumerState<CreateIssueScreen> createState() => _CreateIssueScreenState();
}

class _CreateIssueScreenState extends ConsumerState<CreateIssueScreen> {
  final _titleController = TextEditingController();
  final _descriptionController = TextEditingController();
  final _objectNameController = TextEditingController();
  LatLng? _pickedLocation;
  int? _organizationId;
  int? _categoryId;
  int? _responsibleId;
  bool _submitted = false;
  bool _saving = false;
  bool _locating = false;
  String? _locationError;

  @override
  void dispose() {
    _titleController.dispose();
    _descriptionController.dispose();
    _objectNameController.dispose();
    super.dispose();
  }

  Future<void> _useMyLocation() async {
    setState(() {
      _locating = true;
      _locationError = null;
    });

    try {
      if (!await Geolocator.isLocationServiceEnabled()) {
        throw Exception('location services disabled');
      }

      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }
      if (permission == LocationPermission.denied || permission == LocationPermission.deniedForever) {
        throw Exception('location permission denied');
      }

      final position = await Geolocator.getCurrentPosition();
      setState(() => _pickedLocation = LatLng(position.latitude, position.longitude));
    } catch (_) {
      setState(() => _locationError = AppLocalizations.of(context).issuesLocationError);
    } finally {
      setState(() => _locating = false);
    }
  }

  /// A department-manager has exactly one organization to file against, so
  /// it's used without making them pick it.
  int? _effectiveOrganizationId(IssueOptions options) =>
      _organizationId ?? (options.organizations.length == 1 ? options.organizations.first.id : null);

  Future<void> _save() async {
    final l10n = AppLocalizations.of(context);
    final options = ref.read(issueOptionsProvider).value;
    final organizationId = options == null ? null : _effectiveOrganizationId(options);
    final missingResponsible = (options?.mustChooseResponsible ?? false) && _responsibleId == null;

    setState(() => _submitted = true);

    if (options == null ||
        _titleController.text.trim().isEmpty ||
        organizationId == null ||
        _categoryId == null ||
        missingResponsible ||
        _pickedLocation == null) {
      setState(() => _locationError = _pickedLocation == null ? l10n.issuesMapPickHint : null);
      return;
    }

    setState(() => _saving = true);

    try {
      await ref.read(issuesRepositoryProvider).create(
            title: _titleController.text.trim(),
            description: _descriptionController.text.trim().isEmpty ? null : _descriptionController.text.trim(),
            objectName: _objectNameController.text.trim().isEmpty ? null : _objectNameController.text.trim(),
            organizationId: organizationId,
            categoryId: _categoryId!,
            responsibleEmployeeId: options.mustChooseResponsible ? _responsibleId : null,
            latitude: _pickedLocation!.latitude,
            longitude: _pickedLocation!.longitude,
          );
      ref.invalidate(issuesControllerProvider);

      if (mounted) {
        Navigator.of(context).pop();
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    } finally {
      if (mounted) {
        setState(() => _saving = false);
      }
    }
  }

  Widget _buildSelectors(IssueOptions options, AppLocalizations l10n) {
    final organizationId = _effectiveOrganizationId(options);
    final requiredText = _submitted ? l10n.commonRequired : null;

    return Column(
      children: [
        DropdownButtonFormField<int>(
          initialValue: organizationId,
          isExpanded: true,
          decoration: InputDecoration(
            labelText: l10n.issuesOrganization,
            errorText: organizationId == null ? requiredText : null,
          ),
          items: options.organizations
              .map((organization) => DropdownMenuItem(value: organization.id, child: Text(organization.name, overflow: TextOverflow.ellipsis)))
              .toList(),
          // One organization (a department-manager's own) is preselected
          // and not worth a control that can't be changed.
          onChanged: options.organizations.length == 1
              ? null
              : (value) => setState(() {
                    _organizationId = value;
                    _responsibleId = null;
                  }),
        ),
        const SizedBox(height: 12),
        DropdownButtonFormField<int>(
          initialValue: _categoryId,
          isExpanded: true,
          decoration: InputDecoration(labelText: l10n.issuesCategory, errorText: _categoryId == null ? requiredText : null),
          items: options.categories
              .map((category) => DropdownMenuItem(value: category.id, child: Text(category.name, overflow: TextOverflow.ellipsis)))
              .toList(),
          onChanged: (value) => setState(() => _categoryId = value),
        ),
        const SizedBox(height: 12),
        if (options.mustChooseResponsible)
          _buildResponsibleSelector(organizationId, l10n, requiredText)
        else
          TextFormField(
            readOnly: true,
            initialValue: ref.watch(authControllerProvider).value?.name ?? '',
            decoration: InputDecoration(labelText: l10n.issuesResponsible, helperText: l10n.issuesResponsibleSelf),
          ),
        const SizedBox(height: 12),
      ],
    );
  }

  Widget _buildResponsibleSelector(int? organizationId, AppLocalizations l10n, String? requiredText) {
    if (organizationId == null) {
      return InputDecorator(
        decoration: InputDecoration(labelText: l10n.issuesResponsible, helperText: l10n.issuesPickOrganizationFirst),
        child: const SizedBox(height: 20),
      );
    }

    return ref.watch(responsibleCandidatesProvider(organizationId)).when(
          data: (candidates) => DropdownButtonFormField<int>(
            key: ValueKey('responsible-$organizationId'),
            initialValue: _responsibleId,
            isExpanded: true,
            decoration: InputDecoration(
              labelText: l10n.issuesResponsible,
              helperText: candidates.isEmpty ? l10n.issuesNoCandidates : null,
              errorText: _responsibleId == null ? requiredText : null,
            ),
            items: candidates
                .map((candidate) => DropdownMenuItem(
                      value: candidate.id,
                      child: Text(
                        candidate.subtitle.isEmpty ? candidate.fullName : '${candidate.fullName} · ${candidate.subtitle}',
                        overflow: TextOverflow.ellipsis,
                      ),
                    ))
                .toList(),
            onChanged: (value) => setState(() => _responsibleId = value),
          ),
          loading: () => const LinearProgressIndicator(),
          error: (error, stackTrace) => Text(l10n.commonErrorGeneric),
        );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final options = ref.watch(issueOptionsProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.issuesReportIssue)),
      body: ResponsiveBody(
        child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          options.when(
            data: (data) => _buildSelectors(data, l10n),
            loading: () => const Padding(padding: EdgeInsets.only(bottom: 12), child: LinearProgressIndicator()),
            error: (error, stackTrace) => Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Text(l10n.commonErrorGeneric, style: TextStyle(color: Theme.of(context).colorScheme.error)),
            ),
          ),
          TextField(controller: _titleController, decoration: InputDecoration(labelText: l10n.issuesIssueTitle)),
          const SizedBox(height: 12),
          TextField(
            controller: _descriptionController,
            decoration: InputDecoration(labelText: l10n.issuesDescription),
            maxLines: 2,
          ),
          const SizedBox(height: 12),
          TextField(controller: _objectNameController, decoration: InputDecoration(labelText: l10n.issuesObjectName)),
          const SizedBox(height: 16),
          Text(l10n.issuesMapPickHint, style: Theme.of(context).textTheme.bodySmall),
          const SizedBox(height: 8),
          IssueMap(pickable: true, pickedLocation: _pickedLocation, onPick: (point) => setState(() => _pickedLocation = point), height: 280),
          const SizedBox(height: 8),
          OutlinedButton.icon(
            icon: _locating
                ? const SizedBox(height: 16, width: 16, child: CircularProgressIndicator(strokeWidth: 2))
                : const Icon(Icons.my_location),
            label: Text(l10n.issuesUseMyLocation),
            onPressed: _locating ? null : _useMyLocation,
          ),
          if (_locationError != null) ...[
            const SizedBox(height: 8),
            Text(_locationError!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
          ],
          const SizedBox(height: 20),
          FilledButton(
            onPressed: _saving ? null : _save,
            child: _saving
                ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                : Text(l10n.commonSave),
          ),
        ],
      ),
      ),
    );
  }
}
