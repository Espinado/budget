<?php

namespace Tests\Feature;

use App\Enums\CashflowType;
use App\Livewire\Budget\Dashboard;
use App\Models\Household;
use App\Models\PlanLine;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Budget\PeriodGenerator;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoBudgetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ExpenseReceiptEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_editing_an_expense_keeps_replaces_or_removes_its_single_receipt(): void
    {
        Storage::fake('public');
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $generator = app(PeriodGenerator::class);
        $period = $generator->periodFor($household, CarbonImmutable::now($household->timezone));
        $category = $household->categories()->where('name', 'Сигареты')->firstOrFail();
        $cash = $household->accounts()->where('name', 'Наличные')->firstOrFail();
        $date = CarbonImmutable::parse($period->starts_on)->addDays(2);
        $transaction = $generator->record(
            $household,
            $category,
            CashflowType::Expense,
            450,
            $date,
            'киоск',
            null,
            $user->id,
            $cash,
        );
        $original = UploadedFile::fake()->image('check.jpg')->store('receipts', 'public');
        $transaction->update(['receipt_path' => $original]);
        $lineId = PlanLine::query()
            ->where('period_id', $period->id)
            ->where('category_id', $category->id)
            ->value('id');

        $component = Livewire::actingAs($user)
            ->withQueryParams(['period' => $period->id])
            ->test(Dashboard::class)
            ->call('toggleDeals', $lineId)
            ->assertSeeHtml('class="receipt-preview"')
            ->assertSeeHtml('width="48"')
            ->assertSeeHtml('height="64"')
            ->assertSeeHtml('<dialog class="receipt-dialog"')
            ->assertDontSeeHtml('<dialog class="receipt-dialog" open')
            ->assertDontSeeHtml('class="receipt-edit-shot"')
            ->assertSee($original)
            ->assertDontSeeHtml('receipt-label-empty');

        $listHtml = $component->html();
        $dialogAt = strpos($listHtml, '<dialog class="receipt-dialog"');
        $stageAt = strpos($listHtml, 'class="receipt-stage"');
        $this->assertNotFalse($dialogAt);
        $this->assertNotFalse($stageAt);
        $this->assertGreaterThan($dialogAt, $stageAt);
        $this->assertDoesNotMatchRegularExpression(
            '/<div class="deal-line">\s*<div class="receipt-stage"/',
            $listHtml,
        );

        $component
            ->call('editTransaction', $transaction->id)
            ->assertSee('Открыть чек')
            ->assertSee('Удалить чек')
            ->assertSee('Загрузить файл')
            ->assertSee('Сфотографировать')
            ->assertSee('Скачать')
            ->assertSeeHtml('class="receipt-edit-shot"')
            ->assertSeeHtml('class="sr-only"')
            ->assertSeeHtml('download')
            ->assertSeeHtml('capture="environment"')
            ->assertDontSeeHtml('mt-1 block w-full rounded-xl border border-stone-200 bg-white')
            ->assertSee($original)
            ->set('editNote', 'киоск у дома')
            ->call('saveEditedTransaction')
            ->assertHasNoErrors();

        $transaction->refresh();
        $this->assertSame($original, $transaction->receipt_path);
        $this->assertSame('киоск у дома', $transaction->note);
        Storage::disk('public')->assertExists($original);
        $this->assertSame([$original], Storage::disk('public')->allFiles('receipts'));

        $replacement = Livewire::actingAs($user)
            ->withQueryParams(['period' => $period->id])
            ->test(Dashboard::class)
            ->call('editTransaction', $transaction->id)
            ->set('editReceipt', UploadedFile::fake()->image('replacement.jpg'))
            ->assertHasNoErrors();

        $transaction->refresh();
        $this->assertNotNull($transaction->receipt_path);
        $this->assertNotSame($original, $transaction->receipt_path);
        Storage::disk('public')->assertMissing($original);
        Storage::disk('public')->assertExists($transaction->receipt_path);
        $this->assertSame([$transaction->receipt_path], Storage::disk('public')->allFiles('receipts'));
        $replacement->assertSee($transaction->receipt_path);

        $kept = $transaction->receipt_path;
        $replacement
            ->call('removeTransactionReceipt')
            ->assertHasNoErrors()
            ->assertDontSee('Удалить чек')
            ->assertDontSeeHtml('class="receipt-edit-shot"')
            ->assertSee('Загрузить файл')
            ->assertSee('Сфотографировать');

        $transaction->refresh();
        $this->assertNull($transaction->receipt_path);
        Storage::disk('public')->assertMissing($kept);
        $this->assertSame([], Storage::disk('public')->allFiles('receipts'));

        Livewire::actingAs($user)
            ->withQueryParams(['period' => $period->id])
            ->test(Dashboard::class)
            ->call('toggleDeals', $lineId)
            ->assertDontSeeHtml('class="receipt-preview"')
            ->assertDontSeeHtml('class="receipt-stage"')
            ->assertSeeHtml('receipt-label-empty')
            ->assertSee('киоск у дома');

        $replacement
            ->set('editReceipt', UploadedFile::fake()->image('after-delete.jpg'))
            ->assertHasNoErrors();

        $transaction->refresh();
        $this->assertNotNull($transaction->receipt_path);
        Storage::disk('public')->assertExists($transaction->receipt_path);
        $this->assertSame([$transaction->receipt_path], Storage::disk('public')->allFiles('receipts'));

        $beforeCamera = $transaction->receipt_path;
        $replacement
            ->set('editReceiptCamera', UploadedFile::fake()->image('camera.jpg'))
            ->assertHasNoErrors();

        $transaction->refresh();
        $this->assertNotSame($beforeCamera, $transaction->receipt_path);
        Storage::disk('public')->assertMissing($beforeCamera);
        Storage::disk('public')->assertExists($transaction->receipt_path);
        $this->assertSame([$transaction->receipt_path], Storage::disk('public')->allFiles('receipts'));

        $untouched = $transaction->receipt_path;
        $otherHousehold = Household::query()->create([
            'name' => 'Другая',
            'currency' => 'EUR',
            'timezone' => 'Europe/Riga',
            'anchor_day' => 1,
        ]);
        $other = User::factory()->create();
        $otherHousehold->users()->attach($other->id, ['role' => 'owner']);

        Livewire::actingAs($other)
            ->test(Dashboard::class)
            ->call('editTransaction', $transaction->id)
            ->assertStatus(404);

        Livewire::actingAs($other)
            ->test(Dashboard::class)
            ->set('editingTransactionId', $transaction->id)
            ->call('removeTransactionReceipt')
            ->assertStatus(404);

        Livewire::actingAs($other)
            ->test(Dashboard::class)
            ->set('editingTransactionId', $transaction->id)
            ->set('editReceipt', UploadedFile::fake()->image('stolen.jpg'))
            ->assertStatus(404);

        $this->assertSame($untouched, $transaction->fresh()->receipt_path);
        Storage::disk('public')->assertExists($untouched);
        $this->assertSame([$untouched], Storage::disk('public')->allFiles('receipts'));
        $this->assertSame(1, Transaction::query()->whereKey($transaction->id)->whereNotNull('receipt_path')->count());
    }
}
