import 'dart:convert';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import '../models/cart_item.dart';
import '../config/api_config.dart';
import '../services/auth_service.dart';
import '../models/user.dart';
import 'package:flutter/widgets.dart';
import 'dart:async';

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
  Timer? _notifyTimer;

  List<CartItem> get items => List.unmodifiable(_items);
  int? get currentVendorId => _currentVendorId;
  bool get isLoading => _isLoading;
  bool get isInitialized => _isInitialized;
  
  double get totalAmount => _items.fold(0, (sum, item) => sum + item.totalPrice);
  
  int get itemCount => _items.fold(0, (sum, item) => sum + item.quantity);

  void _debounceNotify() {
    _notifyTimer?.cancel();
    _notifyTimer = Timer(const Duration(milliseconds: 100), () {
      notifyListeners();
    });
  }

  @override
  void dispose() {
    _notifyTimer?.cancel();
    super.dispose();
  }

  Future<void> initialize() async {
    if (_isLoading) return;
    
    try {
      _isLoading = true;
      _debounceNotify();

      if (!_isInitialized) {
        print('Initializing cart service...');
        await loadCart();
        _isInitialized = true;
        print('Cart service initialized with ${_items.length} items');
      }
    } catch (e, stackTrace) {
      print('Error initializing cart: $e');
      print('Stack trace: $stackTrace');
      _items = [];
      _currentVendorId = null;
      _isInitialized = false;
      rethrow;
    } finally {
      _isLoading = false;
      _debounceNotify();
    }
  }

  Future<void> loadCart() async {
    try {
      print('Loading cart from storage...');
      final cartData = await _storage.read(key: _cartKey);
      print('Cart data from storage: $cartData');
      
      if (cartData != null) {
        final List<dynamic> decodedData = json.decode(cartData);
        print('Decoded cart data: $decodedData');
        _items = decodedData.map((item) => CartItem.fromJson(item)).toList();
        print('Loaded ${_items.length} items into cart');
        _updateCurrentVendor();
      } else {
        print('No cart data found in storage');
        _items = [];
        _currentVendorId = null;
      }
    } catch (e, stackTrace) {
      print('Error loading cart: $e');
      print('Stack trace: $stackTrace');
      _items = [];
      _currentVendorId = null;
      rethrow;
    }
  }

  Future<void> saveCart() async {
    try {
      print('Saving cart with ${_items.length} items');
      final cartData = json.encode(_items.map((item) => item.toJson()).toList());
      print('Cart data to save: $cartData');
      await _storage.write(key: _cartKey, value: cartData);
      print('Cart saved successfully');
    } catch (e, stackTrace) {
      print('Error saving cart: $e');
      print('Stack trace: $stackTrace');
      throw Exception('Failed to save cart: $e');
    }
  }

  Future<void> addItem(CartItem newItem) async {
    if (_isLoading) return;
    
    try {
      _isLoading = true;
      _debounceNotify();

      print('Adding item to cart: ${newItem.name}');
      if (!_isInitialized) {
        print('Cart not initialized, initializing...');
        await initialize();
      }

      // Check if we can add items from this vendor
      if (_currentVendorId != null && _currentVendorId != newItem.vendorId) {
        throw Exception('Cannot add items from different vendors. Please clear your cart first.');
      }

      // Find if item already exists
      final existingItemIndex = _items.indexWhere((item) => item.itemId == newItem.itemId);
      
      if (existingItemIndex >= 0) {
        print('Updating quantity for existing item');
        // Update quantity if item exists
        _items[existingItemIndex].quantity += newItem.quantity;
      } else {
        print('Adding new item to cart');
        // Add new item
        _items.add(newItem);
        _updateCurrentVendor();
      }

      print('Saving cart after adding item');
      await saveCart();
      print('Cart updated successfully, total items: ${_items.length}');
    } catch (e, stackTrace) {
      print('Error adding item to cart: $e');
      print('Stack trace: $stackTrace');
      rethrow;
    } finally {
      _isLoading = false;
      _debounceNotify();
    }
  }

  Future<void> removeItem(int itemId) async {
    if (_isLoading) return;
    
    try {
      _isLoading = true;
      _debounceNotify();

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
      _debounceNotify();
    }
  }

  Future<void> updateQuantity(int itemId, int quantity) async {
    if (_isLoading) return;
    
    try {
      _isLoading = true;
      _debounceNotify();

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
      _debounceNotify();
    }
  }

  Future<void> clearCart() async {
    if (_isLoading) return;
    
    try {
      _isLoading = true;
      _debounceNotify();

      if (!_isInitialized) {
        await initialize();
      }

      _items = [];
      _currentVendorId = null;
      await _storage.delete(key: _cartKey);
    } finally {
      _isLoading = false;
      _debounceNotify();
    }
  }

  Future<void> checkout({
    required User user,
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
      _debounceNotify();

      print('Starting checkout process...');
      print('Current cart items: ${_items.length}');
      print('Cart items details: ${_items.map((item) => '${item.name}: ${item.quantity}').join(', ')}');

      if (!_isInitialized) {
        print('Cart not initialized, initializing...');
        await initialize();
      }

      // Double check cart items after initialization
      if (_items.isEmpty) {
        print('Cart is empty after initialization');
        throw Exception('Cart is empty');
      }

      if (user.token == null) {
        print('User token is null');
        throw Exception('User not authenticated');
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

      // Create a copy of cart items to prevent any race conditions
      final itemsToCheckout = List<CartItem>.from(_items);

      // Prepare order data
      final orderData = {
        'user_id': user.id.toString(),
        'vendor_id': _currentVendorId.toString(),
        'payment_method': paymentMethod,
        'order_type': orderType,
        'delivery_location': deliveryLocation,
        'building_name': buildingName,
        'floor_number': floorNumber,
        'room_number': roomNumber,
        'contact_number': contactNumber,
        'delivery_instructions': deliveryInstructions,
        'table_number': tableNumber,
        'items': itemsToCheckout.map((item) => {
          'menu_item_id': item.itemId,
          'quantity': item.quantity,
          'price': item.price,
          'name': item.name,
        }).toList(),
      };

      print('Sending checkout request with data: ${json.encode(orderData)}');

      final checkoutUrl = '${ApiConfig.baseUrl}/checkout.php';
      print('Checkout URL: $checkoutUrl');

      final response = await http.post(
        Uri.parse(checkoutUrl),
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer ${user.token}',
        },
        body: json.encode(orderData),
      );

      print('Checkout response status: ${response.statusCode}');
      print('Checkout response body: ${response.body}');

      if (response.statusCode != 200) {
        try {
          final errorBody = json.decode(response.body);
          throw Exception(errorBody['error'] ?? 'Server error: ${response.statusCode}');
        } catch (e) {
          if (e is FormatException) {
            throw Exception('Server error: ${response.statusCode}. Please try again.');
          }
          rethrow;
        }
      }

      final responseData = json.decode(response.body);
      
      if (!responseData['success']) {
        throw Exception(responseData['error'] ?? 'Failed to place order');
      }

      // Clear cart only after successful order
      print('Order successful, clearing cart');
      await clearCart();
      
    } catch (e, stackTrace) {
      print('Checkout error: $e');
      print('Checkout error stack trace: $stackTrace');
      rethrow;
    } finally {
      _isLoading = false;
      _debounceNotify();
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