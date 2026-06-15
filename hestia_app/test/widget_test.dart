import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hestia_app/main.dart';

void main() {
  testWidgets('App starts and shows Login page', (WidgetTester tester) async {
    await tester.pumpWidget(const KamoroApp());
    expect(find.text('Kamoro Hotel'), findsOneWidget);
    expect(find.byType(TextField), findsNWidgets(2));
    expect(find.text('Se connecter'), findsOneWidget);
  });
}
