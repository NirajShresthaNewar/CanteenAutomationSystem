import 'dart:convert';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter/foundation.dart';
import '../models/cart_item.dart';

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

  List<CartItem> get items => List.unmodifiable(_items);
  int? get currentVendorId => _currentVendorId;
  
  double get totalAmount => _items.fold(0, (sum, item) => sum + item.totalPrice);
  
  int get itemCount => _items.fold(0, (sum, item) => sum + item.quantity);

  Future<void> initialize() async {
    if (!_isInitialized) {
      await loadCart();
      _isInitialized = true;
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
      notifyListeners();
    } catch (e) {
      print('Error loading cart: $e');
      _items = [];
      _currentVendorId = null;
      notifyListeners();
    }
  }

  Future<void> saveCart() async {
    try {
      final cartData = json.encode(_items.map((item) => item.toJson()).toList());
      await _storage.write(key: _cartKey, value: cartData);
      notifyListeners();
    } catch (e) {
      print('Error saving cart: $e');
      throw Exception('Failed to save cart: $e');
    }
  }

  Future<void> addItem(CartItem newItem) async {
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
    notifyListeners();
  }

  Future<void> removeItem(int itemId) async {
    if (!_isInitialized) {
      await initialize();
    }

    _items.removeWhere((item) => item.itemId == itemId);
    if (_items.isEmpty) {
      _currentVendorId = null;
    }
    await saveCart();
    notifyListeners();
  }

  Future<void> updateQuantity(int itemId, int quantity) async {
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
        notifyListeners();
      }
    }
  }

  Future<void> clearCart() async {
    if (!_isInitialized) {
      await initialize();
    }

    _items = [];
    _currentVendorId = null;
    await _storage.delete(key: _cartKey);
    notifyListeners();
  }

  void _updateCurrentVendor() {
    if (_items.isNotEmpty) {
      _currentVendorId = _items.first.vendorId;
    } else {
      _currentVendorId = null;
    }
  }
} 