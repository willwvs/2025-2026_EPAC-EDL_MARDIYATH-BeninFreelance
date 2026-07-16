@php
  $type = $block['type'] ?? 'p';
@endphp

@if ($type === 'p')
  <p class="text-sm sm:text-[15px] text-tx/90 leading-relaxed">{{ $block['text'] }}</p>

@elseif ($type === 'h')
  <h3 class="font-syne font-bold text-lg text-tx pt-2">{{ $block['text'] }}</h3>

@elseif ($type === 'callout')
  @php
    $tone = $block['tone'] ?? 'info';
    $cls = match ($tone) {
      'warn' => 'border-amber-500/35 bg-amber-500/8 text-amber-100',
      'tip' => 'border-emerald-500/35 bg-emerald-500/8 text-emerald-100',
      default => 'border-accent/30 bg-accent/5 text-tx',
    };
    $icon = match ($tone) {
      'warn' => 'fa-triangle-exclamation',
      'tip' => 'fa-lightbulb',
      default => 'fa-circle-info',
    };
  @endphp
  <div class="rounded-xl border px-4 py-3.5 {{ $cls }}">
    <p class="text-xs font-bold uppercase tracking-wider mb-1.5 flex items-center gap-2">
      <i class="fa-solid {{ $icon }}"></i> {{ $block['title'] }}
    </p>
    <p class="text-sm leading-relaxed opacity-95">{{ $block['text'] }}</p>
  </div>

@elseif ($type === 'list')
  @if (!empty($block['title']))
    <p class="text-sm font-semibold text-tx">{{ $block['title'] }}</p>
  @endif
  <ul class="space-y-2 pl-0 list-none">
    @foreach ($block['items'] as $item)
      <li class="flex gap-2.5 text-sm text-tx/90 leading-relaxed">
        <span class="text-accent mt-0.5 shrink-0">•</span>
        <span>{{ $item }}</span>
      </li>
    @endforeach
  </ul>

@elseif ($type === 'steps')
  <ol class="space-y-4">
    @foreach ($block['items'] as $i => $step)
      <li class="flex gap-3">
        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-accent/15 text-accent text-xs font-bold font-syne">{{ $i + 1 }}</span>
        <div class="min-w-0 pt-0.5">
          <p class="font-semibold text-sm text-tx">{{ $step['title'] }}</p>
          <p class="text-sm text-muted leading-relaxed mt-1">{{ $step['text'] }}</p>
        </div>
      </li>
    @endforeach
  </ol>

@elseif ($type === 'visual')
  @php
    $zones = $block['zones'] ?? [];
    $kind = (string)($block['kind'] ?? 'mock');
  @endphp

  <div class="rounded-2xl border border-brd bg-surface2 p-5">
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2 mb-4">
      <div>
        <p class="font-syne font-bold text-base text-tx mb-1">{{ $block['title'] ?? 'Visuel' }}</p>
        @if (!empty($block['caption']))
          <p class="text-sm text-muted leading-relaxed">{{ $block['caption'] }}</p>
        @endif
      </div>
      <div class="text-xs text-muted border border-brd rounded-full px-3 py-1 shrink-0">
        {{ strtoupper($kind) }}
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_1.1fr] gap-4">
      <div class="rounded-xl border border-brd bg-bg/20 p-4">
        <p class="text-xs uppercase tracking-wider font-bold text-muted mb-3">Zone à repérer</p>
        <div class="space-y-3">
          @foreach ($zones as $i => $zone)
            <div class="flex items-start gap-3">
              <span class="flex h-8 w-8 items-center justify-center rounded-full bg-accent/15 text-accent text-xs font-bold font-syne shrink-0">
                {{ $i + 1 }}
              </span>
              <div class="min-w-0">
                <p class="text-sm font-semibold text-tx leading-snug">{{ $zone['label'] ?? '' }}</p>
                @if (!empty($zone['hint']))
                  <p class="text-sm text-muted leading-relaxed mt-0.5">{{ $zone['hint'] }}</p>
                @endif
              </div>
            </div>
          @endforeach
          @if (empty($zones))
            <p class="text-sm text-muted">Aucun visuel défini.</p>
          @endif
        </div>
      </div>

      <div class="rounded-xl border border-brd bg-bg/10 p-4">
        <p class="text-xs uppercase tracking-wider font-bold text-muted mb-3">Ce que ça déclenche</p>
        <div class="space-y-3">
          @foreach ($zones as $i => $zone)
            <div class="flex items-center gap-3">
              <div class="h-3 w-3 rounded-full bg-accent/70 shrink-0"></div>
              <p class="text-sm text-tx/90 leading-relaxed">
                {{ $i + 1 }}. {{ $zone['label'] ?? '' }}
              </p>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>

@elseif ($type === 'faq')
  <div class="space-y-3">
    @foreach ($block['items'] as $faq)
      <details class="group border border-brd rounded-xl bg-surface2 open:bg-surface">
        <summary class="cursor-pointer list-none px-4 py-3.5 font-medium text-sm flex items-center justify-between gap-3">
          <span>{{ $faq['q'] }}</span>
          <i class="fa-solid fa-chevron-down text-muted text-xs group-open:rotate-180 transition-transform"></i>
        </summary>
        <div class="px-4 pb-4 text-sm text-muted leading-relaxed border-t border-brd/60 pt-3">
          {{ $faq['a'] }}
        </div>
      </details>
    @endforeach
  </div>
@endif
