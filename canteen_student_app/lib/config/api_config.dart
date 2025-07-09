import 'app_config.dart';

class ApiConfig {
  // Base URL configuration
  static String get baseUrl => AppConfig.baseUrl + '/api';

  // API endpoints
  static const String login = '/auth/login.php';
  static const String test = '/test.php';
  static const String menuItems = '/menu/items.php';
  static const String menuImage = '/menu/image.php';

  // Headers
  static Map<String, String> headers(String? token) {
    return {
      'Content-Type': 'application/json',
      if (token != null) 'Authorization': 'Bearer $token',
    };
  }
}
