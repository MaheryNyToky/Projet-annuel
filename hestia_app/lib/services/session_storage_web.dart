import 'dart:convert';

import '../models/app_user.dart';
import 'package:web/web.dart' as web;

String? readSessionValue(String key) => web.window.sessionStorage.getItem(key);

void writeSessionValue(String key, String value) {
  web.window.sessionStorage.setItem(key, value);
}

void clearSessionValue(String key) {
  web.window.sessionStorage.removeItem(key);
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
