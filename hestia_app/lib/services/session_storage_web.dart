import 'dart:convert';
import 'dart:html' as html;

import '../models/app_user.dart';

String? readSessionValue(String key) => html.window.sessionStorage[key];

void writeSessionValue(String key, String value) {
  html.window.sessionStorage[key] = value;
}

void clearSessionValue(String key) {
  html.window.sessionStorage.remove(key);
}

String appUserToJson(AppUser user) => json.encode(user.toJson());

AppUser? appUserFromJson(String raw) {
  try {
    final decoded = json.decode(raw);
    if (decoded is Map<String, dynamic>) {
      return AppUser.fromJson(decoded);
    }
    if (decoded is Map) {
      return AppUser.fromJson(decoded.cast<String, dynamic>());
    }
    return null;
  } catch (_) {
    return null;
  }
}
