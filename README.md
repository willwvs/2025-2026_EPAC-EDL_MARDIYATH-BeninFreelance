# BéninFreelance — Frontend

Interface utilisateur de la marketplace de services freelances locaux au Bénin.

## Pages créées

### `index.html` — Page d'accueil
Page de présentation de la plateforme. Elle contient :
- Une navbar sticky avec menu hamburger sur mobile
- Une section hero avec le titre, la description et les deux boutons d'action (chercher un service / proposer ses services)
- Les statistiques de la plateforme (500+ prestataires, 12 catégories, note 4.7★)
- Un aperçu de cartes prestataires (visible sur desktop)
- Un filtre de catégories interactif (Développement, Design, Électricité, Plomberie...)
- Une section "Comment ça marche" en 3 étapes
- Un bloc CTA pour inviter à l'inscription
- Un footer

---

### `login.html` — Connexion
Page de connexion commune aux clients et prestataires. Elle contient :
- Un panneau gauche avec témoignage et statistiques (visible sur desktop)
- Un switch **Client / Prestataire** pour choisir son rôle
- Les champs email et mot de passe avec bouton afficher/masquer
- La validation des champs avec messages d'erreur
- Un bouton "Continuer avec Google"
- Des liens vers les pages d'inscription

---

### `register-client.html` — Inscription client
Formulaire d'inscription en **3 étapes** avec barre de progression. Elle contient :
- **Étape 1** — Nom, prénom, email, téléphone
- **Étape 2** — Ville (liste des villes du Bénin) et quartier
- **Étape 3** — Mot de passe avec indicateur de force (Faible / Moyen / Bon / Fort) + confirmation + case CGV
- Validation à chaque étape avant de passer à la suivante
- Écran de succès à la fin
- Navigation latérale avec indicateurs d'étapes (visible sur desktop)

---

### `register-prestataire.html` — Inscription prestataire
Formulaire d'inscription en **4 étapes** avec barre de progression. Elle contient :
- **Étape 1** — Nom, prénom, email, téléphone, ville
- **Étape 2** — Sélection de la catégorie principale (grille interactive) + biographie professionnelle
- **Étape 3** — Upload du CV (PDF) et de la pièce d'identité (PDF/JPG/PNG) avec drag & drop, validation de la taille (max 5 Mo)
- **Étape 4** — Mot de passe avec indicateur de force + confirmation + case CGV
- Écran d'attente après soumission avec statuts animés (documents reçus → analyse en cours → activation)
- Badge explicatif sur la vérification

---

## Vérification automatique du dossier prestataire

Une fois le formulaire envoyé, le **backend Laravel** (`backend/`) peut analyser le CV et la pièce d’identité via l’**API Gemini** (premier filtre avant un comité d’experts). En résumé :

- **Implémentation** — `backend/app/Support/gemini_verify.php` : requête multimodale (PDF + image), modèle **`gemini-2.5-flash`**, outil **`google_search`** pour le grounding (recherche web), température basse, réponse attendue en JSON (`decision`, `motif_public`).
- **Configuration** — définir **`GEMINI_API_KEY`** dans `backend/.env` (modèle indiqué dans l’URL d’appel ; reprendre les clés documentées dans `backend/.env.example`).
- **Comportement si l’API échoue** (ex. **503** / surcharge côté Google) : pas de verdict IA exploitable — le dossier est placé en **en attente** avec un message générique invitant à réessayer plus tard (voir les logs Laravel pour le détail).
- **Modèles stables** — `gemini-2.5-flash` est le choix GA courant pour la prod ; **`gemini-2.5-flash-lite`** reste compatible avec la même configuration (même style d’appel et outil recherche) si vous voulez tester une autre variante. Les indisponibilités restent possibles sur tous les modèles : des **nouvelles tentatives** (retry / autre moment) restent le bon réflexe.

Pour l’installation et la stack serveur, se reporter au **`backend/README.md`** (Laravel).

---

## Technologies utilisées

- **Tailwind CSS 3** via CDN — pour tout le style et le responsive
- **Google Fonts** — Syne (titres) + DM Sans (texte)
- **JavaScript vanilla** — navigation entre étapes, validations, upload de fichiers

## Responsive

Toutes les pages s'adaptent sur **mobile, tablette et desktop** :
- Menu hamburger sur mobile
- Sidebar cachée sur mobile, remplacée par une barre de progression en haut
- Grilles en colonne sur mobile, en 2-3 colonnes sur desktop
- Boutons en colonne sur mobile, en ligne sur desktop
