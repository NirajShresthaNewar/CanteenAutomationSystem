import 'package:flutter/material.dart';
import '../models/user.dart';
import '../models/cart_item.dart';
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

  Future<void> _processCheckout() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    try {
      // Show loading indicator
      showDialog(
        context: context,
        barrierDismissible: false,
        builder: (context) => const Center(
          child: CircularProgressIndicator(),
        ),
      );

      // Process checkout
      await _cartService.checkout(
        orderType: _orderType,
        paymentMethod: _paymentMethod,
        deliveryLocation: _deliveryLocationController.text,
        buildingName: _buildingNameController.text,
        floorNumber: _floorNumberController.text,
        roomNumber: _roomNumberController.text,
        contactNumber: _contactNumberController.text,
        deliveryInstructions: _deliveryInstructionsController.text,
        tableNumber: _tableNumberController.text,
      );

      // Remove loading indicator
      Navigator.pop(context);

      // Show success message
      if (!mounted) return;
      showDialog(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Success'),
          content: const Text('Your order has been placed successfully!'),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.pop(context); // Close dialog
                Navigator.pop(context); // Go back to cart
                Navigator.pop(context); // Go back to menu
              },
              child: const Text('OK'),
            ),
          ],
        ),
      );
    } catch (e) {
      // Remove loading indicator
      Navigator.pop(context);

      // Show error message
      if (!mounted) return;
      showDialog(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Error'),
          content: Text('Failed to place order: $e'),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('OK'),
            ),
          ],
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Checkout'),
      ),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            _buildOrderTypeSection(),
            const SizedBox(height: 24),
            if (_orderType == 'delivery') _buildDeliveryDetailsSection(),
            if (_orderType == 'dine_in') _buildDineInDetailsSection(),
            const SizedBox(height: 24),
            _buildOrderSummary(),
            const SizedBox(height: 24),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: _processCheckout,
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.red,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 16),
                ),
                child: const Text(
                  'Place Order',
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
} 