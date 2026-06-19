import '../models/app_user.dart';
import 'session_storage_stub.dart'
    if (dart.library.html) 'session_storage_web.dart';

class SessionService {
  static const String _sessionKey = 'user_session';

  Future<AppUser?> loadUser() async {
    final raw = readSessionValue(_sessionKey);
    if (raw == null || raw.isEmpty) return null;

    final user = appUserFromJson(raw);
    if (user == null) {
      clearSessionValue(_sessionKey);
    }
    return user;
  }

  Future<void> saveUser(AppUser user) async {
    writeSessionValue(_sessionKey, appUserToJson(user));
  }

  Future<void> clear() async {
    clearSessionValue(_sessionKey);
  }
}
