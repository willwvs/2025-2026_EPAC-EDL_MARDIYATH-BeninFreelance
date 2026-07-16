<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{{ $meta['title'] }}</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { syne: ['Syne', 'sans-serif'], dm: ['DM Sans', 'sans-serif'] },
          colors: {
            bg: '#0A0A0F', surface: '#13131A', surface2: '#1C1C27', brd: '#2A2A3D',
            accent: '#F5A623', accent2: '#E8855A', tx: '#F0EDE8', muted: '#7A7A9A', ok: '#2ECC71'
          }
        }
      }
    }
  </script>
  <style>
    body { font-family: 'DM Sans', sans-serif; }
    html { scroll-behavior: smooth; }
    .guide-prose h2 { scroll-margin-top: 5.5rem; }
    .toc-link.active { color: #F5A623; border-color: #F5A623; }
  </style>
</head>
<body class="bg-bg text-tx antialiased">

<nav class="sticky top-0 z-50 bg-bg/95 border-b border-brd backdrop-blur-sm">
  <div class="max-w-6xl mx-auto px-4 sm:px-6 flex items-center justify-between h-14 gap-3">
    <div class="flex items-center gap-4 min-w-0">
      <a href="/" class="font-syne font-extrabold text-base shrink-0">Bénin<span class="text-accent">Freelance</span></a>
      <span class="hidden sm:inline text-muted text-xs truncate">Guide d'utilisation</span>
    </div>
    <div class="flex items-center gap-2 shrink-0">
      <a href="/guide/pdf"
         class="inline-flex items-center gap-2 bg-accent text-bg text-sm font-semibold px-3.5 py-2 rounded-lg hover:opacity-90 transition-opacity">
        <i class="fa-solid fa-file-pdf"></i>
        <span class="hidden xs:inline sm:inline">Télécharger le PDF</span>
        <span class="sm:hidden">PDF</span>
      </a>
      <a href="/login" class="hidden sm:inline text-sm text-muted hover:text-tx px-2">Connexion</a>
    </div>
  </div>
</nav>

<header class="border-b border-brd bg-surface">
  <div class="max-w-6xl mx-auto px-4 sm:px-6 py-10 sm:py-14">
    <p class="text-accent text-xs font-semibold tracking-widest uppercase mb-3">Documentation</p>
    <h1 class="font-syne font-extrabold text-3xl sm:text-4xl leading-tight max-w-2xl mb-3">{{ $meta['subtitle'] }}</h1>
    <p class="text-muted text-sm sm:text-base max-w-xl mb-6">
      Parcours client et prestataire, commandes, paiements, abonnements — rédigé pour les nouveaux arrivants.
    </p>
    <div class="flex flex-wrap items-center gap-3 text-xs text-muted">
      <span class="border border-brd rounded-full px-3 py-1">Version {{ $meta['version'] }}</span>
      <span class="border border-brd rounded-full px-3 py-1">Mis à jour le {{ $meta['updated'] }}</span>
      <a href="#client-section" class="border border-brd rounded-full px-3 py-1 hover:border-accent hover:text-accent transition-colors">Je suis client →</a>
      <a href="#presta-section" class="border border-brd rounded-full px-3 py-1 hover:border-accent hover:text-accent transition-colors">Je suis prestataire →</a>
    </div>
  </div>
</header>

<div class="max-w-6xl mx-auto px-4 sm:px-6 py-8 sm:py-12">
  <div class="grid grid-cols-1 lg:grid-cols-[220px_1fr] gap-10 lg:gap-14">

    <aside class="lg:sticky lg:top-20 lg:self-start">
      <p class="text-[11px] uppercase tracking-wider text-muted font-bold mb-3">Sommaire</p>
      <nav id="toc" class="flex lg:flex-col gap-1 overflow-x-auto lg:overflow-visible pb-2 lg:pb-0 -mx-1 px-1">
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

        {{-- Tronc commun (Bienvenue / Démarrer / Aller plus loin) --}}
        @foreach ($topCommonChapters as $chapter)
          <a href="#{{ $chapter['id'] }}"
             data-toc="{{ $chapter['id'] }}"
             class="toc-link shrink-0 text-sm text-muted hover:text-tx border-l-2 border-transparent lg:pl-3 py-1.5 transition-colors whitespace-nowrap lg:whitespace-normal">
            {{ $chapter['title'] }}
          </a>
        @endforeach
        @foreach ($bottomCommonChapters as $chapter)
          <a href="#{{ $chapter['id'] }}"
             data-toc="{{ $chapter['id'] }}"
             class="toc-link shrink-0 text-sm text-muted hover:text-tx border-l-2 border-transparent lg:pl-3 py-1.5 transition-colors whitespace-nowrap lg:whitespace-normal">
            {{ $chapter['title'] }}
          </a>
        @endforeach

        @if (!empty($clientChapters))
          <div class="hidden lg:block text-[10px] uppercase tracking-wider text-muted font-bold mt-3 mb-1 px-3">Client</div>
          @foreach ($clientChapters as $chapter)
            <a href="#{{ $chapter['id'] }}"
               data-toc="{{ $chapter['id'] }}"
               class="toc-link shrink-0 text-sm text-muted hover:text-tx border-l-2 border-transparent lg:pl-3 py-1.5 transition-colors whitespace-nowrap lg:whitespace-normal">
              {{ $chapter['title'] }}
            </a>
          @endforeach
        @endif

        @if (!empty($prestaChapters))
          <div class="hidden lg:block text-[10px] uppercase tracking-wider text-muted font-bold mt-3 mb-1 px-3">Prestataire</div>
          @foreach ($prestaChapters as $chapter)
            <a href="#{{ $chapter['id'] }}"
               data-toc="{{ $chapter['id'] }}"
               class="toc-link shrink-0 text-sm text-muted hover:text-tx border-l-2 border-transparent lg:pl-3 py-1.5 transition-colors whitespace-nowrap lg:whitespace-normal">
              {{ $chapter['title'] }}
            </a>
          @endforeach
        @endif
      </nav>
      <a href="/guide/pdf"
         class="hidden lg:flex mt-6 items-center gap-2 text-sm text-accent hover:underline">
        <i class="fa-solid fa-download"></i> PDF complet
      </a>
    </aside>

    <main class="guide-prose min-w-0 space-y-14">
      {{-- Tronc commun --}}
      @foreach ($topCommonChapters as $chapter)
        <section id="{{ $chapter['id'] }}" class="scroll-mt-24">
          <div class="flex flex-wrap items-center gap-2 mb-4">
            <h2 class="font-syne font-bold text-2xl text-tx">{{ $chapter['title'] }}</h2>
          </div>
          <div class="space-y-5">
            @foreach ($chapter['blocks'] as $block)
              @include('guide.partials.block', ['block' => $block, 'mode' => 'web'])
            @endforeach
          </div>
        </section>
      @endforeach

      {{-- Section Client --}}
      <div id="client-section" class="pt-4">
        <h2 class="font-syne font-extrabold text-3xl text-tx mb-8">Guide Client</h2>
        @foreach ($clientChapters as $chapter)
          <section id="{{ $chapter['id'] }}" class="scroll-mt-24 mb-14">
            <div class="flex flex-wrap items-center gap-2 mb-4">
              <h2 class="font-syne font-bold text-2xl text-tx">{{ $chapter['title'] }}</h2>
              <span class="text-[10px] uppercase tracking-wider font-bold text-ok border border-ok/30 bg-ok/10 rounded-full px-2 py-0.5">Client</span>
            </div>
            <div class="space-y-5">
              @foreach ($chapter['blocks'] as $block)
                @include('guide.partials.block', ['block' => $block, 'mode' => 'web'])
              @endforeach
            </div>
          </section>
        @endforeach
      </div>

      {{-- Section Prestataire --}}
      <div id="presta-section" class="pt-4">
        <h2 class="font-syne font-extrabold text-3xl text-tx mb-8">Guide Prestataire</h2>
        @foreach ($prestaChapters as $chapter)
          <section id="{{ $chapter['id'] }}" class="scroll-mt-24 mb-14">
            <div class="flex flex-wrap items-center gap-2 mb-4">
              <h2 class="font-syne font-bold text-2xl text-tx">{{ $chapter['title'] }}</h2>
              <span class="text-[10px] uppercase tracking-wider font-bold text-accent border border-accent/30 bg-accent/10 rounded-full px-2 py-0.5">Prestataire</span>
            </div>
            <div class="space-y-5">
              @foreach ($chapter['blocks'] as $block)
                @include('guide.partials.block', ['block' => $block, 'mode' => 'web'])
              @endforeach
            </div>
          </section>
        @endforeach
      </div>

      {{-- Aller plus loin --}}
      @foreach ($bottomCommonChapters as $chapter)
        <section id="{{ $chapter['id'] }}" class="scroll-mt-24">
          <div class="flex flex-wrap items-center gap-2 mb-4">
            <h2 class="font-syne font-bold text-2xl text-tx">{{ $chapter['title'] }}</h2>
          </div>
          <div class="space-y-5">
            @foreach ($chapter['blocks'] as $block)
              @include('guide.partials.block', ['block' => $block, 'mode' => 'web'])
            @endforeach
          </div>
        </section>
      @endforeach

      <div class="border border-brd rounded-2xl bg-surface2 p-6 sm:p-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <p class="font-syne font-bold text-lg mb-1">Emportez le guide avec vous</p>
          <p class="text-sm text-muted">PDF A4, typographie claire — idéal à partager ou imprimer.</p>
        </div>
        <a href="/guide/pdf"
           class="inline-flex items-center justify-center gap-2 bg-accent text-bg font-syne font-bold text-sm px-5 py-3 rounded-xl hover:opacity-90 shrink-0">
          <i class="fa-solid fa-file-arrow-down"></i> Télécharger le PDF
        </a>
      </div>
    </main>
  </div>
</div>

<footer class="border-t border-brd mt-8 px-4 sm:px-6 py-6">
  <div class="max-w-6xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-3">
    <a href="/" class="font-syne font-extrabold">Bénin<span class="text-accent">Freelance</span></a>
    <p class="text-xs text-muted">© 2026 — EPAC / UAC — Groupe 5 · Guide v{{ $meta['version'] }}</p>
  </div>
</footer>

<script>
(function () {
  const links = Array.from(document.querySelectorAll('[data-toc]'));
  const sections = links.map(function (a) {
    return document.getElementById(a.getAttribute('data-toc'));
  }).filter(Boolean);

  function setActive() {
    let current = sections[0];
    const y = window.scrollY + 120;
    sections.forEach(function (sec) {
      if (sec.offsetTop <= y) current = sec;
    });
    links.forEach(function (a) {
      a.classList.toggle('active', current && a.getAttribute('data-toc') === current.id);
    });
  }
  window.addEventListener('scroll', setActive, { passive: true });
  setActive();
})();
</script>
</body>
</html>
