import 'dart:async';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import '../config/app_config.dart';
import '../config/api_config.dart';
import '../models/user.dart';
import '../models/cart_item.dart';
import '../services/cart_service.dart';
import '../services/auth_service.dart';
import '../widgets/app_scaffold.dart';

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
    WidgetsBinding.instance.addPostFrameCallback((_) {
    _initializeScreen();
    });
    _cartService.addListener(_onCartChanged);
  }

  @override
  void dispose() {
    _cartService.removeListener(_onCartChanged);
    _debounceTimer?.cancel();
    super.dispose();
  }

  void _onCartChanged() {
    _debounceTimer?.cancel();
    _debounceTimer = Timer(const Duration(milliseconds: 300), () {
      if (mounted) {
        setState(() {});
      }
    });
  }

/*************  ✨ Windsurf Command ⭐  *************/
/// Initializes the screen by setting up the cart service and fetching menu items.
/// 
/// This function attempts to initialize the cart service and then retrieve the 
/// available menu items. If an error occurs during either of these processes, 
/// it sets an error message and stops loading.

/*******  0588c874-f4fc-4431-9a5e-b3499b22af43  *******/
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
      final url = ApiConfig.baseUrl + ApiConfig.menuItems;
      print('Fetching menu items from: $url');
      print('Using token: ${widget.user.token}');  // Debug token
      
      final headers = {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ${widget.user.token}',
      };
      print('Request headers: $headers');  // Debug headers

      final response = await http.get(
        Uri.parse(url),
        headers: headers,
      ).timeout(
        const Duration(seconds: 10),
        onTimeout: () {
          throw TimeoutException('Connection timed out. Please check your internet connection.');
        },
      );

      print('Menu response status: ${response.statusCode}');
      print('Menu response body: ${response.body}');

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
          throw Exception(data['message'] ?? 'No menu items available');
        }
      } else if (response.statusCode == 401 || response.statusCode == 500) {
        // If unauthorized or server error, try to refresh the token
        final authService = AuthService.instance;
        final updatedUser = await authService.checkAuth();
        if (updatedUser != null) {
          // Retry with new token
          final retryResponse = await http.get(
            Uri.parse(url),
            headers: {
              'Content-Type': 'application/json',
              'Authorization': 'Bearer ${updatedUser.token}',
            },
          );
          
          if (retryResponse.statusCode == 200) {
            final data = json.decode(retryResponse.body);
            if (data['status'] == 'success' && data['data'] != null) {
          setState(() {
                _vendors = List<Map<String, dynamic>>.from(data['data']);
                if (_vendors.isNotEmpty) {
                  _selectedVendor = _vendors.first;
                  _menuItems = _selectedVendor?['items'] ?? [];
                }
            _isLoading = false;
          });
              return;
            }
          }
        }
        throw Exception('Session expired. Please login again.');
      } else {
        throw Exception('Server returned ${response.statusCode}: ${response.body}');
      }
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString().replaceAll('Exception: ', '');
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
                Navigator.pushNamed(
                  context,
                  '/cart',
                  arguments: widget.user,
                );
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
    return AppScaffold(
      user: widget.user,
      title: 'Menu',
      actions: [
        if (_cartService.items.isNotEmpty)
          Stack(
            alignment: Alignment.center,
            children: [
              IconButton(
                icon: const Icon(Icons.shopping_cart),
                onPressed: () {
                  Navigator.pushNamed(
                    context,
                    '/cart',
                    arguments: widget.user,
                  );
                },
              ),
              if (_cartService.itemCount > 0)
                Positioned(
                  right: 8,
                  top: 8,
                  child: Container(
                    padding: const EdgeInsets.all(2),
                    decoration: BoxDecoration(
                      color: Colors.red,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    constraints: const BoxConstraints(
                      minWidth: 16,
                      minHeight: 16,
                    ),
                    child: Text(
                      _cartService.itemCount.toString(),
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 10,
                      ),
                      textAlign: TextAlign.center,
                    ),
                  ),
                ),
            ],
          ),
      ],
      body: _error != null
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
                    onPressed: _fetchMenuItems,
                    child: const Text('Retry'),
                  ),
                ],
              ),
            )
          : _isLoading
              ? const Center(child: CircularProgressIndicator())
              : Column(
                  children: [
                    // Vendor selection
                    if (_vendors.isNotEmpty)
                      Padding(
                        padding: const EdgeInsets.all(8.0),
                        child: DropdownButtonFormField<Map<String, dynamic>>(
                          value: _selectedVendor,
                          decoration: const InputDecoration(
                            labelText: 'Select Vendor',
                            border: OutlineInputBorder(),
                          ),
                          items: _vendors.map((vendor) {
                            return DropdownMenuItem<Map<String, dynamic>>(
                              value: vendor,
                              child: Text(vendor['name'] ?? 'Unknown Vendor'),
                            );
                          }).toList(),
                          onChanged: (newValue) {
                            setState(() {
                              _selectedVendor = newValue;
                              _menuItems = _selectedVendor?['items'] ?? [];
                            });
                          },
                        ),
                      ),
                    // Menu items
                    Expanded(
                      child: _menuItems.isEmpty
                          ? const Center(
                              child: Text('No menu items available'),
                            )
                          : RefreshIndicator(
                              onRefresh: _fetchMenuItems,
                              child: GridView.builder(
                                padding: const EdgeInsets.all(8),
                                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                                  crossAxisCount: 2,
                                  childAspectRatio: 0.75,
                                  mainAxisSpacing: 8,
                                  crossAxisSpacing: 8,
                                ),
                                itemCount: _menuItems.length,
                                itemBuilder: (context, index) {
                                  final item = _menuItems[index];
                                  return Card(
                                    clipBehavior: Clip.antiAlias,
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        // Image with fixed aspect ratio
                                        AspectRatio(
                                          aspectRatio: 16/9,
                                          child: Image.network(
                                            _getImageUrl(item['image_path']),
                                            fit: BoxFit.cover,
                                            errorBuilder: (context, error, stackTrace) {
                                              return Container(
                                                color: Colors.grey[200],
                                                child: const Icon(
                                                  Icons.restaurant,
                                                  size: 48,
                                                  color: Colors.grey,
                                                ),
                                              );
                                            },
                                          ),
                                        ),
                                        // Content padding wrapped in Expanded
                                        Expanded(
                                          child: Padding(
                                            padding: const EdgeInsets.all(12.0),
                                            child: Column(
                                              crossAxisAlignment: CrossAxisAlignment.start,
                                              children: [
                                                // Item name
                                                Text(
                                                  item['name'] ?? 'Unnamed Item',
                                                  style: const TextStyle(
                                                    fontWeight: FontWeight.bold,
                                                    fontSize: 16,
                                                  ),
                                                  maxLines: 1,
                                                  overflow: TextOverflow.ellipsis,
                                                ),
                                                const SizedBox(height: 8),
                                                // Description wrapped in Flexible
                                                Flexible(
                                                  child: Text(
                                                    item['description'] ?? 'No description available',
                                                    style: TextStyle(
                                                      color: Colors.grey[600],
                                                      fontSize: 14,
                                                      height: 1.2,
                                                    ),
                                                    maxLines: 2,
                                                    overflow: TextOverflow.ellipsis,
                                                  ),
                                                ),
                                                const SizedBox(height: 8),
                                                // Price
                                                Text(
                                                  'Rs. ${(double.parse(item['price'].toString())).toStringAsFixed(2)}',
                                                  style: TextStyle(
                                                    color: Theme.of(context).primaryColor,
                                                    fontWeight: FontWeight.bold,
                                                    fontSize: 16,
                                                  ),
                                                ),
                                                const SizedBox(height: 8),
                                                // Add to Cart button
                                                SizedBox(
                                                  width: double.infinity,
                                                  child: ElevatedButton(
                                                    onPressed: _isAddingToCart
                                                        ? null
                                                        : () => _addToCart(item),
                                                    style: ElevatedButton.styleFrom(
                                                      padding: const EdgeInsets.symmetric(
                                                        vertical: 12,
                                                      ),
                                                    ),
                                                    child: Text(
                                                      _isAddingToCart ? 'Adding...' : 'Add to Cart',
                                                      style: const TextStyle(
                                                        fontSize: 14,
                                                        fontWeight: FontWeight.bold,
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
                                  );
                                },
                              ),
                            ),
                    ),
                  ],
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
            Text(
                _error!,
              textAlign: TextAlign.center,
                style: const TextStyle(color: Colors.red),
            ),
            const SizedBox(height: 16),
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
        child: Text('No menu items available'),
      );
    }

    return ListView.builder(
      shrinkWrap: true,
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(16),
      itemCount: _vendors.length,
      itemBuilder: (context, vendorIndex) {
        final vendor = _vendors[vendorIndex];
        final items = vendor['items'] as List;

    return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
      children: [
            // Vendor Header
            Card(
              margin: const EdgeInsets.only(bottom: 16),
                    child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                    Text(
                      vendor['vendor_name'],
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                    const SizedBox(height: 4),
                    Text(
                      vendor['opening_hours'] ?? 'Hours not specified',
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        color: Colors.grey[600],
                      ),
                    ),
                  ],
                ),
              ),
            ),
            
            // Menu Items Grid
            GridView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 2,
                childAspectRatio: 0.75,
                crossAxisSpacing: 16,
                mainAxisSpacing: 16,
                    ),
              itemCount: items.length,
              itemBuilder: (context, index) {
                final item = items[index];
                return _buildMenuItem(item);
                },
              ),
      ],
        );
      },
    );
  }

  Widget _buildMenuItem(Map<String, dynamic> item) {
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => _addToCart(item),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Image
            AspectRatio(
              aspectRatio: 1.0,
              child: Image.network(
                  _getImageUrl(item['image_path']),
                  fit: BoxFit.cover,
                  errorBuilder: (context, error, stackTrace) {
                  return Container(
                    color: Colors.grey[200],
                    child: const Icon(
                      Icons.restaurant,
                      size: 48,
                      color: Colors.grey,
                      ),
                    );
                  },
                ),
        ),
            
            Expanded(
              child: Padding(
                padding: const EdgeInsets.all(8.0),
                child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
                      item['name'],
                      style: const TextStyle(
            fontWeight: FontWeight.bold,
                        fontSize: 14,
          ),
                      maxLines: 2,
          overflow: TextOverflow.ellipsis,
        ),
          const SizedBox(height: 4),
          Text(
                      'Rs. ${item['price']}',
            style: TextStyle(
                        color: Theme.of(context).primaryColor,
                fontWeight: FontWeight.bold,
                      ),
              ),
                  ],
                ),
              ),
            ),
            
            // Add to Cart Button
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
              child: ElevatedButton(
                onPressed: () => _addToCart(item),
                style: ElevatedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(vertical: 8),
                  textStyle: const TextStyle(fontSize: 12),
                ),
                child: const Text('Add to Cart'),
              ),
            ),
          ],
        ),
      ),
    );
  }
} 