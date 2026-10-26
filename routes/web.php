<?php

use App\Support\AccountAuthentication;
use App\Support\BarOperations;
use App\Support\FinancialReporting;
use App\Support\FinancialReportWorkbook;
use App\Support\MenuCatalog;
use App\Support\OrderNotifications;
use App\Support\OrderWorkflow;
use App\Support\StaffManagement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

$canManageMenu = static function (AccountAuthentication $accounts): bool {
    if (session('owner_logged_in')) {
        return true;
    }

    return session('barista_logged_in') && $accounts->activeStaff((int) session('barista_id'), 'Barista') !== null;
};

Route::get('/', function () {
    if (session('barista_logged_in')) {
        return redirect()->route('barista.operational');
    }

    if (session('waiter_logged_in')) {
        return redirect()->route('waiter.orders');
    }

    if (session('owner_logged_in')) {
        return redirect()->route('owner.dashboard');
    }

    return redirect()->route('login');
});

Route::get('/login', function () {
    if (session('barista_logged_in')) {
        return redirect()->route('barista.operational');
    }

    if (session('waiter_logged_in')) {
        return redirect()->route('waiter.orders');
    }

    if (session('owner_logged_in')) {
        return redirect()->route('owner.dashboard');
    }

    return view('auth.login', ['title' => 'Gotham City Coffee']);
})->name('login');

Route::post('/login', function (AccountAuthentication $accounts): mixed {
    $validated = $accounts->credentials(request()->only(['email', 'password']));
    $account = $accounts->authenticate($validated['email'], $validated['password']);
    if ($account === null) {
        return back()->withErrors(['email' => 'Email atau password salah.']);
    }
    $role = $accounts->role($account);
    request()->session()->regenerate();
    session()->forget(['owner_logged_in', 'owner_name', 'waiter_logged_in', 'waiter_id', 'waiter_name', 'barista_logged_in', 'barista_id', 'barista_name']);
    if ($role === 'Owner') {
        session(['owner_logged_in' => true, 'owner_name' => $account->name]);

        return redirect()->route('owner.dashboard');
    }
    $sessionRole = strtolower($role);
    session([$sessionRole.'_logged_in' => true, $sessionRole.'_id' => $account->id, $sessionRole.'_name' => $account->name]);

    return redirect()->route($role === 'Waiter' ? 'waiter.orders' : 'barista.operational');
})->middleware('throttle:5,1')->name('login.post');

