class DocumentType {
  DocumentType({required this.id, required this.name, required this.requiresExpiry});

  final int id;
  final String name;
  final bool requiresExpiry;

  factory DocumentType.fromJson(Map<String, dynamic> json) => DocumentType(
        id: json['id'] as int,
        name: json['name'] as String? ?? '',
        requiresExpiry: json['requires_expiry'] as bool? ?? false,
      );
}

class EmployeeDocument {
  EmployeeDocument({
    required this.id,
    this.documentTypeName,
    required this.title,
    this.documentNumber,
    this.issueDate,
    this.expiryDate,
    required this.status,
    this.rejectionReason,
    required this.downloadUrl,
  });

  final int id;
  final String? documentTypeName;
  final String title;
  final String? documentNumber;
  final String? issueDate;
  final String? expiryDate;
  final String status;
  final String? rejectionReason;
  final String downloadUrl;

  factory EmployeeDocument.fromJson(Map<String, dynamic> json) => EmployeeDocument(
        id: json['id'] as int,
        documentTypeName: (json['document_type'] as Map<String, dynamic>?)?['name'] as String?,
        title: json['title'] as String? ?? '',
        documentNumber: json['document_number'] as String?,
        issueDate: json['issue_date'] as String?,
        expiryDate: json['expiry_date'] as String?,
        status: json['status'] as String? ?? 'pending',
        rejectionReason: json['rejection_reason'] as String?,
        downloadUrl: json['download_url'] as String? ?? '',
      );
}
