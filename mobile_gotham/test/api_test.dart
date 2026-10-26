import 'dart:convert';
import 'dart:io';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile_gotham/api.dart';

void main() {
  late HttpServer server;
  late GothamApi api;
  setUp(() async {
    server = await HttpServer.bind(InternetAddress.loopbackIPv4, 0);
    api = GothamApi(baseUrl: 'http://127.0.0.1:${server.port}/api/v1');
  });
  tearDown(() async {
    api.close();
    await server.close(force: true);
  });
  test('Sends bearer token and JSON and reads all collection pages', () async {
    api.token = 'secret-token';
    final requests = <HttpRequest>[];
    server.listen((request) async {
      requests.add(request);
      expect(request.headers.value('Authorization'), 'Bearer secret-token');
      request.response.headers.contentType = ContentType.json;
      if (request.method == 'POST') {
        expect(jsonDecode(await utf8.decoder.bind(request).join()), {
          'items': [
            {'menu_item_id': 7, 'quantity': 2},
          ],
        });
        request.response.statusCode = 201;
        request.response.write(
          jsonEncode({
            'data': {'id': 9},
          }),
        );
      } else {
        final page = int.parse(request.uri.queryParameters['page']!);
        request.response.write(
          jsonEncode({
            'data': [
              {'id': page},
            ],
            'meta': {'last_page': 2},
          }),
        );
      }
      await request.response.close();
    });
    final response = await api.request(
      'POST',
      '/orders',
      body: {
        'items': [
          {'menu_item_id': 7, 'quantity': 2},
        ],
      },
    );
    expect(response['data']['id'], 9);
    expect(await api.collection('/orders?status=completed'), [
      {'id': 1},
      {'id': 2},
    ]);
    expect(requests.length, 3);
    expect(requests.last.uri.queryParameters['status'], 'completed');
  });
  test('Preserves server validation message and unauthorized status', () async {
    server.listen((request) async {
      request.response.statusCode = 401;
      request.response.write(
        jsonEncode({
          'errors': {
            'email': ['Akun tidak aktif.'],
          },
        }),
      );
      await request.response.close();
    });
    await expectLater(
      api.request('GET', '/auth/me'),
      throwsA(
        isA<ApiException>()
            .having((e) => e.status, 'status', 401)
            .having((e) => e.message, 'message', 'Akun tidak aktif.'),
      ),
    );
  });
  test('Reports non JSON errors without exposing HTML', () async {
    server.listen((request) async {
      request.response.statusCode = 500;
      request.response.write('<html>Server error</html>');
      await request.response.close();
    });
    await expectLater(
      api.request('GET', '/orders'),
      throwsA(isA<ApiException>().having((e) => e.status, 'status', 500)),
    );
  });
  test('Resolves Laravel menu image paths against server origin', () {
    expect(
      api.imageUrl('https://example.com/menu.png'),
      'https://example.com/menu.png',
    );
    expect(
      api.imageUrl('uploads/menu/test.png'),
      'http://127.0.0.1:${server.port}/uploads/menu/test.png',
    );
    expect(
      api.imageUrl('/uploads/menu/test.png'),
      'http://127.0.0.1:${server.port}/uploads/menu/test.png',
    );
  });
}
