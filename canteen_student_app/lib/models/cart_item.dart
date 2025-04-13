class CartItem {
  final int itemId;
  final int vendorId;
  final String name;
  final String? description;
  final double price;
  final String? imagePath;
  int quantity;

  CartItem({
    required this.itemId,
    required this.vendorId,
    required this.name,
    this.description,
    required this.price,
    this.imagePath,
    this.quantity = 1,
  });

  double get totalPrice => price * quantity;

  Map<String, dynamic> toJson() {
    return {
      'item_id': itemId,
      'vendor_id': vendorId,
      'name': name,
      'description': description,
      'price': price,
      'image_path': imagePath,
      'quantity': quantity,
    };
  }

  factory CartItem.fromJson(Map<String, dynamic> json) {
    return CartItem(
      itemId: json['item_id'],
      vendorId: json['vendor_id'],
      name: json['name'],
      description: json['description'],
      price: json['price'].toDouble(),
      imagePath: json['image_path'],
      quantity: json['quantity'] ?? 1,
    );
  }
} 