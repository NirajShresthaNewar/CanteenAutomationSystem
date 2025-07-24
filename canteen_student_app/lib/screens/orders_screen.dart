import 'package:flutter/material.dart';
import '../models/user.dart';
import '../models/order.dart';
import '../services/order_service.dart';
import '../config/app_config.dart';
import '../widgets/app_scaffold.dart';

class OrdersScreen extends StatefulWidget {
  final User user;

  const OrdersScreen({Key? key, required this.user}) : super(key: key);

  @override
  State<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends State<OrdersScreen> {
  final OrderService _orderService = OrderService.instance;
  bool _isLoading = true;
  String? _error;
  List<Order> _activeOrders = [];
  List<Order> _orderHistory = [];

  @override
  void initState() {
    super.initState();
    _loadOrders();
  }

  Future<void> _loadOrders() async {
    if (!mounted) return;

    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final activeOrders = await _orderService.getOrders(widget.user, activeOnly: true);
      final allOrders = await _orderService.getOrders(widget.user);
      
      if (!mounted) return;
      
      setState(() {
        _activeOrders = activeOrders;
        _orderHistory = allOrders.where((order) => 
          !activeOrders.any((active) => active.id == order.id)
        ).toList();
        _isLoading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _isLoading = false;
      });
    }
  }

  Future<void> _cancelOrder(Order order) async {
    try {
      await _orderService.cancelOrder(widget.user, order.id);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Order cancelled successfully')),
      );
      _loadOrders();
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Failed to cancel order: $e')),
      );
    }
  }

  Future<void> _reorder(Order order) async {
    try {
      await _orderService.reorder(widget.user, order.id);
      if (!mounted) return;
      
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Order placed successfully')),
      );
      _loadOrders();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Failed to reorder: $e')),
      );
    }
  }

  Widget _buildStatusChip(String status) {
    Color color;
    IconData icon;
    
    switch (status.toLowerCase()) {
      case 'pending':
        color = Colors.orange;
        icon = Icons.pending;
        break;
      case 'confirmed':
        color = Colors.blue;
        icon = Icons.thumb_up;
        break;
      case 'preparing':
        color = Colors.amber;
        icon = Icons.restaurant;
        break;
      case 'ready':
        color = Colors.green;
        icon = Icons.check_circle;
        break;
      case 'out_for_delivery':
        color = Colors.purple;
        icon = Icons.delivery_dining;
        break;
      case 'delivered':
        color = Colors.green;
        icon = Icons.done_all;
        break;
      case 'cancelled':
        color = Colors.red;
        icon = Icons.cancel;
        break;
      default:
        color = Colors.grey;
        icon = Icons.help_outline;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 16, color: color),
          const SizedBox(width: 4),
          Text(
            status.replaceAll('_', ' ').toUpperCase(),
            style: TextStyle(
              color: color,
              fontWeight: FontWeight.w500,
              fontSize: 12,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildOrderCard(Order order, bool isActive) {
    return Card(
      margin: const EdgeInsets.only(bottom: 16),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'Order #${order.id}',
                  style: const TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 16,
                  ),
                ),
                _buildStatusChip(order.status),
              ],
            ),
            const Divider(height: 24),
            Text(
              order.vendorName,
              style: const TextStyle(
                fontWeight: FontWeight.w500,
              ),
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Icon(Icons.access_time, size: 16, color: Colors.grey[600]),
                const SizedBox(width: 4),
                Text(
                  order.estimatedDeliveryTime != null
                      ? 'Estimated delivery: ${_formatDateTime(order.estimatedDeliveryTime!)}'
                      : 'Ordered on ${_formatDateTime(order.orderDate)}',
                  style: TextStyle(
                    color: Colors.grey[600],
                    fontSize: 14,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            // Order Items
            ...order.items.map((item) => Padding(
              padding: const EdgeInsets.symmetric(vertical: 4),
              child: Row(
                children: [
                  Text(
                    '${item.quantity}x',
                    style: const TextStyle(
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(item.name),
                  ),
                  Text(
                    'Rs. ${(item.price * item.quantity).toStringAsFixed(2)}',
                    style: const TextStyle(
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
              ),
            )),
            const Divider(),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'Total: Rs. ${order.totalAmount.toStringAsFixed(2)}',
                  style: const TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 16,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  String _formatDateTime(DateTime dateTime) {
    return '${dateTime.day}/${dateTime.month}/${dateTime.year} ${dateTime.hour}:${dateTime.minute.toString().padLeft(2, '0')}';
  }

  @override
  Widget build(BuildContext context) {
    return AppScaffold(
      user: widget.user,
      title: 'Orders',
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
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
                        onPressed: _loadOrders,
                        child: const Text('Retry'),
                      ),
                    ],
                  ),
                )
              : DefaultTabController(
                  length: 2,
                  child: Column(
                    children: [
                      const TabBar(
                        tabs: [
                          Tab(text: 'Active Orders'),
                          Tab(text: 'Order History'),
                        ],
                      ),
                      Expanded(
                        child: TabBarView(
                          children: [
                            // Active Orders Tab
                            RefreshIndicator(
                              onRefresh: _loadOrders,
                              child: _activeOrders.isEmpty
                                  ? const Center(
                                      child: Text('No active orders'),
                                    )
                                  : ListView.builder(
                                      padding: const EdgeInsets.all(16),
                                      itemCount: _activeOrders.length,
                                      itemBuilder: (context, index) =>
                                          _buildOrderCard(_activeOrders[index], true),
                                    ),
                            ),
                            // Order History Tab
                            RefreshIndicator(
                              onRefresh: _loadOrders,
                              child: _orderHistory.isEmpty
                                  ? const Center(
                                      child: Text('No order history'),
                                    )
                                  : ListView.builder(
                                      padding: const EdgeInsets.all(16),
                                      itemCount: _orderHistory.length,
                                      itemBuilder: (context, index) =>
                                          _buildOrderCard(_orderHistory[index], false),
                                    ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
    );
  }
} 