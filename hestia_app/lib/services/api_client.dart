import 'dart:convert';

import 'package:http/http.dart' as http;

import '../core/app_config.dart';

class ApiClient {
  const ApiClient();

  Uri uri(String path, [Map<String, String>? queryParameters]) {
    return Uri.parse(
      '${AppConfig.apiBaseUrl}$path',
    ).replace(queryParameters: queryParameters);
  }

  Future<http.Response> get(
    String path, [
    Map<String, String>? queryParameters,
  ]) {
    return http.get(uri(path, queryParameters));
  }

  Future<http.Response> postJson(String path, Map<String, dynamic> body) {
    return http.post(
      uri(path),
      headers: {'Content-Type': 'application/json'},
      body: json.encode(body),
    );
  }

  Future<http.Response> putJson(String path, Map<String, dynamic> body) {
    return http.put(
      uri(path),
      headers: {'Content-Type': 'application/json'},
      body: json.encode(body),
    );
  }

  Future<http.Response> updateReservation(int id, Map<String, dynamic> body) {
    return putJson('/api/reservations/$id', body);
  }

  Future<http.Response> delete(String path) {
    return http.delete(uri(path));
  }
}
