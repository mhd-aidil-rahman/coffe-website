import 'package:flutter/material.dart';
import 'api.dart';
import 'portal.dart';

void main() => runApp(const MyApp());
const green = Color(0xff0d5b4d);
const canvas = Color(0xffe6e8e4);

class MyApp extends StatefulWidget {
  const MyApp({super.key, this.api});
  final GothamApi? api;
  @override
  State<MyApp> createState() => _MyAppState();
}

class _MyAppState extends State<MyApp> {
  late final GothamApi api = widget.api ?? GothamApi();
  Map<String, dynamic>? user;
  @override
  void dispose() {
    api.close();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => MaterialApp(
    debugShowCheckedModeBanner: false,
    title: 'Gotham City Coffee',
    theme: ThemeData(
      colorScheme: ColorScheme.fromSeed(seedColor: green),
      scaffoldBackgroundColor: canvas,
      appBarTheme: const AppBarTheme(
        backgroundColor: green,
        foregroundColor: Colors.white,
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Colors.white,
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: green,
          minimumSize: const Size(48, 50),
        ),
      ),
    ),
    home: user == null
        ? LoginPage(api: api, onLogin: (value) => setState(() => user = value))
        : Portal(
            api: api,
            user: user!,
            onLogout: () {
              api.token = null;
              setState(() => user = null);
            },
          ),
  );
}

class LoginPage extends StatefulWidget {
  const LoginPage({super.key, required this.api, required this.onLogin});
  final GothamApi api;
  final ValueChanged<Map<String, dynamic>> onLogin;
  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final form = GlobalKey<FormState>();
  final email = TextEditingController(), password = TextEditingController();
  bool busy = false, hidden = true;
  String? error;
  @override
  void dispose() {
    email.dispose();
    password.dispose();
    super.dispose();
  }

  Future<void> login() async {
    if (busy || !form.currentState!.validate()) {
      return;
    }
    setState(() {
      busy = true;
      error = null;
    });
    try {
      final response = await widget.api.request(
        'POST',
        '/auth/login',
        body: {
          'email': email.text.trim(),
          'password': password.text,
          'device_name': 'Gotham Mobile',
        },
      );
      final data = response['data'] as Map<String, dynamic>;
      final account = data['user'] as Map<String, dynamic>;
      if (!['Owner', 'Waiter', 'Barista'].contains(account['role'])) {
        throw const ApiException('Role akun tidak didukung.');
      }
      widget.api.token = data['token'] as String;
      if (mounted) {
        widget.onLogin(account);
      }
    } catch (e) {
      if (mounted) {
        setState(() => error = '$e');
      }
    } finally {
      if (mounted) {
        setState(() => busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    body: SafeArea(
      child: LayoutBuilder(
        builder: (context, box) {
          final brand = Padding(
            padding: const EdgeInsets.all(28),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Image.asset('assets/logo.png', width: 88, height: 88),
                const SizedBox(height: 20),
                const Text(
                  'GOTHAM CITY COFFEE',
                  style: TextStyle(color: Colors.white70, letterSpacing: 2),
                ),
                const SizedBox(height: 16),
                const Text(
                  'Diracik dengan hati.\nDikelola dengan rapi.',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 30,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                const SizedBox(height: 16),
                const Text(
                  'Satu tempat untuk menjaga setiap pesanan dan pelayanan tetap istimewa.',
                  style: TextStyle(color: Colors.white70, height: 1.6),
                ),
              ],
            ),
          );
          final panel = Padding(
            padding: const EdgeInsets.all(28),
            child: Form(
              key: form,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text(
                    'Selamat datang kembali',
                    style: TextStyle(color: green),
                  ),
                  const SizedBox(height: 12),
                  const Text(
                    'Masuk ke akun Anda',
                    style: TextStyle(fontSize: 28, fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 8),
                  const Text('Kelola hari Anda di Gotham City Coffee.'),
                  const SizedBox(height: 28),
                  if (error != null) ...[
                    Text(error!, style: const TextStyle(color: Colors.red)),
                    const SizedBox(height: 16),
                  ],
                  TextFormField(
                    controller: email,
                    keyboardType: TextInputType.emailAddress,
                    autofillHints: const [AutofillHints.username],
                    decoration: const InputDecoration(
                      labelText: 'Email',
                      prefixIcon: Icon(Icons.alternate_email),
                    ),
                    validator: (v) => v == null || !v.contains('@')
                        ? 'Masukkan email yang valid.'
                        : null,
                  ),
                  const SizedBox(height: 18),
                  TextFormField(
                    controller: password,
                    obscureText: hidden,
                    autofillHints: const [AutofillHints.password],
                    onFieldSubmitted: (_) => login(),
                    decoration: InputDecoration(
                      labelText: 'Password',
                      prefixIcon: const Icon(Icons.lock_outline),
                      suffixIcon: IconButton(
                        tooltip: hidden
                            ? 'Tampilkan password'
                            : 'Sembunyikan password',
                        onPressed: () => setState(() => hidden = !hidden),
                        icon: Icon(
                          hidden
                              ? Icons.visibility_outlined
                              : Icons.visibility_off_outlined,
                        ),
                      ),
                    ),
                    validator: (v) =>
                        v == null || v.isEmpty ? 'Masukkan password.' : null,
                  ),
                  const SizedBox(height: 24),
                  FilledButton(
                    onPressed: busy ? null : login,
                    child: busy
                        ? const SizedBox(
                            width: 22,
                            height: 22,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Text('Masuk ke Dashboard'),
                  ),
                  const SizedBox(height: 28),
                  const Text(
                    'Gotham City Coffee · Staff Portal',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: Colors.grey, fontSize: 12),
                  ),
                ],
              ),
            ),
          );
          return ColoredBox(
            color: green,
            child: Center(
              child: SingleChildScrollView(
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 1100),
                  child: box.maxWidth >= 760
                      ? Row(
                          children: [
                            Expanded(child: brand),
                            Expanded(
                              child: Card(
                                margin: const EdgeInsets.all(24),
                                child: panel,
                              ),
                            ),
                          ],
                        )
                      : Column(
                          children: [
                            brand,
                            Container(
                              decoration: const BoxDecoration(
                                color: canvas,
                                borderRadius: BorderRadius.vertical(
                                  top: Radius.circular(28),
                                ),
                              ),
                              child: panel,
                            ),
                          ],
                        ),
                ),
              ),
            ),
          );
        },
      ),
    ),
  );
}
