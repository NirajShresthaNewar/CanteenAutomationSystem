class Order {
  final int id;
  final int userId;
  final int vendorId;
  final String vendorName;
  final String orderType;
  final String paymentMethod;
  final String status;
  final double totalAmount;
  final DateTime orderDate;
  final List<OrderItem> items;
  final String? deliveryLocation;
  final String? buildingName;
  final String? floorNumber;
  final String? roomNumber;
  final String? contactNumber;
  final String? deliveryInstructions;
  final String? tableNumber;
  final DateTime? estimatedDeliveryTime;

  Order({
    required this.id,
    required this.userId,
    required this.vendorId,
    required this.vendorName,
    required this.orderType,
    required this.paymentMethod,
    required this.status,
    required this.totalAmount,
    required this.orderDate,
    required this.items,
    this.deliveryLocation,
    this.buildingName,
    this.floorNumber,
    this.roomNumber,
    this.contactNumber,
    this.deliveryInstructions,
    this.tableNumber,
    this.estimatedDeliveryTime,
  });

  factory Order.fromJson(Map<String, dynamic> json) {
    return Order(
      id: json['order_id'],
      userId: json['user_id'],
      vendorId: json['vendor_id'],
      vendorName: json['vendor_name'] ?? 'Unknown Vendor',
      orderType: json['order_type'],
      paymentMethod: json['payment_method'],
      status: json['status'],
      totalAmount: double.parse(json['total_amount'].toString()),
      orderDate: DateTime.parse(json['order_date']),
      items: (json['items'] as List)
          .map((item) => OrderItem.fromJson(item))
          .toList(),
      deliveryLocation: json['delivery_location'],
      buildingName: json['building_name'],
      floorNumber: json['floor_number'],
      roomNumber: json['room_number'],
      contactNumber: json['contact_number'],
      deliveryInstructions: json['delivery_instructions'],
      tableNumber: json['table_number'],
      estimatedDeliveryTime: json['estimated_delivery_time'] != null
          ? DateTime.parse(json['estimated_delivery_time'])
          : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'order_id': id,
      'user_id': userId,
      'vendor_id': vendorId,
      'vendor_name': vendorName,
      'order_type': orderType,
      'payment_method': paymentMethod,
      'status': status,
      'total_amount': totalAmount,
      'order_date': orderDate.toIso8601String(),
      'items': items.map((item) => item.toJson()).toList(),
      'delivery_location': deliveryLocation,
      'building_name': buildingName,
      'floor_number': floorNumber,
      'room_number': roomNumber,
      'contact_number': contactNumber,
      'delivery_instructions': deliveryInstructions,
      'table_number': tableNumber,
      'estimated_delivery_time':
          estimatedDeliveryTime?.toIso8601String(),
    };
  }
}

class OrderItem {
  final int menuItemId;
  final String name;
  final int quantity;
  final double price;
  final String? imagePath;

  OrderItem({
    required this.menuItemId,
    required this.name,
    required this.quantity,
    required this.price,
    this.imagePath,
  });

  factory OrderItem.fromJson(Map<String, dynamic> json) {
    return OrderItem(
      menuItemId: json['menu_item_id'],
      name: json['name'],
      quantity: json['quantity'],
      price: double.parse(json['price'].toString()),
      imagePath: json['image_path'],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'menu_item_id': menuItemId,
      'name': name,
      'quantity': quantity,
      'price': price,
      'image_path': imagePath,
    };
  }
} 