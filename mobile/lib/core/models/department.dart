class Department {
  Department({required this.id, required this.name, this.code});

  final int id;
  final String name;
  final String? code;

  factory Department.fromJson(Map<String, dynamic> json) => Department(
        id: json['id'] as int,
        name: json['name'] as String? ?? '',
        code: json['code'] as String?,
      );
}
