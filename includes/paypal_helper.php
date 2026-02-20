<?php
/**
 * Helper pour créer une commande PayPal et obtenir l'URL d'approbation (redirect checkout).
 * Utilisé par registration-next-session et nclex-registration-form.
 */

/**
 * Récupère un token OAuth2 PayPal.
 * @param string $clientId
 * @param string $clientSecret
 * @param bool $sandbox
 * @return string|null token ou null en cas d'erreur
 */
/**
 * @return array ['token' => string|null, 'error' => string|null] pour afficher l'erreur PayPal
 */
function paypal_get_access_token($clientId, $clientSecret, $sandbox = true) {
    $baseUrl = $sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
    $url = $baseUrl . '/v1/oauth2/token';

    $trySslVerify = true;
    $lastError = null;

    for ($attempt = 0; $attempt <= 1; $attempt++) {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_USERPWD => $clientId . ':' . $clientSecret,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Accept-Language: en_US',
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => $trySslVerify,
            CURLOPT_SSLVERSION => 6, // TLS 1.2 requis par PayPal
        ];
        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        if ($response === false) {
            $lastError = $curlErr ?: 'Connexion PayPal impossible.';
            // Sur Windows/WAMP, erreur SSL courante : réessayer sans vérification SSL (sandbox uniquement)
            if ($trySslVerify && $sandbox && ($curlErrno === 60 || stripos($curlErr, 'SSL') !== false || stripos($curlErr, 'certificate') !== false)) {
                $trySslVerify = false;
                continue;
            }
            return ['token' => null, 'error' => $lastError];
        }

        $data = json_decode($response, true);
        if ($httpCode !== 200) {
            $msg = $data['error_description'] ?? $data['error'] ?? $data['message'] ?? '';
            $fullMsg = $msg ? ('HTTP ' . $httpCode . ' – ' . $msg) : ('HTTP ' . $httpCode);
            return ['token' => null, 'error' => $fullMsg];
        }
        $token = $data['access_token'] ?? null;
        return ['token' => $token, 'error' => $token ? null : 'Pas de token dans la réponse.'];
    }

    return ['token' => null, 'error' => $lastError ?: 'Connexion PayPal impossible.'];
}

/**
 * Crée une commande PayPal et retourne l'URL d'approbation (redirect utilisateur).
 * @param string $accessToken
 * @param string $amountValue ex. "53.99"
 * @param string $returnUrl
 * @param string $cancelUrl
 * @param string $itemName
 * @param bool $sandbox
 * @return array ['url' => approval_url, 'order_id' => id] ou ['error' => message]
 */
function paypal_create_order($accessToken, $amountValue, $returnUrl, $cancelUrl, $itemName = 'Registration', $sandbox = true) {
    $baseUrl = $sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
    // PayPal exige un montant au format "XX.XX" (string, 2 décimales)
    $amountStr = is_numeric($amountValue) ? number_format((float) $amountValue, 2, '.', '') : $amountValue;
    $body = [
        'intent' => 'CAPTURE',
        'purchase_units' => [
            [
                'amount' => [
                    'currency_code' => 'USD',
                    'value' => $amountStr,
                ],
                'description' => $itemName,
            ],
        ],
        'application_context' => [
            'return_url' => $returnUrl,
            'cancel_url' => $cancelUrl,
            'brand_name' => 'Orchidee LLC',
        ],
    ];
    $ch = curl_init($baseUrl . '/v2/checkout/orders');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($body),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => $sandbox ? false : true,
        CURLOPT_SSLVERSION => 6,
    ]);
    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);
    if ($response === false) {
        return ['error' => $curlErr ?: 'Connection to PayPal failed.'];
    }
    $data = json_decode($response, true);
    if ($httpCode < 200 || $httpCode >= 300 || empty($data['id'])) {
        $msg = $data['message'] ?? null;
        $detail = null;
        if (!empty($data['details']) && is_array($data['details'])) {
            $d = $data['details'][0];
            $detail = $d['description'] ?? $d['issue'] ?? null;
        }
        $err = $detail ?: $msg ?: $data['name'] ?? ('HTTP ' . $httpCode);
        if ($msg && $msg !== $err) {
            $err = $msg . ' – ' . $err;
        } elseif (!$err) {
            $err = 'HTTP ' . $httpCode;
        }
        return ['error' => $err];
    }
    $approvalUrl = null;
    if (!empty($data['links'])) {
        foreach ($data['links'] as $link) {
            if (isset($link['rel']) && $link['rel'] === 'approve') {
                $approvalUrl = $link['href'];
                break;
            }
        }
    }
    if (!$approvalUrl) {
        return ['error' => 'No approval URL in PayPal response'];
    }
    return ['url' => $approvalUrl, 'order_id' => $data['id']];
}
