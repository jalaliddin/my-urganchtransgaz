/// An emergency contact or a bank record, both self-managed by the
/// employee (`PUT /profile` with `contacts`).
class EmployeeContact {
  EmployeeContact({
    this.id,
    required this.type,
    required this.fullName,
    this.relationship,
    this.phone,
    this.address,
    this.bankName,
    this.bankAccountNumber,
  });

  /// Null for one not saved yet.
  final int? id;

  /// `emergency` or `bank`.
  final String type;

  /// The contact person, or the bank account holder.
  final String fullName;
  final String? relationship;
  final String? phone;
  final String? address;
  final String? bankName;
  final String? bankAccountNumber;

  bool get isBank => type == 'bank';

  factory EmployeeContact.fromJson(Map<String, dynamic> json) => EmployeeContact(
        id: json['id'] as int?,
        type: json['type'] as String? ?? 'emergency',
        fullName: json['full_name'] as String? ?? '',
        relationship: json['relationship'] as String?,
        phone: json['phone'] as String?,
        address: json['address'] as String?,
        bankName: json['bank_name'] as String?,
        bankAccountNumber: json['bank_account_number'] as String?,
      );

  /// The server replaces the whole list on save, so every kept contact is
  /// sent back with its id.
  Map<String, dynamic> toJson() => {
        'id': ?id,
        'type': type,
        'full_name': fullName,
        'relationship': relationship,
        'phone': phone,
        'address': address,
        'bank_name': bankName,
        'bank_account_number': bankAccountNumber,
      };
}