Route::get('/logout', function () {
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout');

Route::get('/owner/dashboard', function (MenuCatalog $catalog, FinancialReporting $reports): mixed {
    if (! session('owner_logged_in')) {
        return redirect()->route('login');
    }

    $menus = $catalog->query()
        ->orderByDesc('menu_items.created_at')
        ->get();
    $dashboard = $reports->dashboard(5);
    $recentOrders = $dashboard['recent_orders'];
    $metrics = collect($dashboard)->except('recent_orders')->all();

    return view('owner.dashboard', [
        'title' => 'Owner Dashboard',
        'menus' => $menus,
        'metrics' => $metrics,
        'recentOrders' => $recentOrders,
    ]);
})->name('owner.dashboard');

Route::get('/owner/menu', function (MenuCatalog $catalog): mixed {
    if (! session('owner_logged_in')) {
        return redirect()->route('login');
    }

    $tab = request('tab', 'menu');
    $categories = $catalog->categories();
    $items = $catalog->query()
        ->orderByDesc('menu_items.created_at')
        ->get();

    return view('owner.menu', [
        'title' => 'Menu Management',
        'tab' => $tab,
        'categories' => $categories,
        'items' => $items,
        'menuManagementRoute' => 'owner.menu',
        'menuBackRoute' => 'owner.dashboard',
    ]);
})->name('owner.menu');

Route::post('/owner/menu', function (AccountAuthentication $accounts, MenuCatalog $catalog) use ($canManageMenu): mixed {
    if (! $canManageMenu($accounts)) {
        return redirect()->route('login');
    }
    $catalog->saveItem(request()->only(['name', 'price', 'category_id', 'description', 'active']), null, request()->file('image'));

    return redirect()->route(session('owner_logged_in') ? 'owner.menu' : 'barista.menu', ['tab' => 'menu'])->with('success', 'Menu berhasil ditambahkan.');
})->name('owner.menu.store');

Route::post('/owner/menu/{id}/update', function (AccountAuthentication $accounts, MenuCatalog $catalog, int $id) use ($canManageMenu): mixed {
    if (! $canManageMenu($accounts)) {
        return redirect()->route('login');
    }
    $catalog->saveItem(request()->only(['name', 'price', 'category_id', 'description']), $id, request()->file('image'));

    return redirect()->route(session('owner_logged_in') ? 'owner.menu' : 'barista.menu', ['tab' => 'menu'])->with('success', 'Menu berhasil diperbarui.');
})->name('owner.menu.update');

Route::post('/owner/menu/{id}/delete', function (AccountAuthentication $accounts, MenuCatalog $catalog, int $id) use ($canManageMenu): mixed {
    if (! $canManageMenu($accounts)) {
        return redirect()->route('login');
    }
    $catalog->deleteItem($id);

    return redirect()->route(session('owner_logged_in') ? 'owner.menu' : 'barista.menu', ['tab' => 'menu'])->with('success', 'Menu berhasil dihapus.');
})->name('owner.menu.delete');

Route::post('/owner/menu/{id}/toggle', function (AccountAuthentication $accounts, MenuCatalog $catalog, int $id) use ($canManageMenu): mixed {
    if (! $canManageMenu($accounts)) {
        return redirect()->route('login');
    }
    $catalog->toggleAvailable($id);

    return redirect()->route(session('owner_logged_in') ? 'owner.menu' : 'barista.menu', ['tab' => 'menu'])->with('success', 'Status menu berhasil diubah.');
})->name('owner.menu.toggle');

Route::post('/owner/categories', function (AccountAuthentication $accounts, MenuCatalog $catalog) use ($canManageMenu): mixed {
    if (! $canManageMenu($accounts)) {
        return redirect()->route('login');
    }
    $catalog->saveCategory(request()->only(['name', 'description']));

    return redirect()->route(session('owner_logged_in') ? 'owner.menu' : 'barista.menu', ['tab' => 'categories'])->with('success', 'Kategori berhasil ditambahkan.');
})->name('owner.categories.store');

Route::post('/owner/categories/{id}/update', function (AccountAuthentication $accounts, MenuCatalog $catalog, int $id) use ($canManageMenu): mixed {
    if (! $canManageMenu($accounts)) {
        return redirect()->route('login');
    }
    $catalog->saveCategory(request()->only(['name', 'description']), $id);

    return redirect()->route(session('owner_logged_in') ? 'owner.menu' : 'barista.menu', ['tab' => 'categories'])->with('success', 'Kategori berhasil diperbarui.');
})->name('owner.categories.update');

Route::post('/owner/categories/{id}/delete', function (AccountAuthentication $accounts, MenuCatalog $catalog, int $id) use ($canManageMenu): mixed {
    if (! $canManageMenu($accounts)) {
        return redirect()->route('login');
    }
    try {
        $catalog->deleteCategory($id);
    } catch (ValidationException $error) {
        return redirect()->route(session('owner_logged_in') ? 'owner.menu' : 'barista.menu', ['tab' => 'categories'])->withErrors($error->errors());
    }

    return redirect()->route(session('owner_logged_in') ? 'owner.menu' : 'barista.menu', ['tab' => 'categories'])->with('success', 'Kategori berhasil dihapus.');
})->name('owner.categories.delete');

Route::get('/owner/staff', function (StaffManagement $staff): mixed {
    if (! session('owner_logged_in')) {
        return redirect()->route('login');
    }

    $staffMembers = $staff->query()->get();

    return view('owner.staff', [
        'title' => 'Kelola Staff',
        'staffMembers' => $staffMembers,
    ]);
})->name('owner.staff');

Route::post('/owner/staff', function (StaffManagement $staff): mixed {
    if (! session('owner_logged_in')) {
        return redirect()->route('login');
    }
    $staff->save(request()->only(['name', 'email', 'position', 'password']));

    return redirect()->route('owner.staff')->with('success', 'Staff berhasil ditambahkan.');
})->name('owner.staff.store');

Route::post('/owner/staff/{id}/update', function (StaffManagement $staff, int $id): mixed {
    if (! session('owner_logged_in')) {
        return redirect()->route('login');
    }
    $staff->save(request()->only(['name', 'email', 'position', 'password']), $id);

    return redirect()->route('owner.staff')->with('success', 'Data staff berhasil diperbarui.');
})->name('owner.staff.update');

Route::post('/owner/staff/{id}/toggle', function (StaffManagement $staff, int $id): mixed {
    if (! session('owner_logged_in')) {
        return redirect()->route('login');
    }
    $staff->toggleActive($id);

    return redirect()->route('owner.staff')->with('success', 'Status staff berhasil diubah.');
})->name('owner.staff.toggle');

Route::post('/owner/staff/{id}/delete', function (StaffManagement $staff, int $id): mixed {
    if (! session('owner_logged_in')) {
        return redirect()->route('login');
    }
    $staff->delete($id);

    return redirect()->route('owner.staff')->with('success', 'Staff berhasil dihapus.');
})->name('owner.staff.delete');

Route::get('/waiter/orders', function (AccountAuthentication $accounts, MenuCatalog $catalog, OrderWorkflow $workflow): mixed {
    $waiter = $accounts->activeStaff((int) session('waiter_id'), 'Waiter');

    if (! session('waiter_logged_in') || ! $waiter) {
        session()->forget(['waiter_logged_in', 'waiter_id', 'waiter_name']);

        return redirect()->route('login');
    }

    $menus = $catalog->query()
        ->where('menu_items.active', true)
        ->orderBy('menu_items.name')
        ->get();

    $orders = $workflow->query($waiter)
        ->whereIn('status', ['sent_to_kitchen', 'cancellation_pending'])
        ->select('id', 'customer_name', 'status', 'total_amount', 'cancellation_reason', 'created_at', 'void_code_hash', 'void_code_expires_at')
        ->orderByDesc('created_at')
        ->get()
        ->map(function (object $order): object {
            $order->void_code_approved = $order->void_code_hash !== null;
            $order->void_code_issued = $order->void_code_hash !== null
                && $order->void_code_expires_at !== null
                && now()->lt($order->void_code_expires_at);
            unset($order->void_code_hash, $order->void_code_expires_at);

            return $order;
        });

    $orders = $workflow->withOrderItems($orders);
    $orderItems = $orders->mapWithKeys(fn (object $order): array => [$order->id => $order->items]);

    return view('waiter.orders', [
        'title' => 'Waiter - Order Taking',
        'waiter' => $waiter,
        'menus' => $menus,
        'orders' => $orders,
        'orderItems' => $orderItems,
    ]);
})->name('waiter.orders');

Route::post('/waiter/orders', function (AccountAuthentication $accounts, OrderWorkflow $workflow): mixed {
    $waiter = $accounts->activeStaff((int) session('waiter_id'), 'Waiter');
    if (! session('waiter_logged_in') || ! $waiter) {
        return redirect()->route('login');
    }
    $validated = request()->validate(['customer_name' => ['nullable', 'string', 'max:255'], 'items' => ['required', 'array', 'min:1', 'max:50'], 'items.*' => ['required', 'integer', 'min:1', 'max:99']]);
    $items = [];
    foreach ($validated['items'] as $menuId => $quantity) {
        $items[] = ['menu_item_id' => (int) $menuId, 'quantity' => $quantity];
    }
    try {
        $order = $workflow->create($waiter, ['customer_name' => $validated['customer_name'] ?? null, 'items' => $items]);
    } catch (ValidationException $error) {
        return redirect()->route('waiter.orders')->withErrors($error->errors());
    }

    return redirect()->route('waiter.orders')->with('success', 'Order #'.$order->id.' berhasil dikirim ke dapur.');
})->name('waiter.orders.store');

Route::post('/waiter/orders/{id}/pay', function (AccountAuthentication $accounts, OrderWorkflow $workflow, int $id): mixed {
    $waiter = $accounts->activeStaff((int) session('waiter_id'), 'Waiter');
    if (! session('waiter_logged_in') || ! $waiter) {
        return redirect()->route('login');
    }
    try {
        $workflow->pay($waiter, $id, request()->only(['payment_method']));
    } catch (ValidationException $error) {
        return redirect()->route('waiter.orders')->withErrors($error->errors());
    } catch (HttpException $error) {
        if ($error->getStatusCode() === 409) {
            abort(404);
        }
        throw $error;
    }

    return redirect()->route('waiter.orders')->with('success', 'Pembayaran berhasil dicatat.');
})->name('waiter.orders.pay');

Route::get('/notifications/completed-orders', function (AccountAuthentication $accounts, OrderNotifications $notifications): mixed {
    if (session('owner_logged_in')) {
        $accountIsValid = true;
    } elseif (session('waiter_logged_in')) {
        $accountIsValid = $accounts->activeStaff((int) session('waiter_id'), 'Waiter') !== null;
    } elseif (session('barista_logged_in')) {
        $accountIsValid = $accounts->activeStaff((int) session('barista_id'), 'Barista') !== null;
    } else {
        $accountIsValid = false;
    }

    if (! $accountIsValid) {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }

    return response()->json(['events' => $notifications->completed(request()->only(['after_paid_at', 'after_order_id']))]);
})->name('notifications.completed-orders');

Route::post('/waiter/orders/{id}/cancel', function (AccountAuthentication $accounts, OrderWorkflow $workflow, int $id): mixed {
    $waiter = $accounts->activeStaff((int) session('waiter_id'), 'Waiter');
    if (! session('waiter_logged_in') || ! $waiter) {
        return redirect()->route('login');
    }
    try {
        $workflow->requestCancellation($waiter, $id, request()->only(['reason']));
    } catch (ValidationException $error) {
        return redirect()->route('waiter.orders')->withErrors($error->errors());
    } catch (HttpException $error) {
        if ($error->getStatusCode() === 409) {
            abort(404);
        }
        throw $error;
    }

    return redirect()->route('waiter.orders')->with('success', 'Permintaan pembatalan dikirim ke owner.');
})->name('waiter.orders.cancel');

Route::post('/waiter/orders/{id}/void', function (AccountAuthentication $accounts, OrderWorkflow $workflow, int $id): mixed {
    $waiter = $accounts->activeStaff((int) session('waiter_id'), 'Waiter');
    if (! session('waiter_logged_in') || ! $waiter) {
        return redirect()->route('login');
    }
    try {
        $workflow->void($waiter, $id, request()->only(['void_code']));
    } catch (ValidationException $error) {
        return redirect()->route('waiter.orders')->withErrors($error->errors());
    } catch (HttpException $error) {
        if ($error->getStatusCode() === 409) {
            abort(404);
        }
        throw $error;
    }

    return redirect()->route('waiter.orders')->with('success', 'Pesanan berhasil dibatalkan dengan Kode Void.');
})->middleware('throttle:5,1')->name('waiter.orders.void');

Route::get('/barista/operational', function (AccountAuthentication $accounts, BarOperations $operations): mixed {
    $barista = $accounts->activeStaff((int) session('barista_id'), 'Barista');
    if (! session('barista_logged_in') || ! $barista) {
        return redirect()->route('login');
    }
    request()->validate(['period' => ['nullable', Rule::in(['today', '7days', '30days'])]]);
    $period = request('period', 'today');
    $data = $operations->operational($period, null);

    return view('barista.operational', [
        'title' => 'Barista - Operasional', 'barista' => $barista, 'period' => $period,
        'income' => $data['summary']['income'], 'expenses' => $data['summary']['expenses'], 'profit' => $data['summary']['profit'],
        'transactions' => $data['transactions'], 'incomingOrders' => $data['incoming_orders'], 'menus' => $data['active_menu_items'],
    ]);
})->name('barista.operational');

Route::get('/barista/menu', function (AccountAuthentication $accounts, MenuCatalog $catalog): mixed {
    $barista = $accounts->activeStaff((int) session('barista_id'), 'Barista');

    if (! session('barista_logged_in') || ! $barista) {
        return redirect()->route('login');
    }

    $menus = $catalog->query()
        ->orderByDesc('menu_items.created_at')
        ->get();

    $categories = $catalog->categories();

    return view('owner.menu', [
        'title' => 'Menu Management',
        'tab' => request('tab', 'menu'),
        'categories' => $categories,
        'items' => $menus,
        'menuManagementRoute' => 'barista.menu',
        'menuBackRoute' => 'barista.operational',
    ]);
})->name('barista.menu');

Route::post('/barista/menu/{id}/status', function (AccountAuthentication $accounts, MenuCatalog $catalog, int $id): mixed {
    $barista = $accounts->activeStaff((int) session('barista_id'), 'Barista');
    if (! session('barista_logged_in') || ! $barista) {
        return redirect()->route('login');
    }
    request()->validate(['active' => ['required', 'boolean']]);
    $catalog->setAvailable($id, request()->boolean('active'));

    return redirect()->route('barista.menu')->with('success', 'Status menu berhasil diperbarui.');
})->name('barista.menu.status');

Route::post('/barista/transactions', function (AccountAuthentication $accounts, BarOperations $operations): mixed {
    $barista = $accounts->activeStaff((int) session('barista_id'), 'Barista');
    if (! session('barista_logged_in') || ! $barista) {
        return redirect()->route('login');
    }
    $operations->create($barista, request()->only(['type', 'category', 'description', 'amount', 'notes']));

    return redirect()->route('barista.operational')->with('success', 'Transaksi bar berhasil dicatat.');
})->name('barista.transactions.store');

Route::get('/owner/operational', function (StaffManagement $staff, OrderWorkflow $workflow): mixed {
    if (! session('owner_logged_in')) {
        return redirect()->route('login');
    }

    $staffMembers = $staff->query()->get();
    $cancellationOrders = $workflow->pendingCancellations()
        ->get();

    return view('owner.operational', [
        'title' => 'Owner - Operational',
        'staffMembers' => $staffMembers,
        'cancellationOrders' => $cancellationOrders,
    ]);
})->name('owner.operational');

Route::post('/owner/orders/{id}/cancellation', function (OrderWorkflow $workflow, int $id): mixed {
    if (! session('owner_logged_in')) {
        return redirect()->route('login');
    }
    $result = $workflow->decideCancellation($id, request()->only(['decision']));
    $approved = request('decision') === 'approve';
    $response = redirect()->route('owner.operational')->with('success', $approved
        ? 'Permintaan pembatalan disetujui. Berikan Kode Void ini kepada waiter dalam 15 menit.'
        : 'Pembatalan order ditolak; order kembali aktif.');

    return $result['void_code'] ? $response->with('void_code', $result['void_code'])->with('void_code_order_id', $id) : $response;
})->name('owner.orders.cancellation');

Route::get('/owner/laporan', function (FinancialReporting $reports): mixed {
    if (! session('owner_logged_in')) {
        return redirect()->route('login');
    }

    request()->validate([
        'period' => ['nullable', Rule::in(['today', '7days', '30days', 'custom'])],
        'start_date' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d'],
        'end_date' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        'export' => ['nullable', Rule::in(['xlsx'])],
    ]);

    $period = request('period', '30days');
    if ($period === 'custom') {
        $periodStart = Carbon::createFromFormat('Y-m-d', request('start_date'))->startOfDay();
        $periodEnd = Carbon::createFromFormat('Y-m-d', request('end_date'))->endOfDay();
    } else {
        $periodStart = match ($period) {
            'today' => now()->startOfDay(),
            '7days' => now()->subDays(6)->startOfDay(),
            default => now()->subDays(29)->startOfDay(),
        };
        $periodEnd = now()->endOfDay();
    }

    $report = $reports->report($periodStart, $periodEnd);
    $income = $report['income'];
    $expenses = $report['expenses'];
    $dailyTrends = $report['daily_trends'];
    $paymentsByMethod = $report['payments_by_method'];
    $expensesByCategory = $report['expenses_by_category'];
    $recentTransactions = $report['recent_orders'];
    $recentBarTransactions = $report['recent_bar_transactions'];

    if (request('export') === 'xlsx') {
        $exportRows = $reports->exportRows($periodStart, $periodEnd);
        $filename = 'laporan-transaksi-'.$periodStart->format('Ymd').'-'.$periodEnd->format('Ymd').'.xlsx';

        return response()->streamDownload(function () use ($exportRows, $periodStart, $periodEnd, $income, $expenses, $dailyTrends): void {
            $temporaryWorkbook = tempnam(sys_get_temp_dir(), 'gotham-report-');

            if ($temporaryWorkbook === false) {
                throw new RuntimeException('Tidak dapat menyiapkan file laporan Excel.');
            }

            try {
                app(FinancialReportWorkbook::class)->create(
                    $temporaryWorkbook,
                    $periodStart,
                    $periodEnd,
                    $income,
                    $expenses,
                    0,
                    $dailyTrends,
                    $exportRows->cursor(),
                );
                readfile($temporaryWorkbook);
            } finally {
                @unlink($temporaryWorkbook);
            }
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    return view('owner.laporan', [
        'title' => 'Laporan Keuangan',
        'period' => $period,
        'startDate' => $period === 'custom' ? request('start_date') : null,
        'endDate' => $period === 'custom' ? request('end_date') : null,
        'income' => $income,
        'expenses' => $expenses,
        'profit' => $income - $expenses,
        'refunds' => 0,
        'paymentsByMethod' => $paymentsByMethod,
        'expensesByCategory' => $expensesByCategory,
        'dailyTrends' => $dailyTrends,
        'recentTransactions' => $recentTransactions,
        'recentBarTransactions' => $recentBarTransactions,
    ]);
})->name('owner.laporan');

Route::get('/owner/inventory', function () {
    if (! session('owner_logged_in')) {
        return redirect()->route('login');
    }

    return view('owner.inventory', ['title' => 'Inventaris']);
})->name('owner.inventory');
