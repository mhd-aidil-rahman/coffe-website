import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile_gotham/api.dart';
import 'package:mobile_gotham/main.dart';
import 'package:mobile_gotham/portal.dart';

class FakeApi extends GothamApi {
  FakeApi(this.role, {this.reject = false});
  final String role;
  final bool reject;
  final calls = <String>[];
  String revision = 'initial';
  bool offline = false;
  int menuPrice = 22000;
  final extraOrders = <Map<String, dynamic>>[];
  @override
  Future<Map<String, dynamic>> request(
    String method,
    String path, {
    Map<String, dynamic>? body,
  }) async {
    calls.add('$method $path');
    if (path == '/sync') {
      if (offline) {
        throw const ApiException('Tidak dapat terhubung ke server.');
      }
      return {
        'data': {'revision': revision},
      };
    }
    if (path == '/auth/login') {
      if (reject) {
        throw const ApiException('Email atau password salah.', 401);
      }
      return {
        'data': {
          'token': 'test-token',
          'user': {'id': 1, 'name': 'Test User', 'role': role},
        },
      };
    }
    return {'data': <String, dynamic>{}};
  }

  @override
  Future<List<Map<String, dynamic>>> collection(String path) async {
    calls.add('GET $path');
    if (path == '/menu-items') {
      return [
        {
          'id': 7,
          'name': 'Kopi Susu Gotham',
          'price': menuPrice,
          'category_id': 1,
          'category_name': 'Kopi',
          'active': true,
          'description': 'Kopi susu gula aren',
        },
      ];
    }
    if (path == '/menu-categories') {
      return [
        {'id': 1, 'name': 'Kopi'},
      ];
    }
    if (path == '/orders') {
      return [
        ...extraOrders,
        {
          'id': 9,
          'customer_name': 'Rani',
          'waiter_name': 'Waiter Gotham',
          'total_amount': 44000,
          'status': 'sent_to_kitchen',
          'items': [
            {'name': 'Kopi Susu Gotham', 'quantity': 2, 'line_total': 44000},
          ],
        },
      ];
    }
    return [];
  }
}

