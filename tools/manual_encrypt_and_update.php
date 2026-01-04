<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/secure_key.php';
if ($argc < 3) {
    echo "Usage: php tools/manual_encrypt_and_update.php <id> <secret>\n";
    exit(1);
}
$id = intval($argv[1]);
$secret = $argv[2];
function pc_encrypt($plain)
{
    $keyDef = CREDENTIAL_KEY ?? '';
    if (strpos($keyDef, 'base64:') === 0) {
        $key = base64_decode(substr($keyDef, 7));
    } else {
        $key = $keyDef;
    }
    if (!$key || strlen($key) < 16) throw new Exception('Credential key not configured');
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $cipher);
}
$enc = pc_encrypt($secret);
$stmt = $pdo->prepare('UPDATE credential_store SET encrypted_secret=? WHERE id=?');
$stmt->execute([$enc, $id]);
echo "Encrypted and updated id $id with secret '$secret'\n";
echo "Encrypted value: $enc\n";
echo "Length: " . strlen($enc) . "\n";
