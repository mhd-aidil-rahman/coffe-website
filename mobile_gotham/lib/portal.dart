import 'dart:async';
import 'package:flutter/material.dart';
import 'api.dart';
import 'main.dart' show green;

String rupiah(dynamic value) =>
    'Rp ${(num.tryParse('$value') ?? 0).round().toString().replaceAllMapped(RegExp(r'(\d)(?=(\d{3})+(?!\d))'), (m) => '${m[1]}.')}';
String statusLabel(dynamic value) =>
    const {
      'sent_to_kitchen': 'Dikirim ke dapur',
      'cancellation_pending': 'Menunggu pembatalan',
      'completed': 'Selesai',
      'cancelled': 'Dibatalkan',
    }[value] ??
    '$value';

class Portal extends StatefulWidget {
  const Portal({
    super.key,
    required this.api,
    required this.user,
    required this.onLogout,
  });
  final GothamApi api;
  final Map<String, dynamic> user;
  final VoidCallback onLogout;
  @override
  State<Portal> createState() => _PortalState();
}

class _PortalState extends State<Portal> with WidgetsBindingObserver {
  String get role => widget.user['role'] as String;
  List<String> get tabs => role == 'Owner'
      ? ['Dashboard', 'Menu', 'Pesanan', 'Staff', 'Laporan']
      : role == 'Waiter'
      ? ['Buat Pesanan', 'Pesanan']
      : ['Operasional', 'Menu', 'Pesanan'];
  int selected = 0;
  bool loading = true, working = false, refreshing = false;
  String? error;
  String search = '', category = 'Semua', period = 'today';
  Map<String, dynamic> overview = {}, report = {};
  List<Map<String, dynamic>> menus = [],
      categories = [],
      orders = [],
      staff = [];
  final Map<int, int> cart = {};
  final customer = TextEditingController();
  Timer? timer;
  String? revision;
  bool checkingRevision = false;
  bool suspended = false;
  int openDialogs = 0;
  DateTimeRange? range;
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    refresh();
    startPolling();
  }

  void startPolling() {
    timer?.cancel();
    timer = Timer.periodic(
      const Duration(seconds: 1),
      (_) => checkForChanges(),
    );
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      suspended = false;
      refresh(silent: true);
      startPolling();
    } else {
      suspended = true;
      timer?.cancel();
    }
  }

  @override
  void dispose() {
    timer?.cancel();
    customer.dispose();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  Future<void> refresh({bool silent = false}) async {
    if (refreshing || working) {
      return;
    }
    refreshing = true;
    if (!silent) {
      setState(() {
        loading = true;
        error = null;
      });
    }
    try {
      final syncResponse = await widget.api.request('GET', '/sync');
      final fetchedRevision = syncResponse['data']['revision'] as String?;
      final results = await Future.wait([
        widget.api.collection('/menu-items'),
        widget.api.collection('/menu-categories'),
        widget.api.collection('/orders'),
        if (role == 'Owner') widget.api.collection('/owner/staff'),
        if (role == 'Owner') widget.api.request('GET', '/owner/dashboard'),
        if (role == 'Owner')
          widget.api.request(
            'GET',
            '/owner/reports/financial${range == null ? '' : '?start_date=${date(range!.start)}&end_date=${date(range!.end)}'}',
          ),
        if (role == 'Barista')
          widget.api.request('GET', '/barista/operational?period=$period'),
      ]);
      if (!mounted || suspended || openDialogs > 0) {
        return;
      }
      final updatedOrders = results[2] as List<Map<String, dynamic>>;
      final previousStatuses = {
        for (final order in orders) order['id']: order['status'],
      };
      final completed = updatedOrders.where(
        (order) =>
            order['status'] == 'completed' &&
            previousStatuses.containsKey(order['id']) &&
            previousStatuses[order['id']] != 'completed',
      );
      for (final order in completed) {
        message(
          'Pesanan #${order['id']} selesai · ${rupiah(order['total_amount'])}',
        );
      }
      setState(() {
        menus = results[0] as List<Map<String, dynamic>>;
        categories = results[1] as List<Map<String, dynamic>>;
        orders = results[2] as List<Map<String, dynamic>>;
        if (role == 'Owner') {
          staff = results[3] as List<Map<String, dynamic>>;
          overview = (results[4] as Map)['data'] as Map<String, dynamic>;
          report = (results[5] as Map)['data'] as Map<String, dynamic>;
        }
        if (role == 'Barista') {
          overview = (results[3] as Map)['data'] as Map<String, dynamic>;
        }
        error = null;
        revision = fetchedRevision;
      });
    } catch (e) {
      if (!mounted) {
        return;
      }
      if (e is ApiException && e.status == 401) {
        widget.onLogout();
        return;
      }
      setState(() => error = '$e');
    } finally {
      refreshing = false;
      if (mounted) {
        setState(() => loading = false);
      }
    }
  }

  Future<void> checkForChanges() async {
    if (!mounted ||
        suspended ||
        checkingRevision ||
        refreshing ||
        working ||
        openDialogs > 0) {
      return;
    }
    checkingRevision = true;
    try {
      final response = await widget.api.request('GET', '/sync');
      if (!mounted || suspended || openDialogs > 0) {
        return;
      }
      final nextRevision = response['data']['revision'] as String?;
      if (error != null || nextRevision != revision) {
        await refresh(silent: true);
      }
    } catch (e) {
      if (!mounted) {
        return;
      }
      if (e is ApiException && (e.status == 401 || e.status == 403)) {
        widget.onLogout();
      } else {
        setState(() => error = '$e');
      }
    } finally {
      checkingRevision = false;
    }
  }

  String date(DateTime d) =>
      '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';
  void message(String text) {
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));
    }
  }

  Future<Map<String, dynamic>?> mutate(
    String method,
    String path, {
    Map<String, dynamic>? body,
  }) async {
    if (working) {
      return null;
    }
    setState(() => working = true);
    Map<String, dynamic>? result;
    try {
      result = await widget.api.request(method, path, body: body);
      message('Berhasil disimpan.');
    } catch (e) {
      if (e is ApiException && e.status == 401) {
        widget.onLogout();
      } else {
        message('$e');
      }
    } finally {
      if (mounted) {
        setState(() => working = false);
      }
    }
    if (mounted && result != null) {
      await refresh(silent: true);
    }
    return result;
  }

  Future<T?> trackedDialog<T>({
    required BuildContext context,
    required WidgetBuilder builder,
  }) async {
    openDialogs++;
    try {
      return await showDialog<T>(context: context, builder: builder);
    } finally {
      openDialogs--;
    }
  }

  Future<bool> confirm(String text) async =>
      await trackedDialog<bool>(
        context: context,
        builder: (c) => AlertDialog(
          title: const Text('Konfirmasi'),
          content: Text(text),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(c, false),
              child: const Text('Batal'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(c, true),
              child: const Text('Lanjutkan'),
            ),
          ],
        ),
      ) ??
      false;
  Future<Map<String, dynamic>?> edit(
    String title,
    Map<String, String> fields, {
    Map<String, List<String>> choices = const {},
    Set<String> optional = const {},
    Set<String> numeric = const {},
    Set<String> secret = const {},
  }) async {
    final controllers = fields.map(
      (k, v) => MapEntry(k, TextEditingController(text: v)),
    );
    final form = GlobalKey<FormState>();
    final result = await trackedDialog<Map<String, dynamic>>(
      context: context,
      builder: (c) => AlertDialog(
        title: Text(title),
        content: SizedBox(
          width: 460,
          child: SingleChildScrollView(
            child: Form(
              key: form,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: fields.keys
                    .map(
                      (key) => Padding(
                        padding: const EdgeInsets.only(bottom: 14),
                        child: choices.containsKey(key)
                            ? DropdownButtonFormField<String>(
                                initialValue: controllers[key]!.text.isEmpty
                                    ? choices[key]!.first
                                    : controllers[key]!.text,
                                isExpanded: true,
                                decoration: InputDecoration(
                                  labelText: label(key),
                                ),
                                items: choices[key]!
                                    .map(
                                      (v) => DropdownMenuItem(
                                        value: v,
                                        child: Text(
                                          key == 'category_id'
                                              ? '${categories.firstWhere((category) => '${category['id']}' == v)['name']}'
                                              : v,
                                        ),
                                      ),
                                    )
                                    .toList(),
                                onChanged: (v) => controllers[key]!.text = v!,
                              )
                            : TextFormField(
                                controller: controllers[key],
                                obscureText: secret.contains(key),
                                keyboardType: numeric.contains(key)
                                    ? TextInputType.number
                                    : TextInputType.text,
                                decoration: InputDecoration(
                                  labelText: label(key),
                                ),
                                validator: (v) {
                                  if (!optional.contains(key) &&
                                      (v == null || v.trim().isEmpty)) {
                                    return 'Wajib diisi.';
                                  }
                                  if (numeric.contains(key) &&
                                      (int.tryParse(v ?? '') ?? 0) < 1) {
                                    return 'Masukkan angka lebih dari 0.';
                                  }
                                  return null;
                                },
                              ),
                      ),
                    )
                    .toList(),
              ),
            ),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(c),
            child: const Text('Batal'),
          ),
          FilledButton(
            onPressed: () {
              if (form.currentState!.validate()) {
                Navigator.pop(
                  c,
                  controllers.map(
                    (k, v) => MapEntry(
                      k,
                      numeric.contains(k)
                          ? int.parse(v.text)
                          : k == 'password'
                          ? v.text
                          : v.text.trim(),
                    ),
                  ),
                );
              }
            },
            child: const Text('Simpan'),
          ),
        ],
      ),
    );
    // Wait for the dialog's exit animation before releasing its controllers.
    await Future<void>.delayed(const Duration(milliseconds: 300));
    for (final controller in controllers.values) {
      controller.dispose();
    }
    return result;
  }

  String label(String key) =>
      const {
        'name': 'Nama',
        'price': 'Harga',
        'category_id': 'Kategori',
        'description': 'Deskripsi',
        'image_path': 'Path / URL gambar',
        'email': 'Email',
        'password': 'Password',
        'position': 'Role',
        'type': 'Jenis',
        'category': 'Kategori',
        'amount': 'Nominal',
        'notes': 'Catatan',
        'reason': 'Alasan pembatalan',
        'void_code': 'Kode void',
      }[key] ??
      key;
  @override
  Widget build(BuildContext context) {
    final screen = MediaQuery.sizeOf(context);
    final wide = screen.width >= 760 && screen.height >= 500;
    final body = RefreshIndicator(
      onRefresh: refresh,
      child: ListView(
        padding: EdgeInsets.all(wide ? 28 : 16),
        children: [
          Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 1300),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(
                    'GOTHAM CITY COFFEE / ${role.toUpperCase()}',
                    style: const TextStyle(
                      color: green,
                      fontSize: 12,
                      letterSpacing: 1.5,
                    ),
                  ),
                  const SizedBox(height: 10),
                  Text(
                    tabs[selected],
                    style: const TextStyle(
                      fontSize: 30,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  Text(
                    'Halo, ${widget.user['name']}',
                    style: const TextStyle(color: Colors.black54),
                  ),
                  const SizedBox(height: 22),
                  if (loading || working) const LinearProgressIndicator(),
                  if (error != null)
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          children: [
                            Text(error!),
                            TextButton(
                              onPressed: () => refresh(),
                              child: const Text('Coba lagi'),
                            ),
                          ],
                        ),
                      ),
                    ),
                  if (!loading) ...content(),
                  const SizedBox(height: 50),
                ],
              ),
            ),
          ),
        ],
      ),
    );
    return Scaffold(
      appBar: AppBar(
        title: const Text('Gotham City Coffee'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: loading ? null : () => refresh(),
            icon: const Icon(Icons.refresh),
          ),
          IconButton(
            tooltip: 'Keluar',
            onPressed: working
                ? null
                : () async {
                    if (await confirm('Keluar dari akun Anda?')) {
                      if (await mutate('DELETE', '/auth/logout') != null) {
                        widget.onLogout();
                      }
                    }
                  },
            icon: const Icon(Icons.logout),
          ),
        ],
      ),
      body: Row(
        children: [
          if (wide)
            NavigationRail(
              selectedIndex: selected,
              labelType: NavigationRailLabelType.all,
              onDestinationSelected: select,
              destinations: [
                for (var i = 0; i < tabs.length; i++)
                  NavigationRailDestination(
                    icon: Icon(tabIcon(tabs[i])),
                    label: Text(tabs[i]),
                  ),
              ],
            ),
          Expanded(child: body),
        ],
      ),
      bottomNavigationBar: wide
          ? null
          : NavigationBar(
              selectedIndex: selected,
              onDestinationSelected: select,
              destinations: [
                for (final tab in tabs)
                  NavigationDestination(icon: Icon(tabIcon(tab)), label: tab),
              ],
            ),
    );
  }

  IconData tabIcon(String tab) => switch (tab) {
    'Dashboard' => Icons.dashboard_outlined,
    'Menu' || 'Buat Pesanan' => Icons.restaurant_menu,
    'Staff' => Icons.people_outline,
    'Laporan' => Icons.insights,
    'Operasional' => Icons.account_balance_wallet_outlined,
    _ => Icons.receipt_long_outlined,
  };
  void select(int value) => setState(() {
    selected = value;
    search = '';
    category = 'Semua';
  });
  List<Widget> content() => switch (tabs[selected]) {
    'Dashboard' => [
      ...metrics({
        'Total Pesanan': overview['total_orders'],
        'Pendapatan': rupiah(overview['income']),
        'Order Aktif': overview['active_orders'],
        'Pembatalan Pending': overview['pending_cancellations'],
      }),
      heading('Pesanan Terbaru'),
      ...orders.take(5).map(orderCard),
      heading('Semua Menu'),
      ...menuContent(),
    ],
    'Buat Pesanan' => menuContent(ordering: true),
    'Menu' => menuContent(),
    'Pesanan' => [
      if (orders.isEmpty) empty('Belum ada pesanan.'),
      ...orders.map(orderCard),
    ],
    'Staff' => staffContent(),
    'Laporan' => reportContent(),
    _ => operationalContent(),
  };
  Widget heading(String text) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 18),
    child: Text(
      text,
      style: const TextStyle(fontSize: 21, fontWeight: FontWeight.bold),
    ),
  );
  Widget empty(String text) => Padding(
    padding: const EdgeInsets.all(28),
    child: Center(
      child: Text(text, style: const TextStyle(color: Colors.black54)),
    ),
  );
  List<Widget> metrics(Map<String, dynamic> values) => [
    LayoutBuilder(
      builder: (c, b) {
        final columns = b.maxWidth > 850
            ? 4
            : b.maxWidth > 430
            ? 2
            : 1;
        return Wrap(
          spacing: 12,
          runSpacing: 12,
          children: values.entries
              .map(
                (e) => SizedBox(
                  width: (b.maxWidth - (columns - 1) * 12) / columns,
                  child: Card(
                    margin: EdgeInsets.zero,
                    child: Padding(
                      padding: const EdgeInsets.all(20),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(e.key, style: const TextStyle(color: green)),
                          const SizedBox(height: 14),
                          Text(
                            '${e.value ?? 0}',
                            style: const TextStyle(
                              fontSize: 24,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              )
              .toList(),
        );
      },
    ),
    const SizedBox(height: 16),
  ];
  List<Widget> menuContent({bool ordering = false}) {
    final filtered = menus
        .where(
          (m) =>
              '${m['name']} ${m['category_name']}'.toLowerCase().contains(
                search.toLowerCase(),
              ) &&
              (category == 'Semua' || '${m['category_id']}' == category),
        )
        .toList();
    return [
      TextField(
        decoration: const InputDecoration(
          hintText: 'Cari menu',
          prefixIcon: Icon(Icons.search),
        ),
        onChanged: (v) => setState(() => search = v),
      ),
      const SizedBox(height: 12),
      SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: Row(
          children: [
            ChoiceChip(
              label: const Text('Semua'),
              selected: category == 'Semua',
              onSelected: (_) => setState(() => category = 'Semua'),
            ),
            for (final c in categories)
              Padding(
                padding: const EdgeInsets.only(left: 8),
                child: ChoiceChip(
                  label: Text('${c['name']}'),
                  selected: category == '${c['id']}',
                  onSelected: (_) => setState(() => category = '${c['id']}'),
                ),
              ),
          ],
        ),
      ),
      if (!ordering && role != 'Waiter')
        Wrap(
          spacing: 8,
          children: [
            TextButton.icon(
              onPressed: working ? null : () => editMenu(),
              icon: const Icon(Icons.add),
              label: const Text('Tambah menu'),
            ),
            TextButton.icon(
              onPressed: working ? null : manageCategories,
              icon: const Icon(Icons.category_outlined),
              label: const Text('Kelola kategori'),
            ),
          ],
        ),
      const SizedBox(height: 12),
      if (filtered.isEmpty) empty('Tidak ada menu.'),
      LayoutBuilder(
        builder: (c, b) {
          final columns = b.maxWidth >= 1050
              ? 3
              : b.maxWidth >= 660
              ? 2
              : 1;
          return Wrap(
            spacing: 12,
            runSpacing: 12,
            children: filtered
                .map(
                  (m) => SizedBox(
                    width: (b.maxWidth - (columns - 1) * 12) / columns,
                    child: menuCard(m, ordering),
                  ),
                )
                .toList(),
          );
        },
      ),
      if (ordering) ...[
        heading('Ringkasan Pesanan'),
        TextField(
          controller: customer,
          decoration: const InputDecoration(
            labelText: 'Nama pelanggan (opsional)',
          ),
        ),
        const SizedBox(height: 12),
        for (final m in menus.where((m) => (cart[m['id']] ?? 0) > 0))
          ListTile(
            contentPadding: EdgeInsets.zero,
            title: Text('${m['name']} × ${cart[m['id']]}'),
            trailing: Text(rupiah((m['price'] as num) * cart[m['id']]!)),
          ),
        Text(
          'Total: ${rupiah(menus.fold<num>(0, (sum, m) => sum + (m['price'] as num) * (cart[m['id']] ?? 0)))}',
          style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold),
        ),
        const SizedBox(height: 16),
        FilledButton.icon(
          onPressed: working || !cart.values.any((v) => v > 0)
              ? null
              : submitOrder,
          icon: const Icon(Icons.send_outlined),
          label: const Text('Kirim ke Dapur'),
        ),
      ],
    ];
  }

  Widget menuCard(Map<String, dynamic> m, bool ordering) => Card(
    margin: EdgeInsets.zero,
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(12),
                child: m['image_path'] == null
                    ? const SizedBox(
                        width: 68,
                        height: 68,
                        child: Icon(Icons.local_cafe, size: 36, color: green),
                      )
                    : Image.network(
                        widget.api.imageUrl('${m['image_path']}'),
                        width: 68,
                        height: 68,
                        fit: BoxFit.cover,
                        errorBuilder: (_, e, s) => const SizedBox(
                          width: 68,
                          height: 68,
                          child: Icon(Icons.local_cafe, color: green),
                        ),
                      ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '${m['name']}',
                      style: const TextStyle(
                        fontSize: 17,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    Text(
                      '${m['category_name'] ?? ''}',
                      style: const TextStyle(color: Colors.black54),
                    ),
                    Text(
                      rupiah(m['price']),
                      style: const TextStyle(
                        color: green,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          if ('${m['description'] ?? ''}'.isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 10),
              child: Text('${m['description']}'),
            ),
          if (ordering)
            Row(
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                IconButton(
                  tooltip: 'Kurangi',
                  onPressed: working || (cart[m['id']] ?? 0) == 0
                      ? null
                      : () => setState(
                          () => cart[m['id']] = (cart[m['id']] ?? 0) - 1,
                        ),
                  icon: const Icon(Icons.remove_circle_outline),
                ),
                Text('${cart[m['id']] ?? 0}'),
                IconButton(
                  tooltip: 'Tambah',
                  onPressed: working || (cart[m['id']] ?? 0) >= 99
                      ? null
                      : () => setState(
                          () => cart[m['id']] = (cart[m['id']] ?? 0) + 1,
                        ),
                  icon: const Icon(Icons.add_circle, color: green),
                ),
              ],
            ),
          if (!ordering && role != 'Waiter')
            Row(
              children: [
                Expanded(
                  child: SwitchListTile(
                    contentPadding: EdgeInsets.zero,
                    title: const Text('Aktif'),
                    value: m['active'] == true,
                    onChanged: working
                        ? null
                        : (v) => mutate(
                            'PATCH',
                            '/menu-items/${m['id']}/availability',
                            body: {'active': v},
                          ),
                  ),
                ),
                IconButton(
                  tooltip: 'Edit menu',
                  onPressed: working ? null : () => editMenu(m),
                  icon: const Icon(Icons.edit_outlined),
                ),
                IconButton(
                  tooltip: 'Hapus menu',
                  onPressed: working
                      ? null
                      : () async {
                          if (await confirm('Hapus ${m['name']}?')) {
                            await mutate('DELETE', '/menu-items/${m['id']}');
                          }
                        },
                  icon: const Icon(Icons.delete_outline),
                ),
              ],
            ),
        ],
      ),
    ),
  );
  Future<void> submitOrder() async {
    if (!await confirm('Kirim pesanan ini ke dapur?')) {
      return;
    }
    final result = await mutate(
      'POST',
      '/orders',
      body: {
        'customer_name': customer.text.trim().isEmpty
            ? null
            : customer.text.trim(),
        'items': [
          for (final e in cart.entries.where((e) => e.value > 0))
            {'menu_item_id': e.key, 'quantity': e.value},
        ],
      },
    );
    if (result != null && mounted) {
      setState(() {
        cart.clear();
        customer.clear();
        selected = 1;
      });
    }
  }

  Future<void> editMenu([Map<String, dynamic>? item]) async {
    if (categories.isEmpty) {
      message('Tambahkan kategori terlebih dahulu.');
      return;
    }
    final data = await edit(
      item == null ? 'Tambah Menu' : 'Edit Menu',
      {
        'name': '${item?['name'] ?? ''}',
        'price': '${item?['price'] ?? ''}',
        'category_id': '${item?['category_id'] ?? categories.first['id']}',
        'description': '${item?['description'] ?? ''}',
        'image_path': '${item?['image_path'] ?? ''}',
      },
      choices: {'category_id': categories.map((c) => '${c['id']}').toList()},
      numeric: {'price'},
      optional: {'description', 'image_path'},
    );
    if (data != null) {
      data['category_id'] = int.parse(data['category_id'] as String);
      await mutate(
        item == null ? 'POST' : 'PATCH',
        '/menu-items${item == null ? '' : '/${item['id']}'}',
        body: data,
      );
    }
  }

  Future<void> manageCategories() async {
    final selection = await trackedDialog<String>(
      context: context,
      builder: (c) => SimpleDialog(
        title: const Text('Kelola Kategori'),
        children: [
          SimpleDialogOption(
            onPressed: () => Navigator.pop(c, 'new'),
            child: const Text('+ Tambah kategori'),
          ),
          for (final item in categories)
            SimpleDialogOption(
              onPressed: () => Navigator.pop(c, '${item['id']}'),
              child: Text('${item['name']}'),
            ),
        ],
      ),
    );
    if (selection == null || !mounted) {
      return;
    }
    final item = selection == 'new'
        ? null
        : categories.firstWhere((c) => '${c['id']}' == selection);
    if (item != null) {
      final action = await trackedDialog<String>(
        context: context,
        builder: (c) => SimpleDialog(
          title: Text('${item['name']}'),
          children: [
            for (final action in ['Edit', 'Hapus'])
              SimpleDialogOption(
                onPressed: () => Navigator.pop(c, action),
                child: Text(action),
              ),
          ],
        ),
      );
      if (action == null || !mounted) {
        return;
      }
      if (action == 'Hapus') {
        if (await confirm('Hapus kategori ${item['name']}?')) {
          await mutate('DELETE', '/menu-categories/$selection');
        }
        return;
      }
    }
    final data = await edit(
      'Kategori',
      {
        'name': '${item?['name'] ?? ''}',
        'description': '${item?['description'] ?? ''}',
      },
      optional: {'description'},
    );
    if (data != null) {
      await mutate(
        item == null ? 'POST' : 'PATCH',
        '/menu-categories${item == null ? '' : '/$selection'}',
        body: data,
      );
    }
  }

  Widget orderCard(Map<String, dynamic> o) => Card(
    margin: const EdgeInsets.only(bottom: 12),
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Order #${o['id']} · ${o['customer_name'] ?? 'Pelanggan'}',
            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 17),
          ),
          const SizedBox(height: 6),
          Text(
            '${o['waiter_name']} · ${statusLabel(o['status'])}',
            style: const TextStyle(color: green),
          ),
          Text(
            '${o['created_at'] ?? ''}',
            style: const TextStyle(fontSize: 12, color: Colors.black54),
          ),
          for (final item in (o['items'] as List? ?? []))
            Padding(
              padding: const EdgeInsets.only(top: 6),
              child: Text(
                '${item['name']} × ${item['quantity']} · ${rupiah(item['line_total'])}',
              ),
            ),
          const SizedBox(height: 10),
          Text(
            rupiah(o['total_amount']),
            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 19),
          ),
          if (o['cancellation_reason'] != null)
            Text('Alasan: ${o['cancellation_reason']}'),
          if (role == 'Waiter' && o['status'] == 'sent_to_kitchen')
            Wrap(
              spacing: 8,
              children: [
                FilledButton(
                  onPressed: working ? null : () => pay(o),
                  child: const Text('Pembayaran'),
                ),
                TextButton(
                  onPressed: working
                      ? null
                      : () async {
                          final data = await edit('Ajukan Pembatalan', {
                            'reason': '',
                          });
                          if (data != null) {
                            await mutate(
                              'POST',
                              '/orders/${o['id']}/cancellation-requests',
                              body: data,
                            );
                          }
                        },
                  child: const Text('Batalkan'),
                ),
              ],
            ),
          if (role == 'Waiter' &&
              o['status'] == 'cancellation_pending' &&
              o['void_code_issued'] == true)
            TextButton(
              onPressed: working
                  ? null
                  : () async {
                      final data = await edit(
                        'Masukkan Kode Owner',
                        {'void_code': ''},
                        secret: {'void_code'},
                      );
                      if (data != null) {
                        await mutate(
                          'POST',
                          '/orders/${o['id']}/void',
                          body: data,
                        );
                      }
                    },
              child: const Text('Void dengan kode owner'),
            ),
          if (role == 'Owner' && o['status'] == 'cancellation_pending')
            Wrap(
              spacing: 8,
              children: [
                for (final decision in ['approve', 'reject'])
                  TextButton(
                    onPressed: working
                        ? null
                        : () async {
                            if (!await confirm(
                              decision == 'approve'
                                  ? 'Setujui pembatalan dan buat kode void?'
                                  : 'Tolak permintaan pembatalan?',
                            )) {
                              return;
                            }
                            final result = await mutate(
                              'PATCH',
                              '/owner/orders/${o['id']}/cancellation',
                              body: {'decision': decision},
                            );
                            if (result != null &&
                                mounted &&
                                result['data']['void_code'] != null) {
                              await trackedDialog<void>(
                                context: context,
                                builder: (c) => AlertDialog(
                                  title: const Text('Kode Void'),
                                  content: SelectableText(
                                    '${result['data']['void_code']}\nBerlaku 15 menit. Berikan kode kepada waiter.',
                                  ),
                                  actions: [
                                    TextButton(
                                      onPressed: () => Navigator.pop(c),
                                      child: const Text('Tutup'),
                                    ),
                                  ],
                                ),
                              );
                            }
                          },
                    child: Text(decision == 'approve' ? 'Setujui' : 'Tolak'),
                  ),
              ],
            ),
        ],
      ),
    ),
  );
  Future<void> pay(Map<String, dynamic> o) async {
    final method = await trackedDialog<String>(
      context: context,
      builder: (c) => SimpleDialog(
        title: Text('Pembayaran ${rupiah(o['total_amount'])}'),
        children: [
          for (final m in ['cash', 'qris', 'card'])
            SimpleDialogOption(
              onPressed: () => Navigator.pop(c, m),
              child: Text(m.toUpperCase()),
            ),
        ],
      ),
    );
    if (method != null &&
        mounted &&
        await confirm('Konfirmasi pembayaran ${method.toUpperCase()}?')) {
      await mutate(
        'POST',
        '/orders/${o['id']}/payments',
        body: {'payment_method': method},
      );
    }
  }

  List<Widget> staffContent() => [
    FilledButton.icon(
      onPressed: working ? null : () => editStaff(),
      icon: const Icon(Icons.person_add_outlined),
      label: const Text('Tambah Staff'),
    ),
    const SizedBox(height: 16),
    if (staff.isEmpty) empty('Belum ada staff.'),
    for (final s in staff)
      Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                '${s['name']}',
                style: const TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                ),
              ),
              Text('${s['email']} · ${s['position']}'),
              Row(
                children: [
                  Expanded(
                    child: SwitchListTile(
                      contentPadding: EdgeInsets.zero,
                      title: const Text('Aktif'),
                      value: s['active'] == true,
                      onChanged: working
                          ? null
                          : (v) => mutate(
                              'PATCH',
                              '/owner/staff/${s['id']}/status',
                              body: {'active': v},
                            ),
                    ),
                  ),
                  IconButton(
                    tooltip: 'Edit staff',
                    onPressed: working ? null : () => editStaff(s),
                    icon: const Icon(Icons.edit_outlined),
                  ),
                  IconButton(
                    tooltip: 'Hapus staff',
                    onPressed: working
                        ? null
                        : () async {
                            if (await confirm('Hapus staff ${s['name']}?')) {
                              await mutate('DELETE', '/owner/staff/${s['id']}');
                            }
                          },
                    icon: const Icon(Icons.delete_outline),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
  ];
  Future<void> editStaff([Map<String, dynamic>? s]) async {
    final data = await edit(
      s == null ? 'Tambah Staff' : 'Edit Staff',
      {
        'name': '${s?['name'] ?? ''}',
        'email': '${s?['email'] ?? ''}',
        'position': '${s?['position'] ?? 'Waiter'}',
        'password': '',
      },
      choices: {
        'position': ['Waiter', 'Barista'],
      },
      optional: s == null ? {} : {'password'},
      secret: {'password'},
    );
    if (data != null) {
      if (data['password'] == '') {
        data.remove('password');
      }
      await mutate(
        s == null ? 'POST' : 'PUT',
        '/owner/staff${s == null ? '' : '/${s['id']}'}',
        body: data,
      );
    }
  }

  List<Widget> reportContent() {
    final summary = report['summary'] as Map? ?? {};
    return [
      OutlinedButton.icon(
        onPressed: () async {
          openDialogs++;
          DateTimeRange? picked;
          try {
            picked = await showDateRangePicker(
              context: context,
              firstDate: DateTime(2020),
              lastDate: DateTime.now(),
              initialDateRange: range,
            );
          } finally {
            openDialogs--;
          }
          if (picked != null && mounted) {
            setState(() => range = picked);
            await refresh();
          }
        },
        icon: const Icon(Icons.date_range),
        label: Text(
          range == null
              ? '30 hari terakhir · Ubah periode'
              : '${date(range!.start)} — ${date(range!.end)}',
        ),
      ),
      const SizedBox(height: 16),
      ...metrics({
        'Pemasukan': rupiah(summary['income']),
        'Pengeluaran': rupiah(summary['expenses']),
        'Laba Bersih': rupiah(summary['net']),
      }),
      heading('Pembayaran per Metode'),
      ...rows(report['payments_by_method'], 'payment_method', 'total_amount'),
      heading('Pengeluaran per Kategori'),
      ...rows(report['expenses_by_category'], 'category', 'total_amount'),
      heading('Tren Harian'),
      ...rows(report['daily_trends'], 'payment_date', 'net_amount'),
      heading('Transaksi Bar Terbaru'),
      ...rows(report['recent_bar_transactions'], 'description', 'amount'),
      heading('Pesanan Terbaru'),
      for (final o in (report['recent_orders'] as List? ?? []))
        orderCard(Map<String, dynamic>.from(o)),
    ];
  }

  List<Widget> rows(dynamic values, String title, String amount) =>
      (values as List? ?? []).isEmpty
      ? [empty('Belum ada transaksi.')]
      : [
          for (final v in values as List)
            Card(
              child: ListTile(
                title: Text('${v[title]}'),
                subtitle: Text(rupiah(v[amount])),
              ),
            ),
        ];
  List<Widget> operationalContent() {
    final summary = overview['summary'] as Map? ?? {};
    return [
      DropdownButtonFormField<String>(
        initialValue: period,
        decoration: const InputDecoration(labelText: 'Periode'),
        items: const [
          DropdownMenuItem(value: 'today', child: Text('Hari ini')),
          DropdownMenuItem(value: '7days', child: Text('7 hari')),
          DropdownMenuItem(value: '30days', child: Text('30 hari')),
        ],
        onChanged: (v) {
          if (v != null) {
            setState(() => period = v);
            refresh();
          }
        },
      ),
      const SizedBox(height: 16),
      ...metrics({
        'Pemasukan': rupiah(summary['income']),
        'Pengeluaran': rupiah(summary['expenses']),
        'Laba Bersih': rupiah(summary['profit']),
      }),
      FilledButton.icon(
        onPressed: working
            ? null
            : () async {
                final data = await edit(
                  'Catat Transaksi Bar',
                  {
                    'type': 'expense',
                    'category': 'Operasional',
                    'description': '',
                    'amount': '',
                    'notes': '',
                  },
                  choices: {
                    'type': ['income', 'expense'],
                    'category': [
                      'Penjualan',
                      'Operasional',
                      'Bahan Baku',
                      'Peralatan',
                      'Lainnya',
                    ],
                  },
                  numeric: {'amount'},
                  optional: {'notes'},
                );
                if (data != null) {
                  await mutate('POST', '/barista/transactions', body: data);
                }
              },
        icon: const Icon(Icons.add),
        label: const Text('Catat Transaksi'),
      ),
      heading('Pesanan Masuk'),
      for (final o in orders.where(
        (o) =>
            ['sent_to_kitchen', 'cancellation_pending'].contains(o['status']),
      ))
        orderCard(o),
      heading('Riwayat Transaksi'),
      ...rows(overview['transactions'], 'description', 'amount'),
    ];
  }
}
