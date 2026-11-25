<?php

namespace App\Livewire\Budget;

use App\Mail\VaultDocumentMessage;
use App\Models\SecretDocument;
use App\Models\SecretDocumentSection;
use App\Models\SecretEntry;
use App\Models\SecretGroup;
use App\Support\PersonalVault;
use App\Support\VaultFiles;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Vault extends Component
{
    use WithFileUploads;

    public string $gatePassword = '';

    public ?int $openGroupId = null;

    public ?int $openSectionId = null;

    public bool $showGroupForm = false;

    public bool $showSectionForm = false;

    public string $sectionName = '';

    public ?int $editingSectionId = null;

    public string $groupName = '';

    public ?int $editingGroupId = null;

    public bool $showEntryForm = false;

    public string $entryName = '';

    public string $entryPayload = '';

    public ?int $editingEntryId = null;

    /** @var list<int> */
    public array $revealedIds = [];

    public ?string $pendingDeleteType = null;

    public ?int $pendingDeleteId = null;

    public string $pendingDeleteLabel = '';

    public string $deletePassword = '';

    public mixed $cameraFile = null;

    /** @var array<int, mixed> */
    public array $pickedFiles = [];

    public ?int $previewDocumentId = null;

    public ?int $emailDocumentId = null;

    public string $documentEmail = '';

    public function unlock(): void
    {
        $this->assertNotBlocked('gatePassword');

        $this->validate([
            'gatePassword' => ['required', 'string'],
        ], [], [
            'gatePassword' => __('пароль'),
        ]);

        if (! $this->passwordMatches($this->gatePassword)) {
            $this->failPassword('gatePassword');
        }

        PersonalVault::unlock();
        $this->gatePassword = '';
        $this->resetErrorBag();
    }

    public function openGroup(int $groupId): void
    {
        $this->ownedGroup($groupId);
        $this->openGroupId = $groupId;
        $this->openSectionId = null;
        $this->revealedIds = [];
        $this->closeEntryForm();
        $this->closeGroupForm();
        $this->closeSectionForm();
        $this->closePreview();
        $this->cancelDelete();
    }

    public function closeGroup(): void
    {
        $this->openGroupId = null;
        $this->revealedIds = [];
        $this->closeEntryForm();
        $this->cancelDelete();
    }

    public function openGroupForm(?int $groupId = null): void
    {
        $this->showGroupForm = true;
        $this->editingGroupId = $groupId;
        $this->groupName = $groupId ? $this->ownedGroup($groupId)->name : '';
        $this->cancelDelete();
        $this->resetErrorBag();
    }

    public function closeGroupForm(): void
    {
        $this->showGroupForm = false;
        $this->editingGroupId = null;
        $this->groupName = '';
        $this->resetErrorBag();
    }

    public function saveGroup(): void
    {
        $this->assertUnlocked();
        $this->validate([
            'groupName' => ['required', 'string', 'max:120'],
        ], [], [
            'groupName' => __('название'),
        ]);

        $name = trim($this->groupName);

        if ($this->editingGroupId) {
            $group = $this->ownedGroup($this->editingGroupId);
            $group->update(['name' => $name]);
        } else {
            $sort = (int) SecretGroup::query()->where('user_id', auth()->id())->max('sort') + 10;
            SecretGroup::query()->create([
                'user_id' => auth()->id(),
                'name' => $name,
                'sort' => $sort,
            ]);
        }

        $this->closeGroupForm();
        session()->flash('status', __('Раздел сохранён.'));
    }

    public function askDeleteGroup(int $groupId): void
    {
        $this->assertUnlocked();
        $group = $this->ownedGroup($groupId);
        $this->pendingDeleteType = 'group';
        $this->pendingDeleteId = $group->id;
        $this->pendingDeleteLabel = $group->name;
        $this->deletePassword = '';
        $this->closeGroupForm();
        $this->resetErrorBag();
    }

    public function askDeleteEntry(int $entryId): void
    {
        $this->assertUnlocked();
        $entry = $this->ownedEntry($entryId);
        $this->pendingDeleteType = 'entry';
        $this->pendingDeleteId = $entry->id;
        $this->pendingDeleteLabel = $entry->name;
        $this->deletePassword = '';
        $this->closeEntryForm();
        $this->resetErrorBag();
    }

    public function cancelDelete(): void
    {
        $this->pendingDeleteType = null;
        $this->pendingDeleteId = null;
        $this->pendingDeleteLabel = '';
        $this->deletePassword = '';
        $this->resetErrorBag(['deletePassword']);
    }

    public function confirmDelete(): void
    {
        $this->assertUnlocked();
        $this->assertNotBlocked('deletePassword');

        if ($this->pendingDeleteType === null || $this->pendingDeleteId === null) {
            $this->cancelDelete();

            return;
        }

        $this->validate([
            'deletePassword' => ['required', 'string'],
        ], [], [
            'deletePassword' => __('пароль'),
        ]);

        if (! $this->passwordMatches($this->deletePassword)) {
            $this->failPassword('deletePassword');
        }

        PersonalVault::clearFailures();

        if ($this->pendingDeleteType === 'group') {
            $group = $this->ownedGroup($this->pendingDeleteId);
            $group->delete();

            if ($this->openGroupId === $this->pendingDeleteId) {
                $this->closeGroup();
            }

            session()->flash('status', __('Раздел удалён.'));
        } elseif ($this->pendingDeleteType === 'section') {
            $section = $this->ownedSection($this->pendingDeleteId);
            $paths = $section->documents()->pluck('path')->all();
            $sectionId = (int) $section->id;
            $section->delete();

            foreach ($paths as $path) {
                VaultFiles::delete($path);
            }

            if ($this->openSectionId === $sectionId) {
                $this->closeSection();
            }

            session()->flash('status', __('Раздел удалён.'));
        } elseif ($this->pendingDeleteType === 'document') {
            $document = $this->ownedDocument($this->pendingDeleteId);
            $path = $document->path;
            $document->delete();
            VaultFiles::delete($path);

            if ($this->previewDocumentId === (int) $document->id) {
                $this->previewDocumentId = null;
            }

            if ($this->emailDocumentId === (int) $document->id) {
                $this->closeEmail();
            }

            session()->flash('status', __('Документ удалён.'));
        } else {
            $entry = $this->ownedEntry($this->pendingDeleteId);
            $entry->delete();
            $this->revealedIds = array_values(array_filter(
                $this->revealedIds,
                fn (int $id): bool => $id !== (int) $entry->id,
            ));
            session()->flash('status', __('Код удалён.'));
        }

        $this->cancelDelete();
    }

    public function openEntryForm(?int $entryId = null): void
    {
        $this->assertUnlocked();
        $this->showEntryForm = true;
        $this->editingEntryId = $entryId;
        $this->cancelDelete();

        if ($entryId) {
            $entry = $this->ownedEntry($entryId);
            $this->entryName = $entry->name;
            $this->entryPayload = $entry->payload;
        } else {
            $this->entryName = '';
            $this->entryPayload = '';
        }

        $this->resetErrorBag();
    }

    public function closeEntryForm(): void
    {
        $this->showEntryForm = false;
        $this->editingEntryId = null;
        $this->entryName = '';
        $this->entryPayload = '';
        $this->resetErrorBag();
    }

    public function saveEntry(): void
    {
        $this->assertUnlocked();
        $group = $this->ownedGroup((int) $this->openGroupId);

        $this->validate([
            'entryName' => ['required', 'string', 'max:120'],
            'entryPayload' => ['required', 'string', 'max:2000'],
        ], [], [
            'entryName' => __('название'),
            'entryPayload' => __('код'),
        ]);

        $name = trim($this->entryName);
        $payload = $this->entryPayload;

        if ($this->editingEntryId) {
            $entry = $this->ownedEntry($this->editingEntryId);
            $entry->update([
                'name' => $name,
                'payload' => $payload,
            ]);
            $this->revealedIds = array_values(array_filter(
                $this->revealedIds,
                fn (int $id): bool => $id !== (int) $entry->id,
            ));
        } else {
            $sort = (int) $group->entries()->max('sort') + 10;
            SecretEntry::query()->create([
                'secret_group_id' => $group->id,
                'user_id' => auth()->id(),
                'name' => $name,
                'payload' => $payload,
                'sort' => $sort,
            ]);
        }

        $this->closeEntryForm();
        session()->flash('status', __('Код сохранён.'));
    }

    public function openSectionForm(?int $sectionId = null): void
    {
        $this->assertUnlocked();
        $this->showSectionForm = true;
        $this->editingSectionId = $sectionId;
        $this->sectionName = $sectionId ? $this->ownedSection($sectionId)->name : '';
        $this->cancelDelete();
        $this->resetErrorBag();
    }

    public function closeSectionForm(): void
    {
        $this->showSectionForm = false;
        $this->editingSectionId = null;
        $this->sectionName = '';
        $this->resetErrorBag();
    }

    public function saveSection(): void
    {
        $this->assertUnlocked();
        $this->validate([
            'sectionName' => ['required', 'string', 'max:120'],
        ], [], [
            'sectionName' => __('название'),
        ]);

        $name = trim($this->sectionName);

        if ($this->editingSectionId) {
            $this->ownedSection($this->editingSectionId)->update(['name' => $name]);
            $this->closeSectionForm();
        } else {
            $sort = (int) SecretDocumentSection::query()->where('user_id', auth()->id())->max('sort') + 10;
            $section = SecretDocumentSection::query()->create([
                'user_id' => auth()->id(),
                'name' => $name,
                'sort' => $sort,
            ]);
            $this->closeSectionForm();
            $this->openSection($section->id);
        }

        session()->flash('status', __('Раздел сохранён.'));
    }

    public function openSection(int $sectionId): void
    {
        $this->assertUnlocked();
        $this->ownedSection($sectionId);
        $this->openSectionId = $sectionId;
        $this->openGroupId = null;
        $this->closeGroupForm();
        $this->closeEntryForm();
        $this->closeSectionForm();
        $this->closePreview();
        $this->closeEmail();
        $this->cancelDelete();
        $this->cameraFile = null;
        $this->pickedFiles = [];
    }

    public function closeSection(): void
    {
        $this->openSectionId = null;
        $this->closePreview();
        $this->closeEmail();
        $this->closeSectionForm();
        $this->cameraFile = null;
        $this->pickedFiles = [];
    }

    public function updatedCameraFile(): void
    {
        if ($this->cameraFile === null) {
            return;
        }

        $file = $this->cameraFile;
        $this->cameraFile = null;
        $this->saveUploaded([$file], 'cameraFile');
    }

    public function updatedPickedFiles(): void
    {
        if ($this->pickedFiles === []) {
            return;
        }

        $files = $this->pickedFiles;
        $this->pickedFiles = [];
        $this->saveUploaded($files, 'pickedFiles');
    }

    public function askDeleteSection(int $sectionId): void
    {
        $this->assertUnlocked();
        $section = $this->ownedSection($sectionId);
        $this->pendingDeleteType = 'section';
        $this->pendingDeleteId = $section->id;
        $this->pendingDeleteLabel = $section->name;
        $this->deletePassword = '';
        $this->closeEntryForm();
        $this->closeGroupForm();
        $this->closeSectionForm();
        $this->resetErrorBag();
    }

    public function openPreview(int $documentId): void
    {
        $this->assertUnlocked();
        $this->previewDocumentId = $this->ownedDocument($documentId)->id;
    }

    public function closePreview(): void
    {
        $this->previewDocumentId = null;
    }

    public function openEmail(int $documentId): void
    {
        $this->assertUnlocked();
        $this->emailDocumentId = $this->ownedDocument($documentId)->id;
        $this->documentEmail = '';
        $this->resetErrorBag(['documentEmail']);
    }

    public function closeEmail(): void
    {
        $this->emailDocumentId = null;
        $this->documentEmail = '';
        $this->resetErrorBag(['documentEmail']);
    }

    public function sendDocument(): void
    {
        $this->assertUnlocked();

        if ($this->emailDocumentId === null) {
            return;
        }

        $document = $this->ownedDocument($this->emailDocumentId);

        $this->validate([
            'documentEmail' => ['required', 'email', 'max:190'],
        ], [
            'documentEmail.required' => __('Укажите почту.'),
            'documentEmail.email' => __('Почта выглядит неверно.'),
        ], [
            'documentEmail' => __('почта'),
        ]);

        $key = 'vault-document-mail:'.auth()->id();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'documentEmail' => __('Слишком много писем. Подождите и попробуйте снова.'),
            ]);
        }

        $address = $this->documentEmail;
        $bytes = VaultFiles::read($document->path);

        Mail::to($address)->send(new VaultDocumentMessage(
            filename: $document->downloadFilename(),
            mime: $document->mime,
            contents: $bytes,
        ));

        RateLimiter::hit($key, 600);
        $this->closeEmail();
        session()->flash('status', __('Документ отправлен на :email.', ['email' => $address]));
    }

    /**
     * @param array<int, mixed> $files
     */
    private function saveUploaded(array $files, string $errorKey): void
    {
        $this->assertUnlocked();

        if ($this->openSectionId === null) {
            throw ValidationException::withMessages([
                $errorKey => __('Сначала откройте раздел.'),
            ]);
        }

        $section = $this->ownedSection($this->openSectionId);
        $stored = 0;

        foreach ($files as $file) {
            if (! $file instanceof TemporaryUploadedFile || $file->getSize() < 1) {
                throw ValidationException::withMessages([
                    $errorKey => __('Выберите файл.'),
                ]);
            }

            if ($file->getSize() > VaultFiles::MAX_KILOBYTES * 1024) {
                throw ValidationException::withMessages([
                    $errorKey => __('Файл больше 10 МБ.'),
                ]);
            }

            $extension = strtolower($file->getClientOriginalExtension());
            $contents = $file->get();
            $mime = VaultFiles::mimeFor($extension, $contents);

            if ($mime === null) {
                throw ValidationException::withMessages([
                    $errorKey => __('Нужен файл PDF, JPG, PNG или WEBP.'),
                ]);
            }

            $original = $this->safeOriginalName($file->getClientOriginalName(), $extension);
            $path = VaultFiles::store((int) auth()->id(), $contents);

            SecretDocument::query()->create([
                'user_id' => auth()->id(),
                'secret_document_section_id' => $section->id,
                'name' => $original,
                'original_name' => $original,
                'mime' => $mime,
                'size' => strlen($contents),
                'path' => $path,
            ]);
            $stored++;
        }

        if ($stored === 0) {
            throw ValidationException::withMessages([
                $errorKey => __('Выберите файл.'),
            ]);
        }

        $this->resetErrorBag();
        session()->flash('status', $stored > 1 ? __('Файлы сохранены.') : __('Документ сохранён.'));
    }

    public function askDeleteDocument(int $documentId): void
    {
        $this->assertUnlocked();
        $document = $this->ownedDocument($documentId);
        $this->pendingDeleteType = 'document';
        $this->pendingDeleteId = $document->id;
        $this->pendingDeleteLabel = $document->fileTitle();
        $this->deletePassword = '';
        $this->closeEntryForm();
        $this->closeGroupForm();
        $this->resetErrorBag();
    }

    public function toggleReveal(int $entryId): void
    {
        $this->assertUnlocked();
        $this->ownedEntry($entryId);

        if (in_array($entryId, $this->revealedIds, true)) {
            $this->revealedIds = array_values(array_filter(
                $this->revealedIds,
                fn (int $id): bool => $id !== $entryId,
            ));

            return;
        }

        $this->revealedIds[] = $entryId;
    }

    public function render()
    {
        $blocked = PersonalVault::isBlocked();
        $unlocked = PersonalVault::isUnlocked();
        $groups = collect();
        $openGroup = null;
        $entries = collect();
        $sections = collect();
        $openSection = null;
        $documents = collect();
        $previewDocument = null;

        if ($unlocked) {
            $groups = SecretGroup::query()
                ->where('user_id', auth()->id())
                ->withCount('entries')
                ->orderBy('sort')
                ->orderBy('id')
                ->get();

            if ($this->openGroupId) {
                $openGroup = $groups->firstWhere('id', $this->openGroupId);

                if ($openGroup === null) {
                    $this->openGroupId = null;
                } else {
                    $entries = SecretEntry::query()
                        ->where('user_id', auth()->id())
                        ->where('secret_group_id', $openGroup->id)
                        ->orderBy('sort')
                        ->orderBy('id')
                        ->get();
                }
            }

            if ($this->openGroupId === null) {
                $sections = SecretDocumentSection::query()
                    ->where('user_id', auth()->id())
                    ->withCount('documents')
                    ->orderBy('sort')
                    ->orderBy('id')
                    ->get();

                if ($this->openSectionId) {
                    $openSection = $sections->firstWhere('id', $this->openSectionId);

                    if ($openSection === null) {
                        $this->openSectionId = null;
                    } else {
                        $documents = SecretDocument::query()
                            ->where('user_id', auth()->id())
                            ->where('secret_document_section_id', $openSection->id)
                            ->orderBy('id')
                            ->get();
                    }
                }
            }

            if ($this->previewDocumentId) {
                $previewDocument = $documents->firstWhere('id', $this->previewDocumentId);

                if ($previewDocument === null) {
                    $this->previewDocumentId = null;
                }
            }
        }

        return view('livewire.budget.vault', [
            'blocked' => $blocked,
            'unlocked' => $unlocked,
            'groups' => $groups,
            'openGroup' => $openGroup,
            'entries' => $entries,
            'sections' => $sections,
            'openSection' => $openSection,
            'documents' => $documents,
            'previewDocument' => $previewDocument,
        ])->title(__('Хранение конфиденциальной информации — Family Money'));
    }

    private function passwordMatches(string $password): bool
    {
        $user = auth()->user();

        return $user !== null && Hash::check($password, $user->password);
    }

    private function failPassword(string $field): never
    {
        PersonalVault::registerFailure();

        if (PersonalVault::isBlocked()) {
            $this->resetVaultUi();

            throw ValidationException::withMessages([
                $field => __('Раздел заблокирован после трёх неверных попыток. Выйдите и войдите снова с паролем.'),
            ]);
        }

        auth()->user()?->refresh();
        $left = PersonalVault::remainingAttempts();

        throw ValidationException::withMessages([
            $field => __('Неверный пароль. Осталось попыток: ').$left.'.',
        ]);
    }

    private function assertNotBlocked(string $field): void
    {
        auth()->user()?->refresh();

        if (PersonalVault::isBlocked()) {
            throw ValidationException::withMessages([
                $field => __('Раздел заблокирован после трёх неверных попыток. Выйдите и войдите снова с паролем.'),
            ]);
        }
    }

    private function assertUnlocked(): void
    {
        if (! PersonalVault::isUnlocked()) {
            abort(403);
        }
    }

    private function ownedGroup(int $groupId): SecretGroup
    {
        return SecretGroup::query()
            ->where('user_id', auth()->id())
            ->whereKey($groupId)
            ->firstOrFail();
    }

    private function ownedEntry(int $entryId): SecretEntry
    {
        return SecretEntry::query()
            ->where('user_id', auth()->id())
            ->whereKey($entryId)
            ->firstOrFail();
    }

    private function ownedSection(int $sectionId): SecretDocumentSection
    {
        return SecretDocumentSection::query()
            ->where('user_id', auth()->id())
            ->whereKey($sectionId)
            ->firstOrFail();
    }

    private function ownedDocument(int $documentId): SecretDocument
    {
        return SecretDocument::query()
            ->where('user_id', auth()->id())
            ->whereKey($documentId)
            ->firstOrFail();
    }

    private function safeOriginalName(string $original, string $extension): string
    {
        $name = basename(str_replace('\\', '/', $original));
        $name = str_replace(['"', "\r", "\n"], '', $name);
        $name = trim($name);

        if ($name === '' || $name === '.' || $name === '..') {
            $name = 'document.'.$extension;
        }

        return mb_substr($name, 0, 180);
    }

    private function resetVaultUi(): void
    {
        $this->openGroupId = null;
        $this->openSectionId = null;
        $this->revealedIds = [];
        $this->closeGroupForm();
        $this->closeSectionForm();
        $this->closeEntryForm();
        $this->cancelDelete();
        $this->closePreview();
        $this->closeEmail();
        $this->cameraFile = null;
        $this->pickedFiles = [];
        $this->gatePassword = '';
        $this->resetErrorBag();
    }
}
