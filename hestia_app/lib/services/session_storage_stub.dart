import 'dart:convert';

import '../models/app_user.dart';

final Map<String, String> _sessionMemory = {};

String? readSessionValue(String key) => _sessionMemory[key];

void writeSessionValue(String key, String value) {
  _sessionMemory[key] = value;
}

void clearSessionValue(String key) {
  _sessionMemory.remove(key);
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
