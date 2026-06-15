import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

import '../models/app_user.dart';

class SessionService {
  static const String _sessionKey = 'user_session';

  Future<AppUser?> loadUser() async {
    final prefs = await SharedPreferences.getInstance();
    final userDataString = prefs.getString(_sessionKey);

    if (userDataString == null) return null;

    try {
      return AppUser.fromJson(json.decode(userDataString));
    } catch (_) {
      await prefs.remove(_sessionKey);
      return null;
    }
  }

  Future<void> saveUser(AppUser user) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_sessionKey, json.encode(user.toJson()));
  }

  Future<void> clear() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_sessionKey);
  }
}
