class KpiPeriod {
  KpiPeriod({required this.id, required this.name, this.startDate, this.endDate});

  final int id;
  final String name;
  final String? startDate;
  final String? endDate;

  factory KpiPeriod.fromJson(Map<String, dynamic> json) => KpiPeriod(
        id: json['id'] as int,
        name: json['name'] as String? ?? '',
        startDate: json['start_date'] as String?,
        endDate: json['end_date'] as String?,
      );
}

class KpiIndicator {
  KpiIndicator({required this.id, required this.name, this.measurementUnit});

  final int id;
  final String name;
  final String? measurementUnit;

  factory KpiIndicator.fromJson(Map<String, dynamic> json) => KpiIndicator(
        id: json['id'] as int,
        name: json['name'] as String? ?? '',
        measurementUnit: json['measurement_unit'] as String?,
      );
}

class EmployeeKpi {
  EmployeeKpi({
    required this.id,
    this.period,
    this.indicator,
    required this.targetValue,
    this.actualValue,
    this.score,
    required this.weight,
  });

  final int id;
  final KpiPeriod? period;
  final KpiIndicator? indicator;
  final double targetValue;
  final double? actualValue;
  final double? score;
  final int weight;

  factory EmployeeKpi.fromJson(Map<String, dynamic> json) => EmployeeKpi(
        id: json['id'] as int,
        period: json['period'] is Map<String, dynamic> ? KpiPeriod.fromJson(json['period'] as Map<String, dynamic>) : null,
        indicator: json['indicator'] is Map<String, dynamic>
            ? KpiIndicator.fromJson(json['indicator'] as Map<String, dynamic>)
            : null,
        targetValue: (json['target_value'] as num?)?.toDouble() ?? 0,
        actualValue: (json['actual_value'] as num?)?.toDouble(),
        score: (json['score'] as num?)?.toDouble(),
        weight: json['weight'] as int? ?? 0,
      );
}
