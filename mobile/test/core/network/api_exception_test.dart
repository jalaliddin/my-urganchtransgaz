import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:urtg_mobile/core/network/api_exception.dart';

void main() {
  RequestOptions options() => RequestOptions(path: '/test');

  test('extracts the message from the standard {success:false, message} envelope', () {
    final error = DioException(
      requestOptions: options(),
      type: DioExceptionType.badResponse,
      response: Response(
        requestOptions: options(),
        statusCode: 401,
        data: {'success': false, 'message': 'Login yoki parol noto\'g\'ri.'},
      ),
    );

    final exception = ApiException.fromDioException(error);

    expect(exception.message, 'Login yoki parol noto\'g\'ri.');
    expect(exception.statusCode, 401);
  });

  test('exposes the first validation message for a field via fieldError()', () {
    final error = DioException(
      requestOptions: options(),
      type: DioExceptionType.badResponse,
      response: Response(
        requestOptions: options(),
        statusCode: 422,
        data: {
          'success': false,
          'message': 'Validation error',
          'errors': {
            'email': ['The email field is required.'],
          },
        },
      ),
    );

    final exception = ApiException.fromDioException(error);

    expect(exception.fieldError('email'), 'The email field is required.');
    expect(exception.fieldError('missing'), isNull);
  });

  test('falls back to a connection-error message when there is no response body', () {
    final error = DioException(requestOptions: options(), type: DioExceptionType.connectionError);

    final exception = ApiException.fromDioException(error);

    expect(exception.message, contains('Serverga ulanib bo\'lmadi'));
  });
}
