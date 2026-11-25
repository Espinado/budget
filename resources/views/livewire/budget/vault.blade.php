<div class="space-y-6">
    <div>
        <x-back-link :href="route('dashboard')">{{ __('← К бюджету') }}</x-back-link>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('Хранение конфиденциальной информации') }}</h1>
        <p class="mt-2 max-w-2xl text-stone-600">{{ __('Личные коды и документы только вашего входа. Другие члены семьи их не видят.') }}</p>
    </div>

    @if (session('status'))
        <p class="rounded-2xl bg-tide-soft px-4 py-3 text-base text-tide">{{ session('status') }}</p>
    @endif

    @if ($blocked)
        <section class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:max-w-md sm:p-5">
            <h2 class="text-lg font-medium text-rose-700">{{ __('Раздел заблокирован') }}</h2>
            <p class="mt-2 text-base text-stone-700">{{ __('Три раза введён неверный пароль. Выйдите из Family Money и войдите снова с паролем. «Запомнить меня» блокировку не снимает.') }}</p>
        </section>
    @elseif (! $unlocked)
        <section class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:max-w-md sm:p-5">
            <h2 class="text-lg font-medium">{{ __('Вход в раздел') }}</h2>
            <p class="mt-1 text-base text-stone-600">{{ __('Введите пароль своего аккаунта. Запомнить пароль нельзя. После трёх ошибок раздел блокируется.') }}</p>
            <form wire:submit="unlock" class="mt-4 grid grid-cols-1 gap-2" autocomplete="off">
                <label class="block">
                    <span class="text-base text-stone-600">{{ __('Пароль') }}</span>
                    <input
                        type="password"
                        wire:model="gatePassword"
                        name="vault-gate"
                        autocomplete="off"
                        autocapitalize="off"
                        autocorrect="off"
                        spellcheck="false"
                        class="mt-1 w-full rounded-2xl border border-stone-200 px-3 py-3 {{ $errors->has('gatePassword') ? 'field-invalid' : '' }}"
                    >
                </label>
                @error('gatePassword') <p class="text-base text-rose-700">{{ $message }}</p> @enderror
                <button type="submit" class="w-full rounded-2xl bg-sky-800 px-4 py-3 text-base font-medium text-white sm:w-auto">{{ __('Открыть') }}</button>
            </form>
        </section>
    @else
        @if ($pendingDeleteType)
            <section class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:max-w-md sm:p-5" data-focus-fields>
                <h2 class="text-lg font-medium">{{ __('Удаление') }}</h2>
                <p class="mt-1 text-base text-stone-600">
                    @if ($pendingDeleteType === 'group')
                        {{ __('Удалить раздел «:name» и все коды внутри? Введите пароль аккаунта.', ['name' => $pendingDeleteLabel]) }}
                    @elseif ($pendingDeleteType === 'section')
                        {{ __('Удалить раздел «:name» и все файлы внутри? Введите пароль аккаунта.', ['name' => $pendingDeleteLabel]) }}
                    @elseif ($pendingDeleteType === 'document')
                        {{ __('Удалить документ «:name»? Введите пароль аккаунта.', ['name' => $pendingDeleteLabel]) }}
                    @else
                        {{ __('Удалить код «:name»? Введите пароль аккаунта.', ['name' => $pendingDeleteLabel]) }}
                    @endif
                </p>
                <form wire:submit="confirmDelete" class="mt-4 grid grid-cols-1 gap-2" autocomplete="off">
                    <label class="block">
                        <span class="text-base text-stone-600">{{ __('Пароль') }}</span>
                        <input
                            type="password"
                            wire:model="deletePassword"
                            name="vault-delete"
                            autocomplete="off"
                            autocapitalize="off"
                            spellcheck="false"
                            class="mt-1 w-full rounded-2xl border border-stone-200 px-3 py-3 {{ $errors->has('deletePassword') ? 'field-invalid' : '' }}"
                        >
                    </label>
                    @error('deletePassword') <p class="text-base text-rose-700">{{ $message }}</p> @enderror
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="rounded-2xl bg-rose-600 px-4 py-3 text-base font-medium text-white">{{ __('Удалить') }}</button>
                        <button type="button" wire:click="cancelDelete" class="rounded-2xl bg-sky-800 px-4 py-3 text-base font-medium text-white">{{ __('Отмена') }}</button>
                    </div>
                </form>
            </section>
        @elseif ($openSection === null && $openGroup === null)
            <section class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-lg font-medium">{{ __('Разделы') }}</h2>
                    @unless ($showGroupForm)
                        <button type="button" wire:click="openGroupForm" data-focus-open class="rounded-2xl bg-sky-800 px-4 py-3 text-base font-medium text-white">{{ __('Новый раздел') }}</button>
                    @endunless
                </div>

                @if ($showGroupForm)
                    <form wire:submit="saveGroup" class="mt-4 grid grid-cols-1 gap-2 sm:max-w-md" data-focus-fields>
                        <label class="block">
                            <span class="text-base text-stone-600">{{ __('Название') }}</span>
                            <input type="text" wire:model="groupName" class="mt-1 w-full rounded-2xl border border-stone-200 px-3 py-3 {{ $errors->has('groupName') ? 'field-invalid' : '' }}" placeholder="{{ __('Банковские коды') }}">
                        </label>
                        @error('groupName') <p class="text-base text-rose-700">{{ $message }}</p> @enderror
                        <div class="flex flex-wrap gap-2">
                            <button type="submit" class="rounded-2xl bg-emerald-600 px-4 py-3 text-base font-medium text-white">{{ __('Сохранить') }}</button>
                            <button type="button" wire:click="closeGroupForm" class="rounded-2xl bg-rose-600 px-4 py-3 text-base font-medium text-white">{{ __('Отмена') }}</button>
                        </div>
                    </form>
                @endif

                <ul class="mt-4 divide-y divide-stone-100">
                    @forelse ($groups as $group)
                        <li class="flex items-center justify-between gap-3 py-3">
                            <button type="button" wire:click="openGroup({{ $group->id }})" class="min-w-0 flex-1 text-left">
                                <span class="block text-lg font-medium text-ink">{{ $group->name }}</span>
                                <span class="block text-base text-stone-600">{{ $group->entries_count }} {{ $group->entries_count === 1 ? __('код') : __('кодов') }}</span>
                            </button>
                            <div class="flex shrink-0 gap-2">
                                <button type="button" wire:click="openGroupForm({{ $group->id }})" class="text-base font-medium text-sky-800">{{ __('Изменить') }}</button>
                                <button type="button" wire:click="askDeleteGroup({{ $group->id }})" data-focus-open class="text-base font-medium text-rose-700">{{ __('Удалить') }}</button>
                            </div>
                        </li>
                    @empty
                        <li class="py-3 text-base text-stone-600">{{ __('Пока нет разделов.') }}</li>
                    @endforelse
                </ul>
            </section>

            <section class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-lg font-medium">{{ __('Документы') }}</h2>
                    @unless ($showSectionForm)
                        <button type="button" wire:click="openSectionForm" data-focus-open class="rounded-2xl bg-sky-800 px-4 py-3 text-base font-medium text-white">{{ __('Новый раздел') }}</button>
                    @endunless
                </div>

                @if ($showSectionForm)
                    <form wire:submit="saveSection" class="mt-4 grid grid-cols-1 gap-2 sm:max-w-md" data-focus-fields>
                        <label class="block">
                            <span class="text-base text-stone-600">{{ __('Название') }}</span>
                            <input type="text" wire:model="sectionName" class="mt-1 w-full rounded-2xl border border-stone-200 px-3 py-3 {{ $errors->has('sectionName') ? 'field-invalid' : '' }}" placeholder="{{ __('Банковский договор') }}">
                        </label>
                        @error('sectionName') <p class="text-base text-rose-700">{{ $message }}</p> @enderror
                        <div class="flex flex-wrap gap-2">
                            <button type="submit" class="rounded-2xl bg-emerald-600 px-4 py-3 text-base font-medium text-white">{{ __('Сохранить') }}</button>
                            <button type="button" wire:click="closeSectionForm" class="rounded-2xl bg-rose-600 px-4 py-3 text-base font-medium text-white">{{ __('Отмена') }}</button>
                        </div>
                    </form>
                @endif

                <ul class="mt-4 divide-y divide-stone-100">
                    @forelse ($sections as $section)
                        <li class="flex items-center justify-between gap-3 py-3">
                            <button type="button" wire:click="openSection({{ $section->id }})" class="min-w-0 flex-1 text-left">
                                <span class="block text-lg font-medium text-ink">{{ $section->name }}</span>
                                <span class="block text-base text-stone-600">{{ $section->documents_count }} {{ $section->documents_count === 1 ? __('файл') : __('файлов') }}</span>
                            </button>
                            <div class="flex shrink-0 gap-2">
                                <button type="button" wire:click="openSectionForm({{ $section->id }})" class="text-base font-medium text-sky-800">{{ __('Изменить') }}</button>
                                <button type="button" wire:click="askDeleteSection({{ $section->id }})" data-focus-open class="text-base font-medium text-rose-700">{{ __('Удалить') }}</button>
                            </div>
                        </li>
                    @empty
                        <li class="py-3 text-base text-stone-600">{{ __('Пока нет разделов.') }}</li>
                    @endforelse
                </ul>
            </section>
        @elseif ($openSection)
            <section class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
                <button type="button" wire:click="closeSection" class="inline-flex items-center rounded-2xl bg-sky-100 px-3 py-2 text-base font-medium text-sky-900 ring-1 ring-sky-200 hover:bg-sky-200">{{ __('← К документам') }}</button>
                <h2 class="mt-2 text-lg font-medium">{{ $openSection->name }}</h2>
                <p class="mt-1 text-base text-stone-600">{{ __('Выбрать файлы добавляет PDF или уже снятые снимки, можно несколько. На телефоне кнопка «Сфотографировать» открывает камеру. До 10 МБ, файл шифруется.') }}</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <label class="vault-camera rounded-2xl bg-sky-800 px-4 py-3 text-base font-medium text-white" style="position: relative; overflow: hidden; align-items: center; justify-content: center">
                        {{ __('Сфотографировать') }}
                        <input type="file" wire:model="cameraFile" accept="image/*" capture="environment" style="position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; font-size: 100px">
                    </label>
                    <label class="rounded-2xl bg-emerald-600 px-4 py-3 text-base font-medium text-white" style="position: relative; overflow: hidden; display: inline-flex; align-items: center; justify-content: center">
                        {{ __('Выбрать файлы') }}
                        <input type="file" wire:model="pickedFiles" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,.heif,application/pdf,image/jpeg,image/png,image/webp,image/heic,image/heif" style="position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; font-size: 100px">
                    </label>
                </div>
                @error('cameraFile') <p class="mt-2 text-base text-rose-700">{{ $message }}</p> @enderror
                @error('pickedFiles') <p class="mt-2 text-base text-rose-700">{{ $message }}</p> @enderror
                @error('pickedFiles.*') <p class="mt-2 text-base text-rose-700">{{ $message }}</p> @enderror

                @php($vaultPhotoCount = $documents->filter(fn ($item) => $item->isImage())->count())
                @if ($documents->isEmpty())
                    <p class="mt-4 text-base text-stone-600">{{ __('В этом разделе ещё нет файлов.') }}</p>
                @else
                    <div class="mt-4 flex flex-wrap items-start gap-3">
                        @foreach ($documents as $document)
                            <div style="width: {{ $emailDocumentId === $document->id ? '100%' : ($document->isImage() ? '12rem' : '18rem') }}">
                                @if ($document->isImage())
                                    <button type="button" data-vault-open="image" data-vault-src="{{ route('vault.documents.show', $document) }}" data-vault-title="{{ $document->fileTitle() }}" style="display: block; padding: 0; border: 0; background: transparent; cursor: pointer">
                                        <img src="{{ route('vault.documents.show', $document) }}" alt="{{ $document->fileTitle() }}" class="ring-1 ring-stone-900/10" style="width: 8rem; height: 8rem; object-fit: cover; border-radius: 1rem; display: block">
                                    </button>
                                @else
                                    <button type="button" data-vault-open="pdf" data-vault-src="{{ route('vault.documents.show', $document) }}" data-vault-title="{{ $document->fileTitle() }}" class="w-full rounded-2xl bg-stone-50 px-3 py-3 text-left ring-1 ring-stone-900/10" style="cursor: pointer">
                                        <span class="block text-lg font-medium text-ink">{{ $document->fileTitle() }}</span>
                                    </button>
                                @endif
                                @if ($document->isImage())
                                    <p class="mt-2 text-base font-medium text-ink">{{ $document->fileTitle() }}</p>
                                @endif
                                <div class="mt-2 flex flex-wrap gap-3">
                                    <a href="{{ route('vault.documents.download', $document) }}" class="text-base font-medium text-sky-800">{{ __('Скачать') }}</a>
                                    <button type="button" wire:click="openEmail({{ $document->id }})" class="text-base font-medium text-sky-800">{{ __('Отправить почтой') }}</button>
                                    <button type="button" wire:click="askDeleteDocument({{ $document->id }})" data-focus-open class="text-base font-medium text-rose-700">{{ __('Удалить') }}</button>
                                </div>
                                @if ($emailDocumentId === $document->id)
                                    <form wire:submit="sendDocument" class="mt-3 grid grid-cols-1 gap-2 sm:max-w-md">
                                        <label class="block">
                                            <span class="text-base text-stone-600">{{ __('Почта получателя') }}</span>
                                            <input type="email" wire:model="documentEmail" autocomplete="off" class="mt-1 w-full rounded-2xl border border-stone-200 px-3 py-3 {{ $errors->has('documentEmail') ? 'field-invalid' : '' }}">
                                        </label>
                                        @error('documentEmail') <p class="text-base text-rose-700">{{ $message }}</p> @enderror
                                        <div class="flex flex-wrap gap-2">
                                            <button type="submit" class="rounded-2xl bg-emerald-600 px-4 py-3 text-base font-medium text-white">{{ __('Отправить') }}</button>
                                            <button type="button" wire:click="closeEmail" class="rounded-2xl bg-rose-600 px-4 py-3 text-base font-medium text-white">{{ __('Отмена') }}</button>
                                        </div>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                <div id="vault-lightbox" hidden style="position: fixed; inset: 0; z-index: 80; display: none; align-items: center; justify-content: center; background: rgba(28, 25, 23, 0.82); padding: 1rem">
                    <div style="width: min(100%, 64rem); max-height: 92vh; display: flex; flex-direction: column; gap: 0.75rem">
                        <div class="flex flex-wrap justify-end gap-2">
                            @if ($vaultPhotoCount > 1)
                                <button type="button" id="vault-lightbox-prev" class="rounded-2xl bg-white px-4 py-2 text-base font-medium text-ink">{{ __('Предыдущая') }}</button>
                                <button type="button" id="vault-lightbox-next" class="rounded-2xl bg-white px-4 py-2 text-base font-medium text-ink">{{ __('Следующая') }}</button>
                            @endif
                            <button type="button" id="vault-lightbox-close" class="rounded-2xl bg-white px-4 py-2 text-base font-medium text-ink">{{ __('Закрыть') }}</button>
                        </div>
                        <img id="vault-lightbox-img" alt="" style="display: none; max-width: 100%; max-height: 80vh; object-fit: contain; margin: 0 auto; border-radius: 1rem; background: #fff">
                        <iframe id="vault-lightbox-frame" title="" style="display: none; width: 100%; height: 80vh; border: 0; border-radius: 1rem; background: #fff"></iframe>
                    </div>
                </div>
            </section>
        @else
            <section class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <button type="button" wire:click="closeGroup" class="inline-flex items-center rounded-2xl bg-sky-100 px-3 py-2 text-base font-medium text-sky-900 ring-1 ring-sky-200 hover:bg-sky-200">{{ __('← К разделам') }}</button>
                        <h2 class="mt-2 text-lg font-medium">{{ $openGroup->name }}</h2>
                    </div>
                    @unless ($showEntryForm)
                        <button type="button" wire:click="openEntryForm" data-focus-open class="rounded-2xl bg-sky-800 px-4 py-3 text-base font-medium text-white">{{ __('Новый код') }}</button>
                    @endunless
                </div>

                @if ($showEntryForm)
                    <form wire:submit="saveEntry" class="mt-4 grid grid-cols-1 gap-2 sm:max-w-md" data-focus-fields autocomplete="off">
                        <label class="block">
                            <span class="text-base text-stone-600">{{ __('Название') }}</span>
                            <input type="text" wire:model="entryName" class="mt-1 w-full rounded-2xl border border-stone-200 px-3 py-3 {{ $errors->has('entryName') ? 'field-invalid' : '' }}" placeholder="pin1" autocomplete="off">
                        </label>
                        <label class="block">
                            <span class="text-base text-stone-600">{{ __('Код') }}</span>
                            <input type="text" wire:model="entryPayload" class="mt-1 w-full rounded-2xl border border-stone-200 px-3 py-3 {{ $errors->has('entryPayload') ? 'field-invalid' : '' }}" autocomplete="off" autocapitalize="off" spellcheck="false">
                        </label>
                        @error('entryName') <p class="text-base text-rose-700">{{ $message }}</p> @enderror
                        @error('entryPayload') <p class="text-base text-rose-700">{{ $message }}</p> @enderror
                        <div class="flex flex-wrap gap-2">
                            <button type="submit" class="rounded-2xl bg-emerald-600 px-4 py-3 text-base font-medium text-white">{{ __('Сохранить') }}</button>
                            <button type="button" wire:click="closeEntryForm" class="rounded-2xl bg-rose-600 px-4 py-3 text-base font-medium text-white">{{ __('Отмена') }}</button>
                        </div>
                    </form>
                @endif

                <ul class="mt-4 divide-y divide-stone-100">
                    @forelse ($entries as $entry)
                        @php($shown = in_array((int) $entry->id, $revealedIds, true))
                        <li class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="text-lg font-medium text-ink">{{ $entry->name }}</p>
                                <p class="mt-1 break-all font-mono text-base tabular-nums text-stone-800">
                                    @if ($shown)
                                        {{ $entry->payload }}
                                    @else
                                        ••••••••
                                    @endif
                                </p>
                            </div>
                            <div class="flex shrink-0 flex-wrap gap-2">
                                <button
                                    type="button"
                                    wire:click="toggleReveal({{ $entry->id }})"
                                    class="inline-grid place-items-center rounded-full text-stone-700 hover:bg-stone-100"
                                    style="width: 2.75rem; height: 2.75rem"
                                    title="{{ $shown ? __('Скрыть') : __('Показать') }}"
                                    aria-label="{{ $shown ? __('Скрыть код') : __('Показать код') }}"
                                >
                                    @if ($shown)
                                        <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-5 0-9.27-3.11-11-8 1.02-2.89 2.94-5.1 5.29-6.41"></path>
                                            <path d="M1 1l22 22"></path>
                                            <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c5 0 9.27 3.11 11 8a11.5 11.5 0 0 1-2.16 3.19"></path>
                                            <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"></path>
                                        </svg>
                                    @else
                                        <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    @endif
                                </button>
                                <button type="button" wire:click="openEntryForm({{ $entry->id }})" class="text-base font-medium text-sky-800">{{ __('Изменить') }}</button>
                                <button type="button" wire:click="askDeleteEntry({{ $entry->id }})" data-focus-open class="text-base font-medium text-rose-700">{{ __('Удалить') }}</button>
                            </div>
                        </li>
                    @empty
                        <li class="py-3 text-base text-stone-600">{{ __('В этом разделе ещё нет кодов.') }}</li>
                    @endforelse
                </ul>
            </section>
        @endif
    @endif
    <style>
        .vault-camera { display: none; }
        @media (pointer: coarse) and (max-width: 820px) {
            .vault-camera { display: inline-flex; }
        }
        html.vault-phone .vault-camera { display: inline-flex; }
    </style>
    <script>
        (function () {
            var ua = navigator.userAgent || '';
            if (/Android|iPhone|iPod|webOS|BlackBerry|IEMobile|Opera Mini|Mobile/i.test(ua)) {
                document.documentElement.classList.add('vault-phone');
            }
        })();
        (function () {
            if (window.__vaultLightboxBound) {
                return;
            }
            window.__vaultLightboxBound = true;

            function overlay() {
                return document.getElementById('vault-lightbox');
            }

            function show(button) {
                var box = overlay();
                if (!box || !button) {
                    return;
                }
                var kind = button.getAttribute('data-vault-open');
                var src = button.getAttribute('data-vault-src') || '';
                var title = button.getAttribute('data-vault-title') || '';
                var img = document.getElementById('vault-lightbox-img');
                var frame = document.getElementById('vault-lightbox-frame');
                var prev = document.getElementById('vault-lightbox-prev');
                var next = document.getElementById('vault-lightbox-next');
                var photos = Array.prototype.slice.call(document.querySelectorAll('[data-vault-open="image"]'));
                box.__vaultPhotos = photos;
                box.__vaultIndex = photos.indexOf(button);
                box.__vaultKind = kind;
                if (kind === 'image') {
                    if (frame) {
                        frame.style.display = 'none';
                        frame.removeAttribute('src');
                    }
                    img.src = src;
                    img.alt = title;
                    img.style.display = 'block';
                    if (prev) prev.hidden = photos.length < 2;
                    if (next) next.hidden = photos.length < 2;
                } else {
                    img.style.display = 'none';
                    img.removeAttribute('src');
                    frame.src = src;
                    frame.title = title;
                    frame.style.display = 'block';
                    if (prev) prev.hidden = true;
                    if (next) next.hidden = true;
                }
                box.hidden = false;
                box.style.display = 'flex';
            }

            function closeBox() {
                var box = overlay();
                if (!box) {
                    return;
                }
                var img = document.getElementById('vault-lightbox-img');
                var frame = document.getElementById('vault-lightbox-frame');
                if (img) {
                    img.style.display = 'none';
                    img.removeAttribute('src');
                }
                if (frame) {
                    frame.style.display = 'none';
                    frame.removeAttribute('src');
                }
                box.style.display = 'none';
                box.hidden = true;
            }

            function step(delta) {
                var box = overlay();
                if (!box || box.hidden || box.__vaultKind !== 'image') {
                    return;
                }
                var photos = box.__vaultPhotos || [];
                if (photos.length < 2) {
                    return;
                }
                var index = box.__vaultIndex < 0 ? 0 : box.__vaultIndex;
                show(photos[(index + delta + photos.length) % photos.length]);
            }

            document.addEventListener('click', function (event) {
                var open = event.target.closest('[data-vault-open]');
                if (open) {
                    event.preventDefault();
                    show(open);
                    return;
                }
                if (event.target.closest('#vault-lightbox') && !event.target.closest('#vault-lightbox img, #vault-lightbox iframe, #vault-lightbox button')) {
                    closeBox();
                    return;
                }
                if (event.target.closest('#vault-lightbox-close')) {
                    closeBox();
                    return;
                }
                if (event.target.closest('#vault-lightbox-next')) {
                    step(1);
                }
                if (event.target.closest('#vault-lightbox-prev')) {
                    step(-1);
                }
            });

            document.addEventListener('keydown', function (event) {
                var box = overlay();
                if (!box || box.hidden) {
                    return;
                }
                if (event.key === 'Escape') {
                    event.preventDefault();
                    closeBox();
                } else if (event.key === 'ArrowRight') {
                    event.preventDefault();
                    step(1);
                } else if (event.key === 'ArrowLeft') {
                    event.preventDefault();
                    step(-1);
                }
            });
        })();
    </script>
</div>
