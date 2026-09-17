class Position {
  Position({required this.id, required this.title});

  final int id;
  final String title;

  factory Position.fromJson(Map<String, dynamic> json) => Position(
        id: json['id'] as int,
        title: json['title'] as String? ?? '',
      );
}
