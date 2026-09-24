class ProfileCompletion {
  ProfileCompletion({required this.percentage, required this.missingSections});

  final int percentage;
  final List<String> missingSections;

  factory ProfileCompletion.fromJson(Map<String, dynamic> json) => ProfileCompletion(
        percentage: json['percentage'] as int,
        missingSections: (json['missing_sections'] as List<dynamic>).map((e) => e.toString()).toList(),
      );
}
