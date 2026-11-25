<?php

namespace Tests\Feature;

use App\Enums\CashflowType;
use App\Livewire\Budget\PropertyObjectForm;
use App\Models\Category;
use App\Models\Household;
use App\Models\PropertyContract;
use App\Models\PropertyObject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PropertyContractDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Storage::disk('local')->deleteDirectory('contract-documents');

        parent::tearDown();
    }

    public function test_a_contract_stores_one_private_document_and_shows_its_thumbnail(): void
    {
        [$user, $household] = $this->family('Наша');
        $object = $this->object($household);
        $image = UploadedFile::fake()->image('polis.jpg', 80, 120);

        $card = Livewire::actingAs($user)
            ->test(PropertyObjectForm::class, ['propertyObject' => $object])
            ->call('openContract')
            ->set('contractTitle', 'ERGO')
            ->set('contractNote', 'полис 77')
            ->set('contractParts', [['amount' => '32,09', 'date' => '2026-11-12']])
            ->set('contractDocument', $image)
            ->assertHasNoErrors()
            ->assertSeeHtml('class="receipt-preview"')
            ->assertSeeHtml('class="receipt-dialog"')
            ->assertSeeHtml('class="receipt-stage"')
            ->call('saveContract')
            ->assertHasNoErrors();

        $contract = PropertyContract::query()->where('title', 'ERGO')->firstOrFail();
        $url = route('objects.contracts.document', $contract);
        $html = $card->html();

        $this->assertNotNull($contract->document_path);
        $this->assertSame('image/jpeg', $contract->document_mime);
        $this->assertSame('polis.jpg', $contract->document_name);
        Storage::disk('local')->assertExists($contract->document_path);
        $this->assertStringContainsString(
            'private',
            str_replace('\\', '/', Storage::disk('local')->path($contract->document_path)),
        );
        Storage::disk('public')->assertMissing($contract->document_path);
        $this->assertFileDoesNotExist(public_path($contract->document_path));
        $this->assertSame(1, substr_count($html, 'class="receipt-preview"'));
        $this->assertSame(1, substr_count($html, 'width="48"'));
        $this->assertSame(1, substr_count($html, 'height="64"'));
        $this->assertStringContainsString($url, $html);
        $this->assertStringContainsString('<dialog class="receipt-dialog"', $html);
        $this->assertStringContainsString('showModal()', $html);
        $this->assertStringContainsString('Изменить контракт', $html);
        $this->assertStringNotContainsString('Заменить документ', $html);
        $this->assertStringNotContainsString('Убрать документ', $html);
        $this->assertStringNotContainsString('replacementFiles', $html);

        $this->actingAs($user)
            ->get($url)
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg');

        $kept = $contract->document_path;
        $replacement = UploadedFile::fake()->image('polis-2.jpg', 40, 60);
        $card->call('editContract', $contract->id)
            ->assertSet('contractTitle', 'ERGO')
            ->assertSet('contractNote', 'полис 77')
            ->assertSee('Заменить документ')
            ->assertSee('Убрать документ')
            ->set('contractDocument', $replacement)
            ->call('cancelContract')
            ->assertSet('showContract', false);

        $contract->refresh();
        $this->assertSame($kept, $contract->document_path);
        Storage::disk('local')->assertExists($kept);
        $this->assertSame([$kept], Storage::disk('local')->allFiles('contract-documents'));

        $card->call('editContract', $contract->id)
            ->set('contractDocument', $replacement)
            ->call('saveContract')
            ->assertHasNoErrors()
            ->assertDontSee('Заменить документ');

        $contract->refresh();
        $this->assertSame(1, PropertyContract::query()->count());
        $this->assertNotSame($kept, $contract->document_path);
        Storage::disk('local')->assertMissing($kept);
        Storage::disk('local')->assertExists($contract->document_path);
        $this->assertSame([$contract->document_path], Storage::disk('local')->allFiles('contract-documents'));
        $this->assertSame('image/jpeg', $contract->document_mime);
        $this->assertSame('polis-2.jpg', $contract->document_name);

        [$other] = $this->family('Чужая');
        $this->actingAs($other)
            ->get(route('objects.contracts.document', $contract))
            ->assertNotFound();
        $this->actingAs($other)
            ->get(route('objects.contracts.document', ['contract' => $contract, 'download' => 1]))
            ->assertNotFound();

        $this->actingAs($user)
            ->get(route('objects.contracts.document', $contract))
            ->assertOk();

        $removed = $contract->document_path;
        $card->call('editContract', $contract->id)
            ->call('clearContractDocument')
            ->call('cancelContract');

        $this->assertSame($removed, $contract->fresh()->document_path);
        Storage::disk('local')->assertExists($removed);

        $card->call('editContract', $contract->id)
            ->call('clearContractDocument')
            ->call('saveContract')
            ->assertHasNoErrors()
            ->assertDontSeeHtml('class="receipt-preview"')
            ->assertDontSeeHtml('class="receipt-stage"');

        $this->assertNull($contract->fresh()->document_path);
        Storage::disk('local')->assertMissing($removed);
        $this->assertSame([], Storage::disk('local')->allFiles('contract-documents'));
    }

    public function test_a_pdf_contract_document_uses_a_document_thumbnail_and_an_embedded_view(): void
    {
        [$user, $household] = $this->family('Наша');
        $object = $this->object($household);
        $pdf = UploadedFile::fake()->createWithContent(
            'ergo.pdf',
            "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<<>>\n%%EOF",
        );

        $card = Livewire::actingAs($user)
            ->test(PropertyObjectForm::class, ['propertyObject' => $object])
            ->call('openContract')
            ->set('contractTitle', 'ERGO')
            ->set('contractParts', [['amount' => '10,00', 'date' => '2026-11-20']])
            ->set('contractDocument', $pdf)
            ->assertSeeHtml('contract-document-thumb')
            ->assertSeeHtml('<iframe')
            ->assertDontSeeHtml('class="receipt-stage"')
            ->call('saveContract')
            ->assertHasNoErrors();

        $contract = PropertyContract::query()->where('title', 'ERGO')->firstOrFail();
        $html = $card->html();
        $url = route('objects.contracts.document', $contract);

        $this->assertSame('application/pdf', $contract->document_mime);
        $this->assertStringContainsString('contract-document-thumb', $html);
        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringContainsString($url, $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertDoesNotMatchRegularExpression('/<img[^>]+'.preg_quote($url, '/').'/', $html);
        Storage::disk('local')->assertExists($contract->document_path);
        $this->assertSame([$contract->document_path], Storage::disk('local')->allFiles('contract-documents'));

        $this->actingAs($user)
            ->get($url)
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        [$other] = $this->family('Чужая');
        $this->actingAs($other)->get($url)->assertNotFound();

        $path = $contract->document_path;
        $object->delete();
        Storage::disk('local')->assertMissing($path);
        $this->assertSame([], Storage::disk('local')->allFiles('contract-documents'));
    }

    /**
     * @return array{0: User, 1: Household}
     */
    private function family(string $name): array
    {
        $household = Household::query()->create([
            'name' => $name,
            'currency' => 'EUR',
            'timezone' => 'Europe/Riga',
            'anchor_day' => 1,
        ]);
        $user = User::factory()->create();
        $household->users()->attach($user->id, ['role' => 'owner']);

        return [$user, $household];
    }

    private function object(Household $household): PropertyObject
    {
        $home = Category::query()->create([
            'household_id' => $household->id,
            'type' => CashflowType::Expense,
            'name' => 'Дом',
            'sort' => 1,
        ]);
        $folder = Category::query()->create([
            'household_id' => $household->id,
            'parent_id' => $home->id,
            'type' => CashflowType::Expense,
            'name' => 'Прушу',
            'sort' => 2,
        ]);

        return PropertyObject::query()->create([
            'household_id' => $household->id,
            'category_id' => $folder->id,
            'name' => 'Прушу',
            'kind' => 'apartment',
            'label' => 'Прушу 1',
        ]);
    }
}
