import 'package:flutter/material.dart';
import '../models/user.dart';

class AppScaffold extends StatelessWidget {
  final User user;
  final String title;
  final Widget body;
  final List<Widget>? actions;
  final bool showDrawer;
  final Widget? floatingActionButton;

  const AppScaffold({
    Key? key,
    required this.user,
    required this.title,
    required this.body,
    this.actions,
    this.showDrawer = true,
    this.floatingActionButton,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(title),
        actions: actions,
        leading: showDrawer ? Builder(
          builder: (context) => IconButton(
            icon: const Icon(Icons.menu),
            onPressed: () {
              Scaffold.of(context).openDrawer();
            },
          ),
        ) : null,
      ),
      drawer: showDrawer ? _buildDrawer(context) : null,
      body: body,
      floatingActionButton: floatingActionButton,
    );
  }

  Widget _buildDrawer(BuildContext context) {
    return Drawer(
      child: ListView(
        padding: EdgeInsets.zero,
        children: [
          UserAccountsDrawerHeader(
            accountName: Text(user.username),
            accountEmail: Text(user.schoolName ?? 'Student'),
            currentAccountPicture: CircleAvatar(
              child: Text(
                user.username[0].toUpperCase(),
                style: const TextStyle(fontSize: 32),
              ),
            ),
            decoration: BoxDecoration(
              color: Theme.of(context).primaryColor,
            ),
          ),
          ListTile(
            leading: const Icon(Icons.home),
            title: const Text('Dashboard'),
            onTap: () {
              Navigator.pushNamedAndRemoveUntil(
                context,
                '/home',
                (route) => false,
                arguments: user,
              );
            },
          ),
          ListTile(
            leading: const Icon(Icons.restaurant_menu),
            title: const Text('Menu'),
            onTap: () {
              Navigator.pushNamed(
                context,
                '/menu',
                arguments: user,
              );
            },
          ),
          ListTile(
            leading: const Icon(Icons.shopping_cart),
            title: const Text('Cart'),
            onTap: () {
              Navigator.pushNamed(
                context,
                '/cart',
                arguments: user,
              );
            },
          ),
          ListTile(
            leading: const Icon(Icons.receipt_long),
            title: const Text('Orders'),
            onTap: () {
              Navigator.pushNamed(
                context,
                '/orders',
                arguments: user,
              );
            },
          ),
          const Divider(),
          ListTile(
            leading: const Icon(Icons.logout),
            title: const Text('Logout'),
            onTap: () {
              // TODO: Implement logout
              Navigator.pushNamedAndRemoveUntil(
                context,
                '/',
                (route) => false,
              );
            },
          ),
        ],
      ),
    );
  }
} 