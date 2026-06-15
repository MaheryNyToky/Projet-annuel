class Reservation {
  const Reservation({
    required this.id,
    required this.clientName,
    required this.phone,
    required this.email,
    required this.checkIn,
    required this.checkOut,
    required this.roomIds,
    this.extraBeds = 0,
    this.extraMattresses = 0,
  });

  final int id;
  final String clientName;
  final String phone;
  final String email;
  final DateTime checkIn;
  final DateTime checkOut;
  final List<int> roomIds;
  final int extraBeds;
  final int extraMattresses;

  factory Reservation.fromJson(Map<String, dynamic> json) {
    return Reservation(
      id: _asInt(json['id']),
      clientName: (json['client_name'] ?? '').toString(),
      phone: json['phone'] == 'N/A' ? '' : (json['phone'] ?? '').toString(),
      email: json['email'] == 'N/A' ? '' : (json['email'] ?? '').toString(),
      checkIn: DateTime.parse(json['check_in'].toString()),
      checkOut: DateTime.parse(json['check_out'].toString()),
      roomIds: (json['room_ids'] as List<dynamic>? ?? [])
          .map(_asInt)
          .where((id) => id > 0)
          .toList(),
      extraBeds: _asInt(json['extra_beds'] ?? 0),
      extraMattresses: _asInt(json['extra_mattresses'] ?? 0),
    );
  }

  bool get isEditable {
    final today = DateTime.now();
    final todayOnly = DateTime(today.year, today.month, today.day);
    final checkOutOnly = DateTime(checkOut.year, checkOut.month, checkOut.day);
    return checkOutOnly.isAfter(todayOnly) ||
        checkOutOnly.isAtSameMomentAs(todayOnly);
  }

  Map<String, dynamic> toUpdateJson({
    required String clientName,
    required String phone,
    required String email,
    required DateTime checkIn,
    required DateTime checkOut,
    required List<int> roomIds,
    int extraBeds = 0,
    int extraMattresses = 0,
  }) {
    return {
      'client_name': clientName,
      'customer_phone': phone,
      'customer_email': email,
      'check_in': checkIn.toIso8601String().substring(0, 10),
      'check_out': checkOut.toIso8601String().substring(0, 10),
      'room_ids': roomIds,
      'extra_beds': extraBeds,
      'extra_mattresses': extraMattresses,
    };
  }

  static int _asInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? 0;
  }
}
