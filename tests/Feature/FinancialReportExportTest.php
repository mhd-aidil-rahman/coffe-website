<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinancialReportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_export_all_custom_period_transactions_as_a_styled_excel_workbook(): void
    {
        $this->travelTo('2026-10-09 12:00:00');
        $this->createOrder('completed', '2026-10-08 09:00:00', [
            'total_amount' => 50000,
            'payment_method' => 'cash',
            'paid_at' => '2026-10-08 09:00:00',
        ]);
        $this->createOrder('completed', '2026-10-07 09:00:00', [
            'customer_name' => 'Outside the range',
            'total_amount' => 100000,
            'payment_method' => 'qris',
            'paid_at' => '2026-10-07 09:00:00',
        ]);
        $this->createOrder('cancelled', '2026-10-09 10:00:00', [
            'customer_name' => 'Cancelled order',
            'total_amount' => 18000,
            'cancellation_requested_at' => '2026-10-09 10:00:00',
            'cancellation_reason' => 'Customer changed mind',
        ]);
        $this->createBarTransaction('income', 3000, 'Sales adjustment', '2026-10-09 08:30:00');
        $this->createBarTransaction('expense', 7500, '=HYPERLINK("https://example.test")', '2026-10-08 11:00:00');
        $this->createBarTransaction('expense', 9000, 'Outside expense', '2026-10-07 11:00:00');

        $response = $this->withSession(['owner_logged_in' => true])
            ->get('/owner/laporan?'.http_build_query([
                'period' => 'custom',
                'start_date' => '2026-10-08',
                'end_date' => '2026-10-09',
                'export' => 'xlsx',
            ]));

        $response->assertDownload('laporan-transaksi-20261008-20261009.xlsx');
        $workbookPath = tempnam(sys_get_temp_dir(), 'report-test-');
        file_put_contents($workbookPath, $response->streamedContent());
        $archive = new \ZipArchive;

        $this->assertSame(true, $archive->open($workbookPath));
        $this->assertStringContainsString('Kurva Pemasukan, Pengeluaran &amp; Laba', $archive->getFromName('xl/charts/chart1.xml'));
        $this->assertStringContainsString('<c:smooth val="1"/>', $archive->getFromName('xl/charts/chart1.xml'));
        $this->assertStringContainsString('GOTHAM CITY COFFEE', $archive->getFromName('xl/worksheets/sheet1.xml'));
        $this->assertStringContainsString('53000', $archive->getFromName('xl/worksheets/sheet1.xml'));
        $transactionSheet = $archive->getFromName('xl/worksheets/sheet2.xml');
        $this->assertStringContainsString('Pembayaran order', $transactionSheet);
        $this->assertStringContainsString('Sales adjustment', $transactionSheet);
        $this->assertStringContainsString('=HYPERLINK(', $transactionSheet);
        $this->assertStringNotContainsString('<f>', $transactionSheet);
        $this->assertStringContainsString('Cancelled order', $transactionSheet);
        $this->assertStringContainsString('Customer changed mind', $transactionSheet);
        $this->assertStringNotContainsString('Outside the range', $transactionSheet);
        $this->assertStringNotContainsString('Outside expense', $transactionSheet);

        foreach (['[Content_Types].xml', 'xl/workbook.xml', 'xl/styles.xml', 'xl/worksheets/sheet1.xml', 'xl/worksheets/sheet2.xml', 'xl/drawings/drawing1.xml', 'xl/charts/chart1.xml'] as $xmlFile) {
            $xml = new \DOMDocument;
            $this->assertSame(true, $xml->loadXML($archive->getFromName($xmlFile)));
        }

        $archive->close();
        unlink($workbookPath);
    }

    public function test_owner_can_view_financial_report_for_custom_transaction_period(): void
    {
        $this->travelTo('2026-10-09 12:00:00');
        $this->createOrder('completed', '2026-10-08 09:00:00', [
            'total_amount' => 50000,
            'payment_method' => 'cash',
            'paid_at' => '2026-10-08 09:00:00',
        ]);
        $this->createOrder('completed', '2026-10-07 09:00:00', [
            'customer_name' => 'Outside the range',
            'total_amount' => 100000,
            'payment_method' => 'qris',
            'paid_at' => '2026-10-07 09:00:00',
        ]);

        $this->withSession(['owner_logged_in' => true])
            ->get('/owner/laporan?'.http_build_query([
                'period' => 'custom',
                'start_date' => '2026-10-08',
                'end_date' => '2026-10-09',
            ]))
            ->assertOk()
            ->assertSee('value="custom" selected', false)
            ->assertSee('value="2026-10-08"', false)
            ->assertSee('Rp 50.000')
            ->assertSee('Pilih periode sendiri')
            ->assertDontSee('Outside the range');
    }

    public function test_custom_period_rejects_end_date_before_start_date(): void
    {
        $this->withSession(['owner_logged_in' => true])
            ->get('/owner/laporan?'.http_build_query([
                'period' => 'custom',
                'start_date' => '2026-10-09',
                'end_date' => '2026-10-08',
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('end_date');
    }

    public function test_guest_cannot_export_owner_financial_report(): void
    {
        $this->get('/owner/laporan?export=xlsx')
            ->assertRedirect('/login');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createOrder(string $status, string $createdAt, array $overrides = []): int
    {
        return DB::table('orders')->insertGetId(array_merge([
            'staff_member_id' => null,
            'waiter_name' => 'Waiter',
            'customer_name' => 'Customer',
            'status' => $status,
            'total_amount' => 18000,
            'payment_method' => null,
            'paid_at' => null,
            'cancellation_requested_at' => null,
            'cancellation_reason' => null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ], $overrides));
    }

    private function createBarTransaction(string $type, int $amount, string $description, string $occurredAt): int
    {
        return DB::table('bar_transactions')->insertGetId([
            'staff_member_id' => null,
            'type' => $type,
            'category' => 'Operasional',
            'description' => $description,
            'amount' => $amount,
            'notes' => null,
            'occurred_at' => $occurredAt,
            'created_at' => $occurredAt,
            'updated_at' => $occurredAt,
        ]);
    }
}
