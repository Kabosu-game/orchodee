# 🔧 Solution Erreur 500 - Admin Dashboard

## ❌ Erreur Rencontrée
```
Internal Server Error (500)
```

## ✅ Solutions Rapides

### Solution 1 : Supprimer le fichier .htaccess (RECOMMANDÉ)

1. **Supprimez ou renommez** le fichier `admin/.htaccess`
2. **Renommez-le** en `.htaccess.old` (pour le garder en backup)
3. **Réessayez** d'accéder à `http://localhost/orchidee/admin/`

Si cela fonctionne, le problème venait du fichier .htaccess.

### Solution 2 : Utiliser l'accès direct

Au lieu d'accéder à `/admin/`, utilisez directement :
```
http://localhost/orchidee/admin/dashboard.php
```

### Solution 3 : Vérifier les logs d'erreur

1. **Ouvrez** les logs Apache dans WAMP
2. **Cherchez** les erreurs récentes
3. **Localisation** : `C:\wamp64\logs\apache_error.log`

### Solution 4 : Version simplifiée du .htaccess

Si vous voulez garder un .htaccess, utilisez la version simplifiée :

1. **Supprimez** `admin/.htaccess`
2. **Renommez** `admin/.htaccess.simple` en `admin/.htaccess`

## 🔍 Vérifications

### Vérifier que index.php existe
Le fichier `admin/index.php` doit exister et contenir :
```php
<?php
header("Location: dashboard.php");
exit();
```

### Vérifier les permissions
Assurez-vous que les fichiers ont les bonnes permissions (lecture/exécution).

### Vérifier la configuration PHP
Vérifiez que PHP fonctionne correctement en accédant à :
```
http://localhost/orchidee/admin/dashboard.php
```

## 📝 Si Rien Ne Fonctionne

1. **Accédez directement** : `http://localhost/orchidee/admin/dashboard.php`
2. **Vérifiez** que vous êtes connecté en tant qu'admin
3. **Si non connecté**, allez sur : `http://localhost/orchidee/login.php`

## ⚠️ Note Importante

Le fichier `.htaccess` est optionnel. Si vous n'en avez pas besoin, vous pouvez le supprimer complètement. Le fichier `index.php` fonctionnera sans lui.

---

**Solution la plus simple** : Supprimez `admin/.htaccess` et utilisez directement `admin/dashboard.php`



