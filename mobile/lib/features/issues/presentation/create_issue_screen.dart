import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/widgets/responsive_body.dart';
import '../../../l10n/app_localizations.dart';
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

  Future<void> _save() async {
    final l10n = AppLocalizations.of(context);

    if (_titleController.text.trim().isEmpty || _pickedLocation == null) {
      setState(() => _locationError = _pickedLocation == null ? l10n.issuesMapPickHint : null);
      return;
    }

    setState(() => _saving = true);

    try {
      await ref.read(issuesRepositoryProvider).create(
            title: _titleController.text.trim(),
            description: _descriptionController.text.trim().isEmpty ? null : _descriptionController.text.trim(),
            objectName: _objectNameController.text.trim().isEmpty ? null : _objectNameController.text.trim(),
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

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.issuesReportIssue)),
      body: ResponsiveBody(
        child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
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
