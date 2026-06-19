import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hestia_app/main.dart';
import 'package:hestia_app/screens/folio_page.dart';

void main() {
  testWidgets('App starts and shows Login page', (WidgetTester tester) async {
    await tester.pumpWidget(const KamoroApp());
    expect(find.text('Kamoro Hotel'), findsOneWidget);
    expect(find.byType(TextField), findsNWidgets(2));
    expect(find.text('Se connecter'), findsOneWidget);
  });

  testWidgets('Receptionist cannot access folio before check-in', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: FolioPage(
          reservation: {
            'id': 1,
            'status': 'en_attente',
            'client_name': 'Test Client',
            'room_numbers': '101',
          },
          userName: 'Reception Test',
          role: 'receptionist',
        ),
      ),
    );

    await tester.pump();
    expect(find.text('Folio et facturation'), findsOneWidget);
    expect(find.text('Le folio est réservé après le check-in, sauf pour les administrateurs.'), findsOneWidget);
    expect(find.text('Retour'), findsOneWidget);
  });
}
