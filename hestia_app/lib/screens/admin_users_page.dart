import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import '../core/app_config.dart';

const String baseUrl = AppConfig.apiBaseUrl;
const Color _primary = Color(0xFF0F766E);

class AdminUsersPage extends StatefulWidget {
  const AdminUsersPage({super.key});

  @override
  State<AdminUsersPage> createState() => _AdminUsersPageState();
}

class _AdminUsersPageState extends State<AdminUsersPage> {
  List<dynamic> _users = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _fetchUsers();
  }

  Future<void> _fetchUsers() async {
    if (mounted) {
      setState(() => _isLoading = true);
    }
    const cacheKey = 'admin_users_cache';
    try {
      final response = await http
          .get(Uri.parse('$baseUrl/api/users'))
          .timeout(const Duration(seconds: 5));
      if (!mounted) return;
      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString(cacheKey, json.encode(data));
        if (!mounted) return;
        setState(() => _users = data);
      }
    } catch (e) {
      final prefs = await SharedPreferences.getInstance();
      final cached = prefs.getString(cacheKey);
      if (!mounted) return;
      if (cached != null) {
        setState(() => _users = json.decode(cached));
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Mode dégradé: liste du personnel depuis le cache local.'),
            backgroundColor: Colors.orange,
          ),
        );
      } else {
        debugPrint("Error fetching users: $e");
      }
    }
    if (mounted) {
      setState(() => _isLoading = false);
    }
  }

  Future<void> _deleteUser(dynamic id) async {
    try {
      final response = await http.delete(Uri.parse('$baseUrl/api/users/$id'));
      if (response.statusCode == 200) {
        _fetchUsers();
      }
    } catch (e) {
      debugPrint("Error deleting user: $e");
    }
  }

  void _showUserForm({Map<String, dynamic>? user}) {
    final nameController = TextEditingController(text: user?['name'] ?? '');
    final emailController = TextEditingController(text: user?['email'] ?? '');
    final passwordController = TextEditingController();
    String role = user?['role'] ?? 'receptionist';

    showDialog(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setModalState) => AlertDialog(
          title: Text(
            user == null ? 'Ajouter un personnel' : 'Modifier le profil',
            style: const TextStyle(fontWeight: FontWeight.bold),
          ),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextField(
                  controller: nameController,
                  decoration: const InputDecoration(
                    labelText: 'Nom Complet',
                    prefixIcon: Icon(Icons.person),
                  ),
                ),
                TextField(
                  controller: emailController,
                  decoration: const InputDecoration(
                    labelText: 'Email Professionnel',
                    prefixIcon: Icon(Icons.email),
                  ),
                ),
                TextField(
                  controller: passwordController,
                  decoration: InputDecoration(
                    labelText: user == null
                        ? 'Mot de passe'
                        : 'Nouveau mot de passe (optionnel)',
                    prefixIcon: const Icon(Icons.lock),
                  ),
                  obscureText: true,
                ),
                const SizedBox(height: 15),
                const Text(
                  "Niveau d'accès :",
                  style: TextStyle(fontSize: 12, color: Colors.grey),
                ),
                DropdownButton<String>(
                  value: role,
                  isExpanded: true,
                  items: const [
                    DropdownMenuItem(
                      value: 'admin',
                      child: Text('Administrateur'),
                    ),
                    DropdownMenuItem(
                      value: 'receptionist',
                      child: Text('Réceptionniste'),
                    ),
                  ],
                  onChanged: (val) => setModalState(() => role = val!),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('Annuler'),
            ),
            ElevatedButton(
              onPressed: () async {
                final data = {
                  'name': nameController.text,
                  'email': emailController.text,
                  'role': role,
                };
                if (user != null) data['id'] = user['id'];
                if (passwordController.text.isNotEmpty) {
                  data['password'] = passwordController.text;
                }

                final url = user == null
                    ? '$baseUrl/api/users'
                    : '$baseUrl/api/users/update';

                try {
                  final resp = await http.post(
                    Uri.parse(url),
                    headers: {'Content-Type': 'application/json'},
                    body: json.encode(data),
                  ).timeout(const Duration(seconds: 5));
                  if (!context.mounted) return;
                  if (resp.statusCode == 200) {
                    Navigator.pop(context);
                    _fetchUsers();
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(content: Text('Personnel mis à jour')),
                    );
                  } else {
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(content: Text('Erreur: ${resp.body}')),
                    );
                  }
                } catch (e) {
                  if (!context.mounted) return;
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                      content: Text('Erreur de connexion serveur'),
                    ),
                  );
                }
              },
              child: const Text('Enregistrer'),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Gestion du Personnel'),
        actions: [
          IconButton(
            icon: const Icon(Icons.person_add),
            tooltip: 'Ajouter du personnel',
            onPressed: () => _showUserForm(),
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : Column(
              children: [
                Padding(
                  padding: const EdgeInsets.all(12.0),
                  child: ElevatedButton.icon(
                    onPressed: () => _showUserForm(),
                    icon: const Icon(Icons.add_circle_outline),
                    label: const Text('Ajouter un membre'),
                  ),
                ),
                Expanded(
                  child: ListView.builder(
                    itemCount: _users.length,
                    itemBuilder: (context, index) {
                      final user = _users[index];
                      return Card(
                        margin: const EdgeInsets.symmetric(
                          horizontal: 12,
                          vertical: 6,
                        ),
                        child: ListTile(
                          leading: CircleAvatar(
                            backgroundColor: user['role'] == 'admin'
                                ? const Color(0xFFFFF1F2)
                                : _primary.withValues(alpha: 0.10),
                            child: Icon(
                              user['role'] == 'admin'
                                  ? Icons.security
                                  : Icons.person,
                              color: user['role'] == 'admin'
                                  ? const Color(0xFFBE123C)
                                  : _primary,
                            ),
                          ),
                          title: Text(
                            user['name'],
                            style: const TextStyle(fontWeight: FontWeight.bold),
                          ),
                          subtitle: Text('${user['email']} (${user['role']})'),
                          trailing: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              IconButton(
                                icon: const Icon(Icons.edit, color: _primary),
                                onPressed: () => _showUserForm(user: user),
                              ),
                              IconButton(
                                icon: const Icon(
                                  Icons.delete,
                                  color: Colors.red,
                                ),
                                onPressed: () {
                                  showDialog(
                                    context: context,
                                    builder: (context) => AlertDialog(
                                      title: const Text('Supprimer ?'),
                                      content: Text(
                                        "Voulez-vous vraiment retirer ${user['name']} du staff ?",
                                      ),
                                      actions: [
                                        TextButton(
                                          onPressed: () =>
                                              Navigator.pop(context),
                                          child: const Text('Annuler'),
                                        ),
                                        TextButton(
                                          onPressed: () {
                                            Navigator.pop(context);
                                            _deleteUser(user['id']);
                                          },
                                          child: const Text(
                                            'Supprimer',
                                            style: TextStyle(color: Colors.red),
                                          ),
                                        ),
                                      ],
                                    ),
                                  );
                                },
                              ),
                            ],
                          ),
                        ),
                      );
                    },
                  ),
                ),
              ],
            ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _showUserForm(),
        icon: const Icon(Icons.add),
        label: const Text('Ajouter'),
      ),
    );
  }
}
