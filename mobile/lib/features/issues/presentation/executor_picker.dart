import 'package:flutter/material.dart';

import '../../../l10n/app_localizations.dart';
import '../domain/issue.dart';

/// A searchable checklist in a bottom sheet — a plain dropdown can only hold
/// one value, and an organization can have hundreds of employees. Returns
/// the chosen ids on confirm, or null when the sheet is dismissed.
Future<Set<int>?> showExecutorPicker(
  BuildContext context, {
  required List<IssueExecutorCandidate> candidates,
  required Set<int> initial,
}) {
  final l10n = AppLocalizations.of(context);
  final selected = {...initial};

  return showModalBottomSheet<Set<int>>(
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
                      child: FilledButton(
                        onPressed: () => Navigator.pop(sheetContext, selected),
                        child: Text(l10n.commonConfirm),
                      ),
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
}