void main() {
  testWidgets('Open edit dialogs preserve drafts while remote data changes', (
    tester,
  ) async {
    final api = FakeApi('Owner');
    await tester.pumpWidget(
      MaterialApp(
        home: Portal(
          api: api,
          user: const {'role': 'Owner', 'name': 'Owner'},
          onLogout: () {},
        ),
      ),
    );
    await tester.pumpAndSettle();
    await tester.tap(
      find.descendant(
        of: find.byType(NavigationRail),
        matching: find.text('Menu'),
      ),
    );
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.byTooltip('Edit menu').first);
    await tester.pumpAndSettle();
    await tester.tap(find.byTooltip('Edit menu').first);
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextFormField).first, 'Draft kopi baru');
    final requests = api.calls.length;
    api.revision = 'changed-during-edit';
    await tester.pump(const Duration(seconds: 2));
    expect(api.calls.length, requests);
    expect(find.text('Draft kopi baru'), findsOneWidget);
    await tester.tap(find.text('Batal'));
    await tester.pumpAndSettle();
    await tester.pump(const Duration(seconds: 1));
    await tester.pumpAndSettle();
    expect(api.calls.length, greaterThan(requests));
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox());
  });
  testWidgets(
    'Owner receives new orders automatically without reloading unchanged data',
    (tester) async {
      final api = FakeApi('Owner');
      await tester.pumpWidget(
        MaterialApp(
          home: Portal(
            api: api,
            user: const {'role': 'Owner', 'name': 'Owner'},
            onLogout: () {},
          ),
        ),
      );
      await tester.pumpAndSettle();
      final reads = api.calls.where((call) => call == 'GET /orders').length;
      await tester.pump(const Duration(seconds: 1));
      await tester.pumpAndSettle();
      expect(api.calls.where((call) => call == 'GET /orders').length, reads);
      api.extraOrders.add({
        'id': 10,
        'customer_name': 'Bruce',
        'waiter_name': 'Waiter',
        'total_amount': 22000,
        'status': 'sent_to_kitchen',
        'items': [],
      });
      api.revision = 'new-order';
      await tester.pump(const Duration(seconds: 1));
      await tester.pumpAndSettle();
      expect(find.text('Order #10 · Bruce'), findsOneWidget);
      expect(
        api.calls.where((call) => call == 'GET /orders').length,
        reads + 1,
      );
      await tester.pumpWidget(const SizedBox());
    },
  );

  testWidgets('Waiter cart and customer survive automatic price updates', (
    tester,
  ) async {
    final api = FakeApi('Waiter');
    await tester.pumpWidget(
      MaterialApp(
        home: Portal(
          api: api,
          user: const {'role': 'Waiter', 'name': 'Waiter'},
          onLogout: () {},
        ),
      ),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.byTooltip('Tambah'));
    await tester.enterText(find.byType(TextField).last, 'Dina');
    api.menuPrice = 25000;
    api.revision = 'price-changed';
    await tester.pump(const Duration(seconds: 1));
    await tester.pumpAndSettle();
    expect(find.text('Dina'), findsOneWidget);
    expect(find.text('Total: Rp 25.000'), findsOneWidget);
    expect(find.text('Kopi Susu Gotham × 1'), findsOneWidget);
    await tester.pumpWidget(const SizedBox());
  });

  testWidgets(
    'Polling recovers after connection loss and pauses in background',
    (tester) async {
      final api = FakeApi('Owner');
      await tester.pumpWidget(
        MaterialApp(
          home: Portal(
            api: api,
            user: const {'role': 'Owner', 'name': 'Owner'},
            onLogout: () {},
          ),
        ),
      );
      await tester.pumpAndSettle();
      api.offline = true;
      await tester.pump(const Duration(seconds: 1));
      await tester.pumpAndSettle();
      expect(find.text('Tidak dapat terhubung ke server.'), findsOneWidget);
      api.offline = false;
      await tester.pump(const Duration(seconds: 1));
      await tester.pumpAndSettle();
      expect(find.text('Tidak dapat terhubung ke server.'), findsNothing);
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.inactive);
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.hidden);
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.paused);
      final reads = api.calls.length;
      await tester.pump(const Duration(seconds: 3));
      expect(api.calls.length, reads);
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.hidden);
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.inactive);
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
      await tester.pumpAndSettle();
      expect(api.calls.length, greaterThan(reads));
      await tester.pumpWidget(const SizedBox());
    },
  );
  for (final size in [
    const Size(320, 640),
    const Size(390, 844),
    const Size(800, 1100),
    const Size(1200, 800),
  ]) {
    testWidgets('Login fits $size including large text', (tester) async {
      tester.view.physicalSize = size;
      tester.view.devicePixelRatio = 1;
      tester.platformDispatcher.textScaleFactorTestValue = 1.5;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      addTearDown(tester.platformDispatcher.clearTextScaleFactorTestValue);
      await tester.pumpWidget(MyApp(api: FakeApi('Owner')));
      await tester.pumpAndSettle();
      expect(find.text('Masuk ke akun Anda'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });
  }
  for (final role in ['Owner', 'Waiter', 'Barista']) {
    for (final size in [
      const Size(320, 740),
      const Size(1000, 900),
      const Size(844, 390),
    ]) {
      testWidgets('$role login routes to authorized portal at $size', (
        tester,
      ) async {
        tester.view.physicalSize = size;
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        final api = FakeApi(role);
        await tester.pumpWidget(MyApp(api: api));
        await tester.enterText(
          find.byType(TextFormField).at(0),
          'account@gotham.id',
        );
        await tester.enterText(find.byType(TextFormField).at(1), 'Gotham123!');
        final submit = find.text('Masuk ke Dashboard');
        await tester.ensureVisible(submit);
        await tester.tap(submit);
        await tester.pumpAndSettle();
        expect(
          find.text('GOTHAM CITY COFFEE / ${role.toUpperCase()}'),
          findsOneWidget,
        );
        expect(api.token, 'test-token');
        expect(api.calls.contains('GET /owner/staff'), role == 'Owner');
        expect(
          api.calls.any((v) => v.contains('/barista/operational')),
          role == 'Barista',
        );
        if (role != 'Owner') {
          expect(find.text('Staff'), findsNothing);
        }
        final tabs = role == 'Owner'
            ? ['Menu', 'Pesanan', 'Staff', 'Laporan']
            : role == 'Waiter'
            ? ['Pesanan', 'Buat Pesanan']
            : ['Menu', 'Pesanan', 'Operasional'];
        for (final tab in tabs) {
          final navigation = find.byType(NavigationRail).evaluate().isNotEmpty
              ? find.byType(NavigationRail)
              : find.byType(NavigationBar);
          await tester.tap(
            find.descendant(of: navigation, matching: find.text(tab)),
          );
          await tester.pumpAndSettle();
          expect(
            tester.takeException(),
            isNull,
            reason: '$role / $tab / $size',
          );
        }
        expect(tester.takeException(), isNull);
        await tester.pumpWidget(const SizedBox());
      });
    }
  }
  testWidgets('Invalid credentials keep user on login with error', (
    tester,
  ) async {
    await tester.pumpWidget(MyApp(api: FakeApi('Owner', reject: true)));
    await tester.enterText(find.byType(TextFormField).at(0), 'wrong@gotham.id');
    await tester.enterText(find.byType(TextFormField).at(1), 'wrong');
    await tester.ensureVisible(find.text('Masuk ke Dashboard'));
    await tester.tap(find.text('Masuk ke Dashboard'));
    await tester.pumpAndSettle();
    expect(find.text('Email atau password salah.'), findsOneWidget);
    expect(find.text('Masuk ke akun Anda'), findsOneWidget);
  });
}
