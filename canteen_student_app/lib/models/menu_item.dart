class MenuItem {
  final int id;
  final String name;
  final String description;
  final double price;
  final String? imagePath;
  final String vendorName;
  final bool isVegetarian;
  final bool isVegan;
  final bool isGlutenFree;
  final bool isAvailable;
  final double rating;
  final int estimatedTime;
  final String category;

  MenuItem({
    required this.id,
    required this.name,
    required this.description,
    required this.price,
    this.imagePath,
    required this.vendorName,
    this.isVegetarian = false,
    this.isVegan = false,
    this.isGlutenFree = false,
    this.isAvailable = true,
    this.rating = 0.0,
    this.estimatedTime = 15,
    required this.category,
  });

  // Factory constructor to create a MenuItem from JSON data
  factory MenuItem.fromJson(Map<String, dynamic> json) {
    return MenuItem(
      id: json['item_id'] as int,
      name: json['name'] as String,
      description: json['description'] as String? ?? 'No description available',
      price: (json['price'] as num).toDouble(),
      imagePath: json['image_path'] as String?,
      vendorName: json['vendor_name'] as String? ?? 'Unknown Vendor',
      isVegetarian: json['is_vegetarian'] as bool? ?? false,
      isVegan: json['is_vegan'] as bool? ?? false,
      isGlutenFree: json['is_gluten_free'] as bool? ?? false,
      isAvailable: json['is_available'] as bool? ?? true,
      rating: (json['rating'] as num?)?.toDouble() ?? 0.0,
      estimatedTime: json['estimated_time'] as int? ?? 15,
      category: json['category_name'] as String? ?? 'Other',
    );
  }

  // Convert MenuItem to JSON
  Map<String, dynamic> toJson() {
    return {
      'item_id': id,
      'name': name,
      'description': description,
      'price': price,
      'image_path': imagePath,
      'vendor_name': vendorName,
      'is_vegetarian': isVegetarian,
      'is_vegan': isVegan,
      'is_gluten_free': isGlutenFree,
      'is_available': isAvailable,
      'rating': rating,
      'estimated_time': estimatedTime,
      'category_name': category,
    };
  }
} 