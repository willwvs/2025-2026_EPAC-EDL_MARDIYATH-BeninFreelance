# Configuration FedaPay — BéninFreelance

Ce document détaille **exactement** ce qu'il faut configurer côté FedaPay (sandbox puis live) et dans le `.env` pour activer le flux Commandes + Paiement.

---

## 1. Pré-requis : exposer Laravel publiquement (ngrok)

FedaPay envoie ses webhooks (`transaction.approved`, etc.) vers ton serveur. En local on utilise **ngrok**.

Terminal 1 :

```powershell
cd C:\Projets\Projet-EDL\backend
php artisan serve
```

Terminal 2 :

```powershell
ngrok http 8000
```

Note l'URL HTTPS affichée (ex. `https://abcd-1234.ngrok-free.dev`).

Dans `backend/.env` :

```env
APP_URL=https://abcd-1234.ngrok-free.dev
```

Puis :

```powershell
php artisan config:clear
```

> Le `callback_url` FedaPay est libre (pas de restriction de domaine principal comme MoneyFusion), donc ngrok fonctionne sans souci.

---

## 2. Compte FedaPay sandbox

1. Création : https://sandbox.fedapay.com/register
2. Connexion → menu **Développeur** → **API**
3. Copier la **Clé publique** (test) et la **Clé secrète** (test)

### Variables `.env`

```env
FEDAPAY_ENVIRONMENT=sandbox
FEDAPAY_PUBLIC_KEY=pk_sandbox_xxxxxxxxxxxxxxxx
FEDAPAY_SECRET_KEY=sk_sandbox_xxxxxxxxxxxxxxxx
FEDAPAY_WEBHOOK_SECRET=
FEDAPAY_WEBHOOK_SECRET_PAYOUT=
FEDAPAY_CURRENCY=XOF
FEDAPAY_COUNTRY=bj
```

Recharger :

```powershell
php artisan config:clear
```

---

## 3. Webhooks (à configurer dans le dashboard FedaPay)

Dashboard sandbox → **Développeur** → **Webhooks** → **Ajouter un webhook**

**Webhook 1 — Pay-in (obligatoire si tu veux confirmer le paiement sans rafraîchir la page)**

| Champ | Valeur |
|--------|--------|
| URL | `https://<ton-ngrok>.ngrok-free.dev/api/webhooks/fedapay/payin` |
| Événements | `transaction.approved`, `transaction.canceled`, `transaction.declined` |
| Secret (hash) | copier dans `FEDAPAY_WEBHOOK_SECRET` |

> Tu peux t'en passer en sandbox : la route `/payments/return` synchronise le paiement via l'API au retour client.

**Webhook 2 — Payouts (recommandé : versements prestataire et remboursements)**

| Champ | Valeur |
|--------|--------|
| URL | `https://<ton-ngrok>.ngrok-free.dev/api/webhooks/fedapay/payout` |
| Événements | `payout.sent`, `payout.failed` |
| Secret (hash) | copier dans `FEDAPAY_WEBHOOK_SECRET_PAYOUT` du `.env` (peut être différent du 1er) |

> Sans ce webhook : tu marqueras manuellement les payouts complétés via l'onglet **Versements** du panneau admin (`/admin`).

---

## 4. Numéros de test sandbox

FedaPay sandbox accepte **uniquement** ces numéros Mobile Money pour simuler un succès :

- `64000001`
- `66000001`

Tout autre numéro simule un échec. Aucun FCFA n'est débité.

---

## 5. Lancer le cron (14 j + 48 h)

```powershell
cd C:\Projets\Projet-EDL\backend
php artisan schedule:work
```

---

## 6. Tester le flux complet

1. Client → conversation messagerie → **Commander** → modale → envoyer
2. Prestataire → onglet Commandes → **Accepter** (le profil doit contenir un numéro MM + opérateur)
3. Client → onglet Commandes → **Payer maintenant** → redirection FedaPay
4. Sur la page FedaPay : choisir Mobile Money → numéro `64000001` → confirmer
5. Retour `/payments/return` → la commande passe à **Payée**
6. Prestataire → **Livrer** (fichier + message)
7. Client → **Livraison conforme** → commande **Terminée**, payout prestataire enregistré
8. Admin (`admin.html` → onglet Commandes) peut **rembourser** : appel API refund FedaPay

---

## 6bis. Abonnement mensuel prestataire (Starter / Pro)

L’abonnement remplace la commission 5 % sur les commandes.

| Plan | Prix | Services | Recherche |
|------|------|----------|-----------|
| Gratuit | 0 | 2 | Normal |
| Starter | 3 500 FCFA / 30 j | 10 | Au-dessus du gratuit |
| Pro | 6 000 FCFA / 30 j | Illimité | Priorité |

1. Dashboard prestataire → **Abonnement** → Souscrire Starter ou Pro  
2. Checkout FedaPay sandbox : numéro **`64000001`**  
3. Retour `/payments/return?abonnement_id=…` → plan activé  
4. Renouveler avant `plan_expires_at` (sinon retour automatique au gratuit)  
5. Cron : `php artisan subscriptions:expire` (horaire via `schedule:work`)

Le même webhook payin traite commandes **et** abonnements (metadata `type=abonnement`).

---

## 7. Passer en live / Railway

**Déploiement actuel Railway** : `https://web-production-74ea4.up.railway.app`  
Détails webhooks : voir [`Doc/RAILWAY_DEPLOY.md`](RAILWAY_DEPLOY.md).

1. Activer le **compte live** sur https://live.fedapay.com (formulaire + pièces IFU/RCCM)
2. Dans le dashboard (sandbox puis live), créer les webhooks :
   - Payin : `https://beninfreelance.up.railway.app/api/webhooks/fedapay/payin`
   - Payout : `https://beninfreelance.up.railway.app/api/webhooks/fedapay/payout`
3. Variables Railway (service `web`) :
   ```env
   FEDAPAY_ENVIRONMENT=sandbox   # ou live
   FEDAPAY_PUBLIC_KEY=pk_xxx
   FEDAPAY_SECRET_KEY=sk_xxx
   FEDAPAY_WEBHOOK_SECRET=…     # secret payin
   FEDAPAY_WEBHOOK_SECRET_PAYOUT=…
   APP_URL=https://web-production-74ea4.up.railway.app
   ```
4. Domaine custom : mettre à jour `APP_URL` + les 2 URLs webhooks FedaPay

Aucune modification de code nécessaire : `config/fedapay.php` bascule automatiquement entre `https://sandbox-api.fedapay.com` et `https://api.fedapay.com` selon `FEDAPAY_ENVIRONMENT`.

---

## 8. Notes importantes

- **Versement au prestataire (100 %)** : plus de commission plateforme sur les commandes (modèle abonnement). À la validation client (ou auto 14 j), payout FedaPay du montant total `net_prestataire_fcfa` (= montant commande). Le payout passe à `completed` quand FedaPay renvoie `payout.sent` ou via « Marquer payé » en admin.
- **Remboursement client** : pas d'API refund FedaPay ; on fait un **payout sortant** depuis ta balance vers le numéro Mobile Money du client. Même flux que le payout prestataire.
- **Modes supportés** : MTN Bénin (`mtn_open`) et Moov Bénin (`moov`). Le mapping `mm_mode` se fait dans `FedapayClient::mapPayoutMode`.
- **Sécurité** : ne jamais committer `.env`. Les clés `sk_*` sont sensibles.
