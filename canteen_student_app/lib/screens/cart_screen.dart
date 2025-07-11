import 'dart:async';
import 'package:flutter/material.dart';
import '../models/user.dart';
import '../services/cart_service.dart';
import '../models/cart_item.dart';
import '../config/api_config.dart';
import '../config/app_config.dart';
import '../widgets/app_scaffold.dart';
import 'checkout_screen.dart';

class CartScreen extends StatefulWidget {
  final User user;

  const CartScreen({Key? key, required this.user}) : super(key: key);

  @override
  State<CartScreen> createState() => _CartScreenState();
}

class _CartScreenState extends State<CartScreen> {
  final CartService _cartService = CartService.instance;
  bool _isLoading = true;
  String? _error;
  Timer? _debounceTimer;

  String _getImageUrl(String? imagePath) {
    if (imagePath == null || imagePath.isEmpty) return '';
    
    // Handle base64 encoded images
    if (imagePath.startsWith('data:image')) {
      return imagePath;
    }
    
    // Handle network images
    if (imagePath.startsWith('http')) {
      return imagePath;
    }
    
    // Remove any file:// prefix
    imagePath = imagePath.replaceAll(RegExp(r'^file://'), '');
    
    // If the path starts with uploads/, append it to the base URL without the api prefix
    if (imagePath.startsWith('uploads/')) {
      final baseUrlWithoutApi = AppConfig.baseUrl;
      return '$baseUrlWithoutApi/$imagePath';
    }
    
    // Otherwise use the menu image endpoint
    return '${ApiConfig.baseUrl}${ApiConfig.menuImage}?path=$imagePath';
  }

