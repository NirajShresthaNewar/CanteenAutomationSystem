import 'dart:convert';
import 'package:http/http.dart' as http;
import '../models/order.dart';
import '../models/user.dart';
import '../config/api_config.dart';

class OrderService {
  static OrderService? _instance;
  static OrderService get instance {
    _instance ??= OrderService._internal();
    return _instance!;
  }

  OrderService._internal();

  Future<List<Order>> getOrders(User user, {bool activeOnly = false}) async {
    try {
      final url = Uri.parse('${ApiConfig.baseUrl}/orders/list.php${activeOnly ? '?active_only=1' : ''}');
      
      final response = await http.get(
        url,
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer ${user.token}',
        },
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['status'] == 'success') {
          return (data['data'] as List)
              .map((order) => Order.fromJson(order))
              .toList();
        } else {
          throw Exception(data['message'] ?? 'Failed to fetch orders');
        }
      } else {
        throw Exception('Server returned ${response.statusCode}');
      }
    } catch (e) {
      print('Error fetching orders: $e');
      rethrow;
    }
  }

  Future<Order> getOrderDetails(User user, int orderId) async {
    try {
      final url = Uri.parse('${ApiConfig.baseUrl}/orders/details.php?order_id=$orderId');
      
      final response = await http.get(
        url,
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer ${user.token}',
        },
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['status'] == 'success') {
          return Order.fromJson(data['data']);
        } else {
          throw Exception(data['message'] ?? 'Failed to fetch order details');
        }
      } else {
        throw Exception('Server returned ${response.statusCode}');
      }
    } catch (e) {
      print('Error fetching order details: $e');
      rethrow;
    }
  }

  Future<void> cancelOrder(User user, int orderId) async {
    try {
      final url = Uri.parse('${ApiConfig.baseUrl}/orders/cancel.php');
      
      final response = await http.post(
        url,
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer ${user.token}',
        },
        body: json.encode({
          'order_id': orderId,
        }),
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['status'] != 'success') {
          throw Exception(data['message'] ?? 'Failed to cancel order');
        }
      } else {
        throw Exception('Server returned ${response.statusCode}');
      }
    } catch (e) {
      print('Error canceling order: $e');
      rethrow;
    }
  }

  Future<Order> reorder(User user, int orderId) async {
    try {
      final url = Uri.parse('${ApiConfig.baseUrl}/orders/reorder.php');
      
      final response = await http.post(
        url,
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer ${user.token}',
        },
        body: json.encode({
          'order_id': orderId,
        }),
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['status'] == 'success') {
          return Order.fromJson(data['data']);
        } else {
          throw Exception(data['message'] ?? 'Failed to reorder');
        }
      } else {
        throw Exception('Server returned ${response.statusCode}');
      }
    } catch (e) {
      print('Error reordering: $e');
      rethrow;
    }
  }
} 