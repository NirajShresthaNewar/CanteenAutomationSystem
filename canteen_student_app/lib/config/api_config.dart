class ApiConfig {
  // Base URL configuration
  static const String baseUrl =
      'http://Bhojraj/CanteenAutomationSystem/api'; // Updated to hostname

  // API endpoints
  static const String login = '/auth/login.php';
  static const String test = '/test.php';

  // Headers
  static Map<String, String> headers(String? token) {
    return {
      'Content-Type': 'application/json',
      if (token != null) 'Authorization': 'Bearer $token',
    };
  }
}
