# Prompt : Répliquer le système de paiement (Stripe + PayPal) sur un autre site

Copiez et collez le texte ci-dessous pour demander la mise en place du même système de paiement sur votre autre site.

---

## PROMPT À ENVOYER

```
Je veux implémenter exactement le même système de paiement que sur mon site Orchidee, avec Stripe et PayPal, en réutilisant les MÊMES clés API.

Voici ce dont j'ai besoin :

### 1. CONFIGURATION ADMIN
- Page admin pour configurer les moyens de paiement (Stripe, PayPal, Zelle, Cash App, Bank Deposit)
- **Stripe** : Publishable Key (pk_test_... ou pk_live_...), Secret Key (sk_test_... ou sk_live_...), Webhook Secret (whsec_... optionnel)
- **PayPal** : Client ID, Client Secret, Mode (sandbox ou live)
- Les clés sont saisies par l'admin et stockées en BDD (table `payment_config`)
- Mêmes clés réutilisables sur plusieurs sites

### 2. STRIPE — Redirection serveur
- Créer une Checkout Session via cURL
- Rediriger vers Stripe, puis page de retour pour confirmer le paiement

### 3. PAYPAL — Redirection serveur
- Token OAuth2 puis création de commande via API REST
- Rediriger vers PayPal, puis page de retour pour capturer le paiement

### 4. MÉTHODES MANUELLES
- Zelle, Cash App, Bank Deposit : champ Référence / Transaction ID obligatoire, statut "pending"

### 5. FLUX
- Choix du moyen de paiement → POST → création pending en BDD → redirection Stripe/PayPal ou page confirmation

Réplique exactement cette architecture sur mon nouveau site [PRÉCISER : type de contenu à vendre].
```

---

## FICHIERS DE RÉFÉRENCE DANS ORCHIDEE

| Fichier | Rôle |
|---------|------|
| `admin/payment-settings.php` | Configuration admin des clés Stripe/PayPal et méthodes manuelles |
| `includes/payment_functions.php` | Récupération des méthodes activées depuis `payment_config` |
| `includes/paypal_helper.php` | Token OAuth2 et création de commande PayPal |
| `purchase.php` | Exemple : achat de cours avec Stripe, PayPal, méthodes manuelles |
| `registration-next-session.php` | Exemple : inscription avec les mêmes paiements |
| `payment-success.php` | Retour Stripe après paiement |
| `purchase-payment-success-paypal.php` | Retour PayPal après achat cours |
| `registration-payment-success-paypal.php` | Retour PayPal après inscription |
| `create-stripe-session.php` | Création session Stripe (si appel AJAX) |

---

## CLÉS STRIPE ET PAYPAL

| Méthode | Clés à configurer |
|---------|-------------------|
| **Stripe** | Publishable Key (`pk_test_xxx` / `pk_live_xxx`), Secret Key (`sk_test_xxx` / `sk_live_xxx`), Webhook Secret (`whsec_xxx` optionnel) |
| **PayPal** | Client ID, Client Secret, Mode (sandbox / live) |

---

## SCHÉMA DE LA TABLE payment_config

```sql
CREATE TABLE payment_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_method VARCHAR(50) NOT NULL UNIQUE,
    is_enabled BOOLEAN DEFAULT FALSE,
    config_data TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO payment_config (payment_method, is_enabled, config_data) VALUES
('stripe', FALSE, '{"publishable_key":"","secret_key":"","webhook_secret":""}'),
('paypal', FALSE, '{"client_id":"","client_secret":"","mode":"sandbox"}'),
('zelle', TRUE, '{}'),
('cashapp', TRUE, '{}'),
('bank_deposit', TRUE, '{}');
```

---

## RÉUTILISATION DES CLÉS

- **Stripe** : `pk_test_xxx`, `sk_test_xxx` (test) ou `pk_live_xxx`, `sk_live_xxx` (production) — valides pour tous les sites du même compte
- **PayPal** : Client ID et Client Secret — valides pour tous les domaines configurés (redirect URLs) dans developer.paypal.com
