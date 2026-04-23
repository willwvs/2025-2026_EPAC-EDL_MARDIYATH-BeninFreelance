<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Contacter le prestataire — BéninFreelance</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { syne: ['Syne','sans-serif'] },
          colors: {
            bg: '#0A0A0F', surface: '#13131A', surface2: '#1C1C27',
            brd: '#2A2A3D', accent: '#F5A623', tx: '#F0EDE8',
            muted: '#7A7A9A', ok: '#2ECC71'
          }
        }
      }
    }
  </script>
  <style>
    body { font-family: 'DM Sans', sans-serif; }
    input, textarea { outline: none; }
    input:focus, textarea:focus { border-color: #F5A623 !important; }
    textarea { resize: none; }

    /* Animations personnalisées */
    @keyframes fadeIn {
      from {
        opacity: 0;
      }
      to {
        opacity: 1;
      }
    }

    @keyframes slideInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes slideInDown {
      from {
        opacity: 0;
        transform: translateY(-20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes slideInLeft {
      from {
        opacity: 0;
        transform: translateX(-20px);
      }
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    @keyframes slideInRight {
      from {
        opacity: 0;
        transform: translateX(20px);
      }
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    @keyframes pulse {
      0%, 100% {
        opacity: 1;
      }
      50% {
        opacity: 0.5;
      }
    }

    @keyframes scaleIn {
      from {
        opacity: 0;
        transform: scale(0.95);
      }
      to {
        opacity: 1;
        transform: scale(1);
      }
    }

    /* Classes d'animation */
    .animate-fade-in {
      animation: fadeIn 0.6s ease-out;
    }

    .animate-slide-up {
      animation: slideInUp 0.6s ease-out;
    }

    .animate-slide-down {
      animation: slideInDown 0.6s ease-out;
    }

    .animate-slide-left {
      animation: slideInLeft 0.6s ease-out;
    }

    .animate-slide-right {
      animation: slideInRight 0.6s ease-out;
    }

    .animate-scale-in {
      animation: scaleIn 0.5s ease-out;
    }

    .animate-pulse-soft {
      animation: pulse 3s ease-in-out infinite;
    }

    /* Animation au focus des inputs */
    input, textarea {
      transition: all 0.3s ease;
    }

    input:focus, textarea:focus {
      box-shadow: 0 0 0 3px rgba(245, 166, 35, 0.1);
    }

    /* Animation bouton hover */
    button {
      transition: all 0.3s ease;
    }

    button:hover {
      transform: translateY(-2px);
    }

    button[type="submit"]:hover {
      box-shadow: 0 10px 30px rgba(245, 166, 35, 0.3);
    }

    /* Délai pour animations en cascade */
    .delay-100 { animation-delay: 0.1s; }
    .delay-200 { animation-delay: 0.2s; }
    .delay-300 { animation-delay: 0.3s; }
    .delay-400 { animation-delay: 0.4s; }
    .delay-500 { animation-delay: 0.5s; }
  </style>
</head>
<body class="bg-bg text-tx min-h-screen flex items-start md:items-center justify-center px-4 py-10">

  <div class="w-full max-w-xl bg-surface border border-brd rounded-2xl px-6 py-8 sm:px-8 sm:py-10 animate-fade-in">

    <!-- Intro -->
    <p class="text-muted text-sm mb-4 animate-slide-up delay-100">Votre message sera envoyé dans le contexte du service sélectionné.</p>

    <!-- Titre -->
    <h1 class="font-syne font-extrabold text-2xl sm:text-3xl leading-tight mb-6 animate-slide-down delay-100">
      Contacter le prestataire
    </h1>

    <div class="bg-surface2 border border-brd rounded-xl px-4 py-3 mb-6 animate-slide-up delay-200">
      <p class="text-xs text-muted mb-1">Service concerné</p>
      <p class="font-syne font-bold text-accent text-sm">{{ $service->titre }} — {{ $service->localite }}</p>
    </div>

    <!-- FORMULAIRE -->
    <form
      action="{{ route('message.send', ['prestataire_id' => $prestataire->id]) }}"
      method="POST"
      id="contact-form"
      novalidate
    >
      @csrf
      
      <!-- IDs cachés -->
      <input type="hidden" name="prestataire_id" value="{{ $prestataire->id }}"/>
      <input type="hidden" name="service_id" value="{{ $service->id }}"/>

      <!-- Erreurs Laravel -->
      @if ($errors->any())
        <div class="bg-red-900/30 border border-red-500/30 text-red-400 text-xs px-4 py-3 rounded-xl mb-5 animate-slide-down delay-100">
          @foreach ($errors->all() as $error)
            <p>{{ $error }}</p>
          @endforeach
        </div>
      @endif

      <!-- Message de succès -->
      @if (session('success'))
        <div class="bg-ok/10 border border-ok/30 text-ok text-xs px-4 py-3 rounded-xl mb-5 animate-scale-in">
          {{ session('success') }}
        </div>
      @endif

      <!-- Bloc erreurs JS (affiché si validation échoue côté client) -->
      <div id="js-errors" class="hidden bg-red-900/30 border border-red-500/30 text-red-400 text-xs px-4 py-3 rounded-xl mb-5 animate-slide-down"></div>

      <!-- Nom complet -->
      <div class="mb-4 animate-slide-up delay-300">
        <label for="nom_complet" class="block text-sm text-tx mb-1.5">Nom complet</label>
        <input
          type="text"
          id="nom_complet"
          name="nom_complet"
          placeholder="Votre nom"
          autocomplete="name"
          class="w-full bg-surface2 border border-brd text-tx text-sm px-4 py-3 rounded-xl placeholder-muted transition-colors @error('nom_complet') border-red-500 @enderror"
          value="{{ old('nom_complet') }}"
        />
        @error('nom_complet')
          <p class="text-red-400 text-xs mt-1 animate-fade-in">{{ $message }}</p>
        @enderror
      </div>

      <!-- Email -->
      <div class="mb-4 animate-slide-up delay-400">
        <label for="email" class="block text-sm text-tx mb-1.5">Adresse e-mail</label>
        <input
          type="email"
          id="email"
          name="email"
          placeholder="vous@exemple.com"
          autocomplete="email"
          class="w-full bg-surface2 border border-brd text-tx text-sm px-4 py-3 rounded-xl placeholder-muted transition-colors @error('email') border-red-500 @enderror"
          value="{{ old('email') }}"
        />
        @error('email')
          <p class="text-red-400 text-xs mt-1 animate-fade-in">{{ $message }}</p>
        @enderror
      </div>

      <!-- Téléphone -->
      <div class="mb-4 animate-slide-up delay-500">
        <label for="telephone" class="block text-sm text-tx mb-1.5">Téléphone <span class="text-muted">(optionnel)</span></label>
        <input
          type="tel"
          id="telephone"
          name="telephone"
          placeholder="+229 97 00 00 00"
          autocomplete="tel"
          class="w-full bg-surface2 border border-brd text-tx text-sm px-4 py-3 rounded-xl placeholder-muted transition-colors"
          value="{{ old('telephone') }}"
        />
      </div>

      <!-- Objet -->
      <div class="mb-4 animate-slide-left delay-300">
        <label for="objet" class="block text-sm text-tx mb-1.5">Objet du message</label>
        <input
          type="text"
          id="objet"
          name="objet"
          placeholder="Ex : Demande de devis — Électricité & tableaux BT"
          class="w-full bg-surface2 border border-brd text-tx text-sm px-4 py-3 rounded-xl placeholder-muted transition-colors @error('objet') border-red-500 @enderror"
          value="{{ old('objet', 'Demande de devis — ' . $service->titre) }}"
        />
        @error('objet')
          <p class="text-red-400 text-xs mt-1 animate-fade-in">{{ $message }}</p>
        @enderror
      </div>

      <!-- Message -->
      <div class="mb-6 animate-slide-left delay-400">
        <label for="contenu" class="block text-sm text-tx mb-1.5">Message</label>
        <textarea
          id="contenu"
          name="contenu"
          rows="5"
          placeholder="Décrivez votre besoin, le délai souhaité et toute contrainte d'accès sur site..."
          class="w-full bg-surface2 border border-brd text-tx text-sm px-4 py-3 rounded-xl placeholder-muted transition-colors @error('contenu') border-red-500 @enderror"
        >{{ old('contenu') }}</textarea>
        @error('contenu')
          <p class="text-red-400 text-xs mt-1 animate-fade-in">{{ $message }}</p>
        @enderror
      </div>

      <!-- Boutons -->
      <div class="flex flex-col-reverse sm:flex-row sm:items-center gap-3 mb-4 animate-slide-up delay-500">
        <button
          type="button"
          onclick="history.back()"
          class="w-full sm:w-auto border border-brd text-tx text-sm font-medium px-6 py-3 rounded-xl hover:border-muted hover:text-muted transition-all text-center"
        >
          Annuler
        </button>
        <button
          type="submit"
          class="w-full sm:flex-1 bg-accent text-bg font-syne font-bold text-sm py-3 px-6 rounded-xl hover:opacity-88 hover:-translate-y-0.5 transition-all text-center"
        >
          Envoyer la demande
        </button>
      </div>

      <!-- Mention légale -->
      <p class="text-xs text-muted leading-relaxed animate-fade-in delay-500">
        En envoyant ce formulaire, vous acceptez que vos coordonnées soient transmises au prestataire.
      </p>

    </form>
  </div>

  <script>
    // Animation au focus des inputs
    const inputs = document.querySelectorAll('input, textarea');
    inputs.forEach(input => {
      input.addEventListener('focus', function() {
        this.parentElement.classList.add('animate-pulse-soft');
      });
      input.addEventListener('blur', function() {
        this.parentElement.classList.remove('animate-pulse-soft');
      });
    });

    // Validation légère côté client avant soumission
    document.getElementById('contact-form').addEventListener('submit', function(e) {
      const errors = [];
      const nom     = document.getElementById('nom_complet').value.trim();
      const email   = document.getElementById('email').value.trim();
      const objet   = document.getElementById('objet').value.trim();
      const contenu = document.getElementById('contenu').value.trim();

      if (!nom)    errors.push('Le nom complet est requis.');
      if (!email || !email.includes('@')) errors.push('Adresse e-mail invalide.');
      if (!objet)  errors.push("L'objet du message est requis.");
      if (!contenu || contenu.length < 10) errors.push('Le message doit faire au moins 10 caractères.');

      const errBox = document.getElementById('js-errors');
      if (errors.length > 0) {
        e.preventDefault();
        errBox.innerHTML = errors.map(er => `<p>${er}</p>`).join('');
        errBox.classList.remove('hidden');
        errBox.classList.add('animate-slide-down');
        errBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
      } else {
        errBox.classList.add('hidden');
      }
    });

    // Animation de chargement du formulaire (optionnel)
    window.addEventListener('load', function() {
      const container = document.querySelector('div[class*="animate-fade-in"]');
      if (container) {
        container.style.animationPlayState = 'running';
      }
    });
  </script>

</body>
</html>
