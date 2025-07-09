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
      print('Attempting login for email: $email');
      final response = await http.post(
        Uri.parse(ApiConfig.baseUrl + ApiConfig.login),
        headers: ApiConfig.headers(null),
        body: jsonEncode({
          'email': email,
          'password': password,
        }),
      );

      print('Login response status: ${response.statusCode}');
      print('Login response body: ${response.body}');

      final data = jsonDecode(response.body);

      if (response.statusCode == 200 && data['status'] == 'success') {
        final user = User.fromJson(data['data']);
        _currentUser = user;
        
        print('Login successful. Token: ${user.token}');
        
        // Save user data and token separately
        await storage.write(key: 'user_data', value: jsonEncode(user.toJson()));
        if (user.token != null) {
          await storage.write(key: 'auth_token', value: user.token);
          print('Token stored successfully');
        } else {
          print('Warning: No token received from server');
        }
        
        return user;
      } else {
        throw data['message'] ?? 'Login failed';
      }
    } catch (e) {
      print('Login error: $e');
      throw 'Login failed: $e';
    }
  }

  Future<void> logout() async {
    print('Logging out - clearing storage');
    await storage.delete(key: 'user_data');
    await storage.delete(key: 'auth_token');
    _currentUser = null;
  }

  Future<User?> checkAuth() async {
    try {
      final token = await storage.read(key: 'auth_token');
      final userData = await storage.read(key: 'user_data');
      
      print('Checking auth - Stored token: $token');
      print('Checking auth - Stored user data: $userData');
      
      if (userData != null) {
        final userMap = jsonDecode(userData);
        // Ensure the token from storage is used
        if (token != null) {
          userMap['token'] = token;
        }
        _currentUser = User.fromJson(userMap);
        print('Auth check successful. Current user token: ${_currentUser?.token}');
        return _currentUser;
      }
    } catch (e) {
      print('Auth check failed: $e');
      await logout();
    }
    return null;
  }

  // Helper method to get current token
  Future<String?> getToken() async {
    return await storage.read(key: 'auth_token');
  }
} 