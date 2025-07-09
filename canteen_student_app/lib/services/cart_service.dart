import 'dart:convert';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import '../models/cart_item.dart';
import '../config/api_config.dart';

class CartService with ChangeNotifier {
  static CartService? _instance;
  static CartService get instance {
    _instance ??= CartService._internal();
    return _instance!;
  }

  CartService._internal();

  static const String _cartKey = 'user_cart';
  final FlutterSecureStorage _storage = const FlutterSecureStorage();
  List<CartItem> _items = [];
  int? _currentVendorId;
  bool _isInitialized = false;
  bool _isLoading = false;

  List<CartItem> get items => List.unmodifiable(_items);
  int? get currentVendorId => _currentVendorId;
  bool get isLoading => _isLoading;
  
  double get totalAmount => _items.fold(0, (sum, item) => sum + item.totalPrice);
  
  int get itemCount => _items.fold(0, (sum, item) => sum + item.quantity);

  Future<void> initialize() async {
    if (_isLoading) return;
    
    try {
      _isLoading = true;
      notifyListeners();
      
      if (!_isInitialized) {
        await loadCart();
        _isInitialized = true;
      }
    } catch (e) {
      print('Error initializing cart: $e');
      _items = [];
      _currentVendorId = null;
      _isInitialized = false;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<void> loadCart() async {
    try {
      final cartData = await _storage.read(key: _cartKey);
      if (cartData != null) {
        final List<dynamic> decodedData = json.decode(cartData);
        _items = decodedData.map((item) => CartItem.fromJson(item)).toList();
        _updateCurrentVendor();
      } else {
        _items = [];
        _currentVendorId = null;
      }
    } catch (e) {
      print('Error loading cart: $e');
      _items = [];
      _currentVendorId = null;
      rethrow;
    }
  }

  Future<void> saveCart() async {
    try {
      final cartData = json.encode(_items.map((item) => item.toJson()).toList());
      await _storage.write(key: _cartKey, value: cartData);
    } catch (e) {
      print('Error saving cart: $e');
      throw Exception('Failed to save cart: $e');
    }
  }

  Future<void> addItem(CartItem newItem) async {
    if (_isLoading) return;
    
    try {
      _isLoading = true;
      notifyListeners();

      if (!_isInitialized) {
        await initialize();
      }

      // Check if we can add items from this vendor
      if (_currentVendorId != null && _currentVendorId != newItem.vendorId) {
        throw Exception('Cannot add items from different vendors. Please clear your cart first.');
      }

      // Find if item already exists
      final existingItemIndex = _items.indexWhere((item) => item.itemId == newItem.itemId);
      
      if (existingItemIndex >= 0) {
        // Update quantity if item exists
        _items[existingItemIndex].quantity += newItem.quantity;
      } else {
        // Add new item
        _items.add(newItem);
        _updateCurrentVendor();
      }

      await saveCart();
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<void> removeItem(int itemId) async {
    if (_isLoading) return;
    
    try {
      _isLoading = true;
      notifyListeners();

      if (!_isInitialized) {
        await initialize();
      }

      _items.removeWhere((item) => item.itemId == itemId);
      if (_items.isEmpty) {
        _currentVendorId = null;
      }
      await saveCart();
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<void> updateQuantity(int itemId, int quantity) async {
    if (_isLoading) return;
    
    try {
      _isLoading = true;
      notifyListeners();

      if (!_isInitialized) {
        await initialize();
      }

      final index = _items.indexWhere((item) => item.itemId == itemId);
      if (index >= 0) {
        if (quantity <= 0) {
          await removeItem(itemId);
        } else {
          _items[index].quantity = quantity;
          await saveCart();
        }
      }
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<void> clearCart() async {
    if (_isLoading) return;
    
    try {
      _isLoading = true;
      notifyListeners();

      if (!_isInitialized) {
        await initialize();
      }

      _items = [];
      _currentVendorId = null;
      await _storage.delete(key: _cartKey);
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<void> checkout({
    required String orderType,
    required String paymentMethod,
    String? deliveryLocation,
    String? buildingName,
    String? floorNumber,
    String? roomNumber,
    String? contactNumber,
    String? deliveryInstructions,
    String? tableNumber,
  }) async {
    if (_isLoading) return;
    
    try {
      _isLoading = true;
      notifyListeners();

      if (!_isInitialized) {
        await initialize();
      }

      if (_items.isEmpty) {
        throw Exception('Cart is empty');
      }

      // Validate required fields
      if (orderType == 'delivery') {
        if (deliveryLocation == null || deliveryLocation.isEmpty) {
          throw Exception('Delivery location is required');
        }
        if (contactNumber == null || contactNumber.isEmpty) {
          throw Exception('Contact number is required');
        }
      } else if (orderType == 'dine_in') {
        if (tableNumber == null || tableNumber.isEmpty) {
          throw Exception('Table number is required');
        }
      }

      // Prepare order data
      final orderData = {
        'vendor_id': _currentVendorId,
        'payment_method': paymentMethod,
        'order_type': orderType,
        'delivery_location': deliveryLocation,
        'building_name': buildingName,
        'floor_number': floorNumber,
        'room_number': roomNumber,
        'contact_number': contactNumber,
        'delivery_instructions': deliveryInstructions,
        'table_number': tableNumber,
      };

      // Send order to server
      final response = await http.post(
        Uri.parse('${ApiConfig.baseUrl}/api/checkout.php'),
        body: json.encode(orderData),
        headers: {
          'Content-Type': 'application/json',
        },
      );

      if (response.statusCode != 200) {
        final error = json.decode(response.body);
        throw Exception(error['error'] ?? 'Failed to place order');
      }

      // Clear cart after successful order
      await clearCart();
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  void _updateCurrentVendor() {
    if (_items.isNotEmpty) {
      _currentVendorId = _items.first.vendorId;
    } else {
      _currentVendorId = null;
    }
  }
} 