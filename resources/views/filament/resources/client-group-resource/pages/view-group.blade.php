{{-- Client group detail: details left, members right (ClientsRelationManager, editable here). --}}
@php
    $group = $this->record;
    $rows = [
        'Kontak' => $group->contact_name,
        'Telepon' => $group->contact_phone ? '<a href="tel:' . e($group->contact_phone) . '">' . e($group->contact_phone) . '</a>' : null,
        'Email' => $group->contact_email ? '<a href="mailto:' . e($group->contact_email) . '">' . e($group->contact_email) . '</a>' : null,
        'Alamat' => $group->address ? nl2br(e($group->address)) : null,
        'Catatan internal' => $group->notes ? nl2br(e($group->notes)) : null,
    ];
@endphp

<x-filament-panels::page>
    <div class="kis-gview">
        <section class="kis-card kis-ginfo" aria-labelledby="group-info-title">
            <div class="kis-card__head">
                <div>
                    <h3 class="kis-card__title" id="group-info-title">Informasi grup</h3>
                    <p class="kis-card__sub">Kontak dan catatan internal</p>
                </div>
            </div>
            <dl class="kis-ginfo__list">
                @foreach ($rows as $label => $value)
                    <div>
                        <dt>{{ $label }}</dt>
                        @if (filled($value))
                            <dd>{!! $label === 'Kontak' ? e($value) : $value !!}</dd>
                        @else
                            <dd class="is-empty">Belum diisi</dd>
                        @endif
                    </div>
                @endforeach
            </dl>
        </section>

        <div class="kis-gview__members">
            @livewire(\App\Filament\Resources\ClientGroupResource\RelationManagers\ClientsRelationManager::class, [
                'ownerRecord' => $group,
                'pageClass' => $this::class,
            ], key('group-members-' . $group->id))
        </div>
    </div>
</x-filament-panels::page>
