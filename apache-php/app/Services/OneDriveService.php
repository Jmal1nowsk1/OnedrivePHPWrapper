<?php

namespace App\Services;

use RuntimeException;
use SimpleXMLElement;

class OneDriveService
{
    private string $baseUrl;

    public function __construct()
    {
        $url = config('services.onedrive.webdav_url');
        if (empty($url)) {
            throw new RuntimeException('Brak adresu kontenera WebDAV (WEBDAV_URL).');
        }
        $this->baseUrl = rtrim($url, '/') . '/';
    }

    /**
     * Tworzy katalog na OneDrive.
     */
    public function mkdir(string $directory): array
    {
        $url = $this->baseUrl . rawurlencode($directory);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => 'MKCOL',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30,
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 201) {
            return ['success' => true, 'error' => ''];
        }

        return ['success' => false, 'error' => "Błąd: $httpCode $curlErr"];
    }

    /**
     * Zapisuje plik na OneDrive.
     */
    public function saveFile(string $localFile, string $directory, string $fileName): array
    {
        $parts = array_map('rawurlencode', explode('/', trim($directory, '/')));
        $path  = implode('/', $parts);
        if (!empty($path)) {
            $path .= '/';
        }
        $path .= rawurlencode($fileName);

        $url  = rtrim($this->baseUrl, '/') . '/' . $path;
        $size = filesize($localFile);
        $fp   = fopen($localFile, 'r');

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_PUT            => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_INFILE         => $fp,
            CURLOPT_INFILESIZE     => $size,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 3000,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        $success = $httpCode >= 200 && $httpCode < 300;
        $error   = '';

        if (!$success) {
            if ($curlErr) {
                $error = $curlErr;
            } else {
                $json = json_decode($response, true);
                $error = (json_last_error() === JSON_ERROR_NONE && isset($json['error']))
                    ? $json['error']
                    : "Błąd HTTP $httpCode: $response";
            }
        }

        return ['success' => $success, 'error' => $error, 'httpCode' => $httpCode];
    }

    /**
     * Usuwa plik z OneDrive.
     */
    public function deleteFile(string $path): array
    {
        $url = $this->baseUrl . rawurlencode($path);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 60,
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'error' => ''];
        }

        if ($httpCode === 404) {
            return ['success' => false, 'error' => 'Plik nie istnieje.'];
        }

        return ['success' => false, 'error' => "Błąd: $httpCode $curlErr"];
    }

    /**
     * Zwraca drzewo katalogów z OneDrive.
     */
    public function listDirectories(string $directory, string $depth = 'infinity'): array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . rawurlencode($directory);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => 'PROPFIND',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ["Depth: $depth"],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            return ['success' => false, 'error' => "Błąd HTTP: $httpCode $curlErr", 'directories' => []];
        }

        try {
            $xml = new SimpleXMLElement($response);
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Nie udało się sparsować XML: ' . $e->getMessage(), 'directories' => []];
        }

        $tree = [];

        foreach ($xml->xpath('//*[local-name()="response"]') as $resp) {
            $hrefArr = $resp->xpath('*[local-name()="href"]');
            if (empty($hrefArr)) {
                continue;
            }

            $href = urldecode(trim((string) $hrefArr[0], '/'));

            if ($href === rtrim($directory, '/')) {
                continue;
            }

            $relativePath = ltrim(str_replace(rtrim($directory, '/'), '', $href), '/');
            $parts        = explode('/', $relativePath);
            $current      = &$tree;

            foreach ($parts as $i => $part) {
                if ($i === count($parts) - 1) {
                    if (str_contains($part, '.')) {
                        $current[$part] = null;
                    } else {
                        if (!isset($current[$part])) {
                            $current[$part] = [];
                        }
                    }
                } else {
                    if (!isset($current[$part])) {
                        $current[$part] = [];
                    }
                    $current = &$current[$part];
                }
            }
        }

        return ['success' => true, 'directories' => $tree];
    }

    /**
     * Sprawdza, czy katalog istnieje na OneDrive.
     */
    public function directoryExists(string $directory): bool
    {
        $url = rtrim($this->baseUrl, '/') . '/' . rawurlencode($directory);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => 'PROPFIND',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Depth: 0'],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 20,
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 207;
    }
}

