<?php

namespace Tests\Feature;

use App\Livewire\Budget\Vault;
use App\Mail\VaultDocumentMessage;
use App\Models\Household;
use App\Models\SecretDocument;
use App\Models\SecretDocumentSection;
use App\Models\SecretEntry;
use App\Models\SecretGroup;
use App\Models\User;
use App\Support\PersonalVault;
use App\Support\VaultFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class VaultDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_header_uses_the_new_section_name(): void
    {
        [$owner] = $this->family();

        $this->actingAs($owner)
            ->get('/budget')
            ->assertOk()
            ->assertSee('aria-label="Хранение конфиденциальной информации"', false)
            ->assertSee('title="Хранение конфиденциальной информации"', false)
            ->assertDontSee('aria-label="Пароли"', false);

        $this->actingAs($owner)
            ->get('/vault')
            ->assertOk()
            ->assertSee('Хранение конфиденциальной информации')
            ->assertDontSee('>Пароли<', false);
    }

    public function test_owner_can_upload_preview_and_download_a_document(): void
    {
        Storage::fake(VaultFiles::DISK);
        [$owner, $member] = $this->family();
        $pdf = $this->twoPagePdf();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        Livewire::actingAs($owner)
            ->test(Vault::class)
            ->set('pickedFiles', [UploadedFile::fake()->createWithContent('notes.txt', 'hello')])
            ->assertForbidden();

        $page = Livewire::actingAs($owner)
            ->test(Vault::class)
            ->set('gatePassword', 'secret-pass')
            ->call('unlock')
            ->call('openSectionForm')
            ->set('sectionName', 'Договор банка')
            ->call('saveSection')
            ->assertSee('class="vault-camera ', false)
            ->assertSee('.vault-camera { display: none; }', false)
            ->assertSee('(pointer: coarse) and (max-width: 820px)', false)
            ->assertSee('html.vault-phone .vault-camera', false)
            ->assertSee('Выбрать файлы')
            ->assertSee('capture="environment"', false)
            ->assertSee('accept="image/*"', false)
            ->assertSee('wire:model="pickedFiles"', false)
            ->assertDontSee('Choose File')
            ->set('pickedFiles', [UploadedFile::fake()->createWithContent('notes.txt', 'hello')])
            ->assertHasErrors(['pickedFiles'])
            ->set('pickedFiles', [
                UploadedFile::fake()->createWithContent('page-a.png', $png),
                UploadedFile::fake()->createWithContent('page-b.png', $png),
            ])
            ->assertHasNoErrors()
            ->assertSee('Файлы сохранены.')
            ->assertSee('page-a.png')
            ->assertSee('page-b.png')
            ->assertSee('Договор банка')
            ->assertSee('До 10 МБ')
            ->set('cameraFile', UploadedFile::fake()->createWithContent('camera.jpg', $this->jpeg()))
            ->assertSee('camera.jpg');

        $this->assertSame(1, SecretDocumentSection::query()->where('user_id', $owner->id)->count());
        $this->assertSame(3, SecretDocument::query()->where('user_id', $owner->id)->count());

        $document = SecretDocument::query()->where('user_id', $owner->id)->where('original_name', 'page-a.png')->firstOrFail();
        $this->assertSame($owner->id, $document->user_id);
        $this->assertSame('image/png', $document->mime);
        $this->assertSame('page-a.png', $document->original_name);
        $stored = Storage::disk(VaultFiles::DISK)->get($document->path);
        $this->assertNotSame($pdf, $stored);
        $this->assertStringNotContainsString('%PDF', $stored);
        $this->assertSame(0, SecretDocument::query()->where('user_id', $member->id)->count());

        $page->assertSee('<img', false)
            ->assertSee('src="'.route('vault.documents.show', $document).'"', false)
            ->assertSee('page-a.png')
            ->assertSee('width: 8rem; height: 8rem; object-fit: cover', false)
            ->assertSee('data-vault-open="image"', false)
            ->assertSee('>Закрыть<', false)
            ->assertSee('>Следующая<', false)
            ->assertSee('>Предыдущая<', false)
            ->assertDontSee('max-height: 28rem', false)
            ->assertDontSee('Раздел с названием, внутри него файлы. Другой человек из семьи их не видит.')
            ->assertDontSee('>Просмотр<', false)
            ->assertSee(route('vault.documents.download', $document), false);

        $contract = Livewire::actingAs($owner)
            ->test(Vault::class)
            ->set('gatePassword', 'secret-pass')
            ->call('unlock')
            ->call('openSection', SecretDocumentSection::query()->where('user_id', $owner->id)->value('id'))
            ->set('pickedFiles', [UploadedFile::fake()->createWithContent('contract.pdf', $pdf)])
            ->assertSee('contract.pdf')
            ->assertSee('data-vault-open="pdf"', false)
            ->assertSee('id="vault-lightbox-frame"', false)
            ->assertDontSee('height: 36rem', false);

        $pdfDocument = SecretDocument::query()->where('original_name', 'contract.pdf')->firstOrFail();

        $hash = '70aba2ff199bbde2c7a06f0e6460a4824c9bba4a08f2cc4a6fc8bea6a5cdf44d_1.jpg';
        $hashSection = SecretDocumentSection::query()->create([
            'user_id' => $owner->id,
            'name' => 'С телефона',
            'sort' => 20,
        ]);
        SecretDocument::query()->create([
            'user_id' => $owner->id,
            'secret_document_section_id' => $hashSection->id,
            'name' => $hash,
            'original_name' => $hash,
            'mime' => 'image/jpeg',
            'size' => strlen($this->jpeg()),
            'path' => VaultFiles::store($owner->id, $this->jpeg()),
        ]);

        Livewire::actingAs($owner)
            ->test(Vault::class)
            ->set('gatePassword', 'secret-pass')
            ->call('unlock')
            ->call('openSection', $hashSection->id)
            ->assertSee('Фото 1')
            ->assertSee('<img', false)
            ->assertDontSee($hash)
            ->assertDontSee('>Просмотр<', false);

        $this->flushSession();
        $this->actingAs($owner)
            ->get(route('vault.documents.show', $document))
            ->assertForbidden();

        $preview = $this->actingAs($owner)
            ->withSession([PersonalVault::SESSION_KEY => $owner->id])
            ->get(route('vault.documents.show', $document));
        $preview->assertOk();
        $this->assertStringContainsString('image/png', (string) $preview->headers->get('content-type'));
        $this->assertStringContainsString('inline', (string) $preview->headers->get('content-disposition'));
        $this->assertSame($png, $preview->getContent());

        $download = $this->actingAs($owner)
            ->withSession([PersonalVault::SESSION_KEY => $owner->id])
            ->get(route('vault.documents.download', $document));
        $download->assertOk();
        $this->assertStringContainsString('attachment', (string) $download->headers->get('content-disposition'));
        $this->assertStringContainsString('page-a.png', (string) $download->headers->get('content-disposition'));
        $this->assertSame($png, $download->getContent());

        $pdfPreview = $this->actingAs($owner)
            ->withSession([PersonalVault::SESSION_KEY => $owner->id])
            ->get(route('vault.documents.show', $pdfDocument));
        $pdfPreview->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdfPreview->headers->get('content-type'));
        $this->assertStringContainsString('inline', (string) $pdfPreview->headers->get('content-disposition'));
        $this->assertSame($pdf, $pdfPreview->getContent());

        $this->actingAs($member)
            ->withSession([PersonalVault::SESSION_KEY => $member->id])
            ->get(route('vault.documents.show', $document))
            ->assertNotFound();
        $this->actingAs($member)
            ->withSession([PersonalVault::SESSION_KEY => $member->id])
            ->get(route('vault.documents.download', $document))
            ->assertNotFound();

    }

    public function test_wrong_password_deletes_nothing_and_another_member_cannot_delete(): void
    {
        Storage::fake(VaultFiles::DISK);
        [$owner, $member] = $this->family();
        $pdf = $this->twoPagePdf();

        $group = SecretGroup::query()->create([
            'user_id' => $owner->id,
            'name' => 'Банковские коды',
            'sort' => 10,
        ]);
        $entry = SecretEntry::query()->create([
            'secret_group_id' => $group->id,
            'user_id' => $owner->id,
            'name' => 'pin1',
            'payload' => '4821',
            'sort' => 10,
        ]);

        $page = Livewire::actingAs($owner)
            ->test(Vault::class)
            ->set('gatePassword', 'secret-pass')
            ->call('unlock')
            ->call('openSectionForm')
            ->set('sectionName', 'Паспорт')
            ->call('saveSection')
            ->set('pickedFiles', [UploadedFile::fake()->createWithContent('contract.pdf', $pdf)]);

        $section = SecretDocumentSection::query()->where('user_id', $owner->id)->firstOrFail();
        $document = SecretDocument::query()->where('user_id', $owner->id)->firstOrFail();
        $path = $document->path;

        $page->call('openGroup', $group->id)
            ->call('askDeleteEntry', $entry->id)
            ->assertSee('Удалить код «pin1»?')
            ->assertSee('type="password"', false)
            ->set('deletePassword', 'wrong-pass')
            ->call('confirmDelete')
            ->assertHasErrors(['deletePassword'])
            ->set('deletePassword', '')
            ->call('confirmDelete')
            ->assertHasErrors(['deletePassword']);

        $this->assertTrue(SecretEntry::query()->whereKey($entry->id)->exists());
        $this->assertSame('4821', $entry->fresh()->payload);

        $page->set('deletePassword', 'secret-pass')
            ->call('confirmDelete')
            ->assertHasNoErrors()
            ->assertSee('Код удалён.');

        $this->assertFalse(SecretEntry::query()->whereKey($entry->id)->exists());

        $page->call('closeGroup')
            ->call('askDeleteDocument', $document->id)
            ->assertSee('Удалить документ «contract.pdf»?')
            ->assertSee('type="password"', false)
            ->set('deletePassword', 'wrong-pass')
            ->call('confirmDelete')
            ->assertHasErrors(['deletePassword'])
            ->set('deletePassword', '')
            ->call('confirmDelete')
            ->assertHasErrors(['deletePassword']);

        $this->assertTrue(SecretDocument::query()->whereKey($document->id)->exists());
        Storage::disk(VaultFiles::DISK)->assertExists($path);

        $page->set('deletePassword', 'secret-pass')
            ->call('confirmDelete')
            ->assertHasNoErrors()
            ->assertSee('Документ удалён.');

        $this->assertFalse(SecretDocument::query()->whereKey($document->id)->exists());
        Storage::disk(VaultFiles::DISK)->assertMissing($path);

        $page->call('openSection', $section->id)
            ->set('pickedFiles', [UploadedFile::fake()->createWithContent('second.pdf', $pdf)]);
        $second = SecretDocument::query()->where('original_name', 'second.pdf')->firstOrFail();
        $secondPath = $second->path;

        $page->call('askDeleteSection', $section->id)
            ->assertSee('Удалить раздел «Паспорт» и все файлы внутри?')
            ->assertSee('type="password"', false)
            ->set('deletePassword', '')
            ->call('confirmDelete')
            ->assertHasErrors(['deletePassword'])
            ->set('deletePassword', 'wrong-pass')
            ->call('confirmDelete')
            ->assertHasErrors(['deletePassword']);

        $this->assertTrue(SecretDocumentSection::query()->whereKey($section->id)->exists());
        $this->assertTrue(SecretDocument::query()->whereKey($second->id)->exists());
        Storage::disk(VaultFiles::DISK)->assertExists($secondPath);

        $page->set('deletePassword', 'secret-pass')
            ->call('confirmDelete')
            ->assertSee('Раздел удалён.');

        $this->assertFalse(SecretDocumentSection::query()->whereKey($section->id)->exists());
        $this->assertFalse(SecretDocument::query()->whereKey($second->id)->exists());
        Storage::disk(VaultFiles::DISK)->assertMissing($secondPath);

        $replacement = SecretEntry::query()->create([
            'secret_group_id' => $group->id,
            'user_id' => $owner->id,
            'name' => 'pin2',
            'payload' => '9999',
            'sort' => 20,
        ]);

        $page->call('askDeleteGroup', $group->id)
            ->assertSee('Удалить раздел «Банковские коды»')
            ->set('deletePassword', '')
            ->call('confirmDelete')
            ->assertHasErrors(['deletePassword'])
            ->set('deletePassword', 'wrong-pass')
            ->call('confirmDelete')
            ->assertHasErrors(['deletePassword']);

        $this->assertTrue(SecretGroup::query()->whereKey($group->id)->exists());
        $this->assertTrue(SecretEntry::query()->whereKey($replacement->id)->exists());

        $page->set('deletePassword', 'secret-pass')
            ->call('confirmDelete')
            ->assertSee('Раздел удалён.');

        $this->assertFalse(SecretGroup::query()->whereKey($group->id)->exists());
        $this->assertFalse(SecretEntry::query()->whereKey($replacement->id)->exists());

        $keptGroup = SecretGroup::query()->create([
            'user_id' => $owner->id,
            'name' => 'Оставить',
            'sort' => 30,
        ]);
        $keptEntry = SecretEntry::query()->create([
            'secret_group_id' => $keptGroup->id,
            'user_id' => $owner->id,
            'name' => 'pin-keep',
            'payload' => '1111',
            'sort' => 10,
        ]);
        $kept = SecretDocument::query()->create([
            'user_id' => $owner->id,
            'name' => 'Оставить',
            'original_name' => 'keep.pdf',
            'mime' => 'application/pdf',
            'size' => strlen($pdf),
            'path' => VaultFiles::store($owner->id, $pdf),
        ]);
        $foreign = SecretDocument::query()->create([
            'user_id' => $member->id,
            'name' => 'Чужой',
            'original_name' => 'other.pdf',
            'mime' => 'application/pdf',
            'size' => strlen($pdf),
            'path' => VaultFiles::store($member->id, $pdf),
        ]);

        Livewire::actingAs($owner)
            ->test(Vault::class)
            ->set('gatePassword', 'secret-pass')
            ->call('unlock')
            ->call('askDeleteDocument', $foreign->id)
            ->assertStatus(404);

        Livewire::actingAs($member)
            ->test(Vault::class)
            ->set('gatePassword', 'other-pass')
            ->call('unlock')
            ->call('askDeleteEntry', $keptEntry->id)
            ->assertStatus(404);

        Livewire::actingAs($member)
            ->test(Vault::class)
            ->set('gatePassword', 'other-pass')
            ->call('unlock')
            ->set('pendingDeleteType', 'document')
            ->set('pendingDeleteId', $kept->id)
            ->set('deletePassword', 'other-pass')
            ->call('confirmDelete')
            ->assertStatus(404);

        $this->assertTrue(SecretEntry::query()->whereKey($keptEntry->id)->exists());
        $this->assertSame('1111', $keptEntry->fresh()->payload);
        $this->assertTrue(SecretDocument::query()->whereKey($kept->id)->exists());
        $this->assertTrue(SecretDocument::query()->whereKey($foreign->id)->exists());
        $this->actingAs($member)
            ->withSession([PersonalVault::SESSION_KEY => $member->id])
            ->get(route('vault.documents.download', $kept))
            ->assertNotFound();
    }

    public function test_owner_emails_the_document_to_the_address_they_type(): void
    {
        Storage::fake(VaultFiles::DISK);
        Mail::fake();
        [$owner] = $this->family();
        $pdf = $this->twoPagePdf();

        $page = Livewire::actingAs($owner)
            ->test(Vault::class)
            ->set('gatePassword', 'secret-pass')
            ->call('unlock')
            ->call('openSectionForm')
            ->set('sectionName', 'Договор')
            ->call('saveSection')
            ->set('pickedFiles', [UploadedFile::fake()->createWithContent('contract.pdf', $pdf)]);

        $document = SecretDocument::query()->where('user_id', $owner->id)->firstOrFail();

        $page->call('openEmail', $document->id)
            ->set('documentEmail', '')
            ->call('sendDocument')
            ->assertHasErrors(['documentEmail']);

        Mail::assertNothingSent();

        $page->set('documentEmail', 'clerk@example.com')
            ->call('sendDocument')
            ->assertHasNoErrors()
            ->assertSee('Документ отправлен на clerk@example.com.');

        Mail::assertSent(VaultDocumentMessage::class, function (VaultDocumentMessage $mail) use ($pdf): bool {
            $rendered = $mail->render();
            $attachment = $mail->attachments()[0];

            return $mail->hasTo('clerk@example.com')
                && $mail->filename === 'contract.pdf'
                && $mail->mime === 'application/pdf'
                && $mail->contents === $pdf
                && $attachment->as === 'contract.pdf'
                && $attachment->mime === 'application/pdf'
                && ! str_contains($rendered, 'secret-pass')
                && ! str_contains($rendered, '%PDF');
        });

        Mail::assertNotSent(VaultDocumentMessage::class, function (VaultDocumentMessage $mail): bool {
            return $mail->hasTo('rvr@arguss.lv');
        });

        for ($i = 0; $i < 4; $i++) {
            $page->call('openEmail', $document->id)
                ->set('documentEmail', 'clerk@example.com')
                ->call('sendDocument')
                ->assertHasNoErrors();
        }

        $page->call('openEmail', $document->id)
            ->set('documentEmail', 'clerk@example.com')
            ->call('sendDocument')
            ->assertHasErrors(['documentEmail'])
            ->assertSee('Слишком много писем');

        Mail::assertSent(VaultDocumentMessage::class, 5);
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function family(): array
    {
        $household = Household::query()->create([
            'name' => 'Наша семья',
            'currency' => 'EUR',
            'timezone' => 'Europe/Riga',
            'anchor_day' => 1,
        ]);
        $owner = User::factory()->create([
            'email' => 'anna@example.com',
            'password' => 'secret-pass',
        ]);
        $member = User::factory()->create([
            'email' => 'boris@example.com',
            'password' => 'other-pass',
        ]);
        $owner->households()->attach($household->id, ['role' => 'owner']);
        $member->households()->attach($household->id, ['role' => 'member']);

        return [$owner, $member];
    }

    private function twoPagePdf(): string
    {
        $page1 = 'BT /F1 24 Tf 72 700 Td (Bank contract page 1) Tj ET';
        $page2 = 'BT /F1 24 Tf 72 700 Td (Bank contract page 2) Tj ET';
        $objects = [
            "<< /Type /Catalog /Pages 2 0 R >>",
            "<< /Type /Pages /Count 2 /Kids [3 0 R 4 0 R] >>",
            "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 5 0 R /Resources << /Font << /F1 7 0 R >> >> >>",
            "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 6 0 R /Resources << /Font << /F1 7 0 R >> >> >>",
            "<< /Length ".strlen($page1)." >>\nstream\n".$page1."\nendstream",
            "<< /Length ".strlen($page2)." >>\nstream\n".$page2."\nendstream",
            "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $index => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$body."\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n".$xref."\n%%EOF";

        return $pdf;
    }

    private function jpeg(): string
    {
        return base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAb/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAGf/9k=');
    }
}
