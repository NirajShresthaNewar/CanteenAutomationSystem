import 'package:flutter/material.dart';
import '../models/user.dart';
import '../services/cart_service.dart';

class CheckoutScreen extends StatefulWidget {
  final User user;

  const CheckoutScreen({Key? key, required this.user}) : super(key: key);

  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  final _formKey = GlobalKey<FormState>();
  final CartService _cartService = CartService.instance;
  bool _isLoading = false;
  String? _error;
  
  String _orderType = 'delivery';
  String _paymentMethod = 'cash';
  
  // Delivery details
  final _deliveryLocationController = TextEditingController();
  final _buildingNameController = TextEditingController();
  final _floorNumberController = TextEditingController();
  final _roomNumberController = TextEditingController();
  final _contactNumberController = TextEditingController();
  final _deliveryInstructionsController = TextEditingController();
  
  // Dine-in details
  final _tableNumberController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _validateCart();
  }

  Future<void> _validateCart() async {
    try {
      if (!_cartService.isInitialized) {
        await _cartService.initialize();
      }

      if (_cartService.items.isEmpty) {
        print('Cart is empty during validation');
        Navigator.of(context).pushReplacementNamed('/cart');
        return;
      }

      print('Cart validated successfully. Items count: ${_cartService.items.length}');
      print('Cart items: ${_cartService.items.map((item) => '${item.name}: ${item.quantity}').join(', ')}');
    } catch (e) {
      print('Error validating cart: $e');
      Navigator.of(context).pushReplacementNamed('/cart');
    }
  }

  Future<void> _handleCheckout() async {
    if (!mounted) return;

    // Validate form
    if (!_formKey.currentState!.validate()) {
      return;
    }

    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      print('Starting checkout process...');
      print('Current cart items: ${_cartService.items.length}');
      
      if (_cartService.items.isEmpty) {
        throw Exception('Cart is empty');
      }

      await _cartService.checkout(
        user: widget.user,
        orderType: _orderType,
        paymentMethod: _paymentMethod,
        deliveryLocation: _orderType == 'delivery' ? _deliveryLocationController.text : null,
        buildingName: _orderType == 'delivery' ? _buildingNameController.text : null,
        floorNumber: _orderType == 'delivery' ? _floorNumberController.text : null,
        roomNumber: _orderType == 'delivery' ? _roomNumberController.text : null,
        contactNumber: _orderType == 'delivery' ? _contactNumberController.text : null,
        deliveryInstructions: _orderType == 'delivery' ? _deliveryInstructionsController.text : null,
        tableNumber: _orderType == 'dine_in' ? _tableNumberController.text : null,
      );

      if (!mounted) return;

      // Show success message
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Order placed successfully!'),
          backgroundColor: Colors.green,
          duration: Duration(seconds: 2),
        ),
      );

      // Add delay before navigation
      await Future.delayed(const Duration(seconds: 1));

      if (!mounted) return;

      // Navigate back to home screen and clear all previous routes
      Navigator.of(context).pushNamedAndRemoveUntil(
        '/home',
        (route) => false,
        arguments: widget.user,
      );

    } catch (e) {
      print('Error during checkout: $e');
      if (!mounted) return;
      
      setState(() {
        _error = e.toString();
      });

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Checkout failed: ${e.toString()}'),
          backgroundColor: Colors.red,
        ),
      );
    } finally {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
  }

  @override
  void dispose() {
    _deliveryLocationController.dispose();
    _buildingNameController.dispose();
    _floorNumberController.dispose();
    _roomNumberController.dispose();
    _contactNumberController.dispose();
    _deliveryInstructionsController.dispose();
    _tableNumberController.dispose();
    super.dispose();
  }

  Widget _buildOrderTypeSection() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Order Type',
          style: TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.bold,
          ),
        ),
        const SizedBox(height: 8),
        SegmentedButton<String>(
          segments: const [
            ButtonSegment<String>(
              value: 'delivery',
              label: Text('Delivery'),
              icon: Icon(Icons.delivery_dining),
            ),
            ButtonSegment<String>(
              value: 'dine_in',
              label: Text('Dine In'),
              icon: Icon(Icons.restaurant),
            ),
          ],
          selected: {_orderType},
          onSelectionChanged: (Set<String> newSelection) {
            setState(() {
              _orderType = newSelection.first;
            });
          },
        ),
      ],
    );
  }

  Widget _buildDeliveryDetailsSection() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Delivery Details',
          style: TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.bold,
          ),
        ),
        const SizedBox(height: 16),
        TextFormField(
          controller: _deliveryLocationController,
          decoration: const InputDecoration(
            labelText: 'Delivery Location*',
            border: OutlineInputBorder(),
          ),
          validator: (value) {
            if (_orderType == 'delivery' && (value == null || value.isEmpty)) {
              return 'Please enter delivery location';
            }
            return null;
          },
        ),
        const SizedBox(height: 16),
        TextFormField(
          controller: _buildingNameController,
          decoration: const InputDecoration(
            labelText: 'Building Name',
            border: OutlineInputBorder(),
          ),
        ),
        const SizedBox(height: 16),
        Row(
          children: [
            Expanded(
              child: TextFormField(
                controller: _floorNumberController,
                decoration: const InputDecoration(
                  labelText: 'Floor Number',
                  border: OutlineInputBorder(),
                ),
                keyboardType: TextInputType.number,
              ),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: TextFormField(
                controller: _roomNumberController,
                decoration: const InputDecoration(
                  labelText: 'Room Number',
                  border: OutlineInputBorder(),
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 16),
        TextFormField(
          controller: _contactNumberController,
          decoration: const InputDecoration(
            labelText: 'Contact Number*',
            border: OutlineInputBorder(),
          ),
          keyboardType: TextInputType.phone,
          validator: (value) {
            if (_orderType == 'delivery' && (value == null || value.isEmpty)) {
              return 'Please enter contact number';
            }
            return null;
          },
        ),
        const SizedBox(height: 16),
        TextFormField(
          controller: _deliveryInstructionsController,
          decoration: const InputDecoration(
            labelText: 'Delivery Instructions',
            border: OutlineInputBorder(),
          ),
          maxLines: 2,
        ),
      ],
    );
  }

  Widget _buildDineInDetailsSection() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Dine-in Details',
          style: TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.bold,
          ),
        ),
        const SizedBox(height: 16),
        TextFormField(
          controller: _tableNumberController,
          decoration: const InputDecoration(
            labelText: 'Table Number*',
            border: OutlineInputBorder(),
          ),
          validator: (value) {
            if (_orderType == 'dine_in' && (value == null || value.isEmpty)) {
              return 'Please enter table number';
            }
            return null;
          },
        ),
      ],
    );
  }

  Widget _buildOrderSummary() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Order Summary',
          style: TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.bold,
          ),
        ),
        const SizedBox(height: 16),
        ListView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          itemCount: _cartService.items.length,
          itemBuilder: (context, index) {
            final item = _cartService.items[index];
            return ListTile(
              title: Text(item.name),
              subtitle: Text('Quantity: ${item.quantity}'),
              trailing: Text(
                'Rs. ${(item.price * item.quantity).toStringAsFixed(2)}',
                style: const TextStyle(
                  fontWeight: FontWeight.bold,
                ),
              ),
            );
          },
        ),
        const Divider(),
        Padding(
          padding: const EdgeInsets.symmetric(vertical: 8.0),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'Total Amount:',
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.bold,
                ),
              ),
              Text(
                'Rs. ${_cartService.totalAmount.toStringAsFixed(2)}',
                style: const TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.bold,
                  color: Colors.red,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Checkout'),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : SingleChildScrollView(
              padding: const EdgeInsets.all(16.0),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    if (_error != null)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 16.0),
                        child: Text(
                          _error!,
                          style: const TextStyle(
                            color: Colors.red,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ),
                    _buildOrderTypeSection(),
                    const SizedBox(height: 24),
                    if (_orderType == 'delivery') ...[
                      _buildDeliveryDetailsSection(),
                      const SizedBox(height: 24),
                    ],
                    if (_orderType == 'dine_in') ...[
                      _buildDineInDetailsSection(),
                      const SizedBox(height: 24),
                    ],
                    _buildOrderSummary(),
                    const SizedBox(height: 24),
                    ElevatedButton(
                      onPressed: _isLoading ? null : _handleCheckout,
                      child: Text(_isLoading ? 'Processing...' : 'Place Order'),
                    ),
                  ],
                ),
              ),
            ),
    );
  }
} 