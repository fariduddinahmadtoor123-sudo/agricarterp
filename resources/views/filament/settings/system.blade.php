@php
    $rows = app(\App\Support\Settings\SystemInfoPresenter::class)->rows();
@endphp

<div class="agricart-pc-overview agricart-pc-overview--system">
    <section class="agricart-pc-overview__section" aria-labelledby="settings-system-heading">
        <header class="agricart-pc-overview__section-header">
            <h2 id="settings-system-heading" class="agricart-pc-overview__section-title">System</h2>
            <p class="agricart-pc-overview__section-subtitle">
                Read-only runtime and infrastructure details for this install.
            </p>
        </header>

        <section class="agricart-pc-overview__card agricart-pc-overview__card--system">
            <dl class="agricart-pc-overview__detail-list">
                @foreach ($rows as $row)
                    <div class="agricart-pc-overview__detail-row">
                        <dt>{{ $row['label'] }}</dt>
                        <dd @class([
                            'agricart-pc-overview__detail-value--warning' => ($row['tone'] ?? null) === 'warning',
                        ])>{{ $row['value'] }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    </section>
</div>
