import 'package:flutter_test/flutter_test.dart';
import 'package:urtg_mobile/core/network/paginated.dart';

void main() {
  test('Paginated.fromJson reads the {data, meta} envelope every index() endpoint returns', () {
    final mapped = Paginated<String>.fromJson(
      {
        'data': [
          {'name': 'a'},
          {'name': 'b'},
        ],
        'meta': {'current_page': 2, 'per_page': 2, 'total': 5, 'last_page': 3},
      },
      (json) => json['name'] as String,
    );

    expect(mapped.items, ['a', 'b']);
    expect(mapped.currentPage, 2);
    expect(mapped.lastPage, 3);
    expect(mapped.total, 5);
    expect(mapped.hasMore, isTrue);
  });

  test('hasMore is false on the last page', () {
    final page = Paginated<String>.fromJson(
      {
        'data': [
          {'name': 'a'},
        ],
        'meta': {'current_page': 3, 'per_page': 2, 'total': 5, 'last_page': 3},
      },
      (json) => json['name'] as String,
    );

    expect(page.hasMore, isFalse);
  });
}
