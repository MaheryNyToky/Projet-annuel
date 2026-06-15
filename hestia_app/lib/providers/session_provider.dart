import 'package:flutter/foundation.dart';

import '../models/app_user.dart';
import '../services/session_service.dart';

class SessionProvider extends ChangeNotifier {
  SessionProvider(this._sessionService);

  final SessionService _sessionService;
  AppUser? _user;

  AppUser? get user => _user;
  bool get isAuthenticated => _user != null;

  Future<void> load() async {
    _user = await _sessionService.loadUser();
    notifyListeners();
  }

  Future<void> signIn(AppUser user) async {
    _user = user;
    await _sessionService.saveUser(user);
    notifyListeners();
  }

  Future<void> signOut() async {
    _user = null;
    await _sessionService.clear();
    notifyListeners();
  }
}
