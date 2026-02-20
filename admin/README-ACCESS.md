# 🔐 Accès au Dashboard Admin

## ✅ Accès Correct

### Option 1 : Accès Direct (RECOMMANDÉ)
```
http://localhost/orchidee/admin/dashboard.php
```

### Option 2 : Via Index
```
http://localhost/orchidee/admin/
```
ou
```
http://localhost/orchidee/admin/index.php
```

## 🔑 Authentification

### Si vous n'êtes pas connecté :
- Vous serez automatiquement redirigé vers : `http://localhost/orchidee/login.php`
- Connectez-vous avec un compte admin
- Après connexion, vous serez redirigé vers le dashboard

### Créer un compte Admin

1. **Via phpMyAdmin** :
   ```sql
   INSERT INTO users (first_name, last_name, email, password, role) 
   VALUES ('Admin', 'User', 'admin@orchideellc.com', '$2y$10$...', 'admin');
   ```

2. **Via l'interface d'inscription** :
   - Créez un compte normal via `register.php`
   - Puis modifiez le rôle en 'admin' dans la base de données

## ⚠️ Erreurs Courantes

### Erreur 404 - login.php not found
- **Cause** : Tentative d'accès à `admin/login.php` (n'existe pas)
- **Solution** : Le fichier login.php est à la racine : `http://localhost/orchidee/login.php`

### Erreur 500 - Internal Server Error
- **Cause** : Problème avec le fichier .htaccess
- **Solution** : Supprimez `admin/.htaccess` et utilisez l'accès direct

### Redirection infinie
- **Cause** : Problème de session
- **Solution** : Videz les cookies et réessayez

## 📝 Fichiers Importants

- `admin/dashboard.php` - Page principale du dashboard
- `admin/index.php` - Redirige vers dashboard.php
- `includes/admin_check.php` - Vérifie l'authentification admin
- `login.php` - Page de connexion (à la racine)

## 🔍 Vérification

Pour vérifier que tout fonctionne :

1. ✅ Accédez à `http://localhost/orchidee/login.php`
2. ✅ Connectez-vous avec un compte admin
3. ✅ Vous devriez être redirigé vers `admin/dashboard.php`
4. ✅ Le dashboard devrait s'afficher correctement

---

**Note** : Le fichier `login.php` est à la **racine** du projet, pas dans le dossier `admin`.



