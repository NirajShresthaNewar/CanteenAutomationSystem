import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import '../config/app_config.dart';
import '../models/user.dart';
import '../models/cart_item.dart';
import '../services/cart_service.dart';

class MenuScreen extends StatefulWidget {
  final User user;

  const MenuScreen({Key? key, required this.user}) : super(key: key);

  @override
  _MenuScreenState createState() => _MenuScreenState();
}

class _MenuScreenState extends State<MenuScreen> {
  List<dynamic> _menuItems = [];
  bool _isLoading = true;
  String? _error;
  Map<String, dynamic>? _selectedVendor;
  List<Map<String, dynamic>> _vendors = [];
  final CartService _cartService = CartService.instance;
  bool _isAddingToCart = false;

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
    
    // Handle local images from API
    // The image path should be relative to the API endpoint
    return '${AppConfig.baseUrl}/api/menu/image.php?path=$imagePath';
  }

  @override
  void initState() {
    super.initState();
    _initializeScreen();
    _cartService.addListener(_onCartChanged);
  }

  @override
  void dispose() {
    _cartService.removeListener(_onCartChanged);
    super.dispose();
  }

  void _onCartChanged() {
    if (mounted) {
      setState(() {});
    }
  }

  Future<void> _initializeScreen() async {
    try {
      await _cartService.initialize();
      await _fetchMenuItems();
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = 'Failed to initialize cart: $e';
        _isLoading = false;
      });
    }
  }

  Future<void> _fetchMenuItems() async {
    if (!mounted) return;
    
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final response = await http.get(
        Uri.parse('${AppConfig.baseUrl}/api/menu/items.php'),
        headers: {
          'Authorization': 'Bearer ${widget.user.token}',
        },
      );

      if (!mounted) return;

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        
        if (data['status'] == 'success' && data['data'] != null) {
          if (!mounted) return;
          setState(() {
            _vendors = List<Map<String, dynamic>>.from(data['data']);
            if (_vendors.isNotEmpty) {
              _selectedVendor = _vendors.first;
              _menuItems = _selectedVendor?['items'] ?? [];
            } else {
              _menuItems = [];
            }
            _isLoading = false;
          });
        } else {
          if (!mounted) return;
          setState(() {
            _error = data['message'] ?? 'No menu items available';
            _isLoading = false;
            _menuItems = [];
          });
        }
      } else {
        if (!mounted) return;
        setState(() {
          _error = 'Failed to load menu items. Please try again.';
          _isLoading = false;
          _menuItems = [];
        });
      }
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = 'Network error. Please check your connection.';
        _isLoading = false;
        _menuItems = [];
      });
    }
  }

  Future<void> _addToCart(Map<String, dynamic> item) async {
    if (_isAddingToCart) return;

    setState(() {
      _isAddingToCart = true;
    });

    try {
      // Parse IDs as integers
      final itemId = int.parse(item['item_id'].toString());
      final vendorId = int.parse(_selectedVendor!['vendor_id'].toString());

      final cartItem = CartItem(
        itemId: itemId,
        vendorId: vendorId,
        name: item['name'] ?? 'Unnamed Item',
        description: item['description']?.toString(),
        price: double.parse(item['price'].toString()),
        imagePath: item['image_path']?.toString(),
      );

      await _cartService.addItem(cartItem);

      if (!mounted) return;

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('${cartItem.name} added to cart'),
          duration: const Duration(seconds: 2),
          action: SnackBarAction(
            label: 'VIEW CART',
            onPressed: () {
              if (mounted) {
                Navigator.pushNamed(context, '/cart');
              }
            },
          ),
        ),
      );
    } catch (e) {
      if (!mounted) return;

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(e.toString().replaceAll('Exception: ', '')),
          backgroundColor: Colors.red,
          duration: const Duration(seconds: 3),
        ),
      );
    } finally {
      if (mounted) {
        setState(() {
          _isAddingToCart = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: RefreshIndicator(
        onRefresh: _fetchMenuItems,
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          child: ConstrainedBox(
            constraints: BoxConstraints(
              minHeight: MediaQuery.of(context).size.height - 
                        MediaQuery.of(context).padding.top - 
                        MediaQuery.of(context).padding.bottom,
            ),
            child: _buildContent(),
          ),
        ),
      ),
    );
  }

  Widget _buildContent() {
    if (_isLoading) {
      return const Center(
        child: CircularProgressIndicator(),
      );
    }

    if (_error != null) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Padding(
              padding: const EdgeInsets.all(12.0),
              child: Text(
                _error!,
                style: const TextStyle(color: Colors.red),
                textAlign: TextAlign.center,
              ),
            ),
            ElevatedButton(
              onPressed: _fetchMenuItems,
              child: const Text('Retry'),
            ),
          ],
        ),
      );
    }

    if (_vendors.isEmpty) {
      return const Center(
        child: Text('No vendors available'),
      );
    }

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12.0, vertical: 8.0),
          child: DropdownButtonFormField<String>(
            value: _selectedVendor?['vendor_email'],
            isExpanded: true,
            decoration: const InputDecoration(
              labelText: 'Select Vendor',
              border: OutlineInputBorder(),
              contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            ),
            items: _vendors.map((vendor) {
              return DropdownMenuItem<String>(
                value: vendor['vendor_email'] as String?,
                child: Text(
                  vendor['vendor_name'] ?? 'Unknown Vendor',
                  overflow: TextOverflow.ellipsis,
                ),
              );
            }).toList(),
            onChanged: (String? vendorEmail) {
              if (vendorEmail != null) {
                final vendor = _vendors.firstWhere(
                  (v) => v['vendor_email'] == vendorEmail,
                  orElse: () => {'items': []},
                );
                setState(() {
                  _selectedVendor = vendor;
                  _menuItems = vendor['items'] ?? [];
                });
              }
            },
          ),
        ),
        _menuItems.isEmpty
            ? const Expanded(
                child: Center(
                  child: Text('No menu items available for this vendor'),
                ),
              )
            : ListView.builder(
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                padding: const EdgeInsets.symmetric(horizontal: 12.0, vertical: 8.0),
                itemCount: _menuItems.length,
                itemBuilder: (context, index) {
                  final item = _menuItems[index];
                  if (item == null) return const SizedBox.shrink();
                  
                  return Card(
                    margin: const EdgeInsets.only(bottom: 12),
                    elevation: 1,
                    child: Padding(
                      padding: const EdgeInsets.all(12),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          _buildItemImage(item),
                          const SizedBox(width: 12),
                          Expanded(
                            child: _buildItemDetails(item, context),
                          ),
                        ],
                      ),
                    ),
                  );
                },
              ),
      ],
    );
  }

  Widget _buildItemImage(Map<String, dynamic> item) {
    final size = MediaQuery.of(context).size.width * 0.2; // 20% of screen width
    return SizedBox(
      width: size,
      height: size,
      child: ClipRRect(
        borderRadius: BorderRadius.circular(6),
        child: Container(
          color: Colors.grey[200],
          child: item['image_path'] != null
              ? Image.network(
                  _getImageUrl(item['image_path']),
                  fit: BoxFit.cover,
                  errorBuilder: (context, error, stackTrace) {
                    print('Error loading image: $error');
                    return Icon(
                      Icons.restaurant,
                      size: size * 0.5,
                      color: Colors.grey,
                    );
                  },
                  loadingBuilder: (context, child, loadingProgress) {
                    if (loadingProgress == null) return child;
                    return Center(
                      child: CircularProgressIndicator(
                        value: loadingProgress.expectedTotalBytes != null
                            ? loadingProgress.cumulativeBytesLoaded / 
                              loadingProgress.expectedTotalBytes!
                            : null,
                      ),
                    );
                  },
                )
              : Icon(
                  Icons.restaurant,
                  size: size * 0.5,
                  color: Colors.grey,
                ),
        ),
      ),
    );
  }

  Widget _buildItemDetails(Map<String, dynamic> item, BuildContext context) {
    final screenWidth = MediaQuery.of(context).size.width;
    final isSmallScreen = screenWidth < 360; // Adjust layout for very small screens

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          item['name'] ?? 'Unnamed Item',
          style: TextStyle(
            fontSize: isSmallScreen ? 14 : 16,
            fontWeight: FontWeight.bold,
          ),
          overflow: TextOverflow.ellipsis,
          maxLines: 1,
        ),
        if (item['description'] != null && item['description'].toString().isNotEmpty) ...[
          const SizedBox(height: 4),
          Text(
            item['description'].toString(),
            style: TextStyle(
              fontSize: isSmallScreen ? 12 : 13,
              color: Colors.grey[600],
            ),
            overflow: TextOverflow.ellipsis,
            maxLines: 2,
          ),
        ],
        const SizedBox(height: 8),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          alignment: WrapAlignment.spaceBetween,
          crossAxisAlignment: WrapCrossAlignment.center,
          children: [
            Text(
              'RM ${(item['price'] ?? 0.0).toStringAsFixed(2)}',
              style: TextStyle(
                fontSize: isSmallScreen ? 14 : 15,
                fontWeight: FontWeight.bold,
                color: Colors.green,
              ),
            ),
            SizedBox(
              height: 32,
              child: ElevatedButton.icon(
                onPressed: _isAddingToCart
                    ? null
                    : () => _addToCart(item),
                icon: _isAddingToCart
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                        ),
                      )
                    : Icon(
                        Icons.add_shopping_cart,
                        size: isSmallScreen ? 16 : 18,
                      ),
                label: Text(
                  _isAddingToCart ? 'Adding...' : 'Add to Cart',
                  style: TextStyle(fontSize: isSmallScreen ? 12 : 14),
                ),
                style: ElevatedButton.styleFrom(
                  backgroundColor: Theme.of(context).primaryColor,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 0),
                  disabledBackgroundColor:
                      Theme.of(context).primaryColor.withOpacity(0.6),
                ),
              ),
            ),
          ],
        ),
      ],
    );
  }
} 