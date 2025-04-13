import 'package:http/http.dart' as http;

class AppConfig {
  static String _baseUrl = '';

  static String get baseUrl => _baseUrl;

  // Define the possible server addresses
  static const List<String> serverAddresses = [
    '192.168.1.71:80',  // Primary WiFi address
    '192.168.1.71',     // WiFi without port
    'localhost:80',      // Local development
    '127.0.0.1:80',     // Local development
  ];

  static Future<void> detectServerIP() async {
    try {
      print('Attempting to detect server IP...');

      print('Testing server addresses: $serverAddresses');

      for (String address in serverAddresses) {
        try {
          print('Testing address: $address');
          final testUrl = 'http://$address/CanteenAutomationSystem/api/auth/login.php';
          
          print('Attempting to connect to: $testUrl');
          final response = await http
              .get(Uri.parse(testUrl))
              .timeout(const Duration(seconds: 5)); // Increased timeout to 5 seconds

          print('Response from $address: ${response.statusCode}');

          if (response.statusCode == 200 || 
              response.statusCode == 404 || // Added 404 as valid response
              response.statusCode == 403 || // Added 403 as valid response
              response.statusCode == 405) { // Method not allowed is still valid
            _baseUrl = 'http://$address/CanteenAutomationSystem';
            print('✅ Server found at: $_baseUrl');
            return;
          }
        } catch (e) {
          print('❌ Failed to connect to $address: $e');
          continue; // Continue trying other addresses
        }
      }

      // If we get here, no server responded successfully
      _baseUrl = 'http://${serverAddresses[0]}/CanteenAutomationSystem';
      print('⚠️ No server found, using default: $_baseUrl');
    } catch (e) {
      print('💥 Error in detectServerIP: $e');
      _baseUrl = 'http://${serverAddresses[0]}/CanteenAutomationSystem';
    }
  }

  static String getBaseUrl() {
    print('Current base URL: $_baseUrl');
    return _baseUrl;
  }
}
