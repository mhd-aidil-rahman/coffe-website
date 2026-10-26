import 'dart:async';
import 'dart:convert';
import 'dart:io';

class ApiException implements Exception {
  const ApiException(this.message, [this.status = 0]);
  final String message;
  final int status;
  @override
  String toString() => message;
}

class GothamApi {
  GothamApi({String? baseUrl})
    : baseUrl =
          (baseUrl ??
                  const String.fromEnvironment(
                    'API_BASE_URL',
                    defaultValue: 'http://127.0.0.1:8000/api/v1',
                  ))
              .replaceFirst(RegExp(r'/+$'), '');
  final String baseUrl;
  final HttpClient client = HttpClient()
    ..connectionTimeout = const Duration(seconds: 15);
  String? token;
  void close() => client.close(force: true);
  String imageUrl(String path) {
    final uri = Uri.tryParse(path);
    if (uri != null && (uri.scheme == 'https' || uri.scheme == 'http')) {
      return uri.toString();
    }
    return Uri.parse(
      baseUrl,
    ).resolve(path.startsWith('/') ? path : '/$path').toString();
  }

  Future<Map<String, dynamic>> request(
    String method,
    String path, {
    Map<String, dynamic>? body,
  }) async {
    try {
      return await (() async {
        final request = await client.openUrl(
          method,
          Uri.parse('$baseUrl$path'),
        );
        request.headers.set('Accept', 'application/json');
        if (token != null) {
          request.headers.set('Authorization', 'Bearer $token');
        }
        if (body != null) {
          request.headers.contentType = ContentType.json;
          request.write(jsonEncode(body));
        }
        final response = await request.close();
        final raw = await utf8.decoder.bind(response).join();
        Map<String, dynamic> result;
        try {
          result = raw.isEmpty ? {} : jsonDecode(raw) as Map<String, dynamic>;
        } catch (_) {
          throw ApiException(
            'Respons server tidak valid. Periksa alamat API.',
            response.statusCode,
          );
        }
        if (response.statusCode >= 400) {
          final errors = result['errors'];
          final message = errors is Map
              ? errors.values.expand((e) => e is List ? e : [e]).join('\n')
              : result['message'];
          throw ApiException(
            message?.toString() ?? 'Permintaan gagal (${response.statusCode}).',
            response.statusCode,
          );
        }
        return result;
      })().timeout(const Duration(seconds: 25));
    } on SocketException {
      throw const ApiException(
        'Tidak dapat terhubung ke server. Periksa jaringan dan alamat API.',
      );
    } on TimeoutException {
      throw const ApiException(
        'Server terlalu lama merespons. Silakan coba lagi.',
      );
    } on HandshakeException {
      throw const ApiException('Koneksi aman ke server gagal.');
    }
  }

  Future<List<Map<String, dynamic>>> collection(String path) async {
    final items = <Map<String, dynamic>>[];
    var page = 1;
    while (true) {
      final response = await request(
        'GET',
        '$path${path.contains('?') ? '&' : '?'}page=$page',
      );
      items.addAll((response['data'] as List).cast<Map<String, dynamic>>());
      final meta = response['meta'];
      if (meta is! Map || page >= (meta['last_page'] as int)) {
        break;
      }
      page++;
    }
    return items;
  }
}
