import 'dart:io';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import '../core/app_config.dart';
import '../models/reservation.dart';
import 'package:http/http.dart' as http;
import 'package:http_parser/http_parser.dart';

class CheckInPage extends StatefulWidget {
  final Reservation reservation;

  const CheckInPage({super.key, required this.reservation});

  @override
  State<CheckInPage> createState() => _CheckInPageState();
}

class _CheckInPageState extends State<CheckInPage> {
  final _formKey = GlobalKey<FormState>();
  final ImagePicker _picker = ImagePicker();

  String _fullName = '';
  String _contact = '';
  DateTime? _dateOfBirth;
  String _idType = 'CIN';
  String _idNumber = '';
  File? _idPhoto;
  bool _isLoading = false;

  final List<String> _idTypes = ['CIN', 'Passeport', 'Permis'];

  Future<void> _pickImage(ImageSource source) async {
    final XFile? pickedFile = await _picker.pickImage(source: source);
    if (pickedFile != null) {
      setState(() {
        _idPhoto = File(pickedFile.path);
      });
    }
  }

  Future<void> _selectDate() async {
    final DateTime? picked = await showDatePicker(
      context: context,
      initialDate: DateTime(2000),
      firstDate: DateTime(1900),
      lastDate: DateTime.now(),
    );
    if (picked != null && picked != _dateOfBirth) {
      setState(() {
        _dateOfBirth = picked;
      });
    }
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    if (_dateOfBirth == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Veuillez sélectionner la date de naissance'),
        ),
      );
      return;
    }

    _formKey.currentState!.save();
    setState(() => _isLoading = true);

    try {
      final uri = Uri.parse(
        '${AppConfig.apiBaseUrl}/api/reservations/${widget.reservation.id}/checkin',
      );

      var request = http.MultipartRequest('POST', uri);
      request.headers['Accept'] = 'application/json';

      request.fields['full_name'] = _fullName;
      request.fields['customer_phone'] = _contact;
      request.fields['date_of_birth'] = _dateOfBirth!.toIso8601String().split(
        'T',
      )[0];
      request.fields['id_type'] = _idType;
      request.fields['id_number'] = _idNumber;

      if (_idPhoto != null) {
        request.files.add(
          await http.MultipartFile.fromPath(
            'id_photo',
            _idPhoto!.path,
            contentType: MediaType('image', 'jpeg'), // Assuming jpeg for camera
          ),
        );
      }

      final streamedResponse = await request.send();
      final response = await http.Response.fromStream(streamedResponse);

      if (response.statusCode == 200 || response.statusCode == 201) {
        if (!mounted) return;
        Navigator.of(context).pop(true); // Return true to indicate success
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('Check-in réussi')));
      } else {
        throw Exception('Erreur ${response.statusCode}: ${response.body}');
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Erreur: $e')));
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  void initState() {
    super.initState();
    _fullName = widget.reservation.clientName;
    _contact = widget.reservation.phone;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Check-in (Fiche de Police)')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16.0),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                'Réservation #${widget.reservation.id}',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 16),
              TextFormField(
                initialValue: _fullName,
                decoration: const InputDecoration(
                  labelText: 'Nom et prénom',
                  border: OutlineInputBorder(),
                ),
                validator: (val) =>
                    val == null || val.isEmpty ? 'Le nom est requis' : null,
                onSaved: (val) => _fullName = val!,
              ),
              const SizedBox(height: 16),
              TextFormField(
                initialValue: _contact,
                decoration: const InputDecoration(
                  labelText: 'Contact',
                  border: OutlineInputBorder(),
                ),
                keyboardType: TextInputType.phone,
                onSaved: (val) => _contact = val?.trim() ?? '',
              ),
              const SizedBox(height: 16),
              ListTile(
                title: Text(
                  _dateOfBirth == null
                      ? 'Sélectionner la date de naissance'
                      : 'Date de naissance : ${_dateOfBirth!.toIso8601String().split('T')[0]}',
                ),
                trailing: const Icon(Icons.calendar_today),
                shape: RoundedRectangleBorder(
                  side: const BorderSide(color: Colors.grey),
                  borderRadius: BorderRadius.circular(4),
                ),
                onTap: _selectDate,
              ),
              const SizedBox(height: 16),
              DropdownButtonFormField<String>(
                initialValue: _idType,
                decoration: const InputDecoration(
                  labelText: 'Type de Pièce d\'Identité',
                  border: OutlineInputBorder(),
                ),
                items: _idTypes.map((type) {
                  return DropdownMenuItem(value: type, child: Text(type));
                }).toList(),
                onChanged: (val) => setState(() => _idType = val!),
              ),
              const SizedBox(height: 16),
              TextFormField(
                decoration: const InputDecoration(
                  labelText: 'Numéro de Pièce',
                  border: OutlineInputBorder(),
                ),
                validator: (val) =>
                    val == null || val.isEmpty ? 'Le numéro est requis' : null,
                onSaved: (val) => _idNumber = val!,
              ),
              const SizedBox(height: 24),
              Row(
                children: [
                  Expanded(
                    child: ElevatedButton.icon(
                      onPressed: () => _pickImage(ImageSource.camera),
                      icon: const Icon(Icons.camera_alt),
                      label: const Text('Photo'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () => _pickImage(ImageSource.gallery),
                      icon: const Icon(Icons.upload_file),
                      label: const Text('Importer'),
                    ),
                  ),
                ],
              ),
              if (_idPhoto != null) ...[
                const SizedBox(height: 8),
                Image.file(_idPhoto!, height: 200, fit: BoxFit.cover),
              ],
              const SizedBox(height: 32),
              ElevatedButton(
                onPressed: _isLoading ? null : _submit,
                style: ElevatedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(vertical: 16),
                ),
                child: _isLoading
                    ? const CircularProgressIndicator()
                    : const Text('Valider le Check-in'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
