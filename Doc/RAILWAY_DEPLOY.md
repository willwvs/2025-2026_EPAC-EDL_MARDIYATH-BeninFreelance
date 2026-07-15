# Railway — BéninFreelance

## URL publique (gratuite)

**https://beninfreelance.up.railway.app**

Projet Railway : `beninfreelance` (services `web` + `MySQL`).  
Volume persistant : `/app/backend/public/uploads` (photos services / profils).

`APP_URL` = `https://beninfreelance.up.railway.app` (callbacks FedaPay générés dessus).

> Domaine Railway = `*.up.railway.app` (pas besoin d’acheter un `.com`).

## Webhooks FedaPay

| Type | URL | Events typiques |
|------|-----|-----------------|
| **Payin** | `https://beninfreelance.up.railway.app/api/webhooks/fedapay/payin` | `transaction.approved`, `transaction.canceled`, `transaction.declined` |
| **Payout** | `https://beninfreelance.up.railway.app/api/webhooks/fedapay/payout` | `payout.sent`, `payout.failed` |

Variables sur le service `web` :
- `FEDAPAY_WEBHOOK_SECRET`
- `FEDAPAY_WEBHOOK_SECRET_PAYOUT`

Callbacks navigateur (auto via `APP_URL`) :
- Commandes : `…/payments/return?commande_id=…`
- Abonnements : `…/payments/return?abonnement_id=…`

## Checklist FedaPay

1. Webhooks sandbox pointent vers les URLs ci-dessus  
2. Secrets collés dans Railway  
3. Clés `FEDAPAY_*_KEY` OK  
4. Sandbox test : `64000001`  
5. Live : `FEDAPAY_ENVIRONMENT=live` + clés live (mêmes chemins d’URL)

## Médias (photos / CV)

- Photos publiques : volume `web-volume` monté sur `/app/backend/public/uploads`
- CV / pièces d’identité : archive `uploads/_seed/private.tar` extraite au boot vers `storage/app/private`
- Sync local → volume :

```bash
npx railway ssh keys add -k ~/.ssh/railway_ed25519
npx railway volume files -v web-volume upload backend/public/uploads/prestataires/6.jpeg /prestataires/6.jpeg --overwrite
```

## Déploiement

```bash
npx railway link -p beninfreelance -s web
npx railway up --detach -m "message"
```
