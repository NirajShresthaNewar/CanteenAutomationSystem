import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import '../config/api_config.dart';
import '../models/user.dart';

class AuthService {
  final storage = const FlutterSecureStorage();
  User? _currentUser;

  User? get currentUser => _currentUser;

  Future<User> login(String email, String password) async {
    try {
      final response = await http.post(
        Uri.parse(ApiConfig.baseUrl + ApiConfig.login),
        headers: ApiConfig.headers(null),
        body: jsonEncode({
          'email': email,
          'password': password,
        }),
      );

      final data = jsonDecode(response.body);

      if (response.statusCode == 200 && data['status'] == 'success') {
        final user = User.fromJson(data['data']);
        _currentUser = user;
        
        // Save user data and token
        await storage.write(key: 'user', value: jsonEncode(user.toJson()));
        await storage.write(key: 'token', value: user.token);
        
        return user;
      } else {
        throw data['message'] ?? 'Login failed';
      }
    } catch (e) {
      throw 'Login failed: $e';
    }
  }

  Future<void> logout() async {
    await storage.deleteAll();
    _currentUser = null;
  }

  Future<User?> checkAuth() async {
    try {
      final userStr = await storage.read(key: 'user');
      if (userStr != null) {
        _currentUser = User.fromJson(jsonDecode(userStr));
        return _currentUser;
      }
    } catch (e) {
      await logout();
    }
    return null;
  }
} 