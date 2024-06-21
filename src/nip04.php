<?php

use Elliptic\EC;

function computeSharedSecret($pubkey, $privateKey) {
    $ec = new EC('secp256k1');
    $key = $ec->keyFromPrivate($privateKey);
    $pub = $ec->keyFromPublic('02' . $pubkey, 'hex');
    $sharedSecret = $key->derive($pub->getPublic());

    return $sharedSecret->toString('hex');
}

function encrypt($message, $key) {
    $iv = random_bytes(16);
    if ($iv === false) {
      throw new Exception('Error creating initialization vector');
    }

    $encrypted = openssl_encrypt(
      $message,
      'AES-256-CBC',
      hex2bin($key),
      0,
      $iv
    );

    return $encrypted . '?iv=' . base64_encode($iv);
}

function decrypt($content, $key) {
    list($encryptedData, $iv) = explode('?iv=', $content);
    $iv = base64_decode($iv);

    $decrypted = openssl_decrypt(
      $encryptedData,
      'AES-256-CBC',
      hex2bin($key),
      0,
      $iv
    );

    return $decrypted;
}

// Example usage
$privateKey = '5ffedb37da6f169847654c0797561c97cff25253f6d57bf486e8920220db5a2b';
$pubkey = '69effe7b49a6dd5cf525bd0905917a5005ffe480b58eeb8e861418cf3ae760d9';
$text = '{ "method": "get_info" }';

$sharedSecret = computeSharedSecret($pubkey, $privateKey);
echo "Shared Secret: $sharedSecret\n";

$encrypted = encrypt($text, $sharedSecret);
echo "Encrypted: $encrypted\n";

$decrypted = decrypt($encrypted, $sharedSecret);
echo "Decrypted: $decrypted\n";
