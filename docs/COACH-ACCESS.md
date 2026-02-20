# Accès espace coachs – Orchidee LLC

Ces URLs **ne sont pas dans le menu** du site. Utilisez-les directement ou communiquez-les aux coachs.

---

## 1. Inscription coach

**URL :**  
`https://votre-domaine.com/coach-register.php`  
(ex. en local : `http://localhost/orchidee/coach-register.php`)

- Formulaire : Prénom, Nom, Email, Mot de passe, Confirmation mot de passe.
- Crée un compte avec le rôle **coach**.
- Après inscription, le coach se connecte via la page de connexion classique.

---

## 2. Connexion coach

**URL :**  
`https://votre-domaine.com/login.php`  
(en local : `http://localhost/orchidee/login.php`)

**Alias (redirection) :**  
`https://votre-domaine.com/coach/login.php` → redirige vers la page de connexion ci-dessus.

- Même page que les utilisateurs et l’admin.
- Après connexion avec un compte **coach**, redirection automatique vers le tableau de bord coach.

---

## 3. Tableau de bord coach

**URL :**  
`https://votre-domaine.com/coach/dashboard.php`

- Résumé : nombre de cours, nombre de sessions.
- Liens rapides : créer un cours, planifier une session.
- Menu : Dashboard, My Courses, Live Sessions, Students, Logout.

---

## 4. Mes cours (coach)

**URL :**  
`https://votre-domaine.com/coach/courses.php`

- Liste des cours créés par le coach (sans prix, non affichés sur la page publique).
- **Ajouter un cours :** `coach/courses.php?action=add`
- **Modifier un cours :** `coach/courses.php?action=edit&id=ID`

---

## 5. Sessions live (Google Meet)

**URL :**  
`https://votre-domaine.com/coach/live-sessions.php`

- Liste des sessions (date, heure, lien Meet).
- **Créer une session :** `coach/live-sessions.php?action=add`  
  Ou pour un cours précis : `coach/live-sessions.php?action=add&course_id=ID`
- Champs : cours, titre, date, heure, **URL Google Meet**, durée.
- Les inscrits au cours voient la session sur « My Coaching » et rejoignent la visio **sur la plateforme** (Meet intégré).

---

## 6. Étudiants (coach)

**URL :**  
`https://votre-domaine.com/coach/students.php`

- Liste des personnes assignées aux cours du coach (via Session Registrations et NCLEX Registrations, côté admin).

---

## 7. Côté étudiant / inscrit

### Mes cours (coaching)

**URL :**  
`https://votre-domaine.com/my-coaching.php`

- Réservé aux utilisateurs connectés.
- Affiche les cours qui leur ont été assignés (formulaires Registration / NCLEX).
- Pour chaque cours : sessions à venir et bouton **« Join session »**.

### Rejoindre une session (Meet intégré)

**URL :**  
`https://votre-domaine.com/live-session-view.php?session_id=ID`

- Ouverture de la session **sur la plateforme** (Google Meet en iframe, pas de redirection externe).
- Accès uniquement si l’utilisateur est bien assigné à ce cours.

---

## Récapitulatif des URLs (à adapter avec votre domaine)

| Usage | URL |
|-------|-----|
| Inscription coach | `/coach-register.php` |
| Connexion (tous) | `/login.php` |
| Dashboard coach | `/coach/dashboard.php` |
| Cours du coach | `/coach/courses.php` |
| Sessions live | `/coach/live-sessions.php` |
| Étudiants du coach | `/coach/students.php` |
| Mes cours (étudiant) | `/my-coaching.php` |
| Rejoindre une session | `/live-session-view.php?session_id=ID` |

---

## Base de données

Exécuter **une fois** le script de création des tables (inclut le système coach) :

`https://votre-domaine.com/create-all-tables.php`

Cela ajoute notamment :

- Rôle **coach** dans la table `users`
- Colonne **visible_public** dans `courses` (cours coach = non affichés sur la page cours publique)
- Table **course_live_sessions** (sessions live avec lien Google Meet)
