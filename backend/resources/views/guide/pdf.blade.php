<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <title>{{ $meta['title'] }}</title>
  <style>
    @page {
      margin: 22mm 18mm 24mm 18mm;
    }
    * { box-sizing: border-box; }
    body {
      font-family: DejaVu Sans, sans-serif;
      font-size: 10.5pt;
      line-height: 1.45;
      color: #1a1a1f;
      margin: 0;
      padding: 0;
    }
    .cover {
      text-align: center;
      padding: 48px 12px 36px;
      border-bottom: 2px solid #44444f;
      margin-bottom: 28px;
      page-break-after: always;
    }
    .cover .brand {
      font-size: 22pt;
      font-weight: bold;
      letter-spacing: -0.02em;
      margin: 0 0 8px;
    }
    .cover .brand span { color: #2c2c35; }
    .cover .kicker {
      font-size: 9pt;
      text-transform: uppercase;
      letter-spacing: 0.14em;
      color: #7A7A9A;
      margin: 0 0 18px;
    }
    .cover h1 {
      font-size: 18pt;
      margin: 0 0 12px;
      color: #0A0A0F;
      line-height: 1.25;
    }
    .cover .sub {
      font-size: 10.5pt;
      color: #55556a;
      max-width: 420px;
      margin: 0 auto 22px;
    }
    .meta-row {
      font-size: 9pt;
      color: #7A7A9A;
    }
    .toc {
      page-break-after: always;
      padding-top: 8px;
    }
    .toc h2 {
      font-size: 14pt;
      margin: 0 0 14px;
      padding-bottom: 6px;
      border-bottom: 1px solid #dddde8;
    }
    .toc ol {
      margin: 0;
      padding-left: 20px;
    }
    .toc li {
      margin: 0 0 8px;
      color: #333;
    }
    .toc .role {
      font-size: 8pt;
      color: #7A7A9A;
      text-transform: uppercase;
      letter-spacing: 0.06em;
    }
    h2.chapter {
      font-size: 13.5pt;
      color: #0A0A0F;
      margin: 0 0 10px;
      padding: 8px 0 6px;
      border-bottom: 1.5px solid #44444f;
      page-break-after: avoid;
    }
    .chapter-wrap {
      page-break-inside: avoid;
      margin-bottom: 22px;
    }
    .badge {
      display: inline-block;
      font-size: 7.5pt;
      font-weight: bold;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      padding: 2px 7px;
      border-radius: 10px;
      margin-left: 6px;
      vertical-align: middle;
    }
    .badge-client { background: #e9eaee; color: #33333f; }
    .badge-presta { background: #e9eaee; color: #33333f; }
    h3 {
      font-size: 11pt;
      margin: 14px 0 6px;
      color: #1a1a1f;
      page-break-after: avoid;
    }
    p {
      margin: 0 0 9px;
      text-align: justify;
    }
    ul, ol { margin: 0 0 10px; padding-left: 18px; }
    li { margin: 0 0 4px; }
    .steps {
      margin: 8px 0 12px;
      width: 100%;
      border-collapse: collapse;
    }
    .steps td {
      vertical-align: top;
      padding: 0 0 10px 0;
      page-break-inside: avoid;
    }
    .steps .num {
      width: 22px;
      height: 18px;
      background: #d6d6e2;
      color: #0A0A0F;
      border-radius: 50%;
      font-size: 8pt;
      font-weight: bold;
      text-align: center;
      line-height: 18px;
    }
    .steps .stitle {
      font-weight: bold;
      margin: 0 0 2px 6px;
    }
    .steps .stext {
      color: #444455;
      font-size: 10pt;
      margin: 0 0 0 6px;
    }
    .callout {
      border: 1px solid #dddde8;
      border-left: 3px solid #666673;
      background: #faf9f7;
      padding: 9px 11px;
      margin: 10px 0 12px;
      page-break-inside: avoid;
      border-radius: 2px;
    }
    .callout.warn { border-left-color: #666673; background: #f7f7f7; }
    .callout.tip { border-left-color: #666673; background: #f7f7f7; }
    .callout .ctitle {
      font-size: 8.5pt;
      font-weight: bold;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      margin: 0 0 4px;
      color: #555;
    }
    .callout p { margin: 0; font-size: 10pt; }
    .faq-item {
      margin: 0 0 10px;
      page-break-inside: avoid;
    }
    .faq-q {
      font-weight: bold;
      margin: 0 0 3px;
    }
    .faq-a {
      margin: 0;
      color: #444455;
      font-size: 10pt;
    }

    /* Visuels pédagogiques (mockups HTML -> PDF). */
    .visual {
      border: 1px solid #dddde8;
      background: #f7f7fa;
      padding: 12px;
      margin: 12px 0 16px;
      page-break-inside: avoid;
      border-radius: 2px;
    }
    .visual-title {
      font-size: 10.5pt;
      margin: 0 0 6px;
      color: #0A0A0F;
      font-weight: bold;
    }
    .visual-caption {
      margin: 0 0 10px;
      color: #55556a;
      font-size: 10pt;
    }
    .visual-grid {
      width: 100%;
      border-collapse: collapse;
    }
    .visual-grid td {
      vertical-align: top;
      padding: 0 12px 0 0;
    }
    .visual-grid td:last-child { padding-right: 0; }
    .visual-label {
      font-size: 8.5pt;
      color: #7A7A9A;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      margin: 0 0 8px;
      font-weight: bold;
    }
    .visual-zone {
      margin: 0 0 6px;
      padding: 0;
      display: flex;
      align-items: flex-start;
      gap: 8px;
    }
    .visual-num {
      width: 18px;
      height: 18px;
      border-radius: 50%;
      background: #e3e3ee;
      color: #0A0A0F;
      font-weight: bold;
      font-size: 8.5pt;
      text-align: center;
      line-height: 18px;
      flex: 0 0 18px;
    }
    .visual-zone p {
      margin: 0 0 3px;
      font-size: 10pt;
      color: #1a1a1f;
    }
    .visual-zone small {
      display: block;
      font-size: 9.5pt;
      color: #55556a;
    }
    .footer-note {
      margin-top: 28px;
      padding-top: 10px;
      border-top: 1px solid #dddde8;
      font-size: 8.5pt;
      color: #7A7A9A;
      text-align: center;
    }
  </style>
</head>
<body>

{{-- Couverture --}}
<div class="cover">
  <p class="kicker">Documentation utilisateur</p>
  <p class="brand">Bénin<span>Freelance</span></p>
  <h1>Guide d'utilisation complet</h1>
  <p class="sub">Parcours pas à pas pour les clients et les prestataires : inscription, recherche, messagerie, commandes, paiements et abonnements.</p>
  <p class="meta-row">Version {{ $meta['version'] }} · {{ $meta['updated'] }} · EPAC / UAC — Groupe 5</p>
</div>

{{-- Sommaire --}}
@php
  $topCommonChapters = [];
  $bottomCommonChapters = [];
  $clientChapters = [];
  $prestaChapters = [];
  foreach ($chapters as $chapter) {
    $role = $chapter['role'] ?? null;
    if ($role === 'client') $clientChapters[] = $chapter;
    elseif ($role === 'prestataire') $prestaChapters[] = $chapter;
    else {
      if (($chapter['id'] ?? '') === 'aide') $bottomCommonChapters[] = $chapter;
      else $topCommonChapters[] = $chapter;
    }
  }
@endphp

<div class="toc">
  <h2>Sommaire</h2>
  <p style="margin: 0 0 6px; color:#7A7A9A; font-size:9.5pt;">Tronc commun</p>
  <ol>
    @foreach ($topCommonChapters as $chapter)
      <li>
        {{ $chapter['title'] }}
      </li>
    @endforeach
  </ol>
  <p style="margin: 10px 0 6px; color:#7A7A9A; font-size:9.5pt;">Client</p>
  <ol>
    @foreach ($clientChapters as $chapter)
      <li>
        {{ $chapter['title'] }}
        <span class="role"> — Client</span>
      </li>
    @endforeach
  </ol>
  <p style="margin: 10px 0 6px; color:#7A7A9A; font-size:9.5pt;">Prestataire</p>
  <ol>
    @foreach ($prestaChapters as $chapter)
      <li>
        {{ $chapter['title'] }}
        <span class="role"> — Prestataire</span>
      </li>
    @endforeach
  </ol>
  <p style="margin: 10px 0 6px; color:#7A7A9A; font-size:9.5pt;">Aller plus loin</p>
  <ol>
    @foreach ($bottomCommonChapters as $chapter)
      <li>
        {{ $chapter['title'] }}
      </li>
    @endforeach
  </ol>
</div>

{{-- Chapitres --}}
{{-- Tronc commun (haut) --}}
@foreach ($topCommonChapters as $chapter)
  <div class="chapter-wrap">
    <h2 class="chapter">{{ $chapter['title'] }}</h2>

    @foreach ($chapter['blocks'] as $block)
      @php $type = $block['type'] ?? 'p'; @endphp

      @if ($type === 'p')
        <p>{{ $block['text'] }}</p>
      @elseif ($type === 'h')
        <h3>{{ $block['text'] }}</h3>
      @elseif ($type === 'callout')
        <div class="callout {{ $block['tone'] ?? 'info' }}">
          <div class="ctitle">{{ $block['title'] }}</div>
          <p>{{ $block['text'] }}</p>
        </div>
      @elseif ($type === 'list')
        @if (!empty($block['title']))
          <h3>{{ $block['title'] }}</h3>
        @endif
        <ul>
          @foreach ($block['items'] as $item)
            <li>{{ $item }}</li>
          @endforeach
        </ul>
      @elseif ($type === 'steps')
        <table class="steps">
          @foreach ($block['items'] as $i => $step)
            <tr>
              <td style="width:24px;"><div class="num">{{ $i + 1 }}</div></td>
              <td>
                <div class="stitle">{{ $step['title'] }}</div>
                <div class="stext">{{ $step['text'] }}</div>
              </td>
            </tr>
          @endforeach
        </table>
      @elseif ($type === 'visual')
        @php
          $zones = $block['zones'] ?? [];
        @endphp
        <div class="visual">
          <div class="visual-title">{{ $block['title'] ?? 'Visuel' }}</div>
          @if (!empty($block['caption']))
            <div class="visual-caption">{{ $block['caption'] }}</div>
          @endif
          <table class="visual-grid">
            <tr>
              <td width="50%">
                <div class="visual-label">Zone à repérer</div>
                @if (!empty($zones))
                  @foreach ($zones as $i => $zone)
                    <div class="visual-zone">
                      <div class="visual-num">{{ $i + 1 }}</div>
                      <div>
                        <p>{{ $zone['label'] ?? '' }}</p>
                        @if (!empty($zone['hint']))
                          <small>{{ $zone['hint'] }}</small>
                        @endif
                      </div>
                    </div>
                  @endforeach
                @else
                  <p>Aucun visuel défini.</p>
                @endif
              </td>
              <td width="50%">
                <div class="visual-label">Ce que ça déclenche</div>
                @foreach ($zones as $i => $zone)
                  <div class="visual-zone" style="margin-bottom:6px;">
                    <div class="visual-num">{{ $i + 1 }}</div>
                    <div><p>{{ $zone['label'] ?? '' }}</p></div>
                  </div>
                @endforeach
              </td>
            </tr>
          </table>
        </div>
      @elseif ($type === 'faq')
        @foreach ($block['items'] as $faq)
          <div class="faq-item">
            <p class="faq-q">{{ $faq['q'] }}</p>
            <p class="faq-a">{{ $faq['a'] }}</p>
          </div>
        @endforeach
      @endif
    @endforeach
  </div>
@endforeach

<h2 class="chapter">Section Client</h2>

@foreach ($clientChapters as $chapter)
  <div class="chapter-wrap">
    <h2 class="chapter">
      {{ $chapter['title'] }}
      <span class="badge badge-client">Client</span>
    </h2>
    @foreach ($chapter['blocks'] as $block)
      @php $type = $block['type'] ?? 'p'; @endphp

      @if ($type === 'p')
        <p>{{ $block['text'] }}</p>
      @elseif ($type === 'h')
        <h3>{{ $block['text'] }}</h3>
      @elseif ($type === 'callout')
        <div class="callout {{ $block['tone'] ?? 'info' }}">
          <div class="ctitle">{{ $block['title'] }}</div>
          <p>{{ $block['text'] }}</p>
        </div>
      @elseif ($type === 'list')
        @if (!empty($block['title']))
          <h3>{{ $block['title'] }}</h3>
        @endif
        <ul>
          @foreach ($block['items'] as $item)
            <li>{{ $item }}</li>
          @endforeach
        </ul>
      @elseif ($type === 'steps')
        <table class="steps">
          @foreach ($block['items'] as $i => $step)
            <tr>
              <td style="width:24px;"><div class="num">{{ $i + 1 }}</div></td>
              <td>
                <div class="stitle">{{ $step['title'] }}</div>
                <div class="stext">{{ $step['text'] }}</div>
              </td>
            </tr>
          @endforeach
        </table>
      @elseif ($type === 'visual')
        @php $zones = $block['zones'] ?? []; @endphp
        <div class="visual">
          <div class="visual-title">{{ $block['title'] ?? 'Visuel' }}</div>
          @if (!empty($block['caption']))
            <div class="visual-caption">{{ $block['caption'] }}</div>
          @endif
          <table class="visual-grid">
            <tr>
              <td width="50%">
                <div class="visual-label">Zone à repérer</div>
                @if (!empty($zones))
                  @foreach ($zones as $i => $zone)
                    <div class="visual-zone">
                      <div class="visual-num">{{ $i + 1 }}</div>
                      <div>
                        <p>{{ $zone['label'] ?? '' }}</p>
                        @if (!empty($zone['hint']))<small>{{ $zone['hint'] }}</small>@endif
                      </div>
                    </div>
                  @endforeach
                @else <p>Aucun visuel défini.</p> @endif
              </td>
              <td width="50%">
                <div class="visual-label">Ce que ça déclenche</div>
                @foreach ($zones as $i => $zone)
                  <div class="visual-zone" style="margin-bottom:6px;">
                    <div class="visual-num">{{ $i + 1 }}</div>
                    <div><p>{{ $zone['label'] ?? '' }}</p></div>
                  </div>
                @endforeach
              </td>
            </tr>
          </table>
        </div>
      @elseif ($type === 'faq')
        @foreach ($block['items'] as $faq)
          <div class="faq-item">
            <p class="faq-q">{{ $faq['q'] }}</p>
            <p class="faq-a">{{ $faq['a'] }}</p>
          </div>
        @endforeach
      @endif
    @endforeach
  </div>
@endforeach

<h2 class="chapter">Section Prestataire</h2>

@foreach ($prestaChapters as $chapter)
  <div class="chapter-wrap">
    <h2 class="chapter">
      {{ $chapter['title'] }}
      <span class="badge badge-presta">Prestataire</span>
    </h2>
    @foreach ($chapter['blocks'] as $block)
      @php $type = $block['type'] ?? 'p'; @endphp

      @if ($type === 'p')
        <p>{{ $block['text'] }}</p>
      @elseif ($type === 'h')
        <h3>{{ $block['text'] }}</h3>
      @elseif ($type === 'callout')
        <div class="callout {{ $block['tone'] ?? 'info' }}">
          <div class="ctitle">{{ $block['title'] }}</div>
          <p>{{ $block['text'] }}</p>
        </div>
      @elseif ($type === 'list')
        @if (!empty($block['title']))
          <h3>{{ $block['title'] }}</h3>
        @endif
        <ul>
          @foreach ($block['items'] as $item)
            <li>{{ $item }}</li>
          @endforeach
        </ul>
      @elseif ($type === 'steps')
        <table class="steps">
          @foreach ($block['items'] as $i => $step)
            <tr>
              <td style="width:24px;"><div class="num">{{ $i + 1 }}</div></td>
              <td>
                <div class="stitle">{{ $step['title'] }}</div>
                <div class="stext">{{ $step['text'] }}</div>
              </td>
            </tr>
          @endforeach
        </table>
      @elseif ($type === 'visual')
        @php $zones = $block['zones'] ?? []; @endphp
        <div class="visual">
          <div class="visual-title">{{ $block['title'] ?? 'Visuel' }}</div>
          @if (!empty($block['caption']))
            <div class="visual-caption">{{ $block['caption'] }}</div>
          @endif
          <table class="visual-grid">
            <tr>
              <td width="50%">
                <div class="visual-label">Zone à repérer</div>
                @if (!empty($zones))
                  @foreach ($zones as $i => $zone)
                    <div class="visual-zone">
                      <div class="visual-num">{{ $i + 1 }}</div>
                      <div>
                        <p>{{ $zone['label'] ?? '' }}</p>
                        @if (!empty($zone['hint']))<small>{{ $zone['hint'] }}</small>@endif
                      </div>
                    </div>
                  @endforeach
                @else <p>Aucun visuel défini.</p> @endif
              </td>
              <td width="50%">
                <div class="visual-label">Ce que ça déclenche</div>
                @foreach ($zones as $i => $zone)
                  <div class="visual-zone" style="margin-bottom:6px;">
                    <div class="visual-num">{{ $i + 1 }}</div>
                    <div><p>{{ $zone['label'] ?? '' }}</p></div>
                  </div>
                @endforeach
              </td>
            </tr>
          </table>
        </div>
      @elseif ($type === 'faq')
        @foreach ($block['items'] as $faq)
          <div class="faq-item">
            <p class="faq-q">{{ $faq['q'] }}</p>
            <p class="faq-a">{{ $faq['a'] }}</p>
          </div>
        @endforeach
      @endif
    @endforeach
  </div>
@endforeach

{{-- Aller plus loin --}}
@foreach ($bottomCommonChapters as $chapter)
  <div class="chapter-wrap">
    <h2 class="chapter">{{ $chapter['title'] }}</h2>
    @foreach ($chapter['blocks'] as $block)
      @php $type = $block['type'] ?? 'p'; @endphp

      @if ($type === 'p')
        <p>{{ $block['text'] }}</p>
      @elseif ($type === 'h')
        <h3>{{ $block['text'] }}</h3>
      @elseif ($type === 'callout')
        <div class="callout {{ $block['tone'] ?? 'info' }}">
          <div class="ctitle">{{ $block['title'] }}</div>
          <p>{{ $block['text'] }}</p>
        </div>
      @elseif ($type === 'list')
        @if (!empty($block['title']))
          <h3>{{ $block['title'] }}</h3>
        @endif
        <ul>
          @foreach ($block['items'] as $item)
            <li>{{ $item }}</li>
          @endforeach
        </ul>
      @elseif ($type === 'steps')
        <table class="steps">
          @foreach ($block['items'] as $i => $step)
            <tr>
              <td style="width:24px;"><div class="num">{{ $i + 1 }}</div></td>
              <td>
                <div class="stitle">{{ $step['title'] }}</div>
                <div class="stext">{{ $step['text'] }}</div>
              </td>
            </tr>
          @endforeach
        </table>
      @elseif ($type === 'visual')
        @php $zones = $block['zones'] ?? []; @endphp
        <div class="visual">
          <div class="visual-title">{{ $block['title'] ?? 'Visuel' }}</div>
          @if (!empty($block['caption']))
            <div class="visual-caption">{{ $block['caption'] }}</div>
          @endif
          <table class="visual-grid">
            <tr>
              <td width="50%">
                <div class="visual-label">Zone à repérer</div>
                @if (!empty($zones))
                  @foreach ($zones as $i => $zone)
                    <div class="visual-zone">
                      <div class="visual-num">{{ $i + 1 }}</div>
                      <div>
                        <p>{{ $zone['label'] ?? '' }}</p>
                        @if (!empty($zone['hint']))<small>{{ $zone['hint'] }}</small>@endif
                      </div>
                    </div>
                  @endforeach
                @else <p>Aucun visuel défini.</p> @endif
              </td>
              <td width="50%">
                <div class="visual-label">Ce que ça déclenche</div>
                @foreach ($zones as $i => $zone)
                  <div class="visual-zone" style="margin-bottom:6px;">
                    <div class="visual-num">{{ $i + 1 }}</div>
                    <div><p>{{ $zone['label'] ?? '' }}</p></div>
                  </div>
                @endforeach
              </td>
            </tr>
          </table>
        </div>
      @elseif ($type === 'faq')
        @foreach ($block['items'] as $faq)
          <div class="faq-item">
            <p class="faq-q">{{ $faq['q'] }}</p>
            <p class="faq-a">{{ $faq['a'] }}</p>
          </div>
        @endforeach
      @endif
    @endforeach
  </div>
@endforeach

<div class="footer-note">
  BéninFreelance · Guide d'utilisation v{{ $meta['version'] }} · {{ $meta['updated'] }} · Document généré pour les utilisateurs de la plateforme
</div>

</body>
</html>
