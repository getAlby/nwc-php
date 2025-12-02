<?php

namespace NWC;

require_once "contracts/NWCClient.php";

use Elliptic\EC;
use \GuzzleHttp;
use NWC\Contracts\NWCClient;
use Mdanter\Ecc\Crypto\Signature\SchnorrSignature;

class Client implements NWCClient
{
    private $client;
    private $url;
    private $secret;
    private $relayUrl;
    private $walletPubkey;
    private $sharedSecret;

    public function __construct($connection_uri)
    {
        $this->url = "https://api.getalby.com/nwc/nip47";
        
        // Parse the nostr+walletconnect:// URI properly
        if (strpos($connection_uri, 'nostr+walletconnect://') !== 0) {
            throw new \InvalidArgumentException('Invalid NWC connection URI: must start with nostr+walletconnect://');
        }
        
        // Remove the protocol prefix
        $uri_without_protocol = substr($connection_uri, strlen('nostr+walletconnect://'));
        
        // Split into pubkey and query string
        $parts = explode('?', $uri_without_protocol, 2);
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException('Invalid NWC connection URI: missing query parameters');
        }
        
        $this->walletPubkey = $parts[0];
        
        // Parse query string
        parse_str($parts[1], $query_params);

        // handle multiple relays by using PHP array syntax
        $query_params = str_replace('relay=', 'relay[]=', $query_params);
        
        // Extract relay URL (use first relay if multiple are provided)
        if (isset($query_params['relay'])) {
            $this->relayUrl = is_array($query_params['relay'])
                ? urldecode($query_params['relay'][0])
                : urldecode($query_params['relay']);
        } else {
            throw new \InvalidArgumentException('Invalid NWC connection URI: missing relay parameter');
        }
        
        // Extract secret
        if (!isset($query_params['secret'])) {
            throw new \InvalidArgumentException('Invalid NWC connection URI: missing secret parameter');
        }
        $this->secret = $query_params['secret'];

        if (!preg_match('/^[a-f0-9]{64}$/', $this->walletPubkey)) {
            throw new \InvalidArgumentException('Invalid pubkey');
        }
        if (!preg_match('/^[a-f0-9]{64}$/', $this->secret)) {
            throw new \InvalidArgumentException('Invalid secret');
        }
        if (!filter_var($this->relayUrl, FILTER_VALIDATE_URL) || !in_array(parse_url($this->relayUrl, PHP_URL_SCHEME), ['ws', 'wss'])) {
            throw new \InvalidArgumentException('Invalid relay URL');
        }

        // Generate shared secret
        $this->sharedSecret = $this->computeSharedSecret($this->walletPubkey, $this->secret);
    }

    // deprecated
    public function init()
    {
        return true;
    }

    public function isConnectionValid(): bool
    {
        return !empty($this->secret);
    }

    public function getInfo(): array
    {
        $jsonStr = '{"method": "get_info"}';
        return $this->nip47Request($jsonStr);
    }

    public function getBalance(): array
    {
        $jsonStr = '{"method": "get_balance"}';
        return $this->nip47Request($jsonStr);
    }

    public function addInvoice($invoice): array
    {
        $params = [
          "amount" => $invoice["value"] * 1000,
          "description" => $invoice["memo"]
        ];
        if (array_key_exists("description_hash", $invoice) && !empty($invoice["description_hash"])) {
          $params['description_hash'] = $invoice['description_hash'];
        }
        if (array_key_exists("expiry", $invoice) && !empty($invoice["expiry"])) {
          $params['expiry'] = $invoice['expiry'];
        }

        $jsonStr = json_encode([
          "method" => "make_invoice",
          "params" => $params
        ]);

        $data = $this->nip47Request($jsonStr);

        $data["id"] = $data["payment_hash"];
        $data["r_hash"] = $data["payment_hash"];
        $data["payment_request"] = $data["invoice"];
        return $data;
    }

    public function getInvoice($rHash): array
    {
        $jsonStr = json_encode([
          "method" => "lookup_invoice",
          "params" => [
            "payment_hash" => $rHash
          ]
        ]);

        $data = $this->nip47Request($jsonStr);

        $data["id"] = $data["payment_hash"];
        $data["r_hash"] = $data["payment_hash"];
        $data["payment_request"] = $data["invoice"];
        $data["settled"] = !empty($data["settled_at"]);

        return $data;
    }

    public function isInvoicePaid($rHash): bool
    {
        $invoice = $this->getInvoice($rHash);
        return $invoice["settled"];
    }

    private function nip47Request($payload = null)
    {
        // encrypt the request payload
        $content = $this->encrypt($payload, $this->sharedSecret);

        $ec = new EC('secp256k1');
        $keyPair = $ec->keyFromPrivate($this->secret);
        $publicKey = substr($keyPair->getPublic(true, 'hex'), 2);

        // create the nostr event
        $event = [
          'kind' => 23194,
          'content' => $content,
          'tags' => [['p', $this->walletPubkey]],
          'pubkey' => $publicKey,
          'created_at' => time(),
        ];

        // inspired by https://github.com/nostrver-se/nostr-php 
        $serializedEvent = $this->serializeEvent($event);
        $eventId = hash('sha256', $serializedEvent);
        $event['id'] = $eventId;

        $sign = new SchnorrSignature();
        $signature = $sign->sign($this->secret, $eventId);
        $event['sig'] = $signature['signature'];

        // create the body for http-nostr request
        $body = [
          'walletPubkey' => $this->walletPubkey,
          'relayUrl' => $this->relayUrl,
          'event' => $event,
        ];

        try {
            $response = $this->client()->post($this->url, [
              'json' => $body,
              'headers' => [
                  'Content-Type' => 'application/json',
              ],
            ]);
            $responseBody = $response->getBody()->getContents();
            $result = json_decode($responseBody, true);

            if (isset($result['event']['content'])) {
                $decryptedContent = $this->decrypt($result['event']['content'], $this->sharedSecret);
                $decryptedResult = json_decode($decryptedContent, true);

                if (isset($decryptedResult['result'])) {
                    return $decryptedResult['result'];
                } elseif (isset($decryptedResult['error'])) {
                    throw new \Exception($decryptedResult['error']['message']);
                } else {
                    throw new \Exception("Unexpected response format");
                }
            } else {
                throw new \Exception("Invalid response structure");
            }
        } catch (GuzzleHttp\Exception\RequestException $e) {
            echo "Error making POST request: " . $e->getMessage();
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage();
            return null;
        }
    }

    private function client()
    {
        if ($this->client) {
          return $this->client;
        }
        $this->client = new GuzzleHttp\Client();
        return $this->client;
    }

    private function computeSharedSecret($pubkey, $privateKey) {
        $ec = new EC('secp256k1');
        $key = $ec->keyFromPrivate($privateKey);
        $pub = $ec->keyFromPublic('02' . $pubkey, 'hex');
        $sharedSecret = $key->derive($pub->getPublic());

        return $sharedSecret->toString('hex');
    }

    private function encrypt($message, $key): string {
        $iv = random_bytes(16);

        $encrypted = openssl_encrypt(
          $message,
          'AES-256-CBC',
          hex2bin($key),
          0,
          $iv
        );

        return $encrypted . '?iv=' . base64_encode($iv);
    }

    private function decrypt($content, $key): string {
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

    private function serializeEvent($event): string
    {
        $eventArray = [
            0,
            $event['pubkey'],
            $event['created_at'],
            $event['kind'],
            $event['tags'],
            $event['content'],
        ];
        return json_encode($eventArray, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
