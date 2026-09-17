class Organization {
  Organization({required this.id, required this.name, this.shortName, this.code});

  final int id;
  final String name;
  final String? shortName;
  final String? code;

  factory Organization.fromJson(Map<String, dynamic> json) => Organization(
        id: json['id'] as int,
        name: json['name'] as String? ?? '',
        shortName: json['short_name'] as String?,
        code: json['code'] as String?,
      );
}
