import 'package:dio/dio.dart';

/// A friendly, already-unwrapped error for the UI layer. Every backend
/// error response follows the `{success:false, message, errors?}` envelope
/// (see `backend/app/Http/Concerns/ApiResponses.php`) — this pulls
/// `message` out once so screens never touch raw Dio/HTTP details.
class ApiException implements Exception {
  ApiException(this.message, {this.statusCode, this.fieldErrors});

  final String message;
  final int? statusCode;
  final Map<String, dynamic>? fieldErrors;

  factory ApiException.fromDioException(DioException error) {
    final response = error.response;
    final data = response?.data;

    if (data is Map<String, dynamic>) {
      final message = data['message'] as String?;
      final errors = data['errors'] as Map<String, dynamic>?;

      if (message != null) {
        return ApiException(message, statusCode: response?.statusCode, fieldErrors: errors);
      }
    }

    if (error.type == DioExceptionType.connectionTimeout ||
        error.type == DioExceptionType.receiveTimeout ||
        error.type == DioExceptionType.connectionError) {
      return ApiException('Serverga ulanib bo\'lmadi. Server manzilini tekshiring.');
    }

    return ApiException('Xatolik yuz berdi. Qayta urinib ko\'ring.', statusCode: response?.statusCode);
  }

  /// The first validation message for [field], if the backend returned one.
  String? fieldError(String field) {
    final value = fieldErrors?[field];

    if (value is List && value.isNotEmpty) {
      return value.first.toString();
    }

    return null;
  }
}
