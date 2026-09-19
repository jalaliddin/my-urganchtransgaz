import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/widgets/responsive_body.dart';
import '../../../l10n/app_localizations.dart';
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
  final Set<int> _executorIds = {};
  bool _submitted = false;
  bool _suggestedExecutorApplied = false;
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

    setState(() => _submitted = true);

    if (options == null ||
        _titleController.text.trim().isEmpty ||
        organizationId == null ||
        _categoryId == null ||
        _executorIds.isEmpty ||
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
            executorIds: _executorIds.toList(),
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
              .map((organization) => DropdownMenuItem(
                    value: organization.id,
                    child: Text(organization.name, overflow: TextOverflow.ellipsis),
                  ))
              .toList(),
          // A single organization (everyone who can only file against their
          // own) is preselected and not worth a control that can't change.
          onChanged: options.organizations.length == 1
              ? null
              : (value) => setState(() {
                    _organizationId = value;
                    _executorIds.clear();
                    _suggestedExecutorApplied = false;
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
        _buildExecutorSelector(options, organizationId, l10n, _executorIds.isEmpty ? requiredText : null),
        const SizedBox(height: 12),
      ],
    );
  }

  Widget _buildExecutorSelector(IssueOptions options, int? organizationId, AppLocalizations l10n, String? errorText) {
    if (organizationId == null) {
      return InputDecorator(
        decoration: InputDecoration(labelText: l10n.issuesExecutors, helperText: l10n.issuesPickOrganizationFirst),
        child: const SizedBox(height: 20),
      );
    }

    return ref.watch(executorCandidatesProvider(organizationId)).when(
          data: (candidates) {
            // A department-manager starts with themselves selected (they
            // normally handle what they report) — once, and only if they
            // belong to the organization being filed against.
            if (!_suggestedExecutorApplied && options.defaultExecutorId != null) {
              _suggestedExecutorApplied = true;
              if (candidates.any((candidate) => candidate.id == options.defaultExecutorId)) {
                WidgetsBinding.instance.addPostFrameCallback((_) {
                  if (mounted) setState(() => _executorIds.add(options.defaultExecutorId!));
                });
              }
            }

            final byId = {for (final candidate in candidates) candidate.id: candidate};

            return InputDecorator(
              decoration: InputDecoration(
                labelText: l10n.issuesExecutors,
                errorText: errorText,
                helperText: candidates.isEmpty ? l10n.issuesNoCandidates : null,
              ),
              child: Wrap(
                spacing: 8,
                runSpacing: 4,
                crossAxisAlignment: WrapCrossAlignment.center,
                children: [
                  for (final id in _executorIds)
                    if (byId[id] != null)
                      InputChip(
                        label: Text(byId[id]!.fullName),
                        onDeleted: () => setState(() => _executorIds.remove(id)),
                      ),
                  ActionChip(
                    avatar: const Icon(Icons.person_add_alt_1_outlined, size: 18),
                    label: Text(l10n.issuesSelectExecutors),
                    onPressed: candidates.isEmpty ? null : () => _pickExecutors(candidates, l10n),
                  ),
                ],
              ),
            );
          },
          loading: () => const LinearProgressIndicator(),
          error: (error, stackTrace) => Text(l10n.commonErrorGeneric),
        );
  }

  /// A searchable checklist in a bottom sheet — a plain dropdown can only
  /// hold one value, and an organization can have hundreds of employees.
  Future<void> _pickExecutors(List<IssueExecutorCandidate> candidates, AppLocalizations l10n) async {
    final selected = {..._executorIds};

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (sheetContext) {
        var query = '';

        return StatefulBuilder(
          builder: (context, setSheetState) {
            final visible = candidates
                .where((candidate) => candidate.fullName.toLowerCase().contains(query.toLowerCase()))
                .toList();

            return SizedBox(
              height: MediaQuery.sizeOf(context).height * 0.75,
              child: Column(
                children: [
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
                    child: TextField(
                      decoration: InputDecoration(prefixIcon: const Icon(Icons.search), hintText: l10n.issuesSearchExecutors),
                      onChanged: (value) => setSheetState(() => query = value),
                    ),
                  ),
                  Expanded(
                    child: ListView.builder(
                      itemCount: visible.length,
                      itemBuilder: (context, index) {
                        final candidate = visible[index];

                        return CheckboxListTile(
                          value: selected.contains(candidate.id),
                          title: Text(candidate.fullName),
                          subtitle: candidate.subtitle.isEmpty ? null : Text(candidate.subtitle),
                          onChanged: (checked) => setSheetState(() {
                            checked == true ? selected.add(candidate.id) : selected.remove(candidate.id);
                          }),
                        );
                      },
                    ),
                  ),
                  SafeArea(
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: SizedBox(
                        width: double.infinity,
                        child: FilledButton(onPressed: () => Navigator.pop(sheetContext), child: Text(l10n.commonConfirm)),
                      ),
                    ),
                  ),
                ],
              ),
            );
          },
        );
      },
    );

    setState(() {
      _executorIds
        ..clear()
        ..addAll(selected);
    });
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