  @override
  void initState() {
    super.initState();
    print('CartScreen initialized');
    _cartService.addListener(_onCartChanged);
    // Use post-frame callback to avoid setState during build
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadCart();
    });
  }

  @override
  void dispose() {
    _cartService.removeListener(_onCartChanged);
    _debounceTimer?.cancel();
    super.dispose();
  }

  void _onCartChanged() {
    print('Cart changed notification received');
    _debounceTimer?.cancel();
    _debounceTimer = Timer(const Duration(milliseconds: 300), () {
      if (mounted) {
        setState(() {
          print('Updating CartScreen state, items count: ${_cartService.items.length}');
        });
      }
    });
  }

  Future<void> _loadCart() async {
    if (!mounted) return;
    
    print('Loading cart in CartScreen');
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      await _cartService.initialize();
      if (!mounted) return;
      print('Cart loaded successfully, items count: ${_cartService.items.length}');
    } catch (e, stackTrace) {
      print('Error loading cart in CartScreen: $e');
      print('Stack trace: $stackTrace');
      if (!mounted) return;
      setState(() {
        _error = 'Error loading cart: $e';
      });
    } finally {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
  }

  Future<void> _updateQuantity(CartItem item, int newQuantity) async {
    try {
      await _cartService.updateQuantity(item.itemId, newQuantity);
      if (mounted) {
        setState(() {});
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Error updating quantity: $e'),
          backgroundColor: Colors.red,
        ),
      );
    }
  }

  Future<void> _removeItem(CartItem item) async {
    try {
      await _cartService.removeItem(item.itemId);
      if (mounted) {
        setState(() {});
      }
      
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('${item.name} removed from cart'),
          duration: const Duration(seconds: 2),
        ),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Error removing item: $e'),
          backgroundColor: Colors.red,
        ),
      );
    }
  }

  Future<void> _clearCart() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Clear Cart'),
        content: const Text('Are you sure you want to clear your cart?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Clear'),
          ),
        ],
      ),
    );

    if (confirmed == true && mounted) {
      try {
        await _cartService.clearCart();
        setState(() {});
      } catch (e) {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error clearing cart: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return AppScaffold(
      user: widget.user,
      title: 'My Cart',
      actions: [
        if (_cartService.items.isNotEmpty)
          IconButton(
            icon: const Icon(Icons.delete_outline),
            onPressed: _clearCart,
            tooltip: 'Clear Cart',
          ),
      ],
      body: SafeArea(
        child: _error != null
            ? Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(
                    _error!,
                    style: const TextStyle(color: Colors.red),
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 16),
                  ElevatedButton(
                    onPressed: _loadCart,
                    child: const Text('Retry'),
                  ),
                ],
              ),
            )
            : _isLoading
                ? const Center(child: CircularProgressIndicator())
                : _cartService.items.isEmpty
                    ? Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            const Text(
                              'Your cart is empty',
                              style: TextStyle(
                                fontSize: 20,
                                fontWeight: FontWeight.bold,
                                color: Colors.red,
                              ),
                            ),
                            const SizedBox(height: 8),
                            TextButton(
                              onPressed: () {
                                Navigator.pushNamed(
                                  context,
                                  '/menu',
                                  arguments: widget.user,
                                );
                              },
                              child: const Text(
                                'Add items from the menu',
                                style: TextStyle(
                                  fontSize: 16,
                                  decoration: TextDecoration.underline,
                                  color: Colors.red,
                                ),
                              ),
                            ),
                          ],
                        ),
                      )
                    : Column(
              children: [
                Expanded(
                  child: ListView.builder(
                    itemCount: _cartService.items.length,
                    itemBuilder: (context, index) {
                      final item = _cartService.items[index];
                      return Dismissible(
                        key: Key('cart_item_${item.itemId}'),
                        direction: DismissDirection.endToStart,
                        background: Container(
                          color: Colors.red,
                          alignment: Alignment.centerRight,
                          padding: const EdgeInsets.only(right: 16),
                          child: const Icon(
                            Icons.delete,
                            color: Colors.white,
                          ),
                        ),
                        onDismissed: (direction) {
                          _removeItem(item);
                        },
                        child: Card(
                          margin: const EdgeInsets.symmetric(
                            horizontal: 8,
                            vertical: 4,
                          ),
                          child: ListTile(
                            leading: item.imagePath != null
                                ? ClipRRect(
                                    borderRadius: BorderRadius.circular(4),
                                    child: Image.network(
                                      _getImageUrl(item.imagePath),
                                      width: 56,
                                      height: 56,
                                      fit: BoxFit.cover,
                                      errorBuilder: (context, error, stackTrace) {
                                        return Container(
                                          width: 56,
                                          height: 56,
                                          color: Colors.grey[200],
                                          child: const Icon(
                                            Icons.restaurant,
                                            color: Colors.grey,
                                          ),
                                        );
                                      },
                                    ),
                                  )
                                : Container(
                                    width: 56,
                                    height: 56,
                                    color: Colors.grey[200],
                                    child: const Icon(
                                      Icons.restaurant,
                                      color: Colors.grey,
                                    ),
                                  ),
                            title: Text(
                              item.name,
                              style: const TextStyle(
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            subtitle: Text(
                              'Rs. ${item.price.toStringAsFixed(2)}',
                              style: TextStyle(
                                color: Theme.of(context).primaryColor,
                              ),
                            ),
                            trailing: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                IconButton(
                                  icon: const Icon(Icons.remove),
                                  onPressed: () {
                                    if (item.quantity > 1) {
                                      _updateQuantity(item, item.quantity - 1);
                                    } else {
                                      _removeItem(item);
                                    }
                                  },
                                ),
                                Text(
                                  item.quantity.toString(),
                                  style: const TextStyle(
                                    fontSize: 16,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                                IconButton(
                                  icon: const Icon(Icons.add),
                                  onPressed: () {
                                    _updateQuantity(item, item.quantity + 1);
                                  },
                                ),
                              ],
                            ),
                          ),
                        ),
                      );
                    },
                  ),
                ),
                Card(
                  margin: const EdgeInsets.all(8),
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text(
                              'Total Amount:',
                              style: TextStyle(
                                fontSize: 18,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            Text(
                              'Rs. ${_cartService.totalAmount.toStringAsFixed(2)}',
                              style: TextStyle(
                                fontSize: 18,
                                fontWeight: FontWeight.bold,
                                color: Theme.of(context).primaryColor,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 16),
                        SizedBox(
                          width: double.infinity,
                          child: ElevatedButton(
                            onPressed: () {
                              Navigator.push(
                                context,
                                MaterialPageRoute(
                                  builder: (context) => CheckoutScreen(
                                    user: widget.user,
                                  ),
                                ),
                              );
                            },
                            child: const Padding(
                              padding: EdgeInsets.all(12),
                              child: Text(
                                'Proceed to Checkout',
                                style: TextStyle(fontSize: 16),
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
      ),
    );
  }
} 